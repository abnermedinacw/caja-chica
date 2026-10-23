<?php
require_once __DIR__ . '/helpers.php';

// Cada moneda tiene sus propios degradados para evitar IDs SVG repetidos.
function moneda(int $tam, string $variante = 'cobre'): string
{
    static $numero = 0;
    $id = 'moneda-' . ++$numero;
    [$luz, $medio, $oscuro, $canto] = match ($variante) {
        'plata' => ['#ffffff', '#e0e9f3', '#adbed2', '#879db7'],
        'cobre-oscuro' => ['#ffd7b9', '#e89a73', '#c97049', '#a25335'],
        default => ['#ffe8d3', '#ffb895', '#e88a63', '#bb6244'],
    };
    return <<<SVG
    <svg viewBox="0 0 64 64" width="{$tam}" height="{$tam}" aria-hidden="true" focusable="false">
        <defs>
            <linearGradient id="{$id}-borde" x1="0" y1="0" x2="1" y2="1">
                <stop stop-color="{$luz}"/><stop offset=".45" stop-color="{$medio}"/><stop offset="1" stop-color="{$canto}"/>
            </linearGradient>
            <radialGradient id="{$id}-cara" cx=".3" cy=".18" r=".9">
                <stop stop-color="{$luz}"/><stop offset=".55" stop-color="{$medio}"/><stop offset="1" stop-color="{$oscuro}"/>
            </radialGradient>
        </defs>
        <circle cx="32" cy="34" r="27" fill="{$canto}"/>
        <circle cx="32" cy="29" r="27" fill="url(#{$id}-borde)" stroke="{$canto}" stroke-width=".6"/>
        <circle cx="32" cy="29" r="24.5" fill="none" stroke="{$canto}" stroke-width="1.3" stroke-dasharray=".65 2" opacity=".65"/>
        <circle cx="32" cy="29" r="21.5" fill="url(#{$id}-cara)" stroke="{$canto}" stroke-width=".65"/>
        <circle cx="32" cy="29" r="20.5" fill="none" stroke="{$luz}" stroke-width=".7"/>
        <path d="M17 12a24 24 0 0 1 30 0" fill="none" stroke="{$luz}" stroke-width="1.2" stroke-linecap="round"/>
        <text x="32" y="37" text-anchor="middle" font-family="Manrope, sans-serif" font-size="20" font-weight="800" fill="{$luz}">S/</text>
        <text x="32" y="36" text-anchor="middle" font-family="Manrope, sans-serif" font-size="20" font-weight="800" fill="#26415e">S/</text>
    </svg>
    SVG;
}

