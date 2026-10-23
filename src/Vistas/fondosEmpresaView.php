<?php
require_once __DIR__ . '/layout.php';

function vistaFondosEmpresa(array $fondos, array $empresas, array $trabajadores, bool $puedeAsignar): void
{
    aperturaLayout('Fondos', 'fondos-empresa');
?>
    <div id="app" v-cloak>
        <div class="barra-superior">
            <div>
                <a href="<?= BASE_URL ?>/home" class="volver"><?= icono('volver') ?> Volver</a>
                <h1>Fondos</h1>
            </div>
            <button v-if="datos.puedeAsignar" type="button" class="boton boton-primario" @click="mostrarModalAsignar = true">
                <?= icono('mas') ?> Nuevo Fondo
            </button>
        </div>

        <div class="filtros-cabecera">
            <button type="button" class="boton boton-chico boton-filtros" @click="mostrarFiltros = !mostrarFiltros">
                <?= icono('filtro') ?> Filtros<span v-if="hayFiltrosActivos" class="filtros-badge"></span>
            </button>
            <button type="button" class="boton boton-chico boton-secundario" v-if="hayFiltrosActivos" @click="limpiarFiltros">
                Limpiar filtros
            </button>
        </div>
        <section class="ficha ficha-formulario filtros-historial" v-if="mostrarFiltros">
            <div class="campo">
                <label for="filtroTexto">DNI o nombre</label>
                <input type="text" id="filtroTexto" v-model="filtroTexto" placeholder="Buscar...">
            </div>
            <div class="campo">
                <label for="filtroEmpresa">Empresa</label>
                <select id="filtroEmpresa" v-model="filtroEmpresa">
                    <option value="">Todas</option>
                    <option v-for="e in empresasEnLista" :key="e" :value="e">{{ e }}</option>
                </select>
            </div>
            <div class="campo">
                <label for="filtroTipo">Tipo</label>
                <select id="filtroTipo" v-model="filtroTipo">
                    <option value="">Todos</option>
                    <option value="RECURRENTE">Recurrente</option>
                    <option value="NO_RECURRENTE">No recurrente</option>
                </select>
            </div>
            <div class="campo">
                <label for="filtroEstado">Estado</label>
                <select id="filtroEstado" v-model="filtroEstado">
                    <option value="">Todos</option>
                    <option value="PENDIENTE_DESEMBOLSO">Pendiente de desembolso</option>
                    <option value="ACTIVO">Activo</option>
                    <option value="CERRADO">Cerrado</option>
                </select>
            </div>
            <div class="campo">
                <label for="filtroDesde">Fecha de asignación (desde)</label>
                <input type="date" id="filtroDesde" v-model="filtroDesde">
            </div>
            <div class="campo">
                <label for="filtroHasta">Fecha de asignación (hasta)</label>
                <input type="date" id="filtroHasta" v-model="filtroHasta">
            </div>
        </section>

        <p class="texto-vacio" v-if="datos.fondos.length === 0">Todavía no se asignó ningún fondo.</p>
        <p class="texto-vacio" v-else-if="fondosFiltrados.length === 0">Ningún fondo coincide con estos filtros.</p>
        <div class="tabla-scroll tabla-scroll-vertical" v-else>
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Trabajador</th>
                        <th>DNI</th>
                        <th>Empresa</th>
                        <th>Tipo</th>
                        <th>Monto asignado</th>
                        <th>Saldo disponible</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th v-if="datos.puedeAsignar">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="f in fondosFiltrados" :key="f.fondo_id">
                        <td>{{ f.trabajador }}</td>
                        <td>{{ f.dni }}</td>
                        <td>{{ f.empresa }}</td>
                        <td>{{ f.tipo }}</td>
                        <td>{{ formatearSoles(f.monto_asignado) }}</td>
                        <td>{{ formatearSoles(f.saldo_disponible) }}</td>
                        <td>{{ formatearFecha(f.fecha_asignacion) }}</td>
                        <td><span class="estado" :class="'estado-' + f.estado.toLowerCase()">{{ f.estado }}</span></td>
                        <td v-if="datos.puedeAsignar">
                            <button
                                v-if="f.tipo === 'RECURRENTE' && f.estado === 'ACTIVO'"
                                type="button"
                                class="boton boton-chico"
                                @click="abrirModalEditar(f)">
                                <?= icono('editar') ?>
                            </button>
                            <span v-else>—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <dialog ref="refModalAsignar" class="dialogo-asignar-fondo" v-if="datos.puedeAsignar">
            <h2><?= icono('mas') ?> Nuevo Fondo</h2>

            <div class="campo">
                <label for="empresa_id">Empresa</label>
                <select id="empresa_id" v-model="form.empresa_id" required>
                    <option value="">Selecciona una empresa</option>
                    <option v-for="e in datos.empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                </select>
            </div>
            <div class="campo campo-autocompletar">
                <label for="trabajador_busqueda">Trabajador</label>
                <div v-if="trabajadorSeleccionado" class="trabajador-seleccionado">
                    <div>
                        <strong>{{ trabajadorSeleccionado.nombres }}</strong>
                        <span>
                            DNI {{ trabajadorSeleccionado.dni }} . {{ trabajadorSeleccionado.area || 'Sin área' }}
                        </span>
                    </div>
                    <button
                        type="button"
                        class="boton-limpiar-archivo"
                        aria-label="Cambiar trabajador"
                        title="Cambiar trabajador"
                        @click="quitarTrabajadorSeleccionado">
                        &times;
                    </button>
                </div>
                <template v-else>
                    <input
                        type="text"
                        id="trabajador_busqueda"
                        v-model="busquedaTrabajador"
                        @focus="mostrarSugerencias = true"
                        @blur="ocultarSugerenciasConRetraso"
                        placeholder="Busca por DNI o nombre..."
                        autocomplete="off">
                    <ul class="sugerencias-trabajador" v-if="mostrarSugerencias && sugerenciasTrabajador.length">
                        <li v-for="t in sugerenciasTrabajador" :key="t.id" @mousedown.prevent="elegirTrabajador(t)">
                            {{ t.nombres }} — DNI {{ t.dni }} · {{ t.area }}
                        </li>
                    </ul>
                </template>
            </div>
            <div class="campo">
                <label for="tipo">Tipo</label>
                <select id="tipo" v-model="form.tipo" required>
                    <option value="">Selecciona un tipo</option>
                    <option value="RECURRENTE">Recurrente</option>
                    <option value="NO_RECURRENTE">No recurrente</option>
                </select>
            </div>
            <div class="campo">
                <label for="monto_asignado">Monto asignado</label>
                <input type="number" id="monto_asignado" step="0.01" min="0.01" v-model="form.monto_asignado" required>
            </div>
            <div class="campo">
                <label for="fecha_asignacion">Fecha de asignación</label>
                <input type="date" id="fecha_asignacion" v-model="form.fecha_asignacion" :max="hoy" required>
            </div>
            <div class="campo-check">
                <label>
                    <input type="checkbox" v-model="form.generar_txt">
                    Generar TXT de desembolso al asignar.
                </label>
                <small>
                    El trabajador verá el fondo después de generar el archivo.
                </small>
            </div>
            <p class="error" v-if="error">{{ error }}</p>

            <div class="acciones-modal">
                <button type="button" class="boton boton-secundario" :disabled="guardando" @click="cerrarModalAsignar">Cancelar</button>
                <button type="button" class="boton boton-primario" :disabled="guardando" @click="asignar">
                    <span v-if="guardando" class="spinner"></span>{{ guardando ? 'Guardando…' : (fondoCreadoId ? 'Reintentar TXT' : 'Asignar Fondo') }}
                </button>
            </div>
        </dialog>

        <dialog ref="refModalEditar" class="dialogo-asignar-fondo" v-if="datos.puedeAsignar">
            <h2><?= icono('editar') ?> Editar monto recurrente</h2>

            <p v-if="fondoEditando">
                <strong>{{ fondoEditando.trabajador }}</strong><br>
                {{ fondoEditando.empresa }} · monto actual {{ formatearSoles(fondoEditando.monto_asignado) }}
            </p>

            <div class="campo">
                <label for="monto_editar">Nuevo monto asignado</label>
                <input type="number" id="monto_editar" step="0.01" min="0.01" v-model="montoEditar" required>
            </div>
            <p class="texto-vacio">El cambio se usará para calcular el saldo disponible del trabajador.</p>
            <p class="error" v-if="errorEditar">{{ errorEditar }}</p>

            <div class="acciones-modal">
                <button type="button" class="boton boton-secundario" :disabled="guardandoEditar" @click="cerrarModalEditar">Cancelar</button>
                <button type="button" class="boton boton-primario" :disabled="guardandoEditar" @click="actualizarMonto">
                    <span v-if="guardandoEditar" class="spinner"></span>{{ guardandoEditar ? 'Guardando…' : 'Guardar monto' }}
                </button>
            </div>
        </dialog>
    </div>
    <script>
        window.DATOS_INICIALES = <?= json_encode([
                                        'baseUrl' => BASE_URL,
                                        'fondos' => $fondos,
                                        'empresas' => $empresas,
                                        'trabajadores' => $trabajadores,
                                        'puedeAsignar' => $puedeAsignar,
                                    ]) ?>
    </script>
    <script src="<?= BASE_URL ?>/js/vendor/vue.global.prod.js"></script>
    <script type="module" src="<?= assetUrl('/js/fondos_empresa.js') ?>"></script>
<?php
    cierreLayout();
}
