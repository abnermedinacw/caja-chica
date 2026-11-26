<?php
require_once __DIR__ . '/layout.php';

function vistaPerfil(array $perfil, ?array $mensaje): void
{
    $enmascararCuenta = static function (?string $cuenta, int $minLen = 13, int $maxLen = 20): string {
        $cuenta = preg_replace('/\D/', '', (string)$cuenta);

        $len = strlen($cuenta);
        if ($len < $minLen || $len > $maxLen) return 'No registrada';

        return str_repeat('*', max(0, $len - 6)) . substr($cuenta, -6);
    };

    $tieneBbva = preg_match('/^\d{20}$/', (string)($perfil['cuenta_bbva'] ?? '')) === 1;
    $tieneBcp = preg_match('/^\d{13,14}$/', (string)($perfil['cuenta_bcp'] ?? '')) === 1;
    $bancoPredeterminado = $perfil['banco_predeterminado'] ?? null;
    $bbvaVerificada = (int)($perfil['cuenta_bbva_verificada'] ?? 1) === 1;
    $bcpVerificada = (int)($perfil['cuenta_bcp_verificada'] ?? 1) === 1;
    $etiquetaTipoBcp = ($perfil['tipo_cuenta_bcp'] ?? '') === 'C' ? 'Corriente' : (($perfil['tipo_cuenta_bcp'] ?? '') === 'M' ? 'Maestra' : 'Ahorros');

    $formatearFecha = static function (?string $fecha): string {
        if (!$fecha) return '-';
        return date('d/m/Y', strtotime($fecha));
    };
    
    aperturaLayout('Mi perfil', 'perfil');
    ?>
    <div class="barra-superior" data-perfil>
        <div>
            <a href="<?= BASE_URL ?>/home" class="volver"><?= icono('volver') ?> Volver al inicio</a>
            <h1>Mi perfil</h1>
            <p class="texto-vacio">
                Consulta tus datos y administra tu correo, cuentas bancarias y firma personal.
            </p>
        </div>
    </div>
    <?php if ($mensaje): ?>
        <div class="mensaje-perfil mensaje-perfil-<?= htmlspecialchars($mensaje['tipo']) ?>">
            <?= htmlspecialchars($mensaje['texto']) ?>
        </div>
    <?php endif; ?>
    <section class="ficha">
        <div class="perfil-datos">
            <div>
                <span class="ficha-label">Nombre</span>
                <strong><?= htmlspecialchars($perfil['nombres'] ?? '-') ?></strong>
            </div>
            <div>
                <span class="ficha-label">DNI</span>
                <strong><?= htmlspecialchars($perfil['dni'] ?? '-') ?></strong>
            </div>
            <div>
                <span class="ficha-label">Usuario</span>
                <strong><?= htmlspecialchars($perfil['username'] ?? '-') ?></strong>
            </div>
            <div>
                <span class="ficha-label">Empresa</span>
                <strong><?= htmlspecialchars($perfil['empresa'] ?? '-') ?></strong>
            </div>
            <div>
                <span class="ficha-label">Área</span>
                <strong><?= htmlspecialchars($perfil['area'] ?? '-') ?></strong> 
            </div>
            <div>
                <span class="ficha-label">Cargo</span>
                <strong><?= htmlspecialchars($perfil['cargo'] ?? '-') ?></strong>
            </div>
            <div>
                <span class="ficha-label">Rol</span>
                <strong><?= htmlspecialchars($perfil['rol']) ?></strong>
            </div>
            <div>
                <span class="ficha-label">Jefatura</span>
                <strong><?= !empty($perfil['es_jefe']) ? 'Sí' : 'No' ?></strong>
            </div>
        </div>
    </section>
    <section class="ficha perfil-contacto">
        <h2>Correo</h2>
        <p class="perfil-correo-actual"><?= htmlspecialchars($perfil['correo'] ?: 'No registrado', ENT_QUOTES, 'UTF-8') ?></p>
        <details class="perfil-editor" data-editor-correo>
            <summary class="boton boton-secundario">Editar correo</summary>
            <form action="<?= BASE_URL ?>/actualizar-correo" method="POST" class="formulario-perfil">
                <div class="campo">
                    <label for="correo">Nuevo correo</label>
                    <input id="correo" name="correo" type="email" maxlength="254" autocomplete="email" required>
                </div>
                <div class="campo">
                    <label for="correo_confirmacion">Confirmar nuevo correo</label>
                    <input id="correo_confirmacion" name="correo_confirmacion" type="email" maxlength="254" autocomplete="off" required>
                </div>
                <div class="perfil-editor-acciones">
                    <button type="button" class="boton boton-secundario" data-cancelar-correo>Cancelar</button>
                    <button type="submit" class="boton boton-primario">Guardar correo</button>
                </div>
            </form>
        </details>
    </section>
    <section class="ficha perfil-pago">
        <div class="perfil-pago-cabecera">
            <div>
                <h2>Información de pago</h2>
                <p class="texto-vacio">
                    Tesorería procesará tus reembolsos y fondos únicamente en tu banco predeterminado.<?= ($tieneBbva && $tieneBcp) ? ' Haz clic en la otra cuenta si deseas cambiarlo.' : '' ?>
                </p>
            </div>
            <?php if ($bancoPredeterminado): ?>
                <span class="estado estado-activo">Predeterminado: <?= htmlspecialchars($bancoPredeterminado) ?></span>
            <?php else: ?>
                <span class="estado estado-pendiente">Sin cuenta predeterminada</span>
            <?php endif; ?>
        </div>

        <div class="perfil-cuentas-grid">
            <!-- Cuenta BBVA -->
            <?php
                $esBbvaPredeterminado = $tieneBbva && $bancoPredeterminado === 'BBVA';
                $puedeElegirBbva = $tieneBbva && !$esBbvaPredeterminado;
            ?>
            <div
                class="perfil-cuenta-bloque<?= $esBbvaPredeterminado ? ' perfil-cuenta-activa' : '' ?><?= $puedeElegirBbva ? ' perfil-cuenta-seleccionable' : '' ?>"
                <?= $puedeElegirBbva ? 'data-seleccionar-banco="BBVA" tabindex="0" role="button" aria-label="Usar BBVA como banco predeterminado"' : '' ?>>
                <div class="perfil-cuenta-titulo">
                    <div class="perfil-cuenta-cabecera-izq">
                        <?php if ($tieneBbva): ?>
                            <span class="perfil-cuenta-radio<?= $esBbvaPredeterminado ? ' activo' : '' ?>" aria-hidden="true"></span>
                        <?php endif; ?>
                        <img src="<?= assetUrl('/img/bbva-logo.svg') ?>" alt="BBVA" class="logo-banco-perfil logo-banco-bbva">
                    </div>
                    <?php if ($tieneBbva): ?>
                        <span class="estado <?= $bbvaVerificada ? 'estado-activo' : 'estado-pendiente' ?>">
                            <?= $bbvaVerificada ? 'Verificada' : 'Pendiente de validación' ?>
                        </span>
                    <?php endif; ?>
                </div>
                <p class="perfil-cuenta-enmascarada">
                    <?= htmlspecialchars($enmascararCuenta($perfil['cuenta_bbva'] ?? null, 20, 20)) ?>
                </p>
                <details class="perfil-editor perfil-editar-cuenta" data-editor-cuenta>
                    <summary class="boton boton-secundario boton-chico"><?= $tieneBbva ? 'Cambiar cuenta BBVA' : 'Agregar cuenta BBVA' ?></summary>
                    <form action="<?= BASE_URL ?>/actualizar-cuenta-bbva" method="POST" class="formulario-perfil">
                        <div class="campo">
                            <label for="cuenta_bbva">Nueva cuenta BBVA</label>
                            <input id="cuenta_bbva" name="cuenta_bbva" type="text" inputmode="numeric" maxlength="20" autocomplete="off" placeholder="20 dígitos" data-solo-digitos required>
                        </div>
                        <div class="campo">
                            <label for="cuenta_bbva_confirmacion">Confirmar cuenta BBVA</label>
                            <input id="cuenta_bbva_confirmacion" name="cuenta_bbva_confirmacion" type="text" inputmode="numeric" maxlength="20" autocomplete="off" placeholder="Repite los 20 dígitos" data-solo-digitos required>
                        </div>
                        <label class="campo-check" style="grid-column: 1 / -1; margin: 2px 0;">
                            <input type="checkbox" name="marcar_predeterminado" value="1" <?= !$bancoPredeterminado || $bancoPredeterminado === 'BBVA' ? 'checked' : '' ?>>
                            Usar BBVA como mi banco predeterminado
                        </label>
                        <small class="texto-vacio" style="grid-column: 1 / -1;">
                            Al guardar, Tesorería recibirá un aviso en el sistema para validar la titularidad antes del próximo pago.
                        </small>
                        <div class="perfil-editor-acciones">
                            <button type="button" class="boton boton-secundario" data-cancelar-cuenta>Cancelar</button>
                            <button type="submit" class="boton boton-primario">Guardar BBVA</button>
                        </div>
                    </form>
                </details>
            </div>

            <!-- Cuenta BCP -->
            <?php
                $esBcpPredeterminado = $tieneBcp && $bancoPredeterminado === 'BCP';
                $puedeElegirBcp = $tieneBcp && !$esBcpPredeterminado;
            ?>
            <div
                class="perfil-cuenta-bloque<?= $esBcpPredeterminado ? ' perfil-cuenta-activa' : '' ?><?= $puedeElegirBcp ? ' perfil-cuenta-seleccionable' : '' ?>"
                <?= $puedeElegirBcp ? 'data-seleccionar-banco="BCP" tabindex="0" role="button" aria-label="Usar BCP como banco predeterminado"' : '' ?>>
                <div class="perfil-cuenta-titulo">
                    <div class="perfil-cuenta-cabecera-izq">
                        <?php if ($tieneBcp): ?>
                            <span class="perfil-cuenta-radio<?= $esBcpPredeterminado ? ' activo' : '' ?>" aria-hidden="true"></span>
                        <?php endif; ?>
                        <img src="<?= assetUrl('/img/bcp-logo.svg') ?>" alt="BCP" class="logo-banco-perfil logo-banco-bcp">
                        <?php if ($tieneBcp): ?>
                            <span class="perfil-cuenta-tipo-tag"><?= htmlspecialchars($etiquetaTipoBcp) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($tieneBcp): ?>
                        <span class="estado <?= $bcpVerificada ? 'estado-activo' : 'estado-pendiente' ?>">
                            <?= $bcpVerificada ? 'Verificada' : 'Pendiente de validación' ?>
                        </span>
                    <?php endif; ?>
                </div>
                <p class="perfil-cuenta-enmascarada">
                    <?= htmlspecialchars($enmascararCuenta($perfil['cuenta_bcp'] ?? null, 13, 14)) ?>
                </p>
                <details class="perfil-editor perfil-editar-cuenta" data-editor-cuenta-bcp>
                    <summary class="boton boton-secundario boton-chico"><?= $tieneBcp ? 'Cambiar cuenta BCP' : 'Agregar cuenta BCP' ?></summary>
                    <form action="<?= BASE_URL ?>/actualizar-cuenta-bcp" method="POST" class="formulario-perfil">
                        <div class="campo" style="grid-column: 1 / -1;">
                            <label for="tipo_cuenta_bcp">Tipo de cuenta BCP</label>
                            <select id="tipo_cuenta_bcp" name="tipo_cuenta_bcp" data-tipo-cuenta-bcp required>
                                <option value="A" <?= ($perfil['tipo_cuenta_bcp'] ?? 'A') === 'A' ? 'selected' : '' ?>>Cuenta de Ahorros</option>
                                <option value="C" <?= ($perfil['tipo_cuenta_bcp'] ?? '') === 'C' ? 'selected' : '' ?>>Cuenta Corriente</option>
                            </select>
                        </div>
                        <div class="campo">
                            <label for="cuenta_bcp">Nueva cuenta BCP</label>
                            <input id="cuenta_bcp" name="cuenta_bcp" type="text" inputmode="numeric" maxlength="14" autocomplete="off" placeholder="14 dígitos" data-solo-digitos data-input-bcp required>
                        </div>
                        <div class="campo">
                            <label for="cuenta_bcp_confirmacion">Confirmar cuenta BCP</label>
                            <input id="cuenta_bcp_confirmacion" name="cuenta_bcp_confirmacion" type="text" inputmode="numeric" maxlength="14" autocomplete="off" placeholder="Repite la cuenta BCP" data-solo-digitos data-input-bcp required>
                        </div>
                        <label class="campo-check" style="grid-column: 1 / -1; margin: 2px 0;">
                            <input type="checkbox" name="marcar_predeterminado" value="1" <?= !$bancoPredeterminado || $bancoPredeterminado === 'BCP' ? 'checked' : '' ?>>
                            Usar BCP como mi banco predeterminado
                        </label>
                        <small class="texto-vacio" style="grid-column: 1 / -1;">
                            Al guardar, Tesorería recibirá un aviso en el sistema para validar la titularidad antes del próximo pago.
                        </small>
                        <div class="perfil-editor-acciones">
                            <button type="button" class="boton boton-secundario" data-cancelar-cuenta-bcp>Cancelar</button>
                            <button type="submit" class="boton boton-primario">Guardar BCP</button>
                        </div>
                    </form>
                </details>
            </div>
        </div>

        <dialog id="dialogoCambioBanco" aria-labelledby="tituloCambioBanco">
            <button type="button" class="dialogo-cerrar" aria-label="Cerrar" data-cerrar-cambio-banco>&times;</button>
            <h2 id="tituloCambioBanco">Cambiar banco predeterminado</h2>
            <p>
                ¿Deseas cambiar tu banco predeterminado a <strong id="textoNuevoBanco"></strong>?
            </p>
            <p class="texto-vacio">
                Tus próximos reembolsos y desembolsos de fondos serán procesados únicamente en esa cuenta.
            </p>
            <form action="<?= BASE_URL ?>/actualizar-banco-predeterminado" method="POST" class="acciones-modal">
                <input type="hidden" name="banco_predeterminado" id="inputNuevoBanco" value="">
                <button type="button" class="boton boton-secundario" data-cerrar-cambio-banco>Cancelar</button>
                <button type="submit" class="boton boton-primario">Confirmar cambio</button>
            </form>
        </dialog>
    </section>
    <section class="ficha perfil-firma">
        <div class="perfil-firma-cabecera">
            <div>
                <h2>Firma personal</h2>
                <p class="texto-vacio">Se utilizará en tus declaraciones y aprobaciones.</p>
            </div>
            <span class="firma-estado <?= !empty($perfil['firma_ruta']) ? 'firma-estado-registrada' : 'firma-estado-pendiente' ?>">
                <?= !empty($perfil['firma_ruta']) ? 'Firma registrada' : 'Firma pendiente' ?>
            </span>
        </div>

        <?php if (empty($perfil['firma_ruta'])): ?>
            <div class="firma-pendiente">
                <p>Registra tu firma</p>
                <form
                    action="<?= BASE_URL ?>/subir-firma"
                    method="POST"
                    enctype="multipart/form-data"
                    class="formulario-firma">
                    <div class="campo">
                        <input type="file" id="firma" name="firma" accept="image/png,image/jpeg" required>
                    </div>
                    <small class="texto-vacio">PNG o JPG, máximo 2 MB. Se recomienda fondo transparente.</small>
                    <div class="perfil-editor-acciones">
                        <button type="reset" class="boton boton-secundario">Limpiar</button>
                        <button type="submit" class="boton boton-primario">Agregar firma</button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="firma-registrada">
                <div class="firma-vista-previa">
                    <img
                        src="<?= BASE_URL ?>/ver-firma?v=<?= urlencode($perfil['firma_fecha_actualizacion'] ?? $perfil['firma_fecha_registro']) ?>"
                        alt="Firma personal">
                </div>
                <div class="firma-informacion">
                    <dl class="firma-fechas">
                        <div>
                            <dt>Registrada</dt>
                            <dd><?= $formatearFecha($perfil['firma_fecha_registro']) ?></dd>
                        </div>
                        <?php if ($perfil['firma_fecha_actualizacion']): ?>
                            <div>
                                <dt>Última modificación</dt>
                                <dd><?= $formatearFecha($perfil['firma_fecha_actualizacion']) ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                    <details class="perfil-editor firma-modificar" data-editor-firma>
                        <summary class="boton boton-secundario">Modificar firma</summary>
                        <form
                            id="formModificarFirma"
                            action="<?= BASE_URL ?>/subir-firma"
                            method="POST"
                            enctype="multipart/form-data"
                            class="formulario-firma">
                            <div class="campo">
                                <label for="nuevaFirma">Seleccionar nueva imagen</label>
                                <input 
                                    type="file" 
                                    id="nuevaFirma" 
                                    name="firma" 
                                    accept="image/png,image/jpeg" 
                                    required>
                            </div>
                            <div id="firmaSeleccionada" class="firma-seleccionada" hidden>
                                <span id="nombreNuevaFirma"></span>
                                <button
                                    type="button"
                                    id="limpiarNuevaFirma"
                                    class="boton-limpiar-archivo"
                                    aria-label="Quitar imagen seleccionada">
                                    <?= icono('cerrar') ?>
                                </button>
                            </div>
                            <small class="texto-vacio">
                                Esta imagen reemplazará la firma actual.
                            </small>
                            <div class="perfil-editor-acciones">
                                <button
                                    type="button"
                                    id="cancelarNuevaFirma"
                                    class="boton boton-secundario">
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    class="boton boton-primario">
                                    Guardar nueva firma
                                </button>

                            </div>
                        </form>
                    </details>
                </div>
            </div>
        <?php endif; ?>
    </section>
    <script src="<?= assetUrl('/js/perfil.js') ?>"></script>
    <?php
    cierreLayout();
}
