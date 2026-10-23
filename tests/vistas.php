<?php
if (PHP_SAPI !== 'cli') exit;
register_shutdown_function(function (): void {
    while (ob_get_level() > 0) ob_end_clean();
});
set_error_handler(function (int $nivel, string $mensaje, string $archivo, int $linea): never {
    throw new ErrorException($mensaje, 0, $nivel, $archivo, $linea);
});
require_once __DIR__ . '/../src/Datos/cajaChica.php';
require_once __DIR__ . '/../src/Negocio/exportarPdf.php';
require_once __DIR__ . '/../src/Vistas/verResumenView.php';
require_once __DIR__ . '/../src/Vistas/historialResumenesView.php';
require_once __DIR__ . '/../src/Vistas/historialAprobacionesView.php';
define('BASE_URL', '/caja-chica/public');
define('TIEMPO_INACTIVIDAD', 15 * 60);
$_SESSION = ['usuario_id' => 1, 'trabajador_id' => 1, 'username' => 'Prueba', 'rol' => 'SOLICITANTE'];
$pdo = obtenerConexion();
$ids = $pdo->query('SELECT id FROM caja_chica ORDER BY id DESC LIMIT 3')->fetchAll(PDO::FETCH_COLUMN);
foreach ($ids as $id) {
    $resumen = obtenerResumenAprobador((int)$id);
    if (!$resumen) continue;
    foreach (['LECTURA', 'APROBACION'] as $modo) {
        ob_start();
        vistaVerResumen($resumen, null, [], null, [], null, [], [], [], [], false, [], $modo);
        $html = ob_get_clean();
        if (!str_contains($html, '<div id="app" v-cloak>') || !str_contains($html, 'class="cargando-vista"')) {
            throw new RuntimeException('Falta la protección de carga del resumen.');
        }
    }
    $pdf = generarPdfResumen((int)$id, false);
    if (!$pdf || !str_starts_with($pdf, '%PDF-')) throw new RuntimeException('No se generó el PDF del resumen.');
}
foreach (['vistaHistorialResumenes', 'vistaHistorialAprobaciones'] as $vista) {
    ob_start();
    $vista([]);
    $html = ob_get_clean();
    if (!str_contains($html, '{{ r.numero_formateado }}')) throw new RuntimeException('Historial sin formato de número.');
}
echo "OK: vistas de lectura/aprobación, historiales y PDF de tres cajas existentes sin errores PHP.\n";
