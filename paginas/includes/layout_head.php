<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$path_fix_auth = isset($path_to_root) ? $path_to_root : '../';
if (!isset($_SESSION['usuario_id'])) {
    header("Location: " . $path_fix_auth . "index.html");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <script>
        // Aplicar el tema guardado inmediatamente para evitar parpadeos
        const savedTheme = localStorage.getItem('theme') || 'dark'; // Dark por defecto
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wireless Supply Admin</title>
    
    <?php $path_fix = isset($path_to_root) ? $path_to_root : '../'; ?>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 (Base) -->
    <link href="<?php echo $path_fix; ?>css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link href="<?php echo $path_fix; ?>css/all.min.css" rel="stylesheet">
    
    <!-- Custom Premium Theme -->
    <link href="<?php echo $path_fix; ?>css/pro_theme.css" rel="stylesheet">
    
    <link rel="icon" type="image/jpg" href="<?php echo $path_fix; ?>images/logo.jpg"/>
</head>
<body>
    <div class="app-wrapper">
