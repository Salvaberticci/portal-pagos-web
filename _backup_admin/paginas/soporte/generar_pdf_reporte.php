<?php
/**
 * Generar PDF de Reporte Técnico Individual
 * Usa Dompdf para crear un documento profesional con toda la información
 */

ini_set('memory_limit', '256M');
// Ocultamos deprecated y notices para que no corrompan el binario del PDF en PHP 8+
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Error: ID de reporte no proporcionado.");
}

$id_soporte = intval($_GET['id']);

require_once __DIR__ . '/../../dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../conexion.php';

// Consultar datos completos del reporte
$sql = "SELECT s.*, c.nombre_completo, c.cedula, c.ip_onu as ip, c.direccion, c.telefono, o.nombre_olt, p.nombre_pon
        FROM soportes s
        INNER JOIN contratos c ON s.id_contrato = c.id
        LEFT JOIN olt o ON s.id_olt = o.id_olt
        LEFT JOIN pon p ON s.id_pon = p.id_pon
        WHERE s.id_soporte = $id_soporte";

$result = $conn->query($sql);
if ($result->num_rows == 0) {
    die("Error: Reporte no encontrado.");
}

$r = $result->fetch_assoc();
// Preparar variables
$fecha = date('d/m/Y', strtotime($r['fecha_soporte']));

// Codificar firmas a base64 si existen
$firma_tecnico_b64 = '';
$firma_cliente_b64 = '';

if (!empty($r['firma_tecnico'])) {
    $path_firma_tec = '../../uploads/firmas/' . $r['firma_tecnico'];
    if (file_exists($path_firma_tec)) {
        $firma_tecnico_b64 = 'data:image/png;base64,' . base64_encode(file_get_contents($path_firma_tec));
    }
}

if (!empty($r['firma_cliente'])) {
    $path_firma_cli = '../../uploads/firmas/' . $r['firma_cliente'];
    if (file_exists($path_firma_cli)) {
        $firma_cliente_b64 = 'data:image/png;base64,' . base64_encode(file_get_contents($path_firma_cli));
    }
}

