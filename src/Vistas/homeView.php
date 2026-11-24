<?php
require_once __DIR__ . '/layout.php';

function vistaHome(array $fondos, array $resumenes): void
{
    aperturaLayout('Inicio - T-RINDE', 'home');
?>
    <div class="barra-superior cabecera-home">
        <div>
            <h1>Fondos y resúmenes</h1>
            <p class="texto-vacio">Bienvenido, <?=htmlspecialchars($_SESSION['trabajador_nombre'] ?? $_SESSION['username']) ?>.</p>
        </div>
    </div>

    <section>
        <h2>Fondos activos</h2>
        <?php if (empty($fondos)): ?>
            <p class="texto-vacio">Todavia no tienes ningun fondo asignado.</p>
        <?php else: ?>
            <div class="tabla-scroll tabla-scroll-vertical tabla-home-fondos tabla-scroll-tarjetas">
            <table class="tabla tabla-tarjetas">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Tipo</th>
                        <th>Monto asignado</th>
                        <th>Saldo disponible</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fondos as $f): ?>
                        <tr>
                            <td data-label="Empresa" class="celda-destacada"><?= htmlspecialchars($f['empresa']) ?></td>
                            <td data-label="Tipo"><?= htmlspecialchars($f['tipo']) ?></td>
                            <td data-label="Monto asignado"><?= soles($f['monto_asignado']) ?></td>
                            <td data-label="Saldo disponible" class="celda-monto"><?= soles($f['saldo_disponible']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </section>

    <section>
        <div class="cabecera-mis-resumenes">
            <h2>Mis Resúmenes</h2>
            <div class="cabecera-mis-resumenes-acciones">
                <a href="<?= BASE_URL ?>/historial" class="boton boton-secundario">Ver historial</a>
                <a href="<?= BASE_URL ?>/crear-resumen" class="boton boton-primario"><?= icono('mas') ?> Rendir Caja</a>
            </div>
        </div>
        <?php if (empty($resumenes)): ?>
            <p class="texto-vacio">Sin resúmenes en curso.</p>
        <?php else: ?>
            <div class="tabla-scroll tabla-scroll-tarjetas">
            <table class="tabla tabla-tarjetas tabla-tarjetas-resumen">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Empresa</th>
                        <th>Periodo</th>
                        <th>Total egresos</th>
                        <th>Estado</th>
                        <th>Nivel actual</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resumenes as $r): ?>
                        <tr>
                            <td data-label="Número" class="celda-cabecera-izq"><?= numeroDocumento($r['numero']) ?></td>
                            <td data-label="Empresa"><?= htmlspecialchars($r['empresa']) ?></td>
                            <td data-label="Periodo"><?= formatearFecha(htmlspecialchars($r['periodo_inicio'])) ?>  -  <?= formatearFecha(htmlspecialchars($r['periodo_fin'])) ?></td>
                            <td data-label="Total egresos" class="celda-monto"><?= soles($r['total_egresos']) ?></td>
                            <td data-label="Estado" class="celda-cabecera-der"><span class="estado estado-<?= strtolower($r['estado_general']) ?>"><?= htmlspecialchars($r['estado_general']) ?></span></td>
                            <td data-label="Nivel actual"><?= htmlspecialchars($r['nivel_actual_nombre'] ?? '-') ?></td>
                            <td class="celda-accion-tarjeta">
                                <a href="<?= BASE_URL ?>/ver-resumen?id=<?= (int)$r['id']?>&amp;origen=home" class="boton boton-chico"><?= icono('ver') ?> Ver</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </section>

<?php
cierreLayout();
}
