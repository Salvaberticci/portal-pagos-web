<?php
// Script para insertar una cuenta por cobrar generada manualmente y registrar el detalle en el historial.
// Este script es el procesador del formulario que se encuentra ahora dentro de gestion_cobros.php (modal).

require_once '../conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
// 1. Obtener y sanitizar datos del formulario (GLOBALES)
    $id_contrato_principal = isset($_POST['id_contrato']) ? intval($_POST['id_contrato']) : 0;
    
    // NOTA: 'monto' viene del campo oculto monto_cobro_hidden, que ya almacena el valor en USD
    // (la función calcCobro() en JS hace la conversión antes de guardar en ese campo).
    // Por tanto, NO se debe volver a convertir este valor.
    $monto_total_declarado = isset($_POST['monto']) ? floatval(str_replace(',', '.', $_POST['monto'])) : 0.0;
    
    $referencia_pago = isset($_POST['referencia_pago']) ? $conn->real_escape_string(trim($_POST['referencia_pago'])) : '';
    $id_banco_pago = isset($_POST['id_banco_pago']) ? intval($_POST['id_banco_pago']) : 0;

    // CAMPOS DE JUSTIFICACIÓN
    $autorizado_por = isset($_POST['autorizado_por']) ? $conn->real_escape_string($_POST['autorizado_por']) : '';
    $justificacion_raw = isset($_POST['justificacion']) ? trim($_POST['justificacion']) : '';
    $justificacion = !empty($justificacion_raw) ? $conn->real_escape_string($justificacion_raw) : '';

    // CAPTURA DE MESES SELECCIONADOS (Frontend)
    $meses_usuario = $_POST['meses_seleccionados_mensualidad'] ?? [];
    $extra_meses_usuario = $_POST['extra_meses_seleccionados'] ?? [];

    // 1.5 Manejo de archivo (Capture)
    $capture_path = '';
    if (isset($_FILES['capture_archivo']) && $_FILES['capture_archivo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/pagos/'; // Ajustamos ruta relativa al script actual
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES['capture_archivo']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid('pago_manual_') . '.' . $file_extension;
        $target_file = $upload_dir . $file_name;

        // Validar tipo de imagen
        $check = getimagesize($_FILES['capture_archivo']['tmp_name']);
        if ($check !== false) {
            if (move_uploaded_file($_FILES['capture_archivo']['tmp_name'], $target_file)) {
                $capture_path = 'uploads/pagos/' . $file_name; // Guardamos ruta relativa al ROOT del sistema
            }
        }
    }

    // 2. Definir fechas y estados por defecto (Es un capture de pago)
    $fecha_pago = isset($_POST['fecha_pago']) && !empty($_POST['fecha_pago']) ? $conn->real_escape_string($_POST['fecha_pago']) : date('Y-m-d');
    $fecha_emision = $fecha_pago; // Emitido en la fecha real del pago histórico
    $fecha_vencimiento = $fecha_pago; 
    $estado = 'PAGADO'; // Requisito: Todo cargo manual/desglose nace pagado.

    // 2.5 METADATOS BIMONETARIOS
    $moneda_enviada = isset($_POST['moneda_enviada']) ? strtolower($_POST['moneda_enviada']) : 'usd';
    $tasa_aplicada = isset($_POST['tasa_aplicada']) ? floatval($_POST['tasa_aplicada']) : 0;
    
    // Función auxiliar de conversión al vuelo
    $tasa_valida = ($tasa_aplicada > 0) ? $tasa_aplicada : 1;
    $convertir_a_usd = function($monto) use ($moneda_enviada, $tasa_valida) {
        return ($moneda_enviada === 'bs') ? round($monto / $tasa_valida, 2) : $monto;
    };
    $convertir_a_bs = function($monto) use ($moneda_enviada, $tasa_valida) {
        return ($moneda_enviada === 'bs') ? $monto : round($monto * $tasa_valida, 2);
    };

    if ($id_contrato_principal > 0 && $monto_total_declarado > 0 && !empty($referencia_pago) && $id_banco_pago > 0) {

        // 3. RECUPERAR DATOS DEL DESGLOSE
        $cargos_a_procesar = []; // Array de sub-cargos a insertar
        $sumatoria_backend = 0;

        // --- Mensualidad Principal ---
        if (isset($_POST['desglose_mensualidad_activado']) && $_POST['desglose_mensualidad_activado'] == '1') {
            $monto_bruto = floatval(str_replace(',', '.', $_POST['monto_mensualidad'] ?? 0));
            $monto_usd = $convertir_a_usd($monto_bruto);
            $monto_bs = $convertir_a_bs($monto_bruto);
            $meses = intval($_POST['meses_mensualidad'] ?? 1);
            if ($monto_usd > 0) {
                $cargos_a_procesar[] = [
                    'id_contrato' => $id_contrato_principal,
                    'monto' => $monto_usd, 
                    'monto_bs' => $monto_bs,
                    'justificacion' => "[MENSUALIDAD] Mensualidad ($meses mes/es)" . (!empty($justificacion) ? " - $justificacion" : "")
                ];
                $sumatoria_backend += $monto_usd;
            }
        }

        // --- Instalación ---
        if (isset($_POST['desglose_instalacion_activado']) && $_POST['desglose_instalacion_activado'] == '1') {
            $monto_bruto = floatval(str_replace(',', '.', $_POST['monto_instalacion'] ?? 0));
            $monto_usd = $convertir_a_usd($monto_bruto);
            $monto_bs = $convertir_a_bs($monto_bruto);
            if ($monto_usd > 0) {
                $cargos_a_procesar[] = [
                    'id_contrato' => $id_contrato_principal,
                    'monto' => $monto_usd,
                    'monto_bs' => $monto_bs,
                    'justificacion' => "[INSTALACION] Instalación" . (!empty($justificacion) ? " - $justificacion" : "")
                ];
                $sumatoria_backend += $monto_usd;
            }
        }

        // --- Prorrateo ---
        if (isset($_POST['desglose_prorrateo_activado']) && $_POST['desglose_prorrateo_activado'] == '1') {
            $monto_bruto = floatval(str_replace(',', '.', $_POST['monto_prorrateo'] ?? 0));
            $monto_usd = $convertir_a_usd($monto_bruto);
            $monto_bs = $convertir_a_bs($monto_bruto);
            if ($monto_usd > 0) {
                $cargos_a_procesar[] = [
                    'id_contrato' => $id_contrato_principal,
                    'monto' => $monto_usd,
                    'monto_bs' => $monto_bs,
                    'justificacion' => "[PRORRATEO] Prorrateo" . (!empty($justificacion) ? " - $justificacion" : "")
                ];
                $sumatoria_backend += $monto_usd;
            }
        }

        // --- Equipo ---
        if (isset($_POST['desglose_equipo_activado']) && $_POST['desglose_equipo_activado'] == '1') {
            $monto_bruto = floatval(str_replace(',', '.', $_POST['monto_equipo'] ?? 0));
            $monto_usd = $convertir_a_usd($monto_bruto);
            $monto_bs = $convertir_a_bs($monto_bruto);
            if ($monto_usd > 0) {
                $cargos_a_procesar[] = [
                    'id_contrato' => $id_contrato_principal,
                    'monto' => $monto_usd,
                    'monto_bs' => $monto_bs,
                    'justificacion' => "[EQUIPOS] Pago de Equipo" . (!empty($justificacion) ? " - $justificacion" : "")
                ];
                $sumatoria_backend += $monto_usd;
            }
        }

        // --- Mensualidad Extra (Otros Contratos) ---
        if (isset($_POST['desglose_extra_activado']) && $_POST['desglose_extra_activado'] == '1') {
            $extras_contratos = $_POST['extra_contrato'] ?? [];
            $extras_montos = $_POST['extra_monto'] ?? [];
            $extras_meses = $_POST['extra_meses'] ?? [];

            for ($i = 0; $i < count($extras_contratos); $i++) {
                $id_c_extra = intval($extras_contratos[$i] ?? 0);
                $monto_bruto = floatval(str_replace(',', '.', $extras_montos[$i] ?? 0));
                $monto_usd = $convertir_a_usd($monto_bruto);
                $monto_bs = $convertir_a_bs($monto_bruto);
                $meses_extra = intval($extras_meses[$i] ?? 1);

                if ($id_c_extra > 0 && $monto_usd > 0) {
                    $cargos_a_procesar[] = [
                        'id_contrato' => $id_c_extra, 
                        'monto' => $monto_usd,
                        'monto_bs' => $monto_bs,
                        'justificacion' => "[EXTRA] Mens. Extra ($meses_extra mes/es) (Pagado en Ref $referencia_pago)" . (!empty($justificacion) ? " - $justificacion" : "")
                    ];
                    $sumatoria_backend += $monto_usd;
                }
            }
        }

        // 4. Verificación de integridad - RELAJADA
        // Ahora permitimos que la sumatoria no sea exacta.
        // Si falta dinero, se registrará una deuda.
        // Si sobra, se notificará pero se procesará.
        
        if (count($cargos_a_procesar) === 0) {
            header("Location: gestion_mensualidades.php?maintenance_done=1&message=" . urlencode("Error: No se seleccionó ningún concepto para cobrar.") . "&class=danger");
            exit();
        }

        if (count($cargos_a_procesar) === 0) {
            header("Location: gestion_mensualidades.php?maintenance_done=1&message=" . urlencode("Error: No se activó ningún concepto en el desglose.") . "&class=danger");
            exit();
        }

        // === GESTIÓN DE MONTOS Y EXCEDENTES ===
        // Si hay un excedente (paga más de lo que debe), preservamos los precios originales 
        // y creamos una fila de "Abono" para el sobrante.
        // Si hay un déficit (paga menos), escalamos proporcionalmente para cubrir parte de cada item.
        
        $surplus = round($monto_total_declarado - $sumatoria_backend, 2);

        if ($surplus > 0) {
            // Caso EXCEDENTE: No escalamos ítems, añadimos fila de Abono/Saldo a Favor
            $cargos_a_procesar[] = [
                'id_contrato' => $id_contrato_principal,
                'monto' => $surplus,
                'monto_bs' => round($surplus * $tasa_valida, 2),
                'justificacion' => "[ABONO] Saldo a Favor (Excedente en Pago)" . (!empty($justificacion) ? " - $justificacion" : "")
            ];
            
            // Añadimos nota de total a cada concepto pero manteniendo su monto original
            foreach ($cargos_a_procesar as &$item) {
                $nota_total = " (Total Operación: $" . number_format($monto_total_declarado, 2) . ")";
                $item['justificacion'] .= $nota_total;
            }
        } elseif ($sumatoria_backend > 0 && $monto_total_declarado > 0 && $surplus < 0) {
            // Caso DÉFICIT: Escalamos proporcionalmente (Lógica anterior)
            $factor_real = $monto_total_declarado / $sumatoria_backend;
            $acumulado_monto_usd = 0;
            $acumulado_monto_bs = 0;
            $total_conceptos = count($cargos_a_procesar);
            $total_esperado_bs = $convertir_a_bs($monto_total_declarado);

            for ($i = 0; $i < $total_conceptos; $i++) {
                if ($i === $total_conceptos - 1) {
                    $cargos_a_procesar[$i]['monto'] = round($monto_total_declarado - $acumulado_monto_usd, 2);
                    $cargos_a_procesar[$i]['monto_bs'] = round($total_esperado_bs - $acumulado_monto_bs, 2);
                } else {
                    $cargos_a_procesar[$i]['monto'] = round($cargos_a_procesar[$i]['monto'] * $factor_real, 2);
                    $cargos_a_procesar[$i]['monto_bs'] = round($cargos_a_procesar[$i]['monto_bs'] * $factor_real, 2);
                    $acumulado_monto_usd += $cargos_a_procesar[$i]['monto'];
                    $acumulado_monto_bs += $cargos_a_procesar[$i]['monto_bs'];
                }
                $nota_total = " (Total Operación: $" . number_format($monto_total_declarado, 2) . ")";
                $cargos_a_procesar[$i]['justificacion'] .= $nota_total;
            }
        } else {
            // Caso Montos Iguales o Fallback: Solo añadimos la nota
            foreach ($cargos_a_procesar as &$item) {
                $nota_total = " (Total Operación: $" . number_format($monto_total_declarado, 2) . ")";
                $item['justificacion'] .= $nota_total;
            }
        }

        $conn->begin_transaction();

        try {
            $registros_exitosos = 0;
            // Generar UUID para el grupo de cobros
            $id_grupo_pago = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );

            // 5. INSERTAR CADA CARGO EN EL BUCLE
            foreach ($cargos_a_procesar as $cargo) {
                $c_id_contrato = $cargo['id_contrato'];
                $c_monto_usd = $cargo['monto'];
                $c_monto_bs = $cargo['monto_bs'];
                $c_justificacion_base = $cargo['justificacion'];
                $is_mensualidad = (strpos($c_justificacion_base, '[MENSUALIDAD]') !== false || strpos($c_justificacion_base, '[EXTRA]') !== false);
                $num_meses = 1;
                
                // Extraer número de meses si es mensualidad o extra para el bucle
                if ($is_mensualidad) {
                    if (preg_match('/\((\d+) mes\/es\)/', $c_justificacion_base, $matches)) {
                        $num_meses = intval($matches[1]);
                    }
                }

                $acumulado_mes_usd = 0;
                $acumulado_mes_bs = 0;

                for ($m = 0; $m < $num_meses; $m++) {
                    // Lógica de residuo para meses
                    if ($m === $num_meses - 1) {
                        $monto_por_mes_usd = round($c_monto_usd - $acumulado_mes_usd, 2);
                        $monto_por_mes_bs = round($c_monto_bs - $acumulado_mes_bs, 2);
                    } else {
                        $monto_por_mes_usd = round($c_monto_usd / $num_meses, 2);
                        $monto_por_mes_bs = round($c_monto_bs / $num_meses, 2);
                        $acumulado_mes_usd += $monto_por_mes_usd;
                        $acumulado_mes_bs += $monto_por_mes_bs;
                    }
                    // Si es mensualidad o extra, intentar obtener el mes del array de usuario
                    $mes_nombre = "";
                    if (strpos($c_justificacion_base, '[MENSUALIDAD]') !== false) {
                        $mes_nombre = $meses_usuario[$m] ?? "";
                    } elseif (strpos($c_justificacion_base, '[EXTRA]') !== false) {
                        // Para extras, los meses vienen en el mismo orden que los contratos extra en el POST
                        // Pero como cada cargo[] es Individual aquí, necesitamos un índice global o mapeo.
                        // Sin embargo, el frontend envía extra_meses_seleccionados[] en orden de aparición.
                        // Usaremos un shift para ir consumiendo si es extra, o simplemente el primero si no hay más.
                        $mes_nombre = array_shift($extra_meses_usuario) ?? "";
                    }

                    // Fallback si no hay mes del usuario
                    if (empty($mes_nombre)) {
                        $target_time = strtotime("+$m month");
                        $mes_nombre = strftime('%B %Y', $target_time);
                        if (!$mes_nombre || strpos($mes_nombre, '%') !== false) {
                            $mes_nombre = date('F Y', $target_time);
                        }
                        // Traducción básica
                        $mes_es = ['January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'];
                        foreach($mes_es as $en => $es) $mes_nombre = str_ireplace($en, $es, $mes_nombre);
                    }

                    if ($is_mensualidad) {
                        $base_text = str_replace(['[MENSUALIDAD] ', '[EXTRA] '], '', $c_justificacion_base);
                        $tag = (strpos($c_justificacion_base, '[EXTRA]') !== false) ? '[EXTRA]' : '[MENSUALIDAD]';
                        
                        // Proyectamos fechas exactas sumando meses a la fecha base ($fecha_pago)
                        $loop_fecha_emision = date('Y-m-d', strtotime("+$m month", strtotime($fecha_pago)));
                        $loop_fecha_vencimiento = date('Y-m-d', strtotime("+" . ($m + 1) . " month", strtotime($fecha_pago)));
                        
                        if ($m > 0) {
                            $loop_justificacion = $conn->real_escape_string("$tag [$mes_nombre] - Adelanto de: " . $base_text);
                        } else {
                            $loop_justificacion = $conn->real_escape_string("$tag [$mes_nombre] - " . $base_text);
                        }
                    } else {
                        // Si es Instalación, Equipo, Prorrateo o Genérico
                        $loop_fecha_emision = $fecha_emision;
                        $loop_fecha_vencimiento = $fecha_vencimiento;
                        $loop_justificacion = $conn->real_escape_string($c_justificacion_base);
                    }

                    $sql_cxc = "INSERT INTO cuentas_por_cobrar (
                        id_contrato, 
                        fecha_emision, 
                        fecha_vencimiento, 
                        monto_total, 
                        monto_total_bs,
                        tasa_bcv,
                        estado,
                        fecha_pago,
                        referencia_pago,
                        id_banco,
                        id_grupo_pago,
                        capture_pago,
                        id_plan_cobrado
                    ) VALUES (
                        '$c_id_contrato', 
                        '$loop_fecha_emision', 
                        '$loop_fecha_vencimiento', 
                        '$monto_por_mes_usd', 
                        '$monto_por_mes_bs',
                        '$tasa_aplicada',
                        '$estado',
                        '$fecha_pago',
                        '$referencia_pago',
                        '$id_banco_pago',
                        '$id_grupo_pago',
                        '$capture_path',
                        (SELECT id_plan FROM contratos WHERE id = '$c_id_contrato' LIMIT 1)
                    )";

                    if ($conn->query($sql_cxc) === TRUE) {
                        $id_cobro_cxc = $conn->insert_id;

                        // INSERTAR HISTORIAL (usando monto POR MES, no el total del cargo)
                        $sql_historial = "INSERT INTO cobros_manuales_historial (
                            id_cobro_cxc, 
                            id_contrato, 
                            autorizado_por, 
                            justificacion, 
                            monto_cargado,
                            monto_cargado_bs,
                            tasa_bcv
                        ) VALUES (
                            '$id_cobro_cxc', 
                            '$c_id_contrato', 
                            '$autorizado_por', 
                            '$loop_justificacion', 
                            '$monto_por_mes_usd',
                            '$monto_por_mes_bs',
                            '$tasa_aplicada'
                        )";

                        if (!$conn->query($sql_historial)) {
                            throw new Exception("Error al registrar historial: " . $conn->error);
                        }

                        $registros_exitosos++;
                    } else {
                        throw new Exception("Error al registrar CxC: " . $conn->error);
                    }
                }
            } // Fin foreach

            // 6. LÓGICA DE CONSUMO DE SALDO A FAVOR Y BALANCE (Umbral de $0.50)
            $monto_credito_aplicado = isset($_POST['monto_credito_aplicado']) ? floatval($_POST['monto_credito_aplicado']) : 0;
            
            // Consumir créditos existentes si se aplicó saldo
            if ($monto_credito_aplicado > 0) {
                // Buscar registros de CREDITO pendientes para este cliente, ordenados por antigüedad
                $sql_get_creditos = "SELECT id, saldo_pendiente, monto_pagado FROM clientes_deudores 
                                     WHERE id_contrato = '$id_contrato_principal' AND tipo_registro = 'CREDITO' 
                                     AND estado = 'PENDIENTE' ORDER BY fecha_registro ASC";
                $res_creditos = $conn->query($sql_get_creditos);
                
                $por_consumir = $monto_credito_aplicado;
                while($row_c = $res_creditos->fetch_assoc()) {
                    if ($por_consumir <= 0) break;
                    
                    $id_reg_c = $row_c['id'];
                    $saldo_c  = floatval($row_c['saldo_pendiente']);
                    $pagado_c = floatval($row_c['monto_pagado']);
                    
                    if ($por_consumir >= $saldo_c) {
                        // Consumir todo este registro
                        $nuevo_pagado = $pagado_c + $saldo_c; // En créditos, monto_pagado suma lo 'abonado' al saldo a favor? 
                        // En realidad, para créditos, podríamos solo bajar el saldo_pendiente.
                        $sql_up_c = "UPDATE clientes_deudores SET saldo_pendiente = 0, estado = 'PAGADO', 
                                     notas = CONCAT(notas, ' | Consumido en Cobro Manual Ref: $referencia_pago') 
                                     WHERE id = '$id_reg_c'";
                        $por_consumir -= $saldo_c;
                    } else {
                        // Consumir parcialmente
                        $nuevo_saldo = $saldo_c - $por_consumir;
                        $sql_up_c = "UPDATE clientes_deudores SET saldo_pendiente = '$nuevo_saldo', 
                                     notas = CONCAT(notas, ' | Parcialmente consumido ($$por_consumir) en Ref: $referencia_pago') 
                                     WHERE id = '$id_reg_c'";
                        $por_consumir = 0;
                    }
                    $conn->query($sql_up_c);
                }
            }

            // El balance final se basa en lo que el input reporta (que ya incluye el crédito si se aplicó)
            // comparado con lo que el backend dice que sumaron los cargos.
            $balance = round($sumatoria_backend - $monto_total_declarado, 2);
            
            if ($balance >= 0.50) {
                // Caso DEUDA (Déficit)
                $txt_credito = ($monto_credito_aplicado > 0) ? " (Crédito aplicado: $$monto_credito_aplicado)" : "";
                $notas_registro = "Generado automáticamente desde Cobro Manual (Ref: $referencia_pago).$txt_credito Justificación: " . $justificacion;
                $sql_registro = "INSERT INTO clientes_deudores (
                    id_contrato, tipo_registro, monto_total, monto_pagado, saldo_pendiente, estado, notas
                ) VALUES (
                    '$id_contrato_principal', 'DEUDA', '$sumatoria_backend', '$monto_total_declarado', '$balance', 'PENDIENTE', '$notas_registro'
                )";
                
                if (!$conn->query($sql_registro)) {
                    throw new Exception("Error al registrar en lista de deudores: " . $conn->error);
                }
            } elseif ($balance < -0.49) { // Solo si el exceso es significativo
                // Caso CRÉDITO (Excedente)
                $monto_nuevo_credito = abs($balance);
                $notas_registro = "Saldo a favor generado desde Cobro Manual (Ref: $referencia_pago).";
                $sql_registro = "INSERT INTO clientes_deudores (
                    id_contrato, tipo_registro, monto_total, monto_pagado, saldo_pendiente, estado, notas
                ) VALUES (
                    '$id_contrato_principal', 'CREDITO', '0', '$monto_nuevo_credito', '$monto_nuevo_credito', 'PENDIENTE', '$notas_registro'
                )";
                
                if (!$conn->query($sql_registro)) {
                    throw new Exception("Error al registrar saldo a favor: " . $conn->error);
                }
            }

            $conn->commit();
            
            $status_msg = "";
            if ($balance >= 0.50) {
                $status_msg = " [SALDO PENDIENTE: $$balance registrado en lista de deudores]";
            } elseif ($balance < 0) {
                $status_msg = " [SALDO A FAVOR: $" . abs($balance) . " registrado con éxito]";
            }

            $message = "Capture procesado con éxito. Se generaron $registros_exitosos cobros (Ref: $referencia_pago) por un total de $$monto_total_declarado.$status_msg";
            $class = "success";

        } catch (Exception $e) {
            $conn->rollback();
            $message = "ERROR Crítico: " . $e->getMessage();
            $class = "danger";
        }
    } else {
        $message = "Error: Faltan datos obligatorios para registrar el cobro manual.";
        $class = "danger";
    }

    // $conn->close();

    // Redirigir siempre a gestion_mensualidades.php para mostrar el mensaje de éxito/error en la lista.
    header("Location: gestion_mensualidades.php?message=" . urlencode($message) . "&class=" . $class);
    // exit();
} else {
    // Si acceden directamente al procesador, los enviamos a la lista
    header("Location: gestion_mensualidades.php");
    exit();
}
?>