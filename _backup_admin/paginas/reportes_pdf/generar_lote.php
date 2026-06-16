<?php
// generar_lotes.php

// ----------------------------------------------------------------------
// CONFIGURACIÓN CRÍTICA PARA PROCESOS LARGOS 
// ----------------------------------------------------------------------
ini_set('memory_limit', '512M'); 
set_time_limit(0); 

// Muestra errores de PHP para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Carga de librerías y conexión (Asegúrate que las rutas sean correctas)
require '../../dompdf/autoload.inc.php'; 
require_once '../conexion.php'; 

use Dompdf\Dompdf;
use Dompdf\Options;

// ----------------------------------------------------------------------
// FUNCIÓN AUXILIAR DE FORMATO Y IMÁGENES
// ----------------------------------------------------------------------
function format_field($value) {
    // CORRECCIÓN: Usa ?? '' para convertir cualquier valor NULL a una cadena vacía.
    return '<span class="field-value">' . htmlspecialchars($value ?? '') . '</span>';
}

// Función para codificar imágenes a Base64
function encode_image_to_base64($path, $mime) {
    if (!file_exists($path)) {
        error_log("Advertencia: Archivo de imagen no encontrado en la ruta: " . $path);
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='; 
    }
    $data = file_get_contents($path);
    return 'data:' . $mime . ';base64,' . base64_encode($data);
}

// Rutas de imágenes
$path_encabezado = '../../images/encabezado_contrato_nuevo.PNG'; 
$path_pie = '../../images/piedepagina_contrato.PNG';

// Codificación de las imágenes
$img_encabezado_b64 = encode_image_to_base64($path_encabezado, 'image/png');
$img_pie_b64 = encode_image_to_base64($path_pie, 'image/png');


// ----------------------------------------------------------------------
// 2. ESTILOS CSS (SIN POSITION: FIXED)
// ----------------------------------------------------------------------
$html_style = '
<style>
    /* 1. ESTILO GLOBAL */
    body {
        font-family: "Helvetica", "Arial", sans-serif;
        font-size: 9.5pt; 
        line-height: 1.4; 
        color: #333; 
        padding-top: 0; 
        padding-bottom: 0; 
    }
    
    /* Contenedor para la imagen del encabezado (NO FIJO) */
    #header-image-container {
        height: 120px; 
        text-align: center;
        margin: 0 0 20px 0; 
        padding: 0;
    }
    .header-image {
        width: 100%; 
        max-height: 120px; 
        object-fit: cover;
    }
    
    /* Contenedor para la imagen del pie de página (NO FIJO) */
    #footer-image-container {
        height: 200px; 
        text-align: center;
        margin-top: 40px; 
        padding: 0;
    }
    .footer-image {
        width: 100%; 
        max-height: 200px;
        object-fit: cover;
    }
    
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


