<?php
require_once __DIR__ . '/../Datos/fondos.php';
require_once __DIR__ . '/../Datos/cajaChica.php';
require_once __DIR__ . '/../Datos/empresas.php';
require_once __DIR__ . '/../Datos/comprobantes.php';
require_once __DIR__ . '/../Negocio/adjuntos.php';
require_once __DIR__ . '/aprobaciones.php';
require_once __DIR__ . '/../../config/database.php';

// Crea el resumen en Borrador. El fondo es opcional: cubre al trabajador
// que gasta de su bolsillo para una empresa del grupo sin tener un fondo
// asignado ahí, y luego rinde para que se lo devuelvan. Si no hay fondo,
// el Resumen arranca sin saldo a favor (saldo_inicial 0) — todo lo que
// registre es puro gasto por reponer, no un adelanto que se va gastando.
function crearResumen(
    int $trabajadorId,
    int $usuarioId,
    int $empresaId,
    ?int $fondoId,
    string $periodoInicio,
    string $periodoFin,
    string $fechaEmision
): array
{
    $empresa = obtenerEmpresaPorId($empresaId);
    if (!$empresa) {
        return ['ok' => false, 'error' => 'Selecciona una empresa matriz válida.'];
    }

    $saldoInicial = 0.0;
    if ($fondoId !== null) {
        $fondo = validarFondoActivo($fondoId, $trabajadorId);
        if (!$fondo) {
            return ['ok' => false, 'error' => 'El fondo seleccionado no es válido o ya no está activo.'];
        }
        // El fondo tiene que ser de la misma empresa que se seleccionó — si
        // no, el saldo_inicial que se copia abajo pertenecería a otra
        // empresa distinta a la del Resumen, y el flujo de reposición no
        // cuadraría con quién realmente lo asignó.
        if ((int)$fondo['empresa_id'] !== $empresaId) {
            return ['ok' => false, 'error' => 'El fondo seleccionado no pertenece a la empresa elegida.'];
        }
        $saldoInicial = obtenerSaldoDisponibleFondo($fondoId);
    }

    if($periodoInicio === '' || $periodoFin === '' || $fechaEmision === '') {
        return['ok' => false, 'error' => 'Completa el periodo y fecha de emisión.'];
    }

    if($periodoFin < $periodoInicio) {
        return['ok' => false, 'error' => 'El periodo fin no puede ser antes que el periodo inicio.'];
    }

    if ($fechaEmision > date('Y-m-d') || $periodoFin > date('Y-m-d')) {
        return ['ok' => false, 'error' => 'No se permiten fechas futuras.'];
    }

    $pdo = obtenerConexion();
    $pdo->beginTransaction();
    try {
        // Bloquea al trabajador mientras se calcula su siguiente correlativo.
        // Así dos creaciones simultáneas del mismo trabajador se serializan.
        $stmt = $pdo->prepare('SELECT id FROM trabajador WHERE id = ? FOR UPDATE');
        $stmt->execute([$trabajadorId]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('Trabajador no encontrado.');
        }

        $numero = sgteNumeroResumen($trabajadorId, $empresaId);
        $resumenId = crearBorradorResumen(
            $trabajadorId, $empresaId, $fondoId, $numero,
            $periodoInicio, $periodoFin, $fechaEmision, 0.0, $saldoInicial
        );
        registrarEventoResumen($resumenId, 'CREADO', $usuarioId);
        $pdo->commit();

        return ['ok' => true, 'id' => $resumenId];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'error' => 'No se pudo crear el Resumen. Inténtalo de nuevo.'];
    }
}

function sincronizarTotalesResumen(int $resumenId): void 
{
    recalcularTotalEgresosResumen($resumenId);
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("UPDATE caja_chica SET monto_rendicion = total_egresos WHERE id = ?");
    $stmt->execute([$resumenId]);
}