// Construir HTML del documento
$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Técnico #' . $r['id_soporte'] . '</title>
    <style>
        body {
            font-family: "Helvetica", Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
            margin: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #0d6efd;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #0d6efd;
            font-size: 20pt;
            margin: 0;
        }
        .header p {
            color: #666;
            margin: 5px 0;
        }
        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .section-title {
            background-color: #0d6efd;
            color: white;
            padding: 8px 12px;
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .info-table td {
            padding: 6px 10px;
            border-bottom: 1px solid #eee;
        }
        .info-table td.label {
            font-weight: bold;
            width: 30%;
            color: #555;
        }
        .info-table td.value {
            color: #333;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 9pt;
            font-weight: bold;
        }
        .badge-success { background-color: #198754; color: white; }
        .badge-warning { background-color: #ffc107; color: #000; }
        .badge-danger { background-color: #dc3545; color: white; }
        .badge-info { background-color: #0dcaf0; color: #000; }
        .signature-section {
            margin-top: 40px;
            text-align: center;
        }
        .signature-box {
            display: inline-block;
            width: 45%;
            vertical-align: top;
            text-align: center;
        }
        .signature-box img {
            max-width: 200px;
            max-height: 100px;
            border: 1px solid #ddd;
            padding: 5px;
        }
        .signature-box p {
            margin-top: 10px;
            border-top: 1px solid #000;
            padding-top: 5px;
            font-weight: bold;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>WIRELESS SUPPLY, C.A.</h1>
        <p>REPORTE DE VISITA TÉCNICA</p>
        <p><strong>Reporte #' . $r['id_soporte'] . '</strong> | Fecha: ' . $fecha . '</p>
    </div>

    <div class="section">
        <div class="section-title">INFORMACIÓN DEL CLIENTE</div>
        <table class="info-table">
            <tr>
                <td class="label">Nombre:</td>
                <td class="value">' . htmlspecialchars($r['nombre_completo']) . '</td>
            </tr>
            <tr>
                <td class="label">Cédula:</td>
                <td class="value">' . htmlspecialchars($r['cedula']) . '</td>
            </tr>
            <tr>
                <td class="label">IP Asignada:</td>
                <td class="value">' . htmlspecialchars($r['ip']) . '</td>
            </tr>
            <tr>
                <td class="label">Teléfono:</td>
                <td class="value">' . htmlspecialchars($r['telefono']) . '</td>
            </tr>
            <tr>
                <td class="label">Dirección:</td>
                <td class="value">' . htmlspecialchars($r['direccion']) . '</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">DETALLES DE LA ' . ($r['prioridad'] === 'NIVEL 3' ? 'FALLA MASIVA' : 'VISITA') . '</div>
        <table class="info-table">
            <tr>
                <td class="label">Técnico Asignado:</td>
                <td class="value">' . htmlspecialchars($r['tecnico_asignado']) . '</td>
            </tr>
            <tr>
                <td class="label">Sector:</td>
                <td class="value">' . htmlspecialchars($r['sector']) . '</td>
            </tr>';

if ($r['prioridad'] === 'NIVEL 3') {
    $html .= '
            <tr>
                <td class="label">Zona Afectada:</td>
                <td class="value text-danger fw-bold">' . htmlspecialchars($r['zona_afectada'] ?? '—') . '</td>
            </tr>
            <tr>
                <td class="label text-danger">Clientes Afectados (Est.):</td>
                <td class="value fw-bold text-danger">' . intval($r['clientes_afectados']) . '</td>
            </tr>';
}

$html .= '
            <tr>
                <td class="label">Tipo de Servicio:</td>
                <td class="value"><span style="background:#17a2b8;color:#fff;padding:2px 8px;border-radius:4px;font-weight:bold;font-size:11px;">' . htmlspecialchars($r['tipo_servicio'] ?? 'N/A') . '</span></td>
            </tr>
            <tr>
                <td class="label">Punto de Acceso (OLT):</td>
                <td class="value">' . htmlspecialchars($r['nombre_olt'] ?: 'N/A') . '</td>
            </tr>
            <tr>
                <td class="label">Puerto (PON):</td>
                <td class="value">' . htmlspecialchars($r['nombre_pon'] ?: 'N/A') . '</td>
            </tr>
            <tr>
                <td class="label">Tipo de Falla:</td>
                <td class="value"><span style="background:#ffc107;color:#333;padding:2px 8px;border-radius:4px;font-weight:bold;font-size:11px;">' . htmlspecialchars($r['tipo_falla'] ?? 'N/A') . '</span></td>
            </tr>';

if ($r['prioridad'] !== 'NIVEL 3') {
    $html .= '
            <tr>
                <td class="label">Hora de Solución:</td>
                <td class="value">' . (substr($r['hora_solucion'], 0, 5) ?: '—') . '</td>
            </tr>
            </tr>';
}

$html .= '
            <tr>
                <td class="label">Estado Actual:</td>
                <td class="value">' . ($r['solucion_completada'] ? '<span style="background:#28a745;color:#fff;padding:2px 8px;border-radius:4px;font-weight:bold;font-size:11px;">SOLUCIONADA</span>' : '<span style="background:#dc3545;color:#fff;padding:2px 8px;border-radius:4px;font-weight:bold;font-size:11px;">ACTIVA</span>') . '</td>
            </tr>
        </table>
    </div>';

if ($r['prioridad'] !== 'NIVEL 3') {
    $html .= '
    <div class="section">
        <div class="section-title">DIAGNÓSTICO TÉCNICO</div>
        <table class="info-table">
            <tr>
                <td class="label">Estado ONU:</td>
                <td class="value">' . htmlspecialchars($r['estado_onu']) . '</td>
            </tr>
            <tr>
                <td class="label">Estado Router:</td>
                <td class="value">' . htmlspecialchars($r['estado_router']) . '</td>
            </tr>
            <tr>
                <td class="label">Modelo Router:</td>
                <td class="value">' . htmlspecialchars($r['modelo_router']) . '</td>
            </tr>
            <tr>
                <td class="label">Bajada / Subida / Ping:</td>
                <td class="value">' . htmlspecialchars($r['bw_bajada']) . ' MB / ' . htmlspecialchars($r['bw_subida']) . ' MB / ' . htmlspecialchars($r['bw_ping']) . ' ms</td>
            </tr>
            <tr>
                <td class="label">Dispositivos Conectados:</td>
                <td class="value">' . htmlspecialchars($r['num_dispositivos']) . '</td>
            </tr>';

    if (!empty($r['estado_antena'])) {
        $html .= '
            <tr>
                <td class="label">Estado Antena:</td>
                <td class="value">' . htmlspecialchars($r['estado_antena']) . '</td>
            </tr>
            <tr>
                <td class="label">Valores Antena:</td>
                <td class="value">' . htmlspecialchars($r['valores_antena']) . ' dBm</td>
            </tr>';
    }

    $html .= '
        </table>
    </div>';
}

    $html .= '
    <div class="section">
        <div class="section-title">OBSERVACIONES Y SUGERENCIAS</div>
        <table class="info-table">
            <tr>
                <td class="label">Observaciones:</td>
                <td class="value">' . nl2br(htmlspecialchars($r['observaciones'])) . '</td>
            </tr>
            <tr>
                <td class="label">Sugerencias:</td>
                <td class="value">' . nl2br(htmlspecialchars($r['sugerencias'])) . '</td>
            </tr>
        </table>
    </div>';


$html .= '
    <div class="signature-section">';
if ($r['prioridad'] !== 'NIVEL 3') {
    $html .= '
        <div class="signature-box">
            <p style="border-top: none; font-weight: bold; margin-bottom: 5px;">Firma del Técnico</p>';

    if (!empty($firma_tecnico_b64)) {
        $html .= '<img src="' . $firma_tecnico_b64 . '" alt="Firma Técnico">';
    } else {
        $html .= '<div style="border: 1px dashed #ccc; padding: 40px; color: #999;">Sin firma</div>';
    }

    $html .= '
            <p>' . htmlspecialchars($r['tecnico_asignado']) . '</p>
        </div>
        <div class="signature-box">
            <p style="border-top: none; font-weight: bold; margin-bottom: 5px;">Firma del Cliente</p>';

    if (!empty($firma_cliente_b64)) {
        $html .= '<img src="' . $firma_cliente_b64 . '" alt="Firma Cliente">';
    } else {
        $html .= '<div style="border: 1px dashed #ccc; padding: 40px; color: #999;">Sin firma</div>';
    }

    $html .= '
            <p>' . htmlspecialchars($r['nombre_completo']) . '</p>
        </div>';
} else {
    $html .= '
        <div style="text-align: center; color: #666; font-style: italic; margin-top: 20px; border-top: 1px solid #eee; padding-top: 10px;">
            Este documento representa un reporte de falla masiva de nivel de infraestructura. No requiere firma de cliente final.
        </div>';
}
$html .= '
    </div>

    <div class="footer">
        <p>Wireless Supply, C.A. | Sistema de Gestión Técnica | Generado: ' . date('d/m/Y H:i') . '</p>
    </div>
</body>
</html>';

// Generar PDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Helvetica');
$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream("Reporte_Tecnico_" . $r['id_soporte'] . ".pdf", ["Attachment" => false]);
exit(0);
?>