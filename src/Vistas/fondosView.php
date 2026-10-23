<?php
require_once __DIR__ . '/layout.php';

function vistaFondos(array $fondos): void
{
    aperturaLayout('Mis Fondos', 'fondos');
    ?>
    <div class="barra-superior">
        <div>
            <a href="<?= BASE_URL ?>/home" class="volver"><?= icono('volver') ?>Volver</a>
            <h1>Mis Fondos</h1>
        </div>
    </div>

    <?php if (empty($fondos)): ?>
        <p class="texto-vacio">Todavía no tienes ningún fondo asignado.</p>
    <?php else: ?>
        <?php foreach($fondos as $f): ?>
            <section class="ficha">
                <div class="ficha-fila">
                    <span class="ficha-label">Empresa</span>
                    <span class="ficha-valor"><?= htmlspecialchars($f['empresa']) ?></span>
                    <span class="ficha-label">Tipo</span>
                    <span class="ficha-valor"><?= htmlspecialchars($f['tipo']) ?></span>
                </div>
                <div class="ficha-fila">
                    <span class="ficha-label">Monto asignado</span>
                    <span class="ficha-valor"><?= soles($f['monto_asignado']) ?></span>
                    <span class="ficha-label">Saldo disponible</span>
                    <span class="ficha-valor"><?= soles($f['saldo_disponible']) ?></span>
                </div>
                <div class="ficha-fila">
                    <span class="ficha-label">Fecha de asignación</span>
                    <span class="ficha-valor"><?= formatearFecha($f['fecha_asignacion']) ?></span>
                    <span class="ficha-label">Estado</span>
                    <span class="ficha-valor"><span class="estado estado-<?= strtolower($f['estado']) ?>"><?= htmlspecialchars($f['estado']) ?></span></span>
                </div>

                <?php if (!empty($f['resumenes'])): ?>
                    <div class="tabla-scroll tabla-scroll-vertical tabla-scroll-compacta">
                    <table class="tabla tabla-fondo-resumenes">
                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>Periodo</th>
                                <th>Total egresos</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($f['resumenes'] as $r): ?>
                                <tr>
                                    <td><?= numeroDocumento($r['numero']) ?></td>
                                    <td><?= formatearFecha($r['periodo_inicio']) ?> - <?= formatearFecha($r['periodo_fin']) ?></td>
                                    <td><?= soles($r['total_egresos']) ?></td>
                                    <td><span class="estado estado-<?= strtolower($r['estado_general']) ?>"><?= htmlspecialchars($r['estado_general']) ?></span></td>
                                    <td><a href="<?= BASE_URL ?>/ver-resumen?id=<?= (int)$r['id'] ?>&amp;origen=fondos" class="boton boton-chico"><?= icono('ver') ?> Ver</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php
    cierreLayout();
}
