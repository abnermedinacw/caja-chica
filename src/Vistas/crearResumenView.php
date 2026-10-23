<?php
require_once __DIR__ . '/layout.php';

function vistaCrearResumen(array $fondos, array $empresas, ?string $error): void
{   
    aperturaLayout('Nuevo Resumen', 'home');
    ?>
    <div id="app">
            <div class="barra-superior">
                <div>
                    <a href="<?= BASE_URL ?>/home" class="volver"><?= icono('volver') ?> Volver</a>
                    <h1>Nuevo Resumen</h1>
                </div>
            </div>
            <div class="error" v-if="error">{{ error }}</div>

            <form id="formResumen" action="<?= BASE_URL ?>/crear-resumen" method="POST">
                <section class="ficha ficha-formulario">
                    <div class="campo">
                        <label>Número</label>
                        <input type="text" value="Se asigna al guardar" disabled>
                    </div>
                    <div class="campo">
                        <label for="empresa_id">Empresa matriz</label>
                        <select name="empresa_id" id="empresa_id" v-model="empresaId" required>
                            <option value="">Selecciona una empresa</option>
                            <option v-for="e in datos.empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                        </select>
                    </div>
                    <div class="campo">
                        <label for="fondo_id">Fondo</label>
                        <select name="fondo_id" id="fondo_id" v-model="fondoId" :disabled="!empresaId">
                            <option value="">
                                {{ !empresaId ? 'Selecciona un fondo' : (fondosDeEmpresa.length ? 'Sin fondo asignado' : 'Sin fondo asignado a esta empresa') }}
                            </option>
                            <option v-for="f in fondosDeEmpresa" :key="f.fondo_id" :value="f.fondo_id">
                                {{ f.tipo }} - {{ formatearSoles(f.saldo_disponible) }} disponible
                            </option>
                        </select>
                        <p class="texto-vacio" v-if="empresaId && !fondoId">
                            Sin fondo seleccionado. Se tomará como gasto de bolsillo.
                        </p>
                    </div>
                    <div class="campo">
                        <label for="periodo_inicio">Periodo inicio</label>
                        <input type="date" name="periodo_inicio" id="periodo_inicio" required max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="campo">
                        <label for="periodo_fin">Periodo fin</label>
                        <input type="date" name="periodo_fin" id="periodo_fin" required max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="campo">
                        <label for="fecha_emision">Fecha de emisión</label>
                        <input type="date" name="fecha_emision" id="fecha_emision" required value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                    </div>
                </section>

                <p class="texto-vacio">
                    Detalle
                </p>

                <div class="acciones-crear">
                    <button type="button" class="boton boton-primario" :disabled="guardando" @click="crear">Crear Resumen</button>
                </div>
            </form>
        </div>

        <script>
            window.DATOS_INICIALES = <?= json_encode(['fondos' => $fondos, 'empresas' => $empresas]) ?>
        </script>
        <script src="<?= BASE_URL ?>/js/vendor/vue.global.prod.js"></script>
        <script type="module" src="<?= assetUrl('/js/crear_resumen.js') ?>"></script>
    <?php
    cierreLayout();
}