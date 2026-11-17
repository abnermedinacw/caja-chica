<?php
require_once __DIR__ . '/layout.php';

function vistaAdminResumenes(array $resumenes, array $empresas, array $paginacion): void
{
    $pagina = $paginacion['pagina'];
    $totalPaginas = $paginacion['total_paginas'];
    $total = $paginacion['total'];
    $inicio = max(1, min($pagina - 2, $totalPaginas - 4));
    $fin = min($totalPaginas, $inicio + 4);
    $urlPagina = static fn(int $numero): string => BASE_URL . '/admin-resumenes?' . http_build_query([
        'buscar' => $paginacion['buscar'],
        'empresa_id' => $paginacion['empresa_id'],
        'estado' => $paginacion['estado'],
        'por_pagina' => $paginacion['por_pagina'],
        'pagina' => $numero,
    ]);
    aperturaLayout('Anular resúmenes', 'admin-resumenes');
?>
    <div id="app" v-cloak>
        <div class="barra-superior">
            <div>
                <a href="<?= BASE_URL ?>/home" class="volver"><?= icono('volver') ?>Volver</a>
                <h1>Resúmenes</h1>
            </div>
        </div>

        <div v-if="mensajeExito" class="toast toast-exito" role="status">
            <?= icono('check') ?>
            <span>{{ mensajeExito }}</span>
            <button type="button" class="toast-cerrar" @click="mensajeExito = null" aria-label="Cerrar aviso">×</button>
        </div>

        <div class="filtros-cabecera">
            <button type="button" class="boton boton-chico boton-filtros" @click="mostrarFiltros = !mostrarFiltros">
                <?= icono('filtro') ?> Filtros<span v-if="hayFiltrosActivos" class="filtros-badge"></span>
            </button>
        </div>

        <form class="ficha ficha-formulario filtros-historial" v-if="mostrarFiltros" method="get" action="<?= BASE_URL ?>/admin-resumenes">
            <div class="campo">
                <label for="filtroBuscar">N°, solicitante o DNI</label>
                <input type="search" id="filtroBuscar" name="buscar" v-model="filtroBuscar" maxlength="100" placeholder="Buscar...">
            </div>
            <div class="campo">
                <label for="filtroEmpresa">Empresa</label>
                <select id="filtroEmpresa" name="empresa_id" v-model="filtroEmpresa">
                    <option value="">Todas</option>
                    <option v-for="e in datos.empresas" :key="e.id" :value="String(e.id)">{{ e.razon_social }}</option>
                </select>
            </div>
            <div class="campo">
                <label for="filtroEstado">Estado</label>
                <select id="filtroEstado" name="estado" v-model="filtroEstado">
                    <option value="">Todos</option>
                    <option value="BORRADOR">Borrador</option>
                    <option value="EN_REVISION">En revisión</option>
                    <option value="RECHAZADO">Rechazado</option>
                    <option value="VISADO">Visado</option>
                    <option value="ANULADO">Anulado</option>
                </select>
            </div>
            <div class="campo">
                <label for="porPagina">Filas por página</label>
                <select id="porPagina" name="por_pagina" v-model.number="porPagina">
                    <option :value="10">10</option>
                    <option :value="25">25</option>
                    <option :value="50">50</option>
                </select>
            </div>
            <div class="filtros-trabajadores-acciones">
                <a class="boton boton-chico" href="<?= BASE_URL ?>/admin-resumenes">Limpiar filtros</a>
                <button type="submit" class="boton boton-secundario boton-chico">Aplicar</button>
            </div>
        </form>

        <?php if ($total === 0): ?>
            <p class="texto-vacio"><?= $paginacion['buscar'] !== '' || $paginacion['empresa_id'] > 0 || $paginacion['estado'] !== ''
                ? 'Ningún resumen coincide con los filtros de búsqueda.'
                : 'No hay resúmenes registrados.' ?></p>
        <?php else: ?>
            <div class="tabla-scroll tabla-scroll-vertical">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Solicitante</th>
                            <th>Empresa</th>
                            <th>Emisión</th>
                            <th>Total egresos</th>
                            <th>Nivel actual</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in resumenes" :key="r.id">
                            <td>{{ numeroDocumento(r.numero) }}</td>
                            <td>{{ r.solicitante }} </td>
                            <td>{{ r.empresa }}</td>
                            <td>{{ formatearFecha(r.fecha_emision) }}</td>
                            <td>{{ formatearSoles(r.total_egresos) }}</td>
                            <td>{{ r.nivel_actual_nombre || '-' }}</td>
                            <td><span class="estado" :class="'estado-' + r.estado_general.toLowerCase()">{{ r.estado_general }}</span></td>
                            <td>
                                <div class="acciones-fila">
                                    <a :href="datos.baseUrl + '/ver-resumen?id=' + r.id + '&origen=admin-resumenes'" class="boton boton-secundario boton-chico">
                                        <?= icono('ver') ?> Ver
                                    </a>
                                    <button
                                        v-if="r.estado_general !== 'ANULADO'"
                                        type="button"
                                        class="boton boton-peligro boton-chico"
                                        :disabled="Number(r.txt_bbva_descargado) === 1"
                                        :title="Number(r.txt_bbva_descargado) === 1 ? 'No se puede anular porque su TXT BBVA ya fue descargado' : 'Anular resumen'"
                                        @click="abrirModalAnular(r)">
                                        <?= icono('quitar') ?> Anular
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="paginacion-trabajadores">
                <p>Mostrando <?= ($pagina - 1) * $paginacion['por_pagina'] + 1 ?>–<?= min($pagina * $paginacion['por_pagina'], $total) ?> de <?= $total ?> resúmenes</p>
                <nav aria-label="Páginas de resúmenes">
                    <?php if ($pagina > 1): ?>
                        <a class="boton boton-secundario boton-chico" rel="prev" href="<?= htmlspecialchars($urlPagina($pagina - 1)) ?>">Anterior</a>
                    <?php else: ?>
                        <button type="button" class="boton boton-secundario boton-chico" disabled>Anterior</button>
                    <?php endif; ?>
                    <?php if ($inicio > 1): ?>
                        <a class="boton boton-secundario boton-chico" aria-label="Ir a la página 1" href="<?= htmlspecialchars($urlPagina(1)) ?>">1</a>
                        <?php if ($inicio > 2): ?><span aria-hidden="true">…</span><?php endif; ?>
                    <?php endif; ?>
                    <?php for ($numero = $inicio; $numero <= $fin; $numero++): ?>
                        <?php if ($numero === $pagina): ?>
                            <span class="boton boton-chico pagina-actual" aria-current="page" aria-label="Página <?= $numero ?>"><?= $numero ?></span>
                        <?php else: ?>
                            <a class="boton boton-secundario boton-chico" aria-label="Ir a la página <?= $numero ?>" href="<?= htmlspecialchars($urlPagina($numero)) ?>"><?= $numero ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($fin < $totalPaginas): ?>
                        <?php if ($fin < $totalPaginas - 1): ?><span aria-hidden="true">…</span><?php endif; ?>
                        <a class="boton boton-secundario boton-chico" aria-label="Ir a la página <?= $totalPaginas ?>" href="<?= htmlspecialchars($urlPagina($totalPaginas)) ?>"><?= $totalPaginas ?></a>
                    <?php endif; ?>
                    <?php if ($pagina < $totalPaginas): ?>
                        <a class="boton boton-secundario boton-chico" rel="next" href="<?= htmlspecialchars($urlPagina($pagina + 1)) ?>">Siguiente</a>
                    <?php else: ?>
                        <button type="button" class="boton boton-secundario boton-chico" disabled>Siguiente</button>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>

        <dialog ref="refModalAnular" @cancel.prevent="cerrarModal">
            <button type="button" class="dialogo-cerrar" aria-label="Cerrar modal" @click="cerrarModal" :disabled="anulando">&times;</button>
            <h2>Anular Resumen N° {{ resumenSeleccionado ? numeroDocumento(resumenSeleccionado.numero) : '' }}</h2>
            <p v-if="resumenSeleccionado">
                Solicitante: <strong>{{ resumenSeleccionado.solicitante }}</strong> ({{ resumenSeleccionado.empresa }} · {{ formatearSoles(resumenSeleccionado.total_egresos) }}).
            </p>
            <p class="texto-vacio">
                Esta acción cambiará el resumen a estado <strong>ANULADO</strong> y liberará el saldo comprometido de su fondo.
            </p>
            <div class="campo">
                <label for="motivoAnulacion">Motivo de la anulación<span class="requerido">*</span></label>
                <textarea id="motivoAnulacion" v-model.trim="motivo" rows="3" maxlength="255" placeholder="Ej. Error al seleccionar empresa o fondo, duplicado..." :disabled="anulando"></textarea>
            </div>
            <p class="error" v-if="errorAnulacion">{{ errorAnulacion }}</p>
            <div class="acciones-modal">
                <button type="button" class="boton boton-secundario" @click="cerrarModal" :disabled="anulando">Cancelar</button>
                <button type="button" class="boton boton-peligro" @click="confirmarAnulacion" :disabled="anulando || !motivo.trim()">
                    <span v-if="anulando" class="spinner"></span>
                    {{ anulando ? 'Anulando...' : 'Confirmar anulación' }}
                </button>
            </div>
        </dialog>
    </div>

    <script>
        window.DATOS_INICIALES = <?= json_encode([
            'baseUrl' => BASE_URL,
            'resumenes' => $resumenes,
            'empresas' => $empresas,
            'paginacion' => $paginacion,
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="<?= BASE_URL ?>/js/vendor/vue.global.prod.js"></script>
    <script type="module" src="<?= assetUrl('/js/admin_resumenes.js') ?>"></script>
<?php
    cierreLayout();
}
