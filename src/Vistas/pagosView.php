<?php

require_once __DIR__ . '/layout.php';

function vistaPagos(
    array $empresas,
    bool $puedeGestionarPagos,
    bool $puedeReactivar,
    string $vistaInicial,
    array $cuentasPendientes = []
): void
{
    $rutaActiva = $vistaInicial === 'descargadas' ? 'reactivar-txt-bbva' : 'pagos-bbva';
    $tz = new DateTimeZone('America/Lima');
    $fechaHoyLima = (new DateTimeImmutable('now', $tz))->format('d/m/Y');
    aperturaLayout('Pagos Bancarios', $rutaActiva);
?>
<div id="app" class="pagos-bbva" v-cloak>
    <div class="barra-superior">
        <div>
            <a href="<?= BASE_URL ?>/home" class="volver">
                <?= icono('volver') ?> Volver
            </a>
            <h1>Pagos Bancarios</h1>
        </div>
    </div>

    <!-- Aviso en sistema para Tesorería: cuentas nuevas o editadas por trabajadores -->
    <section v-if="datos.puedeGestionarPagos && cuentasPendientes.length > 0" class="ficha caja-cuentas-pendientes">
        <div class="pagos-bbva-cabecera">
            <div>
                <h2>Pendientes de validación ({{ cuentasPendientes.length }})</h2>
                <p>Uno o más trabajadores registraron o cambiaron su cuenta en su perfil. Verifica la titularidad antes de habilitar pagos.</p>
            </div>
        </div>
        <div class="tabla-scroll tabla-scroll-compacta">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Trabajador</th>
                        <th>DNI</th>
                        <th>Empresa</th>
                        <th>Banco</th>
                        <th>N° de cuenta</th>
                        <th>Registrada</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in cuentasPendientes" :key="`${c.trabajador_id}-${c.banco}`">
                        <td>{{ c.trabajador }}</td>
                        <td>{{ c.dni }}</td>
                        <td>{{ c.empresa }}</td>
                        <td>
                            <span class="estado estado-en_revision">
                                {{ c.banco }}{{ c.tipo_cuenta ? ' (' + (c.tipo_cuenta === 'C' ? 'Corriente' : (c.tipo_cuenta === 'M' ? 'Maestra' : 'Ahorros')) + ')' : '' }}
                            </span>
                        </td>
                        <td><strong class="cuenta-completa-texto">{{ c.cuenta }}</strong></td>
                        <td>{{ formatearFecha(c.fecha_registro) }}</td>
                        <td class="celda-acciones">
                            <button type="button" class="boton boton-primario boton-chico" @click="abrirValidacionCuenta(c)">
                                <?= icono('check') ?> Validar cuenta
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="ficha pagos-bbva-selector">
        <div class="pagos-barra-contexto">
            <div class="pagos-bloque-empresa">
                <label for="empresaPago" class="pagos-contexto-etiqueta">Empresa matriz</label>
                <select id="empresaPago" class="pagos-select-empresa" v-model="empresaId">
                    <option value="">Selecciona una empresa</option>
                    <option
                        v-for="emp in datos.empresas"
                        :key="emp.id"
                        :value="String(emp.id)">
                        {{ emp.razon_social }}
                    </option>
                </select>
            </div>

            <template v-if="empresa && !cargando && datos.puedeGestionarPagos && vista === 'pendientes'">
                <div class="pagos-contexto-divisor" aria-hidden="true"></div>

                <div class="pagos-bloque-banco">
                    <span class="pagos-contexto-etiqueta">Banco emisor (TXT)</span>
                    <div class="pagos-selector-banco" role="tablist" aria-label="Seleccionar banco emisor">
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="banco === 'BBVA'"
                            class="pagos-banco-opcion"
                            :class="{ activo: banco === 'BBVA' }"
                            @click="cambiarBanco('BBVA')">
                            <img src="<?= assetUrl('/img/bbva-logo.svg') ?>" alt="BBVA" class="logo-banco logo-banco-bbva">
                            <span class="pagos-banco-sub">Net Cash</span>
                            <span
                                class="pagos-banco-conteo"
                                :class="{ 'con-pendientes': conteoPendientesPorBanco.BBVA > 0 }">
                                {{ conteoPendientesPorBanco.BBVA }}
                            </span>
                        </button>
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="banco === 'BCP'"
                            class="pagos-banco-opcion"
                            :class="{ activo: banco === 'BCP' }"
                            @click="cambiarBanco('BCP')">
                            <img src="<?= assetUrl('/img/bcp-logo.svg') ?>" alt="BCP" class="logo-banco logo-banco-bcp">
                            <span class="pagos-banco-sub">Telecrédito</span>
                            <span
                                class="pagos-banco-conteo"
                                :class="{ 'con-pendientes': conteoPendientesPorBanco.BCP > 0 }">
                                {{ conteoPendientesPorBanco.BCP }}
                            </span>
                        </button>
                    </div>
                </div>

                <div
                    class="pagos-cuenta-cargo-tarjeta"
                    :class="{ 'sin-cuenta': !tieneCuentaCargoActiva }">
                    <div class="pagos-cuenta-icono" aria-hidden="true">
                        <?= icono('tarjeta') ?>
                    </div>
                    <div class="pagos-cuenta-detalle">
                        <span class="pagos-contexto-etiqueta">Cuenta de cargo origen</span>
                        <template v-if="tieneCuentaCargoActiva">
                            <strong class="pagos-cuenta-tipo">
                                {{ banco === 'BCP'
                                    ? (empresa.tipo_cuenta_cargo_bcp === 'M' ? 'Cta. Maestra Soles (PEN)' : 'Cta. Corriente Soles (PEN)')
                                    : 'Cuenta BBVA Soles (PEN)' }}
                            </strong>
                            <span class="pagos-cuenta-numero">{{ banco }}: {{ cuentaCargoActivaEnmascarada }}</span>
                        </template>
                        <template v-else>
                            <strong class="pagos-cuenta-tipo pagos-cuenta-faltante">Sin cuenta {{ banco }} configurada</strong>
                            <span class="pagos-cuenta-numero">Registra la cuenta para descargar el TXT</span>
                        </template>
                    </div>
                    <button
                        type="button"
                        class="boton boton-secundario boton-chico pagos-btn-configurar"
                        @click="abrirConfiguracionCuenta">
                        <?= icono('editar') ?> Configurar
                    </button>
                </div>
            </template>
        </div>
    </section>

    <p v-if="cargando" class="texto-vacio">
        <span class="spinner"></span>
        Cargando pagos...
    </p>
    <p v-if="mensaje" class="mensaje-perfil mensaje-perfil-exito">{{ mensaje }}</p>
    <p v-if="error" class="error">{{ error }}</p>

    <template v-if="empresa && !cargando">
        <div v-if="datos.puedeGestionarPagos && datos.puedeReactivar" class="pagos-bbva-tabs">
            <button
                type="button"
                class="boton"
                :class="vista === 'pendientes' ? 'boton-primario' : 'boton-secundario'"
                @click="cambiarVista('pendientes')">
                Pendientes de pago
            </button>

            <button
                type="button"
                class="boton"
                :class="vista === 'descargadas' ? 'boton-primario' : 'boton-secundario'"
                @click="cambiarVista('descargadas')">
                Descargas registradas
            </button>
        </div>

        <p v-if="datos.puedeGestionarPagos && vista === 'pendientes' && !tieneCuentaCargoActiva" class="aviso-modo-correccion">
            Primero debes registrar la cuenta de cargo <strong>{{ banco }}</strong>
        </p>

        <p v-if="datos.puedeGestionarPagos && vista === 'pendientes' && registrosSinBanco.length > 0" class="aviso-modo-correccion">
            Hay <strong>{{ registrosSinBanco.length }}</strong> pago(s) pendiente(s) cuyo trabajador aún no tiene cuenta BBVA ni BCP registrada en su perfil:
            {{ registrosSinBanco.map(r => r.trabajador).join(', ') }}.
        </p>

        <!-- Fondos pendientes de desembolso del banco activo -->
        <section v-if="datos.puedeGestionarPagos && vista === 'pendientes'" class="ficha">
            <div class="pagos-bbva-cabecera">
                <div>
                    <h2 class="pagos-titulo-con-logo">
                        <span>Fondos pendientes de desembolso</span>
                        <img
                            :src="banco === 'BCP' ? '<?= assetUrl('/img/bcp-logo.svg') ?>' : '<?= assetUrl('/img/bbva-logo.svg') ?>'"
                            :alt="banco"
                            class="logo-banco-titulo"
                            :class="banco === 'BCP' ? 'logo-banco-bcp' : 'logo-banco-bbva'">
                    </h2>
                    <p>Solo se muestran trabajadores con {{ banco }} como banco predeterminado. Al generar el TXT, el fondo quedará activo.</p>
                </div>
                <button
                    type="button"
                    class="boton boton-primario"
                    :disabled="fondosSeleccionados.length === 0 || !tieneCuentaCargoActiva || generandoFondos"
                    @click="abrirConfirmacionDesembolso">
                    <span v-if="generandoFondos" class="spinner"></span>
                    Descargar TXT {{ banco }} ({{ fondosSeleccionados.length }})
                </button>
            </div>

            <p v-if="fondosPendientesBanco.length === 0" class="texto-vacio">
                No hay fondos pendientes de desembolso para {{ banco }}.
            </p>

            <div v-else class="tabla-scroll tabla-scroll-vertical">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th class="pagos-bbva-col-check">
                                <input type="checkbox" v-model="todosFondosSeleccionados" :disabled="!tieneCuentaCargoActiva || fondosHabilitadosBanco.length === 0" aria-label="Seleccionar todos los fondos">
                            </th>
                            <th>Trabajador</th>
                            <th>DNI</th>
                            <th>Tipo</th>
                            <th>Cuenta {{ banco }}</th>
                            <th>Importe</th>
                            <th>Validación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="fondo in fondosPendientesBanco" :key="fondo.id">
                            <td class="pagos-bbva-col-check">
                                <input type="checkbox" v-model="fondosSeleccionados" :value="Number(fondo.id)" :disabled="!fondo.habilitada || !tieneCuentaCargoActiva" :aria-label="`Seleccionar fondo de ${fondo.trabajador}`">
                            </td>
                            <td>{{ fondo.trabajador }}</td>
                            <td>{{ fondo.dni }}</td>
                            <td>{{ fondo.tipo }}</td>
                            <td>
                                {{ fondo.cuenta_enmascarada }}
                                <small v-if="fondo.tipo_cuenta_etiqueta" class="texto-vacio">({{ fondo.tipo_cuenta_etiqueta }})</small>
                            </td>
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

        <!-- Cajas visadas pendientes del banco activo -->
        <section v-if="datos.puedeGestionarPagos && vista === 'pendientes'" class="ficha">
            <div class="pagos-bbva-cabecera">
                <div>
                    <h2 class="pagos-titulo-con-logo">
                        <span>Cajas visadas</span>
                        <img
                            :src="banco === 'BCP' ? '<?= assetUrl('/img/bcp-logo.svg') ?>' : '<?= assetUrl('/img/bbva-logo.svg') ?>'"
                            :alt="banco"
                            class="logo-banco-titulo"
                            :class="banco === 'BCP' ? 'logo-banco-bcp' : 'logo-banco-bbva'">
                    </h2>
                    <p>Solo se muestran trabajadores con {{ banco }} como banco predeterminado.</p>
                </div>
                <div class="pagos-bbva-acciones">
                    <button
                        type="button"
                        class="boton boton-primario"
                        :disabled="seleccionadas.length === 0 || !tieneCuentaCargoActiva || generando"
                        @click="abrirConfirmacionDescarga">
                        <span v-if="generando" class="spinner"></span>
                        Descargar TXT {{ banco }} ({{ seleccionadas.length }})
                    </button>
                </div>
            </div>

            <p v-if="cajasPendientesBanco.length === 0" class="texto-vacio">
                No hay cajas visadas pendientes de pago para {{ banco }}.
            </p>

            <div v-else class="tabla-scroll tabla-scroll-vertical">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th class="pagos-bbva-col-check">
                                <input type="checkbox" v-model="todosSeleccionados" :disabled="!tieneCuentaCargoActiva || cajasHabilitadasBanco.length === 0" aria-label="Seleccionar todas las cajas">
                            </th>
                            <th>N°</th>
                            <th>Trabajador</th>
                            <th>DNI</th>
                            <th>Cuenta {{ banco }}</th>
                            <th>Importe</th>
                            <th>Validación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="caja in cajasPendientesBanco" :key="caja.id">
                            <td class="pagos-bbva-col-check">
                                <input type="checkbox" v-model="seleccionadas" :value="Number(caja.id)" :disabled="!caja.habilitada || !tieneCuentaCargoActiva">
                            </td>
                            <td>{{ numeroDocumento(caja.numero) }}</td>
                            <td>{{ caja.trabajador }}</td>
                            <td>{{ caja.dni }}</td>
                            <td>
                                {{ caja.cuenta_enmascarada }}
                                <small v-if="caja.tipo_cuenta_etiqueta" class="texto-vacio">({{ caja.tipo_cuenta_etiqueta }})</small>
                            </td>
                            <td>
                                {{ formatearSoles(caja.importe) }}
                                <small v-if="caja.fondo_tipo === 'NO_RECURRENTE'" class="texto-vacio" style="display:block;font-size:11.5px;">
                                    Excedente ({{ formatearSoles(caja.total_rendido) }} - {{ formatearSoles(caja.saldo_inicial) }})
                                </small>
                            </td>
                            <td>
                                <span v-if="caja.habilitada" class="estado estado-activo">Lista</span>
                                <span v-else class="estado estado-pendiente">{{ caja.motivo_invalido }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Pestaña Descargas registradas / Reactivación Admin -->
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
                            <th>Banco</th>
                            <th>Trabajador</th>
                            <th>DNI</th>
                            <th>Cuenta</th>
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
                            <td><span class="estado estado-en_revision">{{ caja.banco_pago || 'BBVA' }}</span></td>
                            <td>{{ caja.trabajador }}</td>
                            <td>{{ caja.dni }}</td>
                            <td>{{ caja.cuenta_enmascarada }}</td>
                            <td>
                                {{ formatearSoles(caja.importe) }}
                                <small v-if="caja.fondo_tipo === 'NO_RECURRENTE'" class="texto-vacio" style="display:block;font-size:11.5px;">
                                    Excedente ({{ formatearSoles(caja.total_rendido) }} - {{ formatearSoles(caja.saldo_inicial) }})
                                </small>
                            </td>
                            <td>{{ formatearFecha(caja.txt_fecha_descarga) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="pagos-bbva-cabecera fondos-reactivacion-cabecera" style="margin-top: 24px;">
                <div>
                    <h3>Desembolsos de fondos registrados</h3>
                    <p class="texto-vacio">Solo se pueden reactivar fondos que aún no tengan resúmenes asociados.</p>
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
                            <th>Banco</th>
                            <th>Trabajador</th>
                            <th>DNI</th>
                            <th>Tipo</th>
                            <th>Importe</th>
                            <th>Desembolsado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="fondo in fondosDesembolsados" :key="fondo.id">
                            <td class="pagos-bbva-col-check"><input type="checkbox" v-model="fondosSeleccionadosReactivar" :value="Number(fondo.id)" :aria-label="`Reactivar fondo de ${fondo.trabajador}`"></td>
                            <td><span class="estado estado-en_revision">{{ fondo.banco_pago || 'BBVA' }}</span></td>
                            <td>{{ fondo.trabajador }}</td>
                            <td>{{ fondo.dni }}</td>
                            <td>{{ fondo.tipo }}</td>
                            <td>{{ formatearSoles(fondo.importe) }}</td>
                            <td>{{ formatearFecha(fondo.fecha_desembolso) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </template>

    <!-- Modal confirmar TXT cajas -->
    <dialog ref="dialogoDescarga" @cancel.prevent="cerrarConfirmacionDescarga">
        <button type="button" class="dialogo-cerrar" aria-label="Cerrar" @click="cerrarConfirmacionDescarga">&times;</button>
        <h2>Confirmar archivo de pago {{ banco }}</h2>
        <p>Banco: <strong>{{ banco === 'BCP' ? 'BCP (Telecrédito Haberes)' : 'BBVA (Net Cash)' }}</strong></p>
        <p>Empresa: <strong>{{ empresa?.razon_social }}</strong></p>
        <p>Cuenta de cargo: <strong>{{ cuentaCargoActivaEnmascarada }}</strong></p>
        <p>Pagos seleccionados: <strong>{{ seleccionadas.length }}</strong></p>
        <p>Total a transferir: <strong>{{ formatearSoles(totalSeleccionado) }}</strong></p>
        <p v-if="banco === 'BCP'" class="aviso-modo-correccion">
            Fecha de proceso BCP: <strong>Hoy ({{ fechaHoyLima }})</strong>. Sube este archivo a Telecrédito hoy mismo; si se sube en otra fecha el banco lo rechazará.
        </p>
        <div class="acciones-modal">
            <button type="button" class="boton boton-secundario" @click="cerrarConfirmacionDescarga">Cancelar</button>
            <button type="button" class="boton boton-primario" :disabled="generando" @click="descargarTxt">
                Descargar TXT {{ banco }}
            </button>
        </div>
    </dialog>

    <!-- Modal confirmar TXT fondos -->
    <dialog ref="dialogoDesembolsoFondo" @cancel.prevent="cerrarConfirmacionDesembolso">
        <button type="button" class="dialogo-cerrar" aria-label="Cerrar" @click="cerrarConfirmacionDesembolso">&times;</button>
        <h2>Confirmar desembolso de fondos {{ banco }}</h2>
        <p>Banco: <strong>{{ banco === 'BCP' ? 'BCP (Telecrédito Haberes)' : 'BBVA (Net Cash)' }}</strong></p>
        <p>Empresa: <strong>{{ empresa?.razon_social }}</strong></p>
        <p>Cuenta de cargo: <strong>{{ cuentaCargoActivaEnmascarada }}</strong></p>
        <p>Fondos seleccionados: <strong>{{ fondosSeleccionados.length }}</strong></p>
        <p>Total a desembolsar: <strong>{{ formatearSoles(totalFondosSeleccionados) }}</strong></p>
        <p class="texto-vacio">Al generar el archivo, los fondos quedarán activos y disponibles para los trabajadores.</p>
        <p v-if="banco === 'BCP'" class="aviso-modo-correccion">
            Fecha de proceso BCP: <strong>Hoy ({{ fechaHoyLima }})</strong>. Sube este archivo a Telecrédito hoy mismo; si se sube en otra fecha el banco lo rechazará.
        </p>
        <div class="acciones-modal">
            <button type="button" class="boton boton-secundario" @click="cerrarConfirmacionDesembolso">Cancelar</button>
            <button type="button" class="boton boton-primario" :disabled="generandoFondos" @click="descargarTxtFondos">
                Descargar TXT {{ banco }}
            </button>
        </div>
    </dialog>

    <!-- Modal configurar cuenta de cargo (BBVA o BCP) -->
    <dialog ref="dialogoCuenta" @cancel.prevent="cerrarConfiguracionCuenta">
        <button type="button" class="dialogo-cerrar" aria-label="Cerrar" @click="cerrarConfiguracionCuenta">&times;</button>
        <h2>Configurar cuenta de cargo {{ banco }}</h2>
        <p>Empresa: <strong>{{ empresa?.razon_social }}</strong></p>
        <p>Cuenta actual: <strong>{{ cuentaCargoActivaEnmascarada }}</strong></p>
        <p v-if="fechaActualizacionCargoActiva" class="texto-vacio">
            Última actualización: {{ formatearFecha(fechaActualizacionCargoActiva) }}
        </p>
        <p v-if="errorCuenta" class="error">{{ errorCuenta }}</p>
        <div v-if="banco === 'BCP'" class="campo">
            <label for="tipoCuentaCargoBcp">Tipo de cuenta de cargo BCP</label>
            <select id="tipoCuentaCargoBcp" v-model="tipoCuentaEmpresaBcp">
                <option value="C">Cuenta Corriente (C)</option>
                <option value="M">Cuenta Maestra (M)</option>
            </select>
        </div>
        <div class="campo">
            <label for="cuentaCargoBanco">Nueva cuenta {{ banco }}</label>
            <input
                id="cuentaCargoBanco"
                type="text"
                inputmode="numeric"
                :maxlength="banco === 'BCP' ? 13 : 20"
                :placeholder="banco === 'BCP' ? '13 dígitos' : '20 dígitos'"
                v-model="cuentaEmpresa"
                @input="limpiarCuentaEmpresa">
        </div>
        <div class="campo">
            <label for="confirmacionCuentaCargoBanco">Confirmar nueva cuenta {{ banco }}</label>
            <input
                id="confirmacionCuentaCargoBanco"
                type="text"
                inputmode="numeric"
                :maxlength="banco === 'BCP' ? 13 : 20"
                :placeholder="banco === 'BCP' ? 'Repite los 13 dígitos' : 'Repite los 20 dígitos'"
                v-model="confirmacionCuentaEmpresa"
                @input="limpiarCuentaEmpresa">
        </div>
        <div class="acciones-modal">
            <button type="button" class="boton boton-secundario" @click="cerrarConfiguracionCuenta">Cancelar</button>
            <button type="button" class="boton boton-primario" :disabled="guardandoCuenta" @click="guardarCuentaEmpresa">
                {{ guardandoCuenta ? 'Guardando…' : 'Guardar cuenta' }}
            </button>
        </div>
    </dialog>

    <!-- Modal validar cuenta nueva de trabajador (Tesorería) -->
    <dialog ref="dialogoValidarCuenta" @cancel.prevent="cerrarValidacionCuenta">
        <button type="button" class="dialogo-cerrar" aria-label="Cerrar" @click="cerrarValidacionCuenta">&times;</button>
        <h2>Validar cuenta de trabajador</h2>
        <template v-if="cuentaPorValidar">
            <p>Trabajador: <strong>{{ cuentaPorValidar.trabajador }}</strong></p>
            <p>DNI: <strong>{{ cuentaPorValidar.dni }}</strong></p>
            <p>Banco: <strong>{{ cuentaPorValidar.banco }}{{ cuentaPorValidar.tipo_cuenta ? ' (' + (cuentaPorValidar.tipo_cuenta === 'C' ? 'Corriente' : 'Ahorros') + ')' : '' }}</strong></p>
            <p>Cuenta completa: <strong class="cuenta-completa-texto">{{ cuentaPorValidar.cuenta }}</strong></p>
            <p class="texto-vacio">Confirma solo después de verificar.</p>
        </template>
        <p v-if="errorValidacionCuenta" class="error">{{ errorValidacionCuenta }}</p>
        <div class="acciones-modal">
            <button type="button" class="boton boton-secundario" :disabled="validandoCuenta" @click="cerrarValidacionCuenta">Cancelar</button>
            <button type="button" class="boton boton-primario" :disabled="validandoCuenta" @click="confirmarValidacionCuenta">
                {{ validandoCuenta ? 'Validando…' : 'Confirmar titularidad' }}
            </button>
        </div>
    </dialog>

    <!-- Modales reactivación Admin -->
    <dialog ref="dialogoReactivar" @cancel.prevent="cerrarReactivacion">
        <button type="button" class="dialogo-cerrar" aria-label="Cerrar" @click="cerrarReactivacion">&times;</button>
        <h2>Reactivar descarga</h2>
        <p>Las {{ seleccionadasReactivar.length }} cajas seleccionadas volverán a aparecer como pagos pendientes.</p>
        <div class="acciones-modal">
            <button type="button" class="boton boton-secundario" @click="cerrarReactivacion">Cancelar</button>
            <button type="button" class="boton boton-primario" :disabled="reactivando" @click="reactivar">
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
        'cuentasPendientes' => $cuentasPendientes,
        'fechaHoyLima' => $fechaHoyLima,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= BASE_URL ?>/js/vendor/vue.global.prod.js"></script>
<script type="module" src="<?= assetUrl('/js/pagos_bbva.js') ?>"></script>
<?php
    cierreLayout();
}