// 3. ESTRUCTURA DEL DOCUMENTO 
$html_contrato_plantilla = '
     <div id="main-content">
     <div id="header-image-container">
        <img src="{IMG_ENCABEZADO_B64}" class="header-image">
    </div>
        <h2>CONTRATO DE SERVICIO N° {ID_CONTRATO}</h2>
    
        <p>En la ciudad de <strong>{LUGAR_FIRMA}</strong> a los <strong>{FECHA_CONTRATO_DIA}</strong> días del mes de <strong>{FECHA_CONTRATO_MES}</strong> del <strong>{FECHA_CONTRATO_ANIO}</strong>, comparecen por una parte la empresa <strong>{NOMBRE_EMPRESA}</strong> y por la otra el/la Sr(a). <strong>{NOMBRE_CLIENTE}</strong>, identificado con Cédula de Identidad V-<strong>{CEDULA_CLIENTE}</strong>, domiciliado en <strong>{DIRECCION_CLIENTE}</strong>, quien en adelante se denominará EL CLIENTE.</p>
        
        <h3>Primera: Objeto</h3>
        <p>La empresa se compromete a prestarle a “El Cliente” servicios de redes privadas de telecomunicaciones específicamente internet por fibra óptica.</p>
        
        <h3>Segunda: Tarifa</h3>
        <p>El importe mensual de su facturación debe ser cancelado por adelantado los primeros 05 días de cada mes, de no haber cancelado su cuota el sistema suspenderá el servicio con excepción de la instalación por lo que será un cargo único que estará reflejado en la factura emitida por la empresa y el cual está incluido en el monto de la instalación. El monto de la instalación no es reembolsable por la empresa.</p>
        
        <h4>2.1 Planes de financiamiento:</h4>
        <p>La empresa le ofrece planes de financiamiento. El Cliente para optar a los planes de financiamiento se comprometerá a cumplir con las cuotas acordadas y en las fechas fijadas en èste contrato. En caso que El Cliente incumpla cualquiera de las obligaciones de pago contenidas en el contrato, le será suspendido el servicio desde el día inmediato posterior al vencimiento del pago respectivo.</p>
        <p>El Cliente acepta que bastará con que el proveedor le notifique mediante comunicación escrita, correo electrónico, mensaje de texto o WhatsApp enviado a los números indicados en el presente contrato para que se entienda que El Cliente conoce cualquier modificación a las condiciones económicas, incluyendo tarifas, aumentos, promociones.</p>
        <p>El Cliente debe reportar los pagos con su capture al siguiente <b>número telefónico 0424-7336576.</b></p>

        <h3>Tercero: Instalación de Equipos</h3>
        <p>La empresa tendrá un lapso no mayor a 48 horas para realizar la instalación y activación del servicio. La empresa proveerá al Cliente de los dispositivos, modelos, aparatos, necesarios para la adecuada prestación del servicio. Los equipos serán instalados por la Empresa, en la localidad indicada por El Cliente y específicamente en la ubicación acordada por las partes. Una vez que la Empresa haya instalado los equipos y establecido el servicio, El Cliente debe firmar la ficha técnica donde queda conforme todos los datos técnicos. En caso que El Cliente desee modificar el lugar de la instalación debe notificar a la empresa y los costos respectivos a la mudanza o traslado será por parte del Cliente.</p>
        
        <h3>Cuarta: Formulación de Reclamos</h3>
        <p>El cliente debe reportar las averías o fallas en el servicio, a la empresa por el siguiente<b> número telefónico 0424-7627776 (Soporte Técnico).</b> La Empresa garantizará la atención y solución al reporte emitido por El Cliente.</p>
        
        <h3>Quinta: Uso de los Equipos Instalados</h3>
        <p>Una vez instalados los equipos, El Cliente se constituirá en depositario de los mismos, obligándose a usarlos de conformidad con el destino y finalidad para los que fueron creados, siendo responsables de todo daño, deterioro que se les cause, siempre que se deban a causas que le sean imputables.</p>
        
        <hr>
        <h3 style="text-align: center; margin-top: 20px;">CONDICIONES COMERCIALES</h3>
 administrativo genera una única fecha de corte apartir del 06 de cada mes, por consiguiente, El Cliente debe cancelar por prorrateo si su instalación se realiza a mediados del mes.</p>        <p><strong>Precio del Servicio:</strong> Los cargos de instalación son iniciales por única vez. Comprenden cancelación de los técnicos por concepto de instalación del servicio, gastos por transporte y combustible, gastos de materiales y equipos, y un primer mes de renta.</p>
        <p>El cliente debe cancelar los primeros 05 días de cada mes, nuestro sistema

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

        <div class="signature-area">
            <div class="signature-box" style="margin-right: 10%;">
                <div class="signature-line">
                    {NOMBRE_CLIENTE}<br>
                </div>
                <div class="signature-role">
                    C.I.: {CEDULA_CLIENTE}<br>
                    EL CLIENTE
                </div>
            </div>
            <p>
                <br>
            </p>
             </div> <div id="footer-image-container">
                <img src="{IMG_PIE_B64}" class="footer-image">
                </div>
            </div> 
        </div>        
';
// ----------------------------------------------------------------------
// LÓGICA DE PROCESAMIENTO DEL FORMULARIO
// ----------------------------------------------------------------------

