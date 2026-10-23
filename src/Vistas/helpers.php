<?php

function soles($monto): string
{
    return 'S/ ' . number_format((float)$monto, 2);
}

function numeroDocumento($numero): string
{
    return str_pad((string)$numero, 3, '0', STR_PAD_LEFT);
}

function resolverRegresoResumen(?string $origen, string $modo): array
{
    $destinos = [
        'home' => [
            'url' => BASE_URL . '/home',
            'texto' => 'Volver al inicio',
        ],
        'bandeja-aprobaciones' => [
            'url' => BASE_URL . '/home',
            'texto' => 'Volver a aprobaciones',
        ],
        'historial' => [
            'url' => BASE_URL . '/historial',
            'texto' => 'Volver al historial',
        ],
        'fondos' => [
            'url' => BASE_URL . '/fondos',
            'texto' => 'Volver a mis fondos',
        ],
        'historial-aprobaciones' => [
            'url' => BASE_URL . '/historial-aprobaciones',
            'texto' => 'Volver al historial de aprobaciones',
        ],
    ];

    $predeterminado = $modo === 'APROBACION'
        ? 'bandeja-aprobaciones'
        : 'home';

    return $destinos[$origen ?? ''] ?? $destinos[$predeterminado];
}

// Cache-busting para CSS/JS: el navegador cachea agresivamente estos
// archivos por su URL. Sin esto, después de editar estilos.css o un .js,
// el usuario puede seguir viendo la versión vieja hasta que el navegador
// decida revalidar por su cuenta (a veces horas después). 
function assetUrl(string $ruta): string
{
    $rutaDisco = __DIR__ . '/../../public' . $ruta;
    $version = is_file($rutaDisco) ? filemtime($rutaDisco) : time();
    return BASE_URL . $ruta . '?v=' . $version;
}

// Versiona también los archivos cargados desde un import, no solo el script de la vista.
function mapaModulosJs(): array
{
    $directorio = __DIR__ . '/../../public/js';
    $archivos = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directorio, FilesystemIterator::SKIP_DOTS)
    );
    $imports = [];
    foreach ($archivos as $archivo) {
        if (!$archivo->isFile() || $archivo->getExtension() !== 'js') continue;
        $relativa = str_replace('\\', '/', substr($archivo->getPathname(), strlen($directorio) + 1));
        if (str_starts_with($relativa, 'vendor/')) continue;
        $ruta = '/js/' . $relativa;
        $imports[BASE_URL . $ruta] = assetUrl($ruta);
    }
    return ['imports' => $imports];
}

// Íconos inline (sin librería externa, sin build step). Usan currentColor,
// así que heredan el color del botón/enlace que los contiene automáticamente.
function icono(string $nombre): string
{
    $iconos = [
        'volver' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>',
        'salir' => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        'editar' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>',
        'quitar' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>',
        'ver' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/></svg>',
        'mas' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
        'cerrar' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>',
        'clip' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a5 5 0 0 1-7.07-7.07l9.19-9.19a3.5 3.5 0 0 1 4.95 4.95l-9.2 9.19a1.5 1.5 0 0 1-2.12-2.12l8.49-8.49"/></svg>',
        'check' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 12.5l2.6 2.6L16 9.5"/></svg>',
        'ocultar' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-6 0-9.5-6.5-9.5-8a10.9 10.9 0 0 1 3.06-4.94M9.9 4.24A10.94 10.94 0 0 1 12 4c6 0 9.5 6.5 9.5 8a10.9 10.9 0 0 1-1.42 2.61M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>',
        'alerta' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        'filtro' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>',
    ];
    return $iconos[$nombre] ?? '';
}

function formatearFecha(?string $fecha): string 
{
    if (!$fecha) return '-';
    $soloFecha = explode(' ', $fecha)[0];
    $partes = explode('-', $soloFecha);
    return count($partes) === 3 ? "{$partes[2]}/{$partes[1]}/{$partes[0]}" : $fecha;
}
