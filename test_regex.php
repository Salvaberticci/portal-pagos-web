<?php
$desc = "Renta y mantenimiento de la red: ZONA KM23 Plan de Internet: Plan Inicio KM23 FTTH 20.00 $ Periodo del 1/Jul./2026 al 31/Jul./2026";
if (preg_match('/Periodo del\s+(\d{1,2})\/([A-Za-z.]+)\/(\d{4})/i', $desc, $m)) {
    $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
    $meses = ['Ene'=>'01','Feb'=>'02','Mar'=>'03','Abr'=>'04','May'=>'05','Jun'=>'06','Jul'=>'07','Ago'=>'08','Sep'=>'09','Oct'=>'10','Nov'=>'11','Dic'=>'12'];
    $monthStr = ucfirst(strtolower(str_replace('.', '', $m[2])));
    if (isset($meses[$monthStr])) {
        $fechaBase = $m[3] . '-' . $meses[$monthStr] . '-' . $day;
        echo "Exito: $fechaBase\n";
    } else {
        echo "Fallo mes: $monthStr\n";
    }
} else {
    echo "Fallo regex\n";
}