function enviarResumenAprobacion(int $resumenId, int $trabajadorId, int $usuarioId): array
{
    $pdo = obtenerConexion();
    $pdo->beginTransaction();
    try {
        // La segunda petición espera y luego vuelve a comprobar el estado actual.
        bloquearResumen($resumenId);
        $resumen = obtenerResumenAprobador($resumenId);
        if (!$resumen || (int)$resumen['trabajador_id'] !== $trabajadorId) {
            return ['ok' => false, 'error' => 'Resumen no encontrado.'];
        }
        if (!esResumenEditable($resumen)) {
            return ['ok' => false, 'error' => 'Este Resumen ya fue enviado o tiene un rechazo en revisión.'];
        }
        if ((float)$resumen['total_egresos'] <= 0) {
            return ['ok' => false, 'error' => 'Agrega al menos un documento con montos antes de enviar.'];
        }
        if (!buscarJefeArea((int)$resumen['area_id'])) {
            return ['ok' => false, 'error' => 'No hay un jefe activo asignado para el área del solicitante.'];
        }
        $sinResponsable = obtenerNivelesSinResponsable();
        if ($sinResponsable) {
            return ['ok' => false, 'error' => 'Falta configurar encargados de aprobación: ' . implode(', ', $sinResponsable) . '.'];
        }

        registrarEventoResumen($resumenId, 'ENVIADO', $usuarioId);
        $primerNivel = calcularNivelDeParada($resumenId, 1, $resumen);
        avanzarOCerrarResumen($resumenId, $resumen, $primerNivel);
        $pdo->commit();
        return ['ok' => true];
    } finally {
        // También libera el bloqueo cuando una validación devuelve un error.
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
}

// Calcula el saldo final. Positivo: el trabajador devuelve dinero. Negativo: La empresa debe
function registrarSaldoFinal(int $resumenId, array $resumen): void
{
    $saldoFinal = (float)$resumen['saldo_inicial'] - (float)$resumen['total_egresos'];
    $esRecurrente = $resumen['fondo_tipo'] === 'RECURRENTE';
    cerrarResumen($resumenId, $saldoFinal, $esRecurrente, $esRecurrente ? date('Y-m-d H:i:s') : null);
}

function liquidarResumen(int $resumenId, int $usuarioId): array
{
    $resumen = obtenerResumenAprobador($resumenId);
    if (!$resumen || $resumen['estado_general'] !== 'VISADO') {
        return ['ok' => false, 'error' => 'Solo se puede liquidar un Resumen ya VISADO.'];
    }
    // fondo_tipo llega NULL cuando el Resumen no tiene fondo asignado (rendición
    // de bolsillo) — ese caso también necesita liquidación explícita, igual que
    // NO_RECURRENTE, así que solo se bloquea cuando SÍ es RECURRENTE.
    if ($resumen['fondo_tipo'] === 'RECURRENTE') {
        return ['ok' => false, 'error' => 'Los fondos RECURRENTE no requieren liquidación explícita.'];
    }
    if ((int)$resumen['liquidado'] === 1) {
        return ['ok' => false, 'error' => 'Este Resumen ya fue liquidado.'];
    }

    cerrarResumen($resumenId, (float)$resumen['saldo_final'], true, date('Y-m-d H:i:s'));
    registrarEventoResumen($resumenId, 'APROBADO', $usuarioId, null, 'Liquidación de fondo no recurrente');
    return ['ok' => true];
}

function editarCabeceraResumen(
    int $resumenId, 
    int $trabajadorId, 
    string $periodoInicio, 
    string $periodoFin, 
    string $fechaEmision
): array {
    $resumen = obtenerResumenAprobador($resumenId);
    if (!$resumen || (int)$resumen['trabajador_id'] !== $trabajadorId) return ['ok' => false, 'error' => 'Resumen no encontrado.'];

    if (!esResumenEditable($resumen)) return ['ok' => false, 'error' => 'Este Resumen ya no se puede editar.'];
    
    if ($periodoInicio === '' || $periodoFin === '' || $fechaEmision === '') return ['ok' => false, 'error' => 'Completa el periodo y fecha de emisión.'];
    
    if ($periodoFin < $periodoInicio) return ['ok' => false, 'error' => 'El periodo fin no puede ser antes que el periodo inicio.'];
    
    $hoy = date('Y-m-d');

    if ($periodoInicio > $hoy || $periodoFin > $hoy || $fechaEmision > $hoy) return ['ok' => false, 'error' => 'No se permiten fechas futuras.'];

    $rango = obtenerRangosFechasDocumentos($resumenId);
    if ($rango !== null) {
        if ($periodoInicio > $rango['inicio_minimo']) return ['ok' => false, 'error' => "El período de inicio no puede ser posterior al {$rango['inicio_minimo']}, porque ya exiten documentos desde esa fecha"];

        if ($periodoFin < $rango['fin_maximo']) return ['ok' => false, 'error' => "El período fin no puede ser anterior al {$rango['fin_maximo']} porque ya exsisten documentos hasta esa fecha."];

        if ($rango['fecha_documento_maxima'] !== null && $fechaEmision < $rango['fecha_documento_maxima']) return ['ok' => false, 'error' => "La fecha de emisión del Resumen no puede ser anterior al {$rango['fecha_documento_maxima']}, porque existe un documento emitido o rendido en esa fecha."]; 
    }

    actualizarCabeceraResumen(
        $resumenId, 
        $periodoInicio, 
        $periodoFin, 
        $fechaEmision
    );
    return ['ok' => true, 'mensaje' => 'Editado correctamente'];
}
