<?php
// generar_contrato_pdf.php

// SOLUCIÓN MEMORIA: Aumenta el límite de memoria para evitar fallos de Dompdf
ini_set('memory_limit', '256M');

// Muestra errores de PHP para depuración (DESHABILITADO para evitar corromper PDF)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Verifica que el ID del contrato haya sido pasado por GET
if (!isset($_GET['id_contrato']) || !is_numeric($_GET['id_contrato'])) {
    die("Error: ID de contrato no proporcionado o inválido.");
}

$id_contrato = intval($_GET['id_contrato']);

// Carga el autoloader de Dompdf (Asegúrate que la ruta sea correcta)
require '../../dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Incluye el archivo de conexión a la base de datos
require_once '../conexion.php';

// ----------------------------------------------------------------------
// NUEVO: 1. MANEJO Y CODIFICACIÓN DE IMÁGENES
// ----------------------------------------------------------------------

// NOTA: Las imágenes DEBEN estar en la misma carpeta que este script.
$path_encabezado = '../../images/encabezado_contrato_nuevo.PNG';
$path_pie = '../../images/logo_footer.png';
$path_firma_gerente = '../../images/firma-gerente.png';

// Función para codificar imágenes a Base64 (más limpia y fiable para Dompdf)
function encode_image_to_base64($path, $mime)
{
    if (!file_exists($path)) {
        // En caso de error, devuelve un marcador de posición transparente
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    }
    $data = file_get_contents($path);
    return 'data:' . $mime . ';base64,' . base64_encode($data);
}

// Codificación de las imágenes
$img_encabezado_b64 = encode_image_to_base64($path_encabezado, 'image/png');
$img_pie_b64 = encode_image_to_base64($path_pie, 'image/png');
$img_firma_empresa_pre = encode_image_to_base64($path_firma_gerente, 'image/png');

// ----------------------------------------------------------------------
// 2. CONSULTA DE DATOS DEL CONTRATO, CLIENTE Y PLAN (EXISTENTE)
// ----------------------------------------------------------------------
$sql = "SELECT 
            c.id AS id_contrato,
            c.nombre_completo AS nombre_cliente,
            c.cedula AS cedula_cliente,
            c.fecha_instalacion AS fecha_contrato,
            c.direccion AS direccion_cliente,
            c.telefono, 
            c.correo, 
            pl.nombre_plan AS nombre_plan,
            pl.monto AS costo_mensual,
            pa.nombre_parroquia AS nombre_parroquia,
            mu.nombre_municipio AS nombre_municipio,
            c.firma_cliente,
            c.firma_tecnico,
            c.vendedor_texto
        FROM contratos c
        LEFT JOIN planes pl ON c.id_plan = pl.id_plan
        LEFT JOIN parroquia pa ON c.id_parroquia = pa.id_parroquia
        LEFT JOIN municipio mu ON c.id_municipio = mu.id_municipio
        WHERE c.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_contrato);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows == 0) {
    die("Error: Contrato no encontrado.");
}

$contrato_data = $result->fetch_assoc();
$stmt->close();
$conn->close();


// 3. PREPARACIÓN DE LAS VARIABLES DINÁMICAS (EXISTENTE)

// Formateo de datos
$fecha_contrato_formateada = date('d/m/Y', strtotime($contrato_data['fecha_contrato']));
$costo_mensual_f = number_format($contrato_data['costo_mensual'], 2, ',', '.');


// Función auxiliar para formatear texto con subrayado (EXISTENTE)
function format_field($value)
{
    return '<span class="field-value">' . htmlspecialchars($value) . '</span>';
}

