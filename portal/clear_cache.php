<?php
$token = $_GET['token'] ?? '';
if ($token !== 'm4r4t3lt2026') {
    http_response_code(403);
    die('Acceso denegado');
}

$resultado = 'OPcache no disponible';
if (function_exists('opcache_reset')) {
    $resultado = opcache_reset() ? 'OPcache limpiado exitosamente' : 'Error al limpiar OPcache';
} else {
    $resultado = 'OPcache no est&aacute; activo en este servidor';
}

header("Cache-Control: no-cache, must-revalidate");
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Clear Cache</title></head>
<body style="font-family:sans-serif;padding:40px;text-align:center;background:#0f172a;color:#e2e8f0;">
    <h2 style="color:#10b981;"><?php echo $resultado; ?></h2>
    <p style="color:#94a3b8;">Filemtime dashboard.php: <?php echo date('Y-m-d H:i:s', filemtime(__DIR__ . '/dashboard.php')); ?></p>
    <p><a href="dashboard.php" style="color:#3b82f6;text-decoration:none;font-weight:600;">&larr; Ir al Dashboard</a></p>
</body>
</html>
