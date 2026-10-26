<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../Negocio/auth.php';

function aperturaLayout(string $titulo, string $rutaActiva): void
{
    $rol = $_SESSION['rol'] ?? null;
    $tieneTrabajador = !empty($_SESSION['trabajador_id']);
    $esJefe = !empty($_SESSION['es_jefe']);
    // Tesorería asigna fondos; Gerencia y Contabilidad los auditan completos;
    // Jefatura ve la misma pantalla pero acotada a su propia gente (el
    // controller decide ese recorte, acá solo importa quién puede entrar).
    $rolesVenFondosEmpresa = ['TESORERIA', 'GERENCIA', 'CONTABILIDAD'];

    // Agrupado por a quién le sirve cada cosa — no una lista plana en el
    // orden en que se fue construyendo cada funcionalidad.
    $secciones = [];

    $secciones[] = ['enlaces' => [
        'home' => ['ruta' => '/home', 'texto' => 'Inicio', 'disponible' => true],
    ]];

    if (puedeVerHistorialAprobaciones()) {
        $secciones[] = ['titulo' => 'Aprobación', 'enlaces' => [
            'historial-aprobaciones' => ['ruta' => '/historial-aprobaciones', 'texto' => 'Historial de aprobaciones', 'disponible' => true],
        ]];
    }

    if (in_array($rol, $rolesVenFondosEmpresa, true) || $esJefe) {
        $secciones[] = ['titulo' => 'Fondos de la empresa', 'enlaces' => [
            'fondos-empresa' => ['ruta' => '/fondos-empresa', 'texto' => 'Fondos', 'disponible' => true],
        ]];
    }

    if ($rol === 'TESORERIA') {
        $secciones[] = [
            'titulo' => 'Pagos',
            'enlaces' => [
                'pagos-bbva' => [
                    'ruta' => '/pagos-bbva',
                    'texto' => 'Pagos BBVA',
                    'disponible' => true,
                ],
            ],
        ];
    }

    if (esAdministrador()) {
        $secciones[] = ['titulo' => 'Administración', 'enlaces' => [
            'trabajadores' => ['ruta' => '/trabajadores', 'texto' => 'Trabajadores', 'disponible' => true],
            'reactivar-txt-bbva' => ['ruta' => '/pagos-bbva?modo=reactivacion', 'texto' => 'Reactivar pagos BBVA', 'disponible' => true],
        ]];
    }

    if ($tieneTrabajador) {
        $secciones[] = ['titulo' => 'Mi cuenta', 'enlaces' => [
            'historial' => ['ruta' => '/historial', 'texto' => 'Mis Resúmenes', 'disponible' => true],
            'fondos' => ['ruta' => '/fondos', 'texto' => 'Mis Fondos', 'disponible' => true],
        ]];
    }

    $nombreUsuario = $_SESSION['trabajador_nombre'] ?? $_SESSION['username'];
    $inicialUsuario = mb_strtoupper(mb_substr($nombreUsuario, 0, 1));
    $etiquetasRol = [
        'SOLICITANTE' => 'Solicitante', 'TESORERIA' => 'Tesorería', 'CONTABILIDAD' => 'Contabilidad', 'GERENCIA' => 'Gerencia', 'ADMIN' => 'Administrador',
    ];
    $etiquetaCargo = $_SESSION['cargo_nombre'] ?? ($etiquetasRol[$_SESSION['rol'] ?? ''] ?? '');

    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($titulo) ?></title>
        <link rel="preload" href="<?= BASE_URL ?>/fonts/manrope.ttf" as="font" type="font/ttf" crossorigin>
        <link rel="stylesheet" href="<?= assetUrl('/css/base.css') ?>">
        <link rel="stylesheet" href="<?= assetUrl('/css/componentes.css') ?>">
        <link rel="stylesheet" href="<?= assetUrl('/css/modulos.css') ?>">
        <link rel="icon" type="image/x-icon" href="<?= assetUrl('/icon-moneda.svg') ?>">
        <script type="importmap"><?= json_encode(mapaModulosJs(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
    </head>
    <body class="con-barra-lateral">
        <aside class="barra-lateral">
            <a href="<?= BASE_URL ?>/home" class="barra-lateral-marca">Caja Chica</a>
            <nav class="barra-lateral-nav" aria-label="Navegación principal">
                <?php foreach ($secciones as $seccion): ?>
                    <?php if (isset($seccion['titulo'])): ?>
                        <div class="barra-lateral-seccion"><?= htmlspecialchars($seccion['titulo']) ?></div>
                    <?php endif; ?>
                    <?php foreach ($seccion['enlaces'] as $clave => $enlace): ?>
                        <?php if ($enlace['disponible']): ?>
                            <a href="<?= BASE_URL . $enlace['ruta'] ?>" class="enlace-lateral <?= $rutaActiva === $clave ? 'enlace-lateral-activo' : '' ?>"<?= $rutaActiva === $clave ? ' aria-current="page"' : '' ?>>
                                <?= htmlspecialchars($enlace['texto']) ?>
                            </a>
                        <?php else: ?>
                            <span class="enlace-lateral enlace-lateral-pendiente" title="Próximamente">
                                <?= htmlspecialchars($enlace['texto']) ?>
                            </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </nav>
            <div class="barra-lateral-usuario">
                <a href="<?= BASE_URL ?>/perfil" class="usuario-chip usuario-chip-enlace" title="Ver mi perfil">
                    <span class="usuario-avatar"><?= htmlspecialchars($inicialUsuario) ?></span>
                    <span class="usuario-datos">
                        <span class="usuario-nombre"><?= htmlspecialchars($nombreUsuario) ?></span>
                        <span class="usuario-cargo"><?= htmlspecialchars($etiquetaCargo) ?></span>
                    </span>
                </a>
                <a href="<?= BASE_URL ?>/logout" class="enlace-lateral enlace-lateral-salir">
                    <?= icono('salir') ?> Salir
                </a>
            </div>
        </aside>
        <main class="contenido-principal">
    <?php
}

function cierreLayout(): void
{
    ?>
        </main>

        <dialog
            id="dialogo-sesion-inactiva"
            class="dialogo-sesion-inactiva"
            aria-labelledby="titulo-sesion-inactiva">
            <div class="sesion-inactiva-icono">!</div>
            <h2 id="titulo-sesion-inactiva">¿Sigues ahí?</h2>

            <p>Tu sesión se cerrará por inactividad en
                <strong id="tiempo-restante-sesion">5:00</strong>
            </p>
            <p class="texto-secundario">
                Si estás llenando un documento, continúa la sesión para no perder los cambios.
            </p>
            <div class="acciones-modal">
                <a href="<?= BASE_URL ?>/logout" class="boton boton-secundario">
                    Cerrar sesión
                </a>

                <button
                    type="button"
                    id="boton-continuar-sesion"
                    class="boton boton-primario">
                    Seguir trabajando
                </button>
            </div>
        </dialog>
        <script>
            window.CONFIG_SESION = <?= json_encode([
                'baseUrl' => BASE_URL,
                'limiteSegundos' => TIEMPO_INACTIVIDAD,
                'avisoSegundos' => 5 * 60,
            ], JSON_UNESCAPED_SLASHES) ?>
        </script>
        <script
            src="<?= assetUrl('/js/sesion_inactividad.js') ?>" defer>
        </script>

    </body>
    </html>
    <?php
}