// Array de reemplazo para los marcadores de posición (MODIFICADO)
$placeholders = [
    '{ID_CONTRATO}' => htmlspecialchars((string) ($contrato_data['id_contrato'] ?? '')),
    '{FECHA_CONTRATO_DIA}' => date('d', strtotime($contrato_data['fecha_contrato'] ?? 'now')),
    '{FECHA_CONTRATO_MES}' => date('m', strtotime($contrato_data['fecha_contrato'] ?? 'now')),
    '{FECHA_CONTRATO_ANIO}' => date('Y', strtotime($contrato_data['fecha_contrato'] ?? 'now')),
    '{NOMBRE_CLIENTE}' => htmlspecialchars((string) ($contrato_data['nombre_cliente'] ?? '')),
    '{CEDULA_CLIENTE}' => htmlspecialchars((string) ($contrato_data['cedula_cliente'] ?? '')),
    '{TELEFONO_CLIENTE}' => htmlspecialchars((string) ($contrato_data['telefono'] ?? '')),
    '{EMAIL_CLIENTE}' => htmlspecialchars((string) ($contrato_data['correo'] ?? '')),
    '{DIRECCION_CLIENTE}' => htmlspecialchars((string) ($contrato_data['direccion_cliente'] ?? '')),
    '{NOMBRE_PLAN}' => htmlspecialchars((string) ($contrato_data['nombre_plan'] ?? 'No especificado')),
    '{COSTO_MENSUAL}' => $costo_mensual_f . ' USD',
    '{NOMBRE_PARROQUIA}' => htmlspecialchars((string) ($contrato_data['nombre_parroquia'] ?? 'No especificada')),
    '{NOMBRE_MUNICIPIO}' => htmlspecialchars((string) ($contrato_data['nombre_municipio'] ?? 'No especificado')),
    '{LUGAR_FIRMA}' => 'Escuque, Sabana Libre ', // ⚠️ CAMBIA ESTO
    '{NOMBRE_EMPRESA}' => 'Wireless Supply, C.A.',
    '{NOMBRE_EMPRESA_REPRESENTANTE}' => 'David Alfonzo García Chinchilla',
    '{CEDULA_REPRESENTANTE}' => '10.913.095',
    '{CARGO_REPRESENTANTE}' => 'Gerente',
    '{IMG_FIRMA_EMPRESA_PRE}' => $img_firma_empresa_pre,
    // Placeholders para formato de campo
    '{NOMBRE_CLIENTE_F}' => format_field((string) ($contrato_data['nombre_cliente'] ?? '')),
    '{CEDULA_CLIENTE_F}' => format_field((string) ($contrato_data['cedula_cliente'] ?? '')),
    '{DIRECCION_CLIENTE_F}' => format_field((string) ($contrato_data['direccion_cliente'] ?? '')),
    '{NOMBRE_PARROQUIA_F}' => format_field((string) ($contrato_data['nombre_parroquia'] ?? 'No especificada')),
    '{NOMBRE_MUNICIPIO_F}' => format_field((string) ($contrato_data['nombre_municipio'] ?? 'No especificado')),
    '{TELEFONO_CLIENTE_F}' => format_field((string) ($contrato_data['telefono'] ?? '')),
    '{EMAIL_CLIENTE_F}' => format_field((string) ($contrato_data['correo'] ?? '')),
    '{VENDEDOR_TEXTO_F}' => format_field((string) ($contrato_data['vendedor_texto'] ?? 'No especificado')),

    // NUEVOS PLACEHOLDERS PARA IMÁGENES (Base64)
    '{IMG_ENCABEZADO_B64}' => $img_encabezado_b64,
    '{IMG_PIE_B64}' => $img_pie_b64,
];

// ----------------------------------------------------------------------
// 7. INCLUSIÓN DE FIRMAS (NUEVO)
// ----------------------------------------------------------------------

$firma_cliente_path = '../../uploads/firmas/' . $contrato_data['firma_cliente'];
$firma_tecnico_path = '../../uploads/firmas/' . $contrato_data['firma_tecnico'];

// Placeholder por defecto si no hay firma (espacio en blanco o texto)
$img_firma_cliente = ''; // O una imagen transparente
$img_firma_tecnico = '';

if (!empty($contrato_data['firma_cliente']) && file_exists($firma_cliente_path)) {
    // Reducimos max-height y max-width para evitar solapamientos
    $img_firma_cliente = '<img src="' . encode_image_to_base64($firma_cliente_path, 'image/png') . '" style="max-height: 70px; max-width: 140px; object-fit: contain;">';
} else {
    $img_firma_cliente = '<br><br><br>'; // Espacio para firma manual si falla digital
}

// Firma Empresa (Firma del técnico o representante)
if (!empty($contrato_data['firma_tecnico']) && file_exists($firma_tecnico_path)) {
    // Reducimos max-height y max-width
    $img_firma_tecnico = '<img src="' . encode_image_to_base64($firma_tecnico_path, 'image/png') . '" style="max-height: 70px; max-width: 140px; object-fit: contain;">';
} else {
    // Si no hay firma digital del técnico, usar firma genérica de la empresa si existe imagen, o dejar espacio
    $img_firma_tecnico = '<br><br><br>';
}

// Agregar placeholders de firmas
$placeholders['{FIRMA_CLIENTE_IMG}'] = $img_firma_cliente;
$placeholders['{FIRMA_EMPRESA_IMG}'] = $img_firma_tecnico;


// 4. ESTRUCTURA DEL DOCUMENTO (MODIFICADO)

