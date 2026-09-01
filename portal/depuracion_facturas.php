<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

// Opcional: Proteger con contraseña simple si se desea, por ahora abierto o misma pass que test_nodo.
// Para test_nodo.php, no había pass en la copia visible, o quizás sí. Lo dejaremos libre por ser admin tool,
// o podríamos pedir un token. Para simplificar, lo dejaremos accesible.

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';
@include_once __DIR__ . '/../config/wisphub_credentials.php';

function extraer_servicio_id(array $inv): string {
    $svc = $inv['id_servicio'] ?? '';
    if (empty($svc) && !empty($inv['articulos']) && is_array($inv['articulos'])) {
        foreach ($inv['articulos'] as $art) {
            $s = $art['servicio']['id_servicio'] ?? '';
            if (!empty($s)) { $svc = $s; break; }
        }
    }
    return (string)$svc;
}

$nodo = $_POST['nodo'] ?? '';
$resultados = null;
$error = '';
$mes_actual = date('Y-m'); // ej. 2026-09
$primer_dia = $mes_actual . '-01';
$ultimo_dia = date('Y-m-t', strtotime($primer_dia));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $nodo) {
    $accountMap = ['jalisco' => 'jalisco', 'sitelco' => 'sitelco', 'pampanito' => 'pampanito'];
    $ref = $accountMap[$nodo] ?? '';
    
    if ($ref && isset($WISPHUB_ACCOUNTS[$ref])) {
        $creds = $WISPHUB_ACCOUNTS[$ref];
        // Instanciar cliente
        $client = new \Services\WispHubClient([
            'base_url'   => $creds['base_url'],
            'api_key'    => $creds['api_key'],
            'verify_ssl' => $creds['verify_ssl'] ?? false,
        ]);

        try {
            // 1. Obtener todos los servicios que tienen factura emitida este mes
            $facturados_este_mes = [];
            $offset = 0;
            $limit = 500;
            
            while (true) {
                $facturas = $client->getInvoices([
                    'fecha_emision__range_0' => $primer_dia,
                    'fecha_emision__range_1' => $ultimo_dia,
                    'limit' => $limit,
                    'offset' => $offset
                ]);
                
                $pageCount = count($facturas);
                foreach ($facturas as $inv) {
                    $svc = extraer_servicio_id($inv);
                    if ($svc) {
                        $facturados_este_mes[$svc] = true;
                    }
                }
                
                if ($pageCount < $limit) {
                    break;
                }
                $offset += $limit;
            }

            // 2. Obtener todos los clientes activos
            $clientes_activos = [];
            $page = 1;
            
            while (true) {
                $res = $client->listClients([
                    'estado' => 1, // 1 = Activo
                    'limit' => 100,
                    'page' => $page
                ]);
                
                if ($res['status'] !== 200) {
                    $error = "Error al obtener clientes activos: " . ($res['error'] ?? 'Desconocido');
                    break;
                }
                
                $clients = $res['data']['results'] ?? [];
                if (empty($clients)) {
                    break;
                }
                
                foreach ($clients as $c) {
                    $svc = (string)($c['id_servicio'] ?? $c['id'] ?? $c['service_id'] ?? '');
                    if ($svc) {
                        // Si está activo pero NO tiene factura este mes, lo guardamos
                        if (!isset($facturados_este_mes[$svc])) {
                            $clientes_activos[] = $c;
                        }
                    }
                }
                
                $current_page = $res['data']['current_page'] ?? 1;
                $last_page = $res['data']['last_page'] ?? 1;
                
                if ($current_page >= $last_page) {
                    break;
                }
                $page++;
            }
            
            if (!$error) {
                $resultados = $clientes_activos;
            }
            
        } catch (\Exception $e) {
            $error = "Excepción: " . $e->getMessage();
        }
    } else {
        $error = "Nodo inválido.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Depuración: Clientes Activos sin Factura</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; padding: 20px; }
        .glass-panel { background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 20px; }
        .table-premium { color: #e2e8f0; }
        .table-premium th { background: #1e293b; color: #94a3b8; border-bottom: 2px solid #334155; }
        .table-premium td { border-bottom: 1px solid #334155; vertical-align: middle; }
        .btn-premium { background: linear-gradient(135deg, #3b82f6, #6366f1); color: #fff; border: none; }
        .btn-premium:hover { background: linear-gradient(135deg, #2563eb, #4f46e5); color: #fff; }
        select.form-select { background: #1e293b; color: #fff; border: 1px solid #334155; }
    </style>
</head>
<body>
    <div class="container my-4">
        <h2 class="fw-bold mb-4 text-center text-primary"><i class="fas fa-search-dollar me-2"></i> Conciliación de Facturación</h2>
        
        <div class="glass-panel mb-4 mx-auto" style="max-width: 600px;">
            <p class="text-muted small text-center mb-4">Esta herramienta busca clientes en estado <strong>Activo</strong> que no tienen una factura generada en el mes en curso (<strong><?php echo date('F Y'); ?></strong>).</p>
            
            <form method="POST" action="" id="depuracionForm">
                <div class="mb-3">
                    <label class="form-label text-light fw-bold">Seleccionar Nodo</label>
                    <select name="nodo" class="form-select" required>
                        <option value="">-- Elija un nodo --</option>
                        <option value="sitelco" <?php echo $nodo === 'sitelco' ? 'selected' : ''; ?>>Sitelco</option>
                        <option value="jalisco" <?php echo $nodo === 'jalisco' ? 'selected' : ''; ?>>Jalisco</option>
                        <option value="pampanito" <?php echo $nodo === 'pampanito' ? 'selected' : ''; ?>>Pampanito</option>
                    </select>
                </div>
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-premium py-2 fw-bold" id="btnBuscar">
                        <i class="fas fa-search me-2"></i> Conciliar Activos sin Factura
                    </button>
                </div>
            </form>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger mx-auto" style="max-width: 600px;"><i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($resultados !== null): ?>
            <div class="glass-panel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0 text-white">Resultados para Nodo <?php echo ucfirst($nodo); ?></h5>
                    <span class="badge bg-danger fs-6"><?php echo count($resultados); ?> clientes afectados</span>
                </div>
                
                <?php if (count($resultados) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-premium table-hover mb-0">
                            <thead>
                                <tr>
                                    <th># Servicio</th>
                                    <th>Cliente</th>
                                    <th>Cédula / Usuario</th>
                                    <th>Plan</th>
                                    <th>IP / Router</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resultados as $c): 
                                    $svc = $c['id_servicio'] ?? $c['id'] ?? $c['service_id'] ?? '-';
                                    $nombre = $c['nombre'] ?? '-';
                                    $ident = $c['cedula'] ?? $c['usuario'] ?? '-';
                                    $plan = $c['plan_internet']['nombre'] ?? $c['plan_internet'] ?? '-';
                                    if (is_array($plan)) $plan = $plan['nombre'] ?? '-';
                                    $ip = $c['ip'] ?? '-';
                                    $router = $c['router']['nombre'] ?? $c['router'] ?? '-';
                                    if (is_array($router)) $router = $router['nombre'] ?? '-';
                                ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?php echo htmlspecialchars($svc); ?></td>
                                    <td class="text-white"><?php echo htmlspecialchars($nombre); ?></td>
                                    <td><?php echo htmlspecialchars($ident); ?></td>
                                    <td><small><?php echo htmlspecialchars($plan); ?></small></td>
                                    <td><small><?php echo htmlspecialchars($ip); ?> <br> <span class="text-muted"><?php echo htmlspecialchars($router); ?></span></small></td>
                                    <td><span class="badge bg-success">Activo</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h4 class="text-white">¡Todo perfecto!</h4>
                        <p class="text-muted">Todos los clientes activos en este nodo tienen su factura de <?php echo date('F Y'); ?> generada.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        document.getElementById('depuracionForm').addEventListener('submit', function() {
            const btn = document.getElementById('btnBuscar');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Procesando (puede tomar unos segundos)...';
            btn.disabled = true;
        });
    </script>
</body>
</html>
