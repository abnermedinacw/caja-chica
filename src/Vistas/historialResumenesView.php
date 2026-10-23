<?php
require_once __DIR__ . '/layout.php';
function vistaHistorialResumenes(array $resumenes): void
{
    aperturaLayout('Historial de Resúmenes', 'historial');
?>
    <div id="app" v-cloak>
        <div class="barra-superior">
            <div>
                <a href="<?= BASE_URL ?>/home" class="volver"><?= icono('volver') ?> Volver</a>
                <h1>Historial de Resúmenes</h1>
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
                <label for="filtroNumero">N°</label>
                <input type="text" id="filtroNumero" v-model="filtroNumero" placeholder="Ej. 5">
            </div>
            <div class="campo">
                <label for="filtroDesde">Desde</label>
                <input type="date" id="filtroDesde" v-model="filtroDesde">
            </div>
            <div class="campo">
                <label for="filtroHasta">Hasta</label>
                <input type="date" id="filtroHasta" v-model="filtroHasta">
            </div>
            <div class="campo">
                <label for="filtroEstado">Estado</label>
                <select id="filtroEstado" v-model="filtroEstado">
                    <option value="">Todos</option>
                    <option value="BORRADOR">Borrador</option>
                    <option value="EN_REVISION">En revisión</option>
                    <option value="RECHAZADO">Rechazado</option>
                    <option value="VISADO">Visado</option>
                </select>
            </div>
            <div class="campo">
                <label for="filtroEmpresa">Empresa</label>
                <select id="filtroEmpresa" v-model="filtroEmpresa">
                    <option value="">Todas</option>
                    <option v-for="empresa in empresas" :key="empresa" :value="empresa">{{ empresa }}</option>
                </select>
            </div>
        </section>
        <p class="texto-vacio" v-if="resumenes.length === 0">Todavía no rendiste ninguna Caja Chica</p>
        <p class="texto-vacio" v-else-if="resumenesFiltrados.length === 0">Ningún Resumen coincide con estos filtros</p>
        <div class="tabla-scroll tabla-scroll-vertical" v-else>
        <table class="tabla">
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Empresa</th>
                    <th>Periodo</th>
                    <th>Total egresos</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="r in resumenesFiltrados" :key="r.id">
                    <td>{{ r.numero_formateado }}</td>
                    <td>{{ r.empresa }}</td>
                    <td>{{ formatearFecha(r.periodo_inicio) }} - {{ formatearFecha(r.periodo_fin) }}</td>
                    <td>{{ formatearSoles(r.total_egresos) }}</td>
                    <td><span class="estado" :class="'estado-'+ r.estado_general.toLowerCase()">{{ r.estado_general }}</span></td>
                    <td><a :href="datos.baseUrl + '/ver-resumen?id=' + r.id + '&amp;origen=historial'" class="boton boton-chico"><?= icono('ver') ?> Ver</a></td>
                </tr>
            </tbody>
        </table>
        </div>
    </div>
        
    <script>
        window.DATOS_INICIALES = <?= json_encode(['baseUrl' => BASE_URL, 'resumenes' => $resumenes]) ?>
    </script>
    <script src="<?= BASE_URL ?>/js/vendor/vue.global.prod.js"></script>
    <script type="module" src="<?= assetUrl('/js/historial_resumenes.js') ?>"></script>
<?php
    cierreLayout();
}