$html_contrato_plantilla = '
    <div id="main-content">
     <div id="header-image-container">
        <img src="{IMG_ENCABEZADO_B64}" class="header-image">
    </div>
        <h2>CONTRATO DE SERVICIO.</h2>
    
        <p>En la ciudad de <strong>{LUGAR_FIRMA}</strong> a los <strong>{FECHA_CONTRATO_DIA}</strong> días del mes de <strong>{FECHA_CONTRATO_MES}</strong> del <strong>{FECHA_CONTRATO_ANIO}</strong>, comparecen por una parte la empresa <strong>{NOMBRE_EMPRESA}</strong> y por la otra el/la Sr(a). <strong>{NOMBRE_CLIENTE}</strong>, identificado con Cédula de Identidad V-<strong>{CEDULA_CLIENTE}</strong>, domiciliado en <strong>{DIRECCION_CLIENTE}</strong>, quien en adelante se denominará EL CLIENTE.</p>
        
        <h3>Primera: Objeto</h3>
        <p>La empresa se compromete a prestarle a “El Cliente” servicios de redes privadas de telecomunicaciones específicamente internet por fibra óptica.</p>
        
        <h3>Segunda: Tarifa</h3>
        <p>El importe mensual de su facturación debe ser cancelado por adelantado los primeros 05 días de cada mes, de no haber cancelado su cuota el sistema suspenderá el servicio con excepción de la instalación por lo que será un cargo único que estará reflejado en la factura emitida por la empresa y el cual está incluido en el monto de la instalación. El monto de la instalación no es reembolsable por la empresa.</p>
        
        <h3>Tercero: Instalación de Equipos</h3>
        <p>La empresa tendrá un lapso no mayor a 48 horas para realizar la instalación y activación del servicio. La empresa proveerá al Cliente de los dispositivos, modelos, aparatos, necesarios para la adecuada prestación del servicio. Los equipos serán instalados por la Empresa, en la localidad indicada por El Cliente y específicamente en la ubicación acordada por las partes. Una vez que la Empresa haya instalado los equipos y establecido el servicio, El Cliente debe firmar la ficha técnica donde queda conforme todos los datos técnicos. En caso que El Cliente desee modificar el lugar de la instalación debe notificar a la empresa y los costos respectivos a la mudanza o traslado serán por parte del Cliente.</p>
        
        <h3>Cuarta: Canales de Comunicación y Reclamos</h3>
        <p>Para la gestión de servicios, reportes y consultas, la Empresa pone a disposición del Cliente los siguientes canales oficiales de comunicación: Atención Administrativa: 0424-7336576 (Consultas sobre facturación, pagos, cambio de planes, actualización de datos personales y solicitudes de traslados). Soporte Técnico: 0424-7627776 (Reporte de fallas en el servicio, configuración de equipos, interrupciones de conexión y asistencia técnica especializada). ÚNICAS VÍAS OFICIALES PARA GARANTIZAR EL REGISTRO Y SEGUIMIENTO DE SUS SOLICITUDES.</p>
        
        <h3>Quinta: Uso de los Equipos Instalados</h3>
        <p>Una vez instalados los equipos, El Cliente se constituirá en depositario de los mismos, obligándose a usarlos de conformidad con el destino y finalidad para los que fueron creados, siendo responsables de todo daño, deterioro que se les cause, siempre que se deban a causas que le sean imputables.</p>
        
        <h3>Sexta: Horarios de Atención y Soporte Técnico</h3>
        <p>LA EMPRESA establece los siguientes canales y horarios para la atención: Atención Personalizada (Soporte Humano): Lunes a Viernes: De 8:00 AM a 5:00 PM. Sábados: De 8:00 AM a 12:00 PM. Exceptuando días festivos y feriados oficiales. Atención Automatizada (GalaBot): Fuera de los horarios de oficina anteriormente mencionados, así como en días no laborables, el CLIENTE será atendido de forma inmediata por GalaBot, nuestro asistente virtual inteligente. GalaBot está facultado para: Gestionar reportes iniciales de fallas y asignar números de ticket. Proporcionar guías de autoayuda y solución de problemas comunes. En caso de que el requerimiento sea de alta complejidad y no pueda ser resuelto por GalaBot, el caso será escalado automáticamente para ser atendido por el personal técnico en la apertura del siguiente bloque de horario administrativo.</p>
        
        <hr>
        <h3 style="text-align: center; margin-top: 20px;">CONDICIONES COMERCIALES</h3>
        <p><strong>Precio del Servicio:</strong> Los cargos de instalación son iniciales por única vez. Comprenden cancelación de los técnicos por concepto de instalación del servicio, gastos por transporte y combustible, gastos de materiales y equipos, y un primer mes de renta. El cliente debe cancelar los primeros 05 días de cada mes, nuestro sistema administrativo genera una única fecha de corte a final de mes, por consiguiente, El Cliente debe cancelar por prorrateo si su instalación se realiza a mediados del mes.</p>

        <table>
            <thead>
                <tr>
                    <th>Servicio</th>
                    <th>Plan</th>
                    <th>Costo Instalación</th>
                    <th>Costo Mensualidad</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Acceso Internet</td>
                    <td>{NOMBRE_PLAN}</td>
                    <td style="text-align: center;"> Acordado Segun Cliente</td>
                    <td style="text-align: center;">{COSTO_MENSUAL}</td>
                </tr>
            </tbody>
        </table>

        <h3 class="client-data-section" style="text-align: left; margin-top: 30px;">DATOS DEL CLIENTE</h3>
        <p><span class="field-label">Nombre y Apellido:</span> {NOMBRE_CLIENTE_F} 
        <span style="float: right;"><span class="field-label">Cédula de Identidad:</span> V-{CEDULA_CLIENTE_F}</span></p>
        <p><span class="field-label">Dirección de Habitación:</span> {DIRECCION_CLIENTE_F}</p>
        <p><span class="field-label">Parroquia:</span> {NOMBRE_PARROQUIA_F}
        <span style="margin-left: 20px;"><span class="field-label">Municipio:</span> {NOMBRE_MUNICIPIO_F}</span></p>
        <p><span class="field-label">Teléfono:</span> {TELEFONO_CLIENTE_F}
        <span style="float: right;"><span class="field-label">e-mail:</span> {EMAIL_CLIENTE_F}</span></p>
        <p><span class="field-label">Vendedor / Instalador:</span> {VENDEDOR_TEXTO_F}</p>

    <div class="signature-area">
        <table style="width: 100%; border: none; margin-top: 50px;">
            <tr>
                <td style="width: 32%; border: none; text-align: center; vertical-align: top; height: 100px;">
                    <div class="signature-box" style="width: 100%;">
                        <div style="margin-bottom: 5px; height: 80px; display: flex; align-items: flex-end; justify-content: center;">{FIRMA_CLIENTE_IMG}</div>
                        <div class="signature-line">
                            {NOMBRE_CLIENTE}<br>
                        </div>
                        <div class="signature-role">
                            C.I.: {CEDULA_CLIENTE}<br>
                            EL CLIENTE
                        </div>
                    </div>
                </td>
                <td style="width: 2%; border: none;"></td>
                <td style="width: 32%; border: none; text-align: center; vertical-align: top; height: 100px;">
                    <div class="signature-box" style="width: 100%;">
                         <div style="margin-bottom: 5px; height: 80px; display: flex; align-items: flex-end; justify-content: center;">{FIRMA_EMPRESA_IMG}</div>
                        <div class="signature-line">
                            VENDEDOR / INSTALADOR<br>
                        </div>
                        <div class="signature-role">
                            TÉCNICO
                        </div>
                    </div>
                </td>
                <td style="width: 2%; border: none;"></td>
                <td style="width: 32%; border: none; text-align: center; vertical-align: top; height: 100px;">
                    <div class="signature-box" style="width: 100%;">
                        <div style="margin-bottom: 5px; height: 80px; display: flex; align-items: flex-end; justify-content: center;">
                            <img src="{IMG_FIRMA_EMPRESA_PRE}" style="max-height: 100px; max-width: 180px; object-fit: contain;">
                         </div>
                        <div class="signature-line">
                            REPRESENTANTE LEGAL<br>
                            C.I.: {CEDULA_REPRESENTANTE}<br>
                            {NOMBRE_EMPRESA_REPRESENTANTE}
                        </div>
                    </div>
                </td>
            </tr>
            </table>
            
             <br><br>
             <div style="width: 100%; text-align: center; margin-top: 25px;">
                <img src="{IMG_PIE_B64}" style="max-width: 380px; height: auto;">
            </div>
        </div>        
