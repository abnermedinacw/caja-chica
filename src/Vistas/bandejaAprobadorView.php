<?php
require_once __DIR__ . '/layout.php';

function vistaBandejaAprobador(string $rol, array $resumenes, bool $tieneTrabajador = false, array $misFondos = [], array $misResumenes = []): void 
{
    $funciones = [];
    if ($rol !== 'SOLICITANTE') {
        $funciones[] = $rol === 'TESORERIA' ? 'Tesorería' : ucfirst(strtolower($rol));
    }
    if (!empty($_SESSION['es_jefe'])) $funciones[] = 'Jefatura';

    aperturaLayout('Bandeja de aprobación', 'home');
    ?>
        <div class="barra-superior">
            <h1>Bandeja de aprobación - <?= htmlspecialchars(implode(' / ', $funciones)) ?></h1>
        </div>

        <section>
            <h2>Resúmenes pendientes</h2>
            <?php if (empty($resumenes)): ?>
                <p class="texto-vacio">No tienes Resúmenes pendientes de aprobación por ahora.</p>
            <?php else: ?>
                <div class="tabla-scroll tabla-scroll-vertical">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Empresa</th>
                            <th>Solicitante</th>
                            <th>Fecha de emisión</th>
                            <th>Total Egresos</th>
                            <th>Situación</th>
                            <th>Etapa</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resumenes as $r): ?>
                            <?php $esRechazoEnCamino = $r['estado_general'] === 'RECHAZADO'; ?>
                            <tr>
                                <td><?= numeroDocumento($r['numero']) ?></td>
                                <td><?= htmlspecialchars($r['empresa']) ?></td>
                                <td><?= htmlspecialchars($r['solicitante']) ?></td>
                                <td><?= formatearFecha($r['fecha_emision']) ?></td>
                                <td><?= soles($r['total_egresos']) ?></td>
                                <td>
                                    <?php if ($esRechazoEnCamino): ?>
                                        <span class="estado estado-rechazado">Rechazo en revisión</span>
                                    <?php else: ?>
                                        <span class="estado estado-en_revision">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($r['etapa']) ?></td>
                                <td>
                                    <a href="<?= BASE_URL ?>/ver-aprobacion?id=<?= (int)$r['id'] ?>&amp;origen=bandeja-aprobaciones" class="boton boton-chico"><?= icono('ver') ?> Ver</a>
                                </td>
                            </tr>
                            <?php endforeach;?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($tieneTrabajador): ?>
            <section>
                <h2>Fondos activos</h2>
                <?php if (empty($misFondos)): ?>
                    <p class="texto-vacio">Todavía no tienes ningún fondo asignado.</p>
                <?php else: ?>
                    <div class="tabla-scroll tabla-scroll-vertical tabla-home-fondos">
                    <table class="tabla">
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th>Tipo</th>
                                <th>Monto asignado</th>
                                <th>Saldo disponible</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($misFondos as $f): ?>
                                <tr>
                                    <td><?= htmlspecialchars($f['empresa']) ?></td>
                                    <td><?= htmlspecialchars($f['tipo']) ?></td>
                                    <td><?= soles($f['monto_asignado']) ?></td>
                                    <td><?= soles($f['saldo_disponible']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </section>

            <section>
                <div class="cabecera-mis-resumenes">
                    <h2>Mis resúmenes</h2>
                    <div class="cabecera-mis-resumenes-acciones">
                        <a href="<?= BASE_URL ?>/historial" class="boton boton-secundario">Ver historial</a>
                        <a href="<?= BASE_URL ?>/crear-resumen" class="boton boton-primario"><?= icono('mas') ?> Crear Resumen</a>
                    </div>
                </div>
                <?php if (empty($misResumenes)): ?>
                    <p class="texto-vacio">Todavía no creaste ningún Resumen propio</p>
                <?php else: ?>
                    <div class="tabla-scroll">
                    <table class="tabla">
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
                            <?php foreach($misResumenes as $r): ?>
                                <tr>
                                    <td><?= numeroDocumento($r['numero']) ?></td>
                                    <td><?= htmlspecialchars($r['empresa']) ?></td>
                                    <td><?= formatearFecha($r['periodo_inicio']) ?> - <?= formatearFecha($r['periodo_fin']) ?></td>
                                    <td><?= soles($r['total_egresos']) ?></td>
                                    <td><span class="estado estado-<?= strtolower($r['estado_general']) ?>"><?= htmlspecialchars($r['estado_general']) ?></span></td>
                                    <td><?= htmlspecialchars($r['nivel_actual_nombre'] ?? '-') ?></td>
                                    <td><a href="<?= BASE_URL ?>/ver-resumen?id=<?= (int)$r['id'] ?>&amp;origen=home" class="boton boton-chico"><?= icono('ver') ?>Ver</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php
    cierreLayout();
}
