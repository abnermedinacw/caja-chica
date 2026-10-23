<?php
require_once __DIR__ . '/layout.php';

function vistaHistorialAprobaciones(array $resumenes): void
{
    aperturaLayout('Historial de aprobaciones', 'historial-aprobaciones');
    ?>
    <div id="app" v-cloak>
        <div class="barra-superior">
            <div>
                <a href="<?= BASE_URL ?>/home" class="volver"><?= icono('volver') ?> Volver</a>
                <h1>Historial de aprobaciones</h1>
            </div>
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
                <label for="filtroTexto">N° o solicitante</label>
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
                <label for="filtroAccion">Acción</label>
                <select id="filtroAccion" v-model="filtroAccion">
                    <option value="">Todas</option>
                    <option value="APROBADO">Aprobado</option>
                    <option value="RECHAZADO">Rechazado</option>
                </select>
            </div>
            <div class="campo">
                <label for="filtroEstado">Estado actual</label>
                <select id="filtroEstado" v-model="filtroEstado">
                    <option value="">Todos</option>
                    <option value="BORRADOR">Borrador</option>
                    <option value="EN_REVISION">En revisión</option>
                    <option value="RECHAZADO">Rechazado</option>
                    <option value="VISADO">Visado</option>
                </select>
            </div>
            <div class="campo">
                <label for="filtroDesde">Desde</label>
                <input type="date" id="filtroDesde" v-model="filtroDesde">
            </div>
            <div class="campo">
                <label for="filtroHasta">Hasta</label>
                <input type="date" id="filtroHasta" v-model="filtroHasta">
            </div>
        </section>

        <p class="texto-vacio" v-if="resumenes.length === 0">Todavía no aprobaste ni rechazaste ningún Resumen.</p>
        <p class="texto-vacio" v-else-if="resumenesFiltrados.length === 0">Ningún Resumen coincide con estos filtros.</p>
        <div class="tabla-scroll tabla-scroll-vertical tabla-scroll-historial-aprobaciones" v-else>
        <table class="tabla">
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Empresa</th>
                    <th>Responsable</th>
                    <th>Total</th>
                    <th>Acción</th>
                    <th>Fecha</th>
                    <th>Estado actual</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="r in resumenesFiltrados" :key="r.id">
                    <td>{{ r.numero_formateado }}</td>
                    <td>{{ r.empresa }}</td>
                    <td>{{ r.solicitante }}</td>
                    <td>{{ formatearSoles(r.total_egresos) }}</td>
                    <td>{{ r.accion === 'APROBADO' ? 'Aprobado' : 'Rechazado' }}</td>
                    <td>{{ formatearFecha(r.fecha_accion) }}</td>
                    <td><span class="estado" :class="'estado-' + r.estado_general.toLowerCase()">{{ r.estado_general }}</span></td>
                    <td><a :href="datos.baseUrl + '/ver-resumen?id=' + r.id + '&amp;origen=historial-aprobaciones'" class="boton boton-chico"><?= icono('ver') ?> Ver</a></td>
                </tr>
            </tbody>
        </table>
        </div>
    </div>
    <script>
        window.DATOS_INICIALES = <?= json_encode(['baseUrl' => BASE_URL, 'resumenes' => $resumenes]) ?>
    </script>
    <script src="<?= BASE_URL ?>/js/vendor/vue.global.prod.js"></script>
    <script type="module" src="<?= assetUrl('/js/historial_aprobaciones.js') ?>"></script>
    <?php
    cierreLayout();
}
