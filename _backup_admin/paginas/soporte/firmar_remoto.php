<?php
// paginas/soporte/firmar_remoto.php
// Página pública para que el CLIENTE firme soportes o contratos

require_once '../conexion.php';

$token = isset($_GET['token']) ? $conn->real_escape_string($_GET['token']) : '';
$type = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : 'soporte'; // 'soporte' o 'contrato'

$doc = null;
$error = null;
$success = false;

if (empty($token)) {
    $error = "Token inválido o expirado.";
} else {
    // Buscar documento por token
    if ($type === 'soporte') {
        $sql = "SELECT s.*, c.nombre_completo, c.cedula, c.direccion 
                FROM soportes s 
                JOIN contratos c ON s.id_contrato = c.id 
                WHERE s.token_firma = '$token' AND s.estado_firma = 'PENDIENTE'";
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            $doc = $res->fetch_assoc();
            $doc['tipo_doc'] = 'Soporte Técnico';
            $doc['titulo'] = "Soporte Técnico #" . $doc['id_soporte'];
        } else {
            $error = "El documento no existe o ya ha sido firmado.";
        }
    } else if ($type === 'contrato') {
        $sql = "SELECT * FROM contratos WHERE token_firma = '$token' AND estado_firma = 'PENDIENTE'";
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            $doc = $res->fetch_assoc();
            $doc['tipo_doc'] = 'Contrato de Servicio';
            $doc['titulo'] = "Contrato de Servicio - " . $doc['nombre_completo'];
        } else {
            $error = "El documento no existe o ya ha sido firmado.";
        }
    } else {
        $error = "Tipo de documento no válido.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Remota de Documentos</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            background-color: #fceceb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .signature-pad {
            border: 2px dashed #dc3545;
            border-radius: 8px;
            width: 100%;
            height: 250px;
            background-color: #fff;
            touch-action: none;
        }

        .card-header {
            background-color: #dc3545;
            color: white;
        }

        .btn-primary {
            background-color: #dc3545;
            border-color: #dc3545;
        }

        .btn-primary:hover {
            background-color: #bb2d3b;
            border-color: #b02a37;
        }

        .info-label {
            font-weight: 600;
            color: #555;
        }

        .summary-box {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
    </style>
</head>

<body>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="card shadow-lg border-0">
                    <div class="card-header text-center py-4">
                        <h3 class="mb-0"><i class="fa-solid fa-file-contract me-2"></i>Firma de Documento</h3>
                    </div>
                    <div class="card-body p-4 p-md-5">

                        <?php if ($error): ?>
                            <div class="alert alert-danger text-center shadow-sm">
                                <i class="fa-solid fa-circle-exclamation fa-2x mb-2 d-block"></i>
                                <h4>Enlace no válido</h4>
                                <p class="mb-0">
                                    <?php echo $error; ?>
                                </p>
                            </div>
                        <?php elseif ($doc): ?>

                            <div class="text-center mb-4">
                                <h5 class="text-muted">
                                    <?php echo $doc['tipo_doc']; ?>
                                </h5>
                                <h2 class="fw-bold">
                                    <?php echo $doc['titulo']; ?>
                                </h2>
                                <p class="text-muted">Por favor revise la información y registre su firma al final.</p>
                            </div>

                            <div class="summary-box">
                                <h5 class="border-bottom pb-2 mb-3 text-danger">Resumen del Servicio</h5>

                                <?php if ($type === 'soporte'): ?>
                                    <div class="row g-3">
                                        <div class="col-sm-6"><span class="info-label">Cliente:</span><br>
                                            <?php echo $doc['nombre_completo']; ?>
                                        </div>
                                        <div class="col-sm-6"><span class="info-label">Cédula:</span><br>
                                            <?php echo $doc['cedula']; ?>
                                        </div>
                                        <div class="col-12"><span class="info-label">Dirección:</span><br>
                                            <?php echo $doc['direccion']; ?>
                                        </div>
                                        <div class="col-sm-6"><span class="info-label">Fecha:</span><br>
                                            <?php echo date('d/m/Y', strtotime($doc['fecha_soporte'])); ?>
                                        </div>
                                        <div class="col-sm-6"><span class="info-label">Técnico:</span><br>
                                            <?php echo $doc['tecnico_asignado']; ?>
                                        </div>
                                        <div class="col-12"><span class="info-label">Motivo:</span><br>
                                            <?php echo $doc['descripcion']; ?>
                                        </div>
                                        <div class="col-12"><span class="info-label">Trabajo Realizado:</span><br>
                                            <?php echo $doc['observaciones']; ?>
                                        </div>
                                    </div>
                                <?php else: // Contrato ?>
                                    <div class="row g-3">
                                        <div class="col-sm-6"><span class="info-label">Cliente:</span><br>
                                            <?php echo $doc['nombre_completo']; ?>
                                        </div>
                                        <div class="col-sm-6"><span class="info-label">Cédula:</span><br>
                                            <?php echo $doc['cedula']; ?>
                                        </div>
                                        <div class="col-12"><span class="info-label">Dirección:</span><br>
                                            <?php echo $doc['direccion']; ?>
                                        </div>
                                        <div class="col-sm-6"><span class="info-label">Plan:</span><br>Plan ID #
                                            <?php echo $doc['id_plan']; ?>
                                        </div>
                                        <div class="col-sm-6"><span class="info-label">Fecha Instalación:</span><br>
                                            <?php echo date('d/m/Y', strtotime($doc['fecha_instalacion'])); ?>
                                        </div>
                                        <div class="col-12"><span class="info-label">Monto A Pagar:</span><br>$
                                            <?php echo number_format($doc['monto_pagar'], 2); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <form id="formFirma">
                                <input type="hidden" name="token" value="<?php echo $token; ?>">
                                <input type="hidden" name="type" value="<?php echo $type; ?>">
                                <input type="hidden" name="firma_data" id="firma_data">

                                <div class="mb-4">
                                    <label class="form-label fw-bold h5 text-danger">Su Firma Digial <span
                                            class="text-danger">*</span></label>
                                    <p class="small text-muted">Dibuje su firma en el recuadro a continuación.</p>
                                    <div class="position-relative">
                                        <canvas id="signaturePad" class="signature-pad"></canvas>
                                        <div class="position-absolute top-0 end-0 p-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary bg-white"
                                                id="clearBtn" title="Limpiar firma">
                                                <i class="fa-solid fa-eraser"></i> Limpiar
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg" id="btnGuardar">
                                        <i class="fa-solid fa-check-circle me-2"></i> Confirmar y Guardar Firma
                                    </button>
                                </div>
                            </form>

                        <?php endif; ?>

                    </div>
                    <div class="card-footer bg-light text-center py-3">
                        <small class="text-muted">Sistema de Gestión - Wireless Supply</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.5/dist/signature_pad.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../../js/jquery.min.js"></script>

    <?php if ($doc && !$error): ?>
        <script>
            const canvas = document.getElementById('signaturePad');
            const clearBtn = document.getElementById('clearBtn');

            // Resize canvas
            function resizeCanvas() {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext("2d").scale(ratio, ratio);
            }
            window.addEventListener("resize", resizeCanvas);
            resizeCanvas();

            const signaturePad = new SignaturePad(canvas, {
                backgroundColor: 'rgba(255, 255, 255, 0)',
                penColor: 'rgb(0, 0, 0)'
            });

            clearBtn.addEventListener('click', () => signaturePad.clear());

            document.getElementById('formFirma').addEventListener('submit', function (e) {
                e.preventDefault();

                if (signaturePad.isEmpty()) {
                    Swal.fire('Atención', 'Por favor dibuje su firma para continuar.', 'warning');
                    return;
                }

                document.getElementById('firma_data').value = signaturePad.toDataURL();
                const btn = document.getElementById('btnGuardar');
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Guardando...';

                const formData = new FormData(this);

                fetch('procesar_firma_remota.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Firma Guardada!',
                                text: 'El documento ha sido firmado correctamente. Gracias.',
                                confirmButtonText: 'Cerrar',
                                allowOutsideClick: false
                            }).then(() => {
                                // Deshabilitar todo o redirigir
                                document.querySelector('.card-body').innerHTML = `
                        <div class="text-center py-5">
                            <i class="fa-solid fa-check-circle text-success fa-5x mb-3"></i>
                            <h2 class="text-success">¡Gracias!</h2>
                            <p class="lead">Su firma ha sido registrada exitosamente.</p>
                            <p class="text-muted">Ya puede cerrar esta ventana.</p>
                        </div>
                    `;
                            });
                        } else {
                            Swal.fire('Error', data.message || 'Error al guardar la firma.', 'error');
                            btn.disabled = false;
                            btn.innerHTML = originalText;
                        }
                    })
                    .catch(error => {
                        console.error(error);
                        Swal.fire('Error', 'Error de conexión. Intente nuevamente.', 'error');
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    });
            });
        </script>
    <?php endif; ?>

</body>

</html>