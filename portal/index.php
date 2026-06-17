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
        .select-wrapper {
            position: relative;
        }
        .select-tipo {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            padding-right: 2.5rem;
            background-image: none !important;
        }
        .select-arrow {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: var(--text-muted, #6b7280);
            font-size: 0.75rem;
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
                            <div class="select-wrapper">
                                <select id="tipo_cedula" class="form-select glass-input select-tipo" style="cursor: pointer;">
                                    <option value="V" selected>Venezolano</option>
                                    <option value="E">Extranjero</option>
                                    <option value="J">Jurídico</option>
                                    <option value="P">Pasaporte</option>
                                    <option value="G">Gubernamental</option>
                                </select>
                                <i class="fas fa-chevron-down select-arrow"></i>
                            </div>
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
