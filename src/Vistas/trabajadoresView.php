<?php
require_once __DIR__ . '/layout.php';

function vistaTrabajadores(array $trabajadores, array $empresas, array $responsables, array $areas, array $paginacion): void
{
    $pagina = $paginacion['pagina'];
    $totalPaginas = $paginacion['total_paginas'];
    $total = $paginacion['total'];
    $inicio = max(1, min($pagina - 2, $totalPaginas - 4));
    $fin = min($totalPaginas, $inicio + 4);
    $urlPagina = static fn(int $numero): string => BASE_URL . '/trabajadores?' . http_build_query([
        'buscar' => $paginacion['buscar'],
        'empresa_id' => $paginacion['empresa_id'],
        'por_pagina' => $paginacion['por_pagina'],
        'pagina' => $numero,
    ]);
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
        <form class="ficha ficha-formulario filtros-historial" v-if="mostrarFiltros" method="get" action="<?= BASE_URL ?>/trabajadores">
            <div class="campo">
                <label for="filtroTexto">DNI o nombre</label>
                <input type="search" id="filtroTexto" name="buscar" v-model="filtroTexto" maxlength="100" placeholder="Buscar...">
            </div>
            <div class="campo">
                <label for="filtroEmpresa">Empresa</label>
                <select id="filtroEmpresa" name="empresa_id" v-model="filtroEmpresa">
                    <option value="">Todas</option>
                    <option v-for="e in datos.empresas" :key="e.id" :value="String(e.id)">{{ e.razon_social }}</option>
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
                <a class="boton boton-chico" href="<?= BASE_URL ?>/trabajadores">Limpiar filtros</a>
                <button type="submit" class="boton boton-secundario boton-chico">Aplicar</button>
            </div>
        </form>

        <!-- Tabla trabajadores -->
        <?php if ($total === 0): ?>
        <p class="texto-vacio"><?= $paginacion['buscar'] !== '' || $paginacion['empresa_id'] > 0
            ? 'Ningún trabajador coincide con los filtros de búsqueda.'
            : 'No hay trabajadores registrados.' ?></p>
        <?php else: ?>
        <div class="tabla-scroll tabla-scroll-vertical">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>DNI</th>
                    <th>Empresa</th>
                    <th>Area</th>
                    <th>Cargo</th>
                    <th>Jefe</th>
                    <th>Acceso</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="t in datos.trabajadores" :key="t.id">
                    <td>{{ t.nombres }}</td>
                    <td>{{ t.dni }}</td>
                    <td>{{ t.empresa }}</td>
                    <td>{{ t.area }}</td>
                    <td>{{ t.cargo ?? '-'}}</td>
                    <td>{{ t.jefe || 'Pendiente de asignar' }}</td>
                    <td>
                        <span v-if="t.acceso === 'ACTIVO'" class="estado estado-activo">Activo</span>
                        <span v-else-if="t.acceso === 'INACTIVO'" class="estado estado-pendiente">Inactivo</span>
                        <span v-else class="estado estado-pendiente">Sin usuario</span>
                    </td>
                </tr>
            </tbody>
        </table>
        </div>
        <div class="paginacion-trabajadores">
            <p>Mostrando <?= ($pagina - 1) * $paginacion['por_pagina'] + 1 ?>–<?= min($pagina * $paginacion['por_pagina'], $total) ?> de <?= $total ?> trabajadores</p>
            <nav aria-label="Páginas de trabajadores">
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
        <!-- Modal de sincronización -->
        <dialog ref="refModalSincronizar" @cancel.prevent="cerrarModal">
            <button type="button" class="dialogo-cerrar" aria-label="Cerrar modal" @click="cerrarModal" :disabled="guardando || buscando">&times;</button>
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
                <div class="campo" v-if="form.area_ts">
                    <label for="areaTs">Área laboral</label>
                    <input id="areaTs" type="text" :value="form.area" readonly>
                </div>
                <div class="campo" v-else>
                    <label for="areaSel">Área<span class="requerido">*</span></label>
                    <select id="areaSel" v-model="form.area_id">
                        <option :value="null">Selecciona un área</option>
                        <option v-for="a in areasDeMatriz" :key="a.id" :value="Number(a.id)">{{ a.nombre }}</option>
                    </select>
                    <small>TS no tiene un área laboral asignada. Selecciona la que corresponde.</small>
                </div>

                <div class="campo" v-if="form.grupos_a_cargo.length">
                    <label>Jefatura reconocida en TS</label>
                    <p>{{ form.grupos_a_cargo.join(', ') }}</p>
                </div>

                <div class="campo" v-if="form.jefe_ts">
                    <label for="jefeTs">Jefe</label>
                    <input id="jefeTs" type="text" :value="form.jefe_nombre + ' · DNI ' + form.jefe_dni" readonly>
                    <p class="error" v-if="form.error_jefe">{{ form.error_jefe }}</p>
                </div>
                <div class="campo" v-else>
                    <label for="jefeLocal">Jefe<span class="requerido">*</span></label>
                    <select id="jefeLocal" v-model="form.jefe_usuario_id">
                        <option :value="null">Selecciona un responsable</option>
                        <option v-for="j in datos.responsables" :key="j.usuario_id" :value="Number(j.usuario_id)">
                            {{j.nombres}} - {{ j.dni }} - {{ j.empresa }}
                        </option>
                    </select>
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
                    <button type="button" class="boton boton-primario" :disabled="guardando || !!form.error_jefe" @click="guardar">
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
                                        'responsables' => $responsables,
                                        'areas' => $areas,
                                        'paginacion' => $paginacion,
                                    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="<?= BASE_URL ?>/js/vendor/vue.global.prod.js"></script>
    <script type="module" src="<?= assetUrl('/js/trabajadores.js') ?>"></script>
<?php
    cierreLayout();
}
