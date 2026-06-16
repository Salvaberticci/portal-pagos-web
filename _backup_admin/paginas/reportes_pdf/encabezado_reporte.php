<?php
// Archivo: encabezado_reporte.php

/**
 * Genera el HTML del encabezado de la empresa para los reportes PDF.
 * @param string $titulo El título específico del reporte (ej: 'Reporte de Clientes', 'Reporte de Cobranza').
 * @return string El código HTML del encabezado.
 */
function generar_encabezado_empresa($titulo) {
    // ----------------------------------------------------------------------
    // ¡IMPORTANTE! MODIFICA ESTOS DATOS CON LA INFORMACIÓN REAL DE TU EMPRESA
    // ----------------------------------------------------------------------
    $nombre_empresa = "Wireless Supply, C.A.";
    $direccion_empresa = "Calle Comercio Con Calle Grupo Escolar, Local Edificio Carper, Sector Centro, Mcpio Escuque, Pquia Sabana Libre, Trujillo";
    $telefono_empresa = "+58 (424-7627776) / +58 (424-7336576)";
    $rif_empresa = "J-50735886-0";
    // ----------------------------------------------------------------------
    
    $html = '
    <div style="text-align: center; margin-bottom: 20px; border-bottom: 1px solid #0057adff; padding-bottom: 10px;">
        <h1 style="margin: 0; font-size: 18px; color: #333;">' . htmlspecialchars($nombre_empresa) . '</h1>
        <p style="margin: 2px 0; font-size: 10px;">' . htmlspecialchars($rif_empresa) . '</p>
        <p style="margin: 2px 0; font-size: 10px;">' . htmlspecialchars($direccion_empresa) . '</p>
        <p style="margin: 2px 0; font-size: 10px;">Teléfono: ' . htmlspecialchars($telefono_empresa) . '</p>
        <h2 style="margin-top: 15px; font-size: 14px; color: #555;">' . htmlspecialchars($titulo) . '</h2>
    </div>
    ';
    return $html;
}

?>