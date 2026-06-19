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
    <link rel="stylesheet" href="css/fontawesome/css/all.min.css">
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
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: 100000;
            justify-content: center;
            align-items: center;
        }
        .modal-backdrop-custom {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.7);
        }
        .modal-glass {
            position: relative;
            max-width: 400px;
            width: 90%;
            padding: 36px 28px;
            z-index: 1;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-box glass-panel animate-fade text-center">
            <div style="text-align: right; margin-bottom: 8px; position: relative; z-index: 3;">
                <button class="theme-toggle" id="themeToggleBtn" title="Cambiar Tema">
                    <i class="fas fa-sun"></i>
                </button>
            </div>
            <div class="login-logo-wrap">
                <img src="../images/logo-galanet.png" alt="Logo Galanet" style="width: 100%; height: auto; display: block;">
            </div>
            <h3 class="mb-2 font-weight-bold text-gradient">Portal de Clientes</h3>
            <p class="text-muted mb-4">Consulta tus contratos y paga tus mensualidades fácilmente.</p>

            <?php $loginError = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : ''; unset($_SESSION['login_error']); ?>
            <div id="login-error-data" data-msg="<?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?>" style="display:none;"></div>

            <form action="auth.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                <div class="mb-4">
                    <h5 class="fw-bold text-gradient mb-3"><i class="fas fa-id-card me-2"></i>Documento de Identidad</h5>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="label-premium mb-1">Tipo</label>
                            <div class="select-wrapper">
                                <select id="tipo_cedula" class="form-select glass-input select-tipo" style="cursor: pointer;">
                                    <option value="V" selected>V</option>
                                    <option value="E">E</option>
                                    <option value="J">J</option>
                                    <option value="P">P</option>
                                    <option value="G">G</option>
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

        form.addEventListener('submit', function(e) {
            var num = cedulaNumero.value;
            if (num.length < 5) {
                e.preventDefault();
                document.getElementById('modal-error-msg').textContent = 'El n\u00famero de c\u00e9dula debe tener al menos 5 d\u00edgitos.';
                document.getElementById('login-error-modal').style.display = 'flex';
                return;
            }
            cedulaHidden.value = tipoCedula.value + num;
            document.getElementById('login-loading').style.display = 'flex';
        });

        // Asegurar que solo se ingresen números en el campo
        cedulaNumero.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Theme Toggle Logic
        (function() {
            const themeBtn = document.getElementById('themeToggleBtn');
            if (!themeBtn) return;
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
        })();

        // Error Modal
        (function() {
            var errDiv = document.getElementById('login-error-data');
            if (errDiv && errDiv.getAttribute('data-msg')) {
                document.getElementById('modal-error-msg').textContent = errDiv.getAttribute('data-msg');
                document.getElementById('login-error-modal').style.display = 'flex';
            }
        })();
        function cerrarModalError() {
            document.getElementById('login-error-modal').style.display = 'none';
        }
    </script>

    <div id="login-error-modal" class="modal-overlay">
        <div class="modal-backdrop-custom" onclick="cerrarModalError()"></div>
        <div class="modal-glass glass-panel animate-fade">
            <div class="mb-3" style="font-size: 3rem; color: var(--danger);">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h5 class="fw-bold mb-1">USUARIO NO ENCONTRADO</h5>
            <p class="text-muted mb-4" id="modal-error-msg">No se encontr&oacute; ning&uacute;n contrato con esta c&eacute;dula.</p>
            <button class="btn btn-premium px-5" onclick="cerrarModalError()">Cerrar</button>
        </div>
    </div>

    <div id="login-loading" class="loading-overlay">
        <div class="spinner"></div>
        <div class="loading-text">Cargando...</div>
        <div class="loading-sub">Consultando tus datos</div>
    </div>
</body>
</html>