$mensaje_resultado = '';
$pdfs_generados = 0;
$pdfs_fallidos = 0;
// NUEVO: Array para almacenar los IDs que fallaron
$ids_fallidos = []; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_inicio'])) {
    
    // 1. CAPTURAR Y VALIDAR RANGOS
    $id_inicio = intval($_POST['id_inicio']);
    $id_fin = intval($_POST['id_fin']);
    
    if ($id_inicio > $id_fin) {
        $mensaje_resultado = '<div class="alert alert-danger">Error: El ID Inicial debe ser menor o igual al ID Final.</div>';
    } else {
        
        // 2. PREPARAR CONSULTA
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
                    mu.nombre_municipio AS nombre_municipio
                FROM contratos c
                INNER JOIN planes pl ON c.id_plan = pl.id_plan
                INNER JOIN parroquia pa ON c.id_parroquia = pa.id_parroquia
                INNER JOIN municipio mu ON c.id_municipio = mu.id_municipio
                WHERE c.id BETWEEN ? AND ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id_inicio, $id_fin);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // 3. VERIFICAR Y CREAR DIRECTORIO DE SALIDA
        $OUTPUT_DIR = 'contratos_lote/';
        if (!is_dir($OUTPUT_DIR)) {
            // Intentar crear el directorio y dar permisos 0777 (escritura)
            if (!mkdir($OUTPUT_DIR, 0777, true)) {
                $mensaje_resultado = '<div class="alert alert-danger">Error FATAL: No se pudo crear el directorio de salida (' . $OUTPUT_DIR . '). Verifique los permisos del servidor.</div>';
                $result->free();
                $stmt->close();
                goto fin_proceso; // Usar goto para salir del bloque si falla la creación
            }
        }

        // 4. BUCLE DE GENERACIÓN DE CONTRATOS
        while ($contrato_data = $result->fetch_assoc()) {
            $id_contrato = $contrato_data['id_contrato'];

            try {
                // REINSTANCIAR DOMPDF AQUÍ para liberar memoria
                $options = new Options();
                $options->set('isHtml5ParserEnabled', true);
                $options->set('defaultFont', 'Helvetica'); 
                $dompdf = new Dompdf($options);
                
                // Formateo de datos
                $fecha_contrato_str = $contrato_data['fecha_contrato'] ?? '1970-01-01'; 
                $fecha_contrato_formateada = date('d/m/Y', strtotime($fecha_contrato_str));
                
                $costo_mensual_val = $contrato_data['costo_mensual'] ?? 0.00;
                $costo_mensual_f = number_format($costo_mensual_val, 2, ',', '.');
                
                // Array de reemplazo para los marcadores de posición
                $placeholders = [
                    // Aplicando ?? '' a todos los campos que puedan ser NULL
                    '{ID_CONTRATO}'         => htmlspecialchars($contrato_data['id_contrato'] ?? ''),
                    '{FECHA_CONTRATO_DIA}'  => date('d', strtotime($fecha_contrato_str)),
                    '{FECHA_CONTRATO_MES}'  => date('m', strtotime($fecha_contrato_str)),
                    '{FECHA_CONTRATO_ANIO}' => date('Y', strtotime($fecha_contrato_str)),
                    '{NOMBRE_CLIENTE}'      => htmlspecialchars($contrato_data['nombre_cliente'] ?? ''),
                    '{CEDULA_CLIENTE}'      => htmlspecialchars($contrato_data['cedula_cliente'] ?? ''),
                    '{TELEFONO_CLIENTE}'    => htmlspecialchars($contrato_data['telefono'] ?? ''),
                    '{EMAIL_CLIENTE}'       => htmlspecialchars($contrato_data['correo'] ?? ''),
                    '{DIRECCION_CLIENTE}'   => htmlspecialchars($contrato_data['direccion_cliente'] ?? ''),
                    '{NOMBRE_PLAN}'         => htmlspecialchars($contrato_data['nombre_plan'] ?? ''),
                    '{COSTO_MENSUAL}'       => $costo_mensual_f . ' USD',
                    '{NOMBRE_PARROQUIA}'    => htmlspecialchars($contrato_data['nombre_parroquia'] ?? ''),
                    '{NOMBRE_MUNICIPIO}'    => htmlspecialchars($contrato_data['nombre_municipio'] ?? ''),
                    '{LUGAR_FIRMA}'         => 'Escuque, Sabana Libre ', 
                    '{NOMBRE_EMPRESA}'      => 'Wireless Supply, C.A.',
                    '{NOMBRE_EMPRESA_REPRESENTANTE}' => 'David Garcia',
                    '{CARGO_REPRESENTANTE}' => 'Gerente',
                    
                    // Placeholders para formato de campo
                    '{NOMBRE_CLIENTE_F}'    => format_field($contrato_data['nombre_cliente']),
                    '{CEDULA_CLIENTE_F}'    => format_field($contrato_data['cedula_cliente']),
                    '{DIRECCION_CLIENTE_F}' => format_field($contrato_data['direccion_cliente']),
                    '{NOMBRE_PARROQUIA_F}'  => format_field($contrato_data['nombre_parroquia']),
                    '{NOMBRE_MUNICIPIO_F}'  => format_field($contrato_data['nombre_municipio']),
                    '{TELEFONO_CLIENTE_F}'  => format_field($contrato_data['telefono']),
                    '{EMAIL_CLIENTE_F}'     => format_field($contrato_data['correo']),
                    
                    // PLACEHOLDERS PARA IMÁGENES
                    '{IMG_ENCABEZADO_B64}' => $img_encabezado_b64,
                    '{IMG_PIE_B64}'        => $img_pie_b64,
                ];

                // 5. ENSAMBLAR HTML Y GENERAR PDF
                $html_final_body = strtr($html_contrato_plantilla, $placeholders);
                $html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Contrato N° ' . $id_contrato . '</title>' . $html_style . '</head><body>' . $html_final_body . '</body></html>';

                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render(); 

                // 6. GUARDAR EL ARCHIVO EN EL SERVIDOR (CORRECCIÓN DE RECURSO BLOQUEADO)
                $output = $dompdf->output();
                $filename = $OUTPUT_DIR . "Contrato_N_" . $id_contrato . ".pdf";
                
                // Usamos fopen/fwrite/fclose para un manejo más explícito del recurso
                $file_handle = @fopen($filename, 'wb');
                
                if ($file_handle !== false) {
                    if (fwrite($file_handle, $output) !== false) {
                        $pdfs_generados++;
                    } else {
                        // Error de escritura
                        $ids_fallidos[] = $id_contrato;
                        $pdfs_fallidos++;
                        error_log("Fallo al escribir contenido en archivo: " . $filename);
                    }
                    // MUY IMPORTANTE: CERRAR EL RECURSO EXPLÍCITAMENTE
                    fclose($file_handle);
                } else {
                    // Error de apertura de archivo (permisos o recurso no disponible)
                    $ids_fallidos[] = $id_contrato;
                    $pdfs_fallidos++;
                    error_log("Fallo al abrir stream para archivo: " . $filename);
                }
                
                // Pausa breve para evitar conflictos de recursos en el siguiente ciclo
                usleep(50000);

            } catch (Exception $e) {
                // NUEVO: Captura el ID del contrato que causó la excepción de Dompdf
                $ids_fallidos[] = $id_contrato;
                $pdfs_fallidos++;
                error_log("Error FATAL al generar PDF para ID $id_contrato: " . $e->getMessage());
            }
        } // Fin del while
        
        $stmt->close();
        
        // 7. MENSAJE FINAL (CORREGIDO PARA MOSTRAR IDs)
        fin_proceso: // Etiqueta para el goto
        if (empty($mensaje_resultado)) { 
            if ($pdfs_generados > 0) {
                $mensaje_resultado = '<div class="alert alert-success mt-4">Proceso terminado: Se generaron ' . $pdfs_generados . ' contratos PDF con éxito.</div>';
            } else {
                $mensaje_resultado = '<div class="alert alert-warning mt-4">Proceso terminado: No se generó ningún contrato. Verifica si hay registros en el rango de IDs.</div>';
            }
    
            if ($pdfs_fallidos > 0) {
                $mensaje_resultado .= '<div class="alert alert-danger">Atención: Hubo ' . $pdfs_fallidos . ' contratos que fallaron al generarse o guardarse.';
                // Muestra la lista de IDs fallidos
                if (!empty($ids_fallidos)) {
                    $mensaje_resultado .= '<p><strong>IDs fallidos:</strong> ' . implode(', ', $ids_fallidos) . '</p>';
                }
                $mensaje_resultado .= 'Por favor, <strong>verifique y corrija los datos</strong> de estos contratos.</div>';
            }
        }


    } // Fin del else (validación de rango)
} // Fin del POST

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generador De Contratos PDF Por Lotes</title>
    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    <link href="../../css/all.min.css" rel="stylesheet">
    <link href="../../css/datatables.min.css" rel="stylesheet">
    <link href="../../css/style3.css" rel="stylesheet">
    <link href="../../css/style4.css" rel="stylesheet">
    <link rel="icon" type="image/jpg" href="../../images/logo.jpg"/>