function vistaLogin(?string $error): void
{
?>
    <!DOCTYPE html>
    <html lang="es">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Caja Chica — Ingresar</title>
        <link rel="stylesheet" href="<?= assetUrl('/css/base.css') ?>">
        <link rel="stylesheet" href="<?= assetUrl('/css/componentes.css') ?>">
        <link rel="preload" href="<?= BASE_URL ?>/fonts/manrope.ttf" as="font" type="font/ttf" crossorigin>
        <link rel="stylesheet" href="<?= assetUrl('/css/login.css') ?>">
        <link rel="icon" type="image/x-icon" href="<?= assetUrl('/icon-moneda.svg') ?>">

    </head>

    <body class="pagina-login">

        <div class="fondo-login">
            <svg class="fondo-red" viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                <g class="fondo-lineas">
                    <line x1="90" y1="120" x2="240" y2="70" />
                    <line x1="240" y1="70" x2="360" y2="190" />
                    <line x1="90" y1="120" x2="360" y2="190" />
                    <line x1="360" y1="190" x2="330" y2="330" />
                    <line x1="90" y1="120" x2="60" y2="300" />
                    <line x1="60" y1="300" x2="330" y2="330" />
                    <line x1="60" y1="300" x2="180" y2="420" />
                    <line x1="330" y1="330" x2="180" y2="420" />

                    <line x1="1180" y1="90" x2="1320" y2="160" />
                    <line x1="1320" y1="160" x2="1280" y2="300" />
                    <line x1="1180" y1="90" x2="1280" y2="300" />
                    <line x1="1320" y1="160" x2="1400" y2="80" />
                    <line x1="1280" y1="300" x2="1390" y2="380" />
                    <line x1="1180" y1="90" x2="1080" y2="180" />

                    <line x1="1050" y1="620" x2="1190" y2="700" />
                    <line x1="1190" y1="700" x2="1150" y2="820" />
                    <line x1="1050" y1="620" x2="1150" y2="820" />
                    <line x1="1190" y1="700" x2="1320" y2="660" />

                    <line x1="140" y1="700" x2="260" y2="760" />
                    <line x1="260" y1="760" x2="220" y2="860" />
                    <line x1="140" y1="700" x2="80" y2="820" />
                </g>
                <g class="fondo-puntos">
                    <circle cx="90" cy="120" r="3.5" /><circle cx="240" cy="70" r="2.5" />
                    <circle cx="360" cy="190" r="3" /><circle cx="330" cy="330" r="2.5" />
                    <circle cx="60" cy="300" r="3" /><circle cx="180" cy="420" r="2.5" />
                    <circle cx="1180" cy="90" r="3" /><circle cx="1320" cy="160" r="3.5" />
                    <circle cx="1280" cy="300" r="2.5" /><circle cx="1400" cy="80" r="2.5" />
                    <circle cx="1390" cy="380" r="3" /><circle cx="1080" cy="180" r="2.5" />
                    <circle cx="1050" cy="620" r="3" /><circle cx="1190" cy="700" r="3.5" />
                    <circle cx="1150" cy="820" r="2.5" /><circle cx="1320" cy="660" r="2.5" />
                    <circle cx="140" cy="700" r="3" /><circle cx="260" cy="760" r="2.5" />
                    <circle cx="220" cy="860" r="2.5" /><circle cx="80" cy="820" r="2.5" />
                </g>
            </svg>

            <button type="button" class="moneda-juego moneda-suelta moneda-suelta-1" aria-label="Hacer saltar la moneda de cobre" title="Haz clic para hacerla saltar"><?= moneda(78, 'cobre') ?></button>
            <button type="button" class="moneda-juego moneda-suelta moneda-suelta-2" aria-label="Hacer saltar la moneda plateada" title="Haz clic para hacerla saltar"><?= moneda(42, 'plata') ?></button>
            <button type="button" class="moneda-juego moneda-suelta moneda-suelta-3" aria-label="Hacer saltar la moneda pequeña" title="Haz clic para hacerla saltar"><?= moneda(30, 'cobre-oscuro') ?></button>
            <button type="button" class="moneda-juego moneda-suelta moneda-suelta-4" aria-label="Hacer saltar la moneda del fondo" title="Haz clic para hacerla saltar"><?= moneda(50, 'plata') ?></button>
            <span class="punto-suelto punto-suelto-1"></span>
            <span class="punto-suelto punto-suelto-2"></span>
            <span class="punto-suelto punto-suelto-3"></span>
        </div>

        <main class="tarjeta-login">
            <section class="panel-marca">
                <svg class="marca-grabado" viewBox="0 0 360 420" aria-hidden="true">
                    <circle cx="270" cy="150" r="165"/><circle cx="270" cy="150" r="130"/>
                    <circle cx="270" cy="150" r="95"/>
                    <path d="M20 350h120m-120 10h75"/>
                </svg>
                <div class="marca-monedas">
                    <button type="button" class="moneda-juego moneda moneda-plata" aria-label="Hacer girar la moneda plateada de Caja Chica" title="Haz clic para hacerla girar"><?= moneda(112, 'plata') ?></button>
                    <button type="button" class="moneda-juego moneda moneda-cobre" aria-label="Hacer girar la moneda de cobre de Caja Chica" title="Haz clic para hacerla girar"><?= moneda(104, 'cobre') ?></button>
                </div>
                <h1 class="marca-titulo">Caja Chica</h1>
                <p class="marca-descripcion">Gestión de fondos y rendiciones.</p>
                <p class="marca-juego-ayuda">Haz clic en las monedas</p>
            </section>

            <section class="panel-form" aria-labelledby="titulo-login">
                <span class="marca-compacta">Caja Chica</span>
                <h2 class="form-titulo" id="titulo-login">Inicia sesión</h2>
                <p class="form-subtitulo">Ingresa con tu usuario para continuar.</p>

                <?php if ($error): ?>
                    <div class="error" role="alert"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form action="<?= BASE_URL ?>/login" method="POST">
                    <label for="username">Usuario</label>
                    <input type="text" id="username" name="username" required autofocus autocomplete="username">
                    <label for="password">Contraseña</label>
                    <div class="campo-password">
                        <input type="password" id="password" name="password" required autocomplete="current-password">
                        <button type="button" class="boton-ojo" id="togglePassword" aria-label="Mostrar contraseña" aria-pressed="false"
                            data-icono-mostrar="<?= htmlspecialchars(icono('ver'), ENT_QUOTES) ?>"
                            data-icono-ocultar="<?= htmlspecialchars(icono('ocultar'), ENT_QUOTES) ?>">
                            <?= icono('ver') ?>
                        </button>
                    </div>
                    <button type="submit" class="boton boton-primario">Ingresar</button>
                </form>

            </section>
        </main>

        <script src="<?= assetUrl('/js/login.js') ?>"></script>
    </body>

    </html>
<?php
}
