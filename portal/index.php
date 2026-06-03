<?php
session_start();
if (isset($_SESSION['cliente_cedula'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <script>
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal de Clientes - Wireless Supply</title>
    <!-- Bootstrap CSS -->
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Estilos Premium -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-box glass-panel animate-fade text-center">
            <div class="d-flex justify-content-end mb-2">
                <button class="theme-toggle" id="themeToggleBtn" title="Cambiar Tema">
                    <i class="fas fa-sun"></i>
                </button>
            </div>
            
            <img src="../images/logo-galanet.png" alt="Logo Galanet" class="img-fluid mb-4" style="max-height: 100px; border-radius: 12px;">
            
            <h3 class="mb-2 font-weight-bold text-gradient">Portal de Clientes</h3>
            <p class="text-muted mb-4">Consulta tus contratos y paga tus mensualidades fácilmente.</p>

            <?php if (isset($_SESSION['login_error'])): ?>
                <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.2); color: #fca5a5; border-radius: 12px; font-size: 0.9rem;">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $_SESSION['login_error']; unset($_SESSION['login_error']); ?>
                </div>
            <?php endif; ?>

            <form action="auth.php" method="POST">
                <div class="mb-4 text-start">
                    <label class="label-premium">Ingresa tu Cédula</label>
                    <div class="input-group">
                        <span class="input-group-text glass-input"><i class="fas fa-id-card text-primary"></i></span>
                        <input type="text" name="cedula" class="form-control glass-input" placeholder="Ej: V12345678" required style="border-left: none; text-transform: uppercase;">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-premium w-100 mb-3">
                    INGRESAR <i class="fas fa-arrow-right ms-2"></i>
                </button>

            </form>

        </div>
    </div>

    <script>
        // Convert to uppercase automatically for cédula
        document.querySelector('input[name="cedula"]').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase().replace(/[^VEJGP0-9]/g, '');
        });

        // Theme Toggle Logic
        document.addEventListener('DOMContentLoaded', function() {
            const themeBtn = document.getElementById('themeToggleBtn');
            const html = document.documentElement;
            const themeIcon = themeBtn.querySelector('i');

            function updateThemeIcon(theme) {
                if (theme === 'dark') {
                    themeIcon.className = 'fas fa-sun';
                } else {
                    themeIcon.className = 'fas fa-moon';
                }
            }

            updateThemeIcon(html.getAttribute('data-theme'));

            themeBtn.addEventListener('click', function() {
                const currentTheme = html.getAttribute('data-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                
                html.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                updateThemeIcon(newTheme);
            });
        });
    </script>
</body>
</html>