</head>

<body>
    <div class="container mt-5">
        <header class="register-header text-center mb-4">
            <h1>Generador De Contratos PDF Por Lotes</h1>
            <p>Wireless Supply, C.A.</p>
        </header>  
        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="id_inicio" class="form-label">ID de Contrato Inicial</label>
                    <input type="number" class="form-control" id="id_inicio" name="id_inicio" value="<?php echo isset($_POST['id_inicio']) ? htmlspecialchars($_POST['id_inicio']) : '1'; ?>" required min="1">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="id_fin" class="form-label">ID de Contrato Final (Lote)</label>
                    <input type="number" class="form-control" id="id_fin" name="id_fin" value="<?php echo isset($_POST['id_fin']) ? htmlspecialchars($_POST['id_fin']) : '50'; ?>" required min="1">
                    <small class="form-text text-muted">Recomendación: Usar un lote conveniente (ej. 50-100) y ejecutar varias veces.</small>
                </div>
            </div>
            
            <button type="submit" class="btn btn-success mt-3"><i class="fas fa-file-pdf"></i> Iniciar Generación de Lote</button>
            <br>
            <br>
             <a href="../menu.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Regresar Al Menú
                </a>
        </form>

        <hr class="my-5">

        <?php echo $mensaje_resultado; ?>

        <?php if ($pdfs_generados > 0): ?>
            <p class="mt-4">Todos los archivos se han guardado en la carpeta del servidor: <strong><?php echo $OUTPUT_DIR; ?></strong> (relativa a este script).</p>
        <?php endif; ?>

    </div>
    <script src="../../js/bootstrap.bundle.min.js"></script>
</body>
</html>