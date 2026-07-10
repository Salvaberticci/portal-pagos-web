<?php /* v2 */
require_once 'security_helper.php';
enforce_https();

// Handle logout via GET (must be BEFORE the login check)
if (isset($_GET['logout'])) {
    if (isset($_SESSION['cliente_cedula'])) {
        log_security_event('LOGOUT', 'Cierre de sesión', $_SESSION['cliente_cedula']);
    }
    session_destroy();
    header('Location: index.php');
    exit;
}

if (isset($_SESSION['cliente_cedula'])) {
    header('Location: dashboard.php');
    exit;
}

// Handle login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        @include_once '../config/test_mode.php';
        if (!defined('TEST_USER_CEDULA')) define('TEST_USER_CEDULA', '');
        if (!defined('DEV_MODE')) define('DEV_MODE', false);

        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/../src/Services/WispHubClient.php';
        $wispConfig = include __DIR__ . '/../config/wisp_hub.php';
        if (DEV_MODE) {
            require_once __DIR__ . '/../src/Services/WispHubDevModeClient.php';
            $wispClient = new \Services\WispHubDevModeClient($wispConfig);
        } else {
            $wispClient = new \Services\WispHubClient($wispConfig);
        }
    } catch (\Throwable $e) {
        error_log("[LOGIN] " . $e->getMessage());
        $_SESSION['login_error'] = "Servicio temporalmente no disponible. Intenta de nuevo en unos minutos.";
        header('Location: index.php');
        exit;
    }

    try {
        $cedula = isset($_POST['cedula']) ? trim($_POST['cedula']) : '';

        if (!check_rate_limit('login', 5, 300)) {
            $_SESSION['login_error'] = "Demasiados intentos. Por favor, intenta de nuevo en unos minutos.";
            header('Location: index.php');
            exit;
        }

        $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        if (!verify_csrf_token($csrf_token)) {
            log_security_event('CSRF_VIOLATION', 'Fallo de verificación CSRF', $cedula);
            $_SESSION['login_error'] = "Petición inválida. Recarga la página.";
            header('Location: index.php');
            exit;
        }

        if (empty($cedula)) {
            $_SESSION['login_error'] = "Por favor, ingresa tu cédula.";
            header('Location: index.php');
            exit;
        }

        $numeroSolo = preg_replace('/^[A-Z]/i', '', $cedula);
        if (strlen($numeroSolo) < 6 || strlen($numeroSolo) > 10) {
            $_SESSION['login_error'] = "Usuario no encontrado";
            header('Location: index.php');
            exit;
        }

        $clientInfo = $wispClient->getClientByDocument($cedula);
        if ($clientInfo['status'] === 0) {
        $_SESSION['login_error'] = "Servicio temporalmente no disponible. Intenta de nuevo en unos minutos.";
        header('Location: index.php');
        exit;
    }
    if ($clientInfo['status'] !== 200 || empty($clientInfo['data']['data']['service_id'] ?? $clientInfo['data']['data']['id_servicio'] ?? '')) {
        $clientInfo = $wispClient->findClientByDocument($cedula);
        if ($clientInfo['status'] === 0) {
            $_SESSION['login_error'] = "Servicio temporalmente no disponible. Intenta de nuevo en unos minutos.";
            header('Location: index.php');
            exit;
        }
    }

    if ($clientInfo['status'] === 200 && !empty($clientInfo['data']['data'])) {
        $cliente = $clientInfo['data']['data'];
        session_regenerate_id(true);
        $_SESSION['cliente_cedula'] = $cedula;
        $_SESSION['cliente_nombre'] = trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['apellidos'] ?? '')) ?: 'Cliente';
        $_SESSION['cliente_telefono'] = $cliente['telefono'] ?? '';
        $_SESSION['wisp_service_id'] = $cliente['service_id'] ?? $cliente['id_servicio'] ?? '';
        log_security_event('LOGIN_SUCCESS', 'Inicio de sesión exitoso', $cedula);
        header('Location: dashboard.php');
        exit;
    } else {
        log_security_event('LOGIN_FAILED', "Cédula no encontrada: $cedula", $cedula);
        $_SESSION['login_error'] = "Usuario no encontrado";
        header('Location: index.php');
        exit;
    }
    } catch (\Throwable $e) {
        error_log("[LOGIN_POST] " . $e->getMessage());
        $_SESSION['login_error'] = "Error al procesar la solicitud. Intenta de nuevo.";
        header('Location: index.php');
        exit;
    }
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
    <!-- Favicon -->
    <link rel="icon" href="../images/favicon.png" type="image/png">
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
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 100000;
            justify-content: center;
            align-items: center;
        }

        .modal-backdrop-custom {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
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
                <img src="../images/logo-galanet.png" alt="Logo Galanet"
                    style="width: 100%; height: auto; display: block;">
            </div>
            <h3 class="mb-2 font-weight-bold text-gradient">Portal de Clientes</h3>
            <p class="text-muted mb-4">Consulta tus contratos y paga tus mensualidades fácilmente.</p>

            <?php $loginError = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : '';
            unset($_SESSION['login_error']); ?>
            <div id="login-error-data" data-msg="<?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?>"
                style="display:none;"></div>

            <form action="index.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                <div class="mb-4">
                    <h5 class="fw-bold text-gradient mb-3"><i class="fas fa-id-card me-2"></i>Documento de Identidad
                    </h5>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="label-premium mb-1">Tipo</label>
                            <div class="select-wrapper">
                                <select id="tipo_cedula" class="form-select glass-input select-tipo"
                                    style="cursor: pointer;">
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
                            <label class="label-premium mb-1">Ingresa tu Documento</label>
                            <input type="number" id="cedula_numero" class="form-control glass-input"
                                placeholder="Ingresa tu número" required>
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

    <div id="login-error-modal" class="modal-overlay">
        <div class="modal-backdrop-custom" onclick="document.getElementById('login-error-modal').style.display='none'">
        </div>
        <div class="modal-glass glass-panel animate-fade">
            <div class="mb-3" style="font-size: 3rem; color: var(--danger);">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h5 class="fw-bold mb-1">USUARIO NO ENCONTRADO</h5>
            <p class="text-muted mb-4" id="modal-error-msg">Usuario no encontrado</p>
            <button class="btn btn-premium px-5"
                onclick="document.getElementById('login-error-modal').style.display='none'">Cerrar</button>
        </div>
    </div>

    <div id="login-loading" class="loading-overlay">
        <div class="spinner"></div>
        <div class="loading-text">Cargando...</div>
        <div class="loading-sub">Consultando tus datos</div>
    </div>

    <script>
        // Concatenar el tipo de cédula y el número al enviar el formulario
        const form = document.querySelector('form');
        form.action = 'index.php';
        const tipoCedula = document.getElementById('tipo_cedula');
        const cedulaNumero = document.getElementById('cedula_numero');
        const cedulaHidden = document.getElementById('cedula_hidden');

        form.addEventListener('submit', function (e) {
            var num = cedulaNumero.value;
            if (num.length < 6 || num.length > 10) {
                e.preventDefault();
                document.getElementById('login-error-modal').style.display = 'flex';
                return;
            }
            cedulaHidden.value = tipoCedula.value + num;
            document.getElementById('login-loading').style.display = 'flex';
        });

        // Asegurar que solo se ingresen números en el campo
        cedulaNumero.addEventListener('input', function (e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Theme Toggle Logic
        (function () {
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

            themeBtn.addEventListener('click', function () {
                const currentTheme = html.getAttribute('data-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

                html.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                updateThemeIcon(newTheme);
            });
        })();

        // Error Modal
        (function () {
            var errDiv = document.getElementById('login-error-data');
            if (errDiv && errDiv.getAttribute('data-msg')) {
                document.getElementById('modal-error-msg').textContent = errDiv.getAttribute('data-msg');
                document.getElementById('login-error-modal').style.display = 'flex';
            }
        })();
    </script>
</body>

</html>