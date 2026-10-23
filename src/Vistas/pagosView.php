<?php

require_once __DIR__ . '/layout.php';

function vistaPagos(
    array $empresas,
    bool $puedeGestionarPagos,
    bool $puedeReactivar,
    string $vistaInicial
): void
{
    $rutaActiva = $vistaInicial === 'descargadas' ? 'reactivar-txt-bbva' : 'pagos-bbva';
    aperturaLayout('Pagos BBVA', $rutaActiva);
?>
<div id="app" class="pagos-bbva" v-cloak>
    <div class="barra-superior">
        <div>
            <a href="<?= BASE_URL ?>/home" class="volver">
                <?= icono('volver') ?> Volver
            </a>
            <h1>Pagos BBVA</h1>
        </div>
    </div>

    <section class="ficha pagos-bbva-selector">
        <div class="campo">
            <label for="empresaPago">Empresa matriz</label>

            <select id="empresaPago" v-model="empresaId">
                <option value="">Selecciona una empresa</option>

                <option
                    v-for="empresa in datos.empresas"
                    :key="empresa.id"
                    :value="String(empresa.id)">
                    {{ empresa.razon_social }}
                </option>
            </select>
        </div>
    </section>
    <p v-if="cargando" class="texto-vacio">
        <span class="spinner"></span>
        Cargando pagos... 
    </p>
    <p v-if="mensaje" class="mensaje-exito">{{ mensaje }}</p>
    <p v-if="error" class="error">{{ error }}</p>

    <template v-if="empresa && !cargando">
        <div v-if="datos.puedeGestionarPagos && datos.puedeReactivar" class="pagos-bbva-tabs">
            <button
                type="button"
                class="boton"
                :class="vista === 'pendientes' ? 'boton-primario' : 'boton-secundario'"
                @click="cambiarVista('pendientes')">
                Cajas visadas
            </button>

            <button
                type="button"
                class="boton"
                :class="vista === 'descargadas' ? 'boton-primario' : 'boton-secundario'"
                @click="cambiarVista('descargadas')">
                Descargas registradas
            </button>
        </div>

        <section v-if="datos.puedeGestionarPagos && vista === 'pendientes'" class="ficha">
            <div class="pagos-bbva-cabecera">
                <div>
                    <h2>Fondos pendientes de desembolso</h2>
                    <p>Al generar el TXT, el fondo quedará visible para el trabajador.</p>
                </div>
                <button
                    type="button"
                    class="boton boton-primario"
                    :disabled="fondosSeleccionados.length === 0 || !empresa.cuenta_cargo_bbva || generandoFondos"
                    @click="abrirConfirmacionDesembolso">
                    <span v-if="generandoFondos" class="spinner"></span>
                    Descargar TXT ({{ fondosSeleccionados.length }})
                </button>
            </div>

            <p v-if="fondosPendientes.length === 0" class="texto-vacio">
                No hay fondos pendientes de desembolso.
            </p>

            <div v-else class="tabla-scroll tabla-scroll-vertical">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th class="pagos-bbva-col-check">
                                <input type="checkbox" v-model="todosFondosSeleccionados" :disabled="!empresa.cuenta_cargo_bbva || fondosHabilitados.length === 0" aria-label="Seleccionar todos los fondos">
                            </th>
                            <th>Trabajador</th>
                            <th>DNI</th>
                            <th>Tipo</th>
                            <th>Cuenta BBVA</th>
                            <th>Importe</th>
                            <th>Validación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="fondo in fondosPendientes" :key="fondo.id">
                            <td class="pagos-bbva-col-check">
                                <input type="checkbox" v-model="fondosSeleccionados" :value="Number(fondo.id)" :disabled="!fondo.habilitada || !empresa.cuenta_cargo_bbva" :aria-label="`Seleccionar fondo de ${fondo.trabajador}`">
                            </td>
                            <td>{{ fondo.trabajador }}</td>
                            <td>{{ fondo.dni }}</td>
                            <td>{{ fondo.tipo }}</td>
                            <td>{{ fondo.cuenta_enmascarada }}</td>
                            <td>{{ formatearSoles(fondo.importe) }}</td>
                            <td>
                                <span v-if="fondo.habilitada" class="estado estado-activo">Lista</span>
                                <span v-else class="estado estado-pendiente">{{ fondo.motivo_invalido }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </section>

        <section v-if="datos.puedeGestionarPagos && vista === 'pendientes'" class="ficha">
            <div class="pagos-bbva-cabecera">
                <div>
                    <h2>Cajas visadas</h2>
                    <p>
                        Cuenta de cargo:
                        <strong>{{ empresa.cuenta_enmascarada }}</strong>
                    </p>
                </div>
                <div class="pagos-bbva-acciones">
                    <button type="button" class="boton boton-secundario" @click="abrirConfiguracionCuenta">
                        Configurar cuenta
                    </button>
                    <button
                        type="button"
                        class="boton boton-primario"
                        :disabled="seleccionadas.length === 0 || !empresa.cuenta_cargo_bbva || generando"
                        @click="abrirConfirmacionDescarga">
                        <span v-if="generando" class="spinner"></span>
                        Descargar TXT ({{ seleccionadas.length }})
                    </button>
                </div>
            </div>

            <p v-if="!empresa.cuenta_cargo_bbva" class="texto-advertencia">
                Primero debes registrar la cuenta de cargo de esta empresa.
            </p>
            <p v-if="pagos.length === 0" class="texto-vacio">
                No hay cajas visadas pendientes de desembolso.
            </p>

            <div v-else class="tabla-scroll tabla-scroll-vertical">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th class="pagos-bbva-col-check">
                                <input type="checkbox" v-model="todosSeleccionados" :disabled="!empresa.cuenta_cargo_bbva || cajasHabilitadas.length === 0">
                            </th>
                            <th>N°</th>
                            <th>Trabajador</th>
                            <th>DNI</th>
                            <th>Cuenta BBVA</th>
                            <th>Importe</th>
                            <th>Validación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="caja in pagos" :key="caja.id">
                            <td class="pagos-bbva-col-check">
                                <input type="checkbox" v-model="seleccionadas" :value="Number(caja.id)" :disabled="!caja.habilitada || !empresa.cuenta_cargo_bbva">
                            </td>
                            <td>{{ numeroDocumento(caja.numero) }}</td>
                            <td>{{ caja.trabajador }}</td>
                            <td>{{ caja.dni }}</td>
                            <td>{{ caja.cuenta_enmascarada }}</td>
                            <td>{{ formatearSoles(caja.importe) }}</td>
                            <td>
                                <span v-if="caja.descargada" class="estado estado-txt-descargado">
                                    TXT descargado
                                </span>
                                <span v-else-if="caja.habilitada" class="estado estado-activo">
                                    Lista
                                </span>
                                <span v-else class="estado estado-pendiente">
                                    {{ caja.motivo_invalido }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </section>

        <section v-if="datos.puedeReactivar && vista === 'descargadas'" class="ficha">
            <div class="pagos-bbva-cabecera">
                <div>
                    <h2>Descargas registradas</h2>
                </div>
                <button
                    type="button"
                    class="boton boton-primario"
                    :disabled="seleccionadasReactivar.length === 0 || reactivando"
                    @click="abrirReactivacion">
                    Reactivar ({{ seleccionadasReactivar.length }})
                </button>
            </div>

            <p v-if="descargadas.length === 0" class="texto-vacio">
                No hay descargas registradas para esta empresa.
            </p>

            <div v-else class="tabla-scroll tabla-scroll-vertical">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th class="pagos-bbva-col-check">
                                <input type="checkbox" v-model="descargadasSeleccionadas" aria-label="Seleccionar todas las descargas">
                            </th>
                            <th>N°</th>
                            <th>Trabajador</th>
                            <th>DNI</th>
                            <th>Cuenta BBVA</th>
                            <th>Importe</th>
                            <th>Descargado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="caja in descargadas" :key="caja.id">
                            <td class="pagos-bbva-col-check">
                                <input type="checkbox" v-model="seleccionadasReactivar" :value="Number(caja.id)" :aria-label="`Seleccionar caja ${numeroDocumento(caja.numero)}`">
                            </td>
                            <td>{{ numeroDocumento(caja.numero) }}</td>
                            <td>{{ caja.trabajador }}</td>
                            <td>{{ caja.dni }}</td>
                            <td>{{ caja.cuenta_enmascarada }}</td>
                            <td>{{ formatearSoles(caja.importe) }}</td>
                            <td>{{ formatearFecha(caja.txt_bbva_fecha_descarga) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="pagos-bbva-cabecera fondos-reactivacion-cabecera">
                <div>
                    <h3>Desembolsos de fondos registrados</h3>
                    <p>Solo se pueden reactivar fondos que aún no tienen resúmenes.</p>
                </div>
                <button type="button" class="boton boton-primario" :disabled="fondosSeleccionadosReactivar.length === 0 || reactivandoFondos" @click="abrirReactivacionFondos">
                    Reactivar fondos ({{ fondosSeleccionadosReactivar.length }})
                </button>
            </div>
            <p v-if="fondosDesembolsados.length === 0" class="texto-vacio">No hay desembolsos de fondos que se puedan reactivar.</p>
            <div v-else class="tabla-scroll tabla-scroll-vertical">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th class="pagos-bbva-col-check"><input type="checkbox" v-model="todosFondosReactivarSeleccionados" aria-label="Seleccionar todos los fondos reactivables"></th>
                            <th>Trabajador</th><th>DNI</th><th>Tipo</th><th>Importe</th><th>Desembolsado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="fondo in fondosDesembolsados" :key="fondo.id">
                            <td class="pagos-bbva-col-check"><input type="checkbox" v-model="fondosSeleccionadosReactivar" :value="Number(fondo.id)" :aria-label="`Reactivar fondo de ${fondo.trabajador}`"></td>
                            <td>{{ fondo.trabajador }}</td><td>{{ fondo.dni }}</td><td>{{ fondo.tipo }}</td>
                            <td>{{ formatearSoles(fondo.importe) }}</td><td>{{ formatearFecha(fondo.fecha_desembolso) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </template>
    <dialog ref="dialogoDescarga" @cancel.prevent="cerrarConfirmacionDescarga">
        <button type="button" class="dialogo-cerrar" @click="cerrarConfirmacionDescarga">
            &times;
        </button>
        <h2>Confirmar archivo de pago</h2>
        <p>Empresa: <strong>{{ empresa?.razon_social }}</strong></p>
        <p>Cuenta: <strong>{{ empresa?.cuenta_enmascarada }}</strong></p>
        <p>Pagos: <strong>{{ seleccionadas.length }}</strong></p>
        <p>Total: <strong>{{ formatearSoles(totalSeleccionado) }}</strong></p>

        <div class="acciones-modal">
            <button type="button" class="boton boton-secundario" @click="cerrarConfirmacionDescarga">
                Cancelar
            </button>
            <button type="button" class="boton boton-primario" :disabled="generando" @click="descargarTxt">
                Descargar TXT
            </button>
        </div>
    </dialog>

    <dialog ref="dialogoDesembolsoFondo" @cancel.prevent="cerrarConfirmacionDesembolso">
        <button type="button" class="dialogo-cerrar" aria-label="Cerrar" @click="cerrarConfirmacionDesembolso">&times;</button>
        <h2>Confirmar desembolso de fondos</h2>
        <p>Empresa: <strong>{{ empresa?.razon_social }}</strong></p>
        <p>Fondos: <strong>{{ fondosSeleccionados.length }}</strong></p>
        <p>Total: <strong>{{ formatearSoles(totalFondosSeleccionados) }}</strong></p>
        <p class="texto-advertencia">Al generar el archivo, los fondos estarán disponibles para los trabajadores.</p>
        <div class="acciones-modal">
            <button type="button" class="boton boton-secundario" @click="cerrarConfirmacionDesembolso">Cancelar</button>
            <button type="button" class="boton boton-primario" :disabled="generandoFondos" @click="descargarTxtFondos">Descargar TXT</button>
        </div>
    </dialog>

    <dialog ref="dialogoCuenta" @cancel.prevent="cerrarConfiguracionCuenta">
        <button type="button" class="dialogo-cerrar" aria-label="Cerrar" @click="cerrarConfiguracionCuenta">&times;</button>
        <h2>Configurar cuenta de cargo</h2>
        <p>Empresa: <strong>{{ empresa?.razon_social }}</strong></p>
        <p>Cuenta actual: <strong>{{ empresa?.cuenta_enmascarada }}</strong></p>
        <p v-if="empresa?.cuenta_cargo_bbva_fecha_actualizacion" class="texto-vacio">
            Última actualización: {{ formatearFecha(empresa.cuenta_cargo_bbva_fecha_actualizacion) }}
        </p>
        <p v-if="errorCuenta" class="error">{{ errorCuenta }}</p>
        <div class="campo">
            <label for="cuentaCargoBbva">Nueva cuenta BBVA</label>
            <input id="cuentaCargoBbva" type="text" inputmode="numeric" maxlength="20" placeholder="20 dígitos" v-model="cuentaEmpresa" @input="limpiarCuentaEmpresa">
        </div>
        <div class="campo">
            <label for="confirmacionCuentaCargoBbva">Confirmar nueva cuenta</label>
            <input id="confirmacionCuentaCargoBbva" type="text" inputmode="numeric" maxlength="20" placeholder="Repite los 20 dígitos" v-model="confirmacionCuentaEmpresa" @input="limpiarCuentaEmpresa">
        </div>
        <div class="acciones-modal">
            <button type="button" class="boton boton-secundario" @click="cerrarConfiguracionCuenta">Cancelar</button>
            <button type="button" class="boton boton-primario" :disabled="guardandoCuenta" @click="guardarCuentaEmpresa">
                {{ guardandoCuenta ? 'Guardando…' : 'Guardar cuenta' }}
            </button>
        </div>
    </dialog>

    <dialog ref="dialogoReactivar" @cancel.prevent="cerrarReactivacion">
        <button type="button" class="dialogo-cerrar" aria-label="Cerrar" @click="cerrarReactivacion">
            &times;
        </button>
        <h2>Reactivar descarga</h2>
        <p>Las {{ seleccionadasReactivar.length }} cajas seleccionadas volverán a aparecer como pagos pendientes.</p>
        <div class="acciones-modal">
            <button type="button" class="boton boton-secundario" @click="cerrarReactivacion">
                Cancelar
            </button>
            <button
                type="button"
                class="boton boton-primario"
                :disabled="reactivando"
                @click="reactivar">
                {{ reactivando ? 'Reactivando…' : 'Confirmar reactivación' }}
            </button>
        </div>
    </dialog>

    <dialog ref="dialogoReactivarFondos" @cancel.prevent="cerrarReactivacionFondos">
        <button type="button" class="dialogo-cerrar" aria-label="Cerrar" @click="cerrarReactivacionFondos">&times;</button>
        <h2>Reactivar desembolso</h2>
        <p>Los {{ fondosSeleccionadosReactivar.length }} fondos seleccionados volverán a estar pendientes de desembolso.</p>
        <p class="texto-advertencia">No estarán disponibles para el trabajador hasta que se genere nuevamente el TXT.</p>
        <div class="acciones-modal">
            <button type="button" class="boton boton-secundario" @click="cerrarReactivacionFondos">Cancelar</button>
            <button type="button" class="boton boton-primario" :disabled="reactivandoFondos" @click="reactivarFondos">
                {{ reactivandoFondos ? 'Reactivando…' : 'Confirmar reactivación' }}
            </button>
        </div>
    </dialog>
</div>
<script>
    window.DATOS_INICIALES = <?= json_encode([
        'baseUrl' => BASE_URL,
        'empresas' => $empresas,
        'puedeGestionarPagos' => $puedeGestionarPagos,
        'puedeReactivar' => $puedeReactivar,
        'vistaInicial' => $vistaInicial,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<script src="<?= BASE_URL ?>/js/vendor/vue.global.prod.js"></script>
<script type="module" src="<?= assetUrl('/js/pagos_bbva.js') ?>"></script>
<?php
    cierreLayout();
}