';


// Reemplaza los placeholders en el HTML de la plantilla
$html_final_body = strtr($html_contrato_plantilla, $placeholders);

// 5. CONFIGURACIÓN Y GENERACIÓN DE DOMPDF (EXISTENTE)
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Helvetica');
$dompdf = new Dompdf($options);


// 6. ESTILOS CSS PROFESIONALES (MODIFICADO)
$html_style = '
<style>
    /* 1. ESTILO GLOBAL */
    body {
        font-family: "Helvetica", "Arial", sans-serif;
        font-size: 9.5pt; 
        line-height: 1.4; 
        color: #333; 
        /* NUEVO: Padding superior para dejar espacio al encabezado fijo */
        padding-top: 120px; 
        /* NUEVO: Padding inferior para dejar espacio al pie de página fijo */
        padding-bottom: 70px; 
    }
    
    /* NUEVO: Contenedor para la imagen del encabezado (posición fija) */
    #header-image-container {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 100px; /* Altura del contenedor del encabezado */
        z-index: 1000;
        text-align: center;
        margin: 0; /* Asegura que no haya márgenes que lo muevan */
        padding: 0;
    }
    .header-image {
        width: 100%; /* La imagen debe ocupar todo el ancho del documento */
        max-height: 100px; 
        object-fit: cover;
    }
    
    /* El footer ahora se muestra directamente bajo Vendedor / Instalador, eliminamos posición fija */
    
    /* 2. ENCABEZADOS Y SECCIONES */
    .company-header {
        font-size: 16pt;
        font-weight: bold;
        color: #1C4E80; 
        margin-bottom: 5px;
    }
    h2 {
        text-align: center;
        color: #1C4E80;
        /* Eliminamos el margin-top para que esté justo después del padding del body */
        margin: 0 0 30px 0; 
        font-size: 18pt;
        text-transform: uppercase;
        border-bottom: 2px solid #ddd; 
        padding-bottom: 5px;
    }
    h3 {
        color: #1C4E80;
        margin-top: 30px;
        margin-bottom: 10px;
        font-size: 14pt;
        border-left: 5px solid #1C4E80; 
        padding: 5px 10px;
        background-color: #f7f9fb; 
        font-weight: bold;
    }
    h4 {
        color: #555;
        margin-top: 15px;
        margin-bottom: 5px;
        font-size: 11pt;
    }
    
    /* 3. PÁRRAFOS Y LÍNEAS */
    p {
        text-align: justify;
        margin-bottom: 15px;
    }
    hr {
        border: 0;
        border-top: 1px solid #ccc;
        margin: 40px 0;
    }
    
    /* 4. TABLA DE TARIFAS */
    table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
        border: 1px solid #ddd;
    }
    th, td {
        padding: 10px;
        border: 1px solid #ddd;
    }
    thead th {
        background-color: #eaf1f8; 
        color: #1C4E80;
        font-weight: bold;
        text-align: center;
        text-transform: uppercase;
    }
    
    /* 5. DATOS DEL CLIENTE (FORMATO SUBRAYADO) */
    .field-label {
        font-weight: bold;
        color: #333;
    }
    .field-value {
        border-bottom: 1px solid #888; 
        padding: 0 5px;
        display: inline-block;
        min-width: 150px; 
        font-weight: normal;
    }

    /* 6. FIRMAS */
    .signature-area {
        margin-top: 100px;
        text-align: center;
        width: 100%;
    }
    .signature-box {
        display: inline-block;
        width: 45%;
        padding-top: 10px;
    }
    .signature-line {
        border-top: 1px solid #000;
        padding-top: 5px;
        margin-top: 15px;
        font-weight: bold;
    }
    .signature-role {
        font-size: 10pt;
        color: #555;
        margin-top: 2px;
    }
    
</style>';

// Crea el HTML completo
$html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Contrato N° ' . $contrato_data['id_contrato'] . '</title>' . $html_style . '</head><body>' . $html_final_body . '</body></html>';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();


// ----------------------------------------------------------------------
// 9. SALIDA (STREAM O FILE)
// ----------------------------------------------------------------------

if (isset($_GET['save_to_file']) && $_GET['save_to_file'] == 1) {
    // Modo guardar en servidor (para email)
    $output = $dompdf->output();
    $pdf_dir = '../../uploads/contratos/pdf/';
    if (!file_exists($pdf_dir)) {
        mkdir($pdf_dir, 0755, true);
    }
    $file_name = 'Contrato_' . $id_contrato . '_' . date('YmdHis') . '.pdf';
    $file_path = $pdf_dir . $file_name;

    file_put_contents($file_path, $output);

    // Retornar ruta JSON
    echo json_encode(['status' => 'success', 'path' => $file_path, 'file_name' => $file_name]);
    exit;
} else {
    // Modo descarga normal navegador
    $dompdf->stream("Contrato_N_" . $contrato_data['id_contrato'] . ".pdf", ["Attachment" => false]);
    exit(0);
}