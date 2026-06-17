<?php
require_once 'security_helper.php';
enforce_https();
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
    <style>
        .select-tipo {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            padding-right: 2.25rem;
        }
        .select-tipo option {
            background: var(--bg-card, #1e293b);
            color: var(--text-main, #e2e8f0);
        }
    </style>
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
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($_SESSION['login_error'] ?? '', ENT_QUOTES, 'UTF-8'); unset($_SESSION['login_error']); ?>
                </div>
            <?php endif; ?>

            <form action="auth.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                <div class="mb-4">
                    <h5 class="fw-bold text-gradient mb-3"><i class="fas fa-id-card me-2"></i>Documento de Identidad</h5>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="label-premium mb-1">Tipo</label>
                            <select id="tipo_cedula" class="form-select glass-input select-tipo" style="cursor: pointer;">
                                <option value="V" selected>Venezolano</option>
                                <option value="E">Extranjero</option>
                                <option value="J">Jurídico</option>
                                <option value="P">Pasaporte</option>
                                <option value="G">Gubernamental</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="label-premium mb-1">Número</label>
                            <input type="number" id="cedula_numero" class="form-control glass-input" placeholder="Ingresa tu número" required>
                        </div>
                    </div>
                    <input type="hidden" name="cedula" id="cedula_hidden">
                </div>

                <button type="submit" class="btn btn-premium w-100 mb-3 py-3">
                    Aceptar <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </form>

        </div>
    </div>

    <script>
        // Concatenar el tipo de cédula y el número al enviar el formulario
        const form = document.querySelector('form');
        const tipoCedula = document.getElementById('tipo_cedula');
        const cedulaNumero = document.getElementById('cedula_numero');
        const cedulaHidden = document.getElementById('cedula_hidden');

        form.addEventListener('submit', function() {
            cedulaHidden.value = tipoCedula.value + cedulaNumero.value;
        });

        // Asegurar que solo se ingresen números en el campo
        cedulaNumero.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
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
