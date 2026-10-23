<?php
require_once __DIR__ . '/layout.php';

function vistaPerfil(array $perfil, ?array $mensaje): void
{
    $enmascararCuenta = static function (?string $cuenta): string {
        $cuenta = preg_replace('/\D/', '', (string)$cuenta);

        if (!preg_match('/^\d{20}$/', $cuenta)) return 'No registrada';

        return str_repeat('*', 14) . substr($cuenta, -6);
    };

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
                Consulta tus datos y administra tu correo, cuenta BBVA y firma personal.
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
        <h2>Correo de notificaciones</h2>
        <p class="perfil-correo-actual"><?= htmlspecialchars($perfil['correo'] ?: 'No registrado', ENT_QUOTES, 'UTF-8') ?></p>
        <details class="perfil-editor" data-editor-correo>
            <summary class="boton boton-secundario">Modificar correo</summary>
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
            </div>
            <strong class="perfil-cuenta-enmascarada">
                <?= htmlspecialchars($enmascararCuenta($perfil['cuenta_bbva'] ?? null)) ?>
            </strong>
        </div>
        <details class="perfil-editor perfil-editar-cuenta" data-editor-cuenta>
            <summary class="boton boton-secundario">Modificar cuenta</summary>
            <form action="<?= BASE_URL ?>/actualizar-cuenta-bbva" method="POST" class="formulario-perfil">
                <div class="campo">
                    <label for="cuenta_bbva">Nueva cuenta BBVA</label>
                    <input id="cuenta_bbva" name="cuenta_bbva" type="text" inputmode="numeric" maxlength="20" autocomplete="off" placeholder="20 dígitos" data-cuenta-bbva required>
                </div>
                <div class="campo">
                    <label for="cuenta_bbva_confirmacion">
                        Confirmar nueva cuenta
                    </label>
                    <input id="cuenta_bbva_confirmacion" name="cuenta_bbva_confirmacion" type="text" inputmode="numeric" maxlength="20" autocomplete="off" placeholder="Repite los 20 dígitos" data-cuenta-bbva required>
                </div>
                <div class="perfil-editor-acciones">
                    <button type="button" class="boton boton-secundario" data-cancelar-cuenta>
                        Cancelar
                    </button>
                    <button type="submit" class="boton boton-primario">
                        Guardar cuenta
                    </button>
                </div>
            </form>
        </details>
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
