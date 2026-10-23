<?php
require_once __DIR__ . '/layout.php';

function vistaTrabajadores(array $trabajadores, array $empresas): void
{
    aperturaLayout('Trabajadores', 'trabajadores');
?>
    <div id="app">
        <div class="barra-superior">
            <div>
                <a href="<?= BASE_URL ?>/home" class="volver"><?= icono('volver') ?>Volver</a>
                <h1>Trabajadores</h1>
            </div>
            <button type="button" class="boton boton-primario" @click="mostrarModalSincronizar = true"><?= icono('mas') ?>Sincronizar Trabajador</button>
        </div>

        <!-- Filtros -->
        <div class="filtros-cabecera">
            <button type="button" class="boton boton-chico boton-filtros" @click="mostrarFiltros = !mostrarFiltros">
                <?= icono('filtro') ?> Filtros<span v-if="hayFiltrosActivos" class="filtros-badge"></span>
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
        </section>

        <!-- Tabla trabajadores -->
        <p class="texto-vacio" v-if="datos.trabajadores.length === 0">No hay trabajadores registrados</p>
        <p class="texto-vacio" v-else-if="trabajadoresFiltrados.length === 0">Ningun trabajador coincide con los filtros de búsqueda.</p>
        <div class="tabla-scroll tabla-scroll-vertical" v-else>
        <table class="tabla">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>DNI</th>
                    <th>Empresa</th>
                    <th>Area</th>
                    <th>Cargo</th>
                    <th>Acceso</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="t in trabajadoresFiltrados" :key="t.id">
                    <td>{{ t.nombres }}</td>
                    <td>{{ t.dni }}</td>
                    <td>{{ t.empresa }}</td>
                    <td>{{ t.area }}</td>
                    <td>{{ t.cargo ?? '-'}}</td>
                    <td>
                        <span v-if="t.tiene_usuario" class="estado estado-activo">Con acceso</span>
                        <span v-else class="estado estado-pendiente">Sin acceso</span>
                    </td>
                </tr>
            </tbody>
        </table>
        </div>
        <!-- Modal de sincronización -->
        <dialog ref="refModalSincronizar" @cancel.prevent="cerrarModal">
            <button type="button" class="dialogo-cerrar" aria-label="Cerrar modal" @click="cerrarModal">&times;</button>
            <h2>Sincronizar Trabajador</h2>
            <div v-if="!trabajadorEncontrado">
                <div class="campo">
                    <label for="dniInput">DNI</label>
                    <input
                        type="text"
                        id="dniInput"
                        v-model="dniBusqueda"
                        maxlength="8"
                        placeholder="Ingresa 8 dígitos"
                        @keyup.enter="buscarPorDni">
                </div>
                <p class="error" v-if="errorBusqueda">{{ errorBusqueda }}</p>
                <div class="acciones-modal">
                    <button type="button" class="boton boton-secundario" @click="cerrarModal">Cancelar</button>
                    <button type="button" class="boton boton-primario" :disabled="buscando" @click="buscarPorDni">
                        <span v-if="buscando" class="spinner"></span>
                        {{ buscando ? 'Buscando... ' : 'Buscar' }}
                    </button>
                </div>
            </div>
            <div v-if="trabajadorEncontrado">
                <p class="texto-vacio">Confirme datos antes de guardar:</p>
                <div class="campo">
                    <label>Nombre</label>
                    <input type="text" :value="form.nombres" disabled>
                </div>
                <div class="campo">
                    <label>Empresa Matriz</label>
                    <input type="text" :value="form.matriz_razon_social" disabled>
                </div>
                <div class="campo" v-if="form.esCliente">
                    <label>Empresa Cliente</label>
                    <input type="text" :value="form.cliente_razon_social" disabled>
                </div>
                <div class="campo">
                    <label>Cargo</label>
                    <input type="text" :value="form.cargo" disabled>
                </div>
                <div class="campo">
                    <label>Cuenta BBVA</label>
                    <input type="text" :value="form.cuenta_bbva ? '************' + form.cuenta_bbva.slice(-8) : 'No encontrada'"
                    disabled>
                    <small v-if="form.advertencia_cuenta" class="texto-advertencia">{{ form.advertencia_cuenta }}</small>
                </div>
                <!-- Si vino área de la bbdd externa, se preselecciona por nombre, pero el usuario puede cambiarla si hace falta. -->  
                <div class="campo">
                    <label for="areaSel">Área <span class="requerido">*</span></label>
                    <select id="areaSel" v-model="form.area">
                        <option value="">Selecciona un área</option>
                        <option v-for="a in areasFiltradas" :key="a" :value="a">{{a}}</option>
                    </select>
                    <label class="campo-check">
                        Es Jefe de Área
                        <input type="checkbox" v-model="form.es_jefe">
                    </label>
                </div>

                <h3>Acceso al sistema</h3>
                <div class="campo">
                    <label for="username">Usuario</label>
                    <input id="username" v-model.trim="form.username" required>
                </div>
                <div class="campo">
                    <label for="correo">Correo corporativo</label>
                    <input id="correo" type="email" v-model.trim="form.correo" placeholder="usuario@t-soluciona.com.pe" required>
                </div>
                <div class="campo">
                    <label for="password">Contraseña</label>
                    <input id="password" type="password" v-model="form.password" required>
                </div>
                <div class="campo">
                    <label for="rol">Rol</label>
                    <select id="rol" v-model="form.rol" required>
                        <option value="SOLICITANTE">Solicitante</option>
                        <option value="TESORERIA">Tesorería</option>
                        <option value="CONTABILIDAD">Contabilidad</option>
                        <option value="GERENCIA">Gerencia</option>
                    </select>
                </div>
                <label class="campo-check">
                    Otorgar permisos de adminstración.
                    <input type="checkbox" v-model="form.es_admin">
                </label>

                <p class="error" v-if="errorGuardado">{{ errorGuardado }}</p>
                <div class="acciones-modal">
                    <button type="button" class="boton boton-secundario" :disabled="guardando" @click="volverABuscar">Regresar </button>
                    <button type="button" class="boton boton-primario" :disabled="guardando" @click="guardar">
                        <span v-if="guardando" class="spinner"></span>
                        {{ guardando ? 'Guardando... ' : 'Registrar Trabajador' }}
                    </button>
                </div>
            </div>
        </dialog>
    </div>
    <script>
        window.DATOS_INICIALES = <?= json_encode([
                                        'baseUrl' => BASE_URL,
                                        'trabajadores' => $trabajadores,
                                        'empresas' => $empresas,
                                    ]) ?>;
    </script>
    <script src="<?= BASE_URL ?>/js/vendor/vue.global.prod.js"></script>
    <script type="module" src="<?= assetUrl('/js/trabajadores.js') ?>"></script>
<?php
    cierreLayout();
}
