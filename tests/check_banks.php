<?php
$bancos = json_decode(file_get_contents(__DIR__ . '/../paginas/principal/bancos.json'), true);
if (!$bancos) { echo "Error leyendo bancos.json\n"; exit(1); }

echo "=== BANCOS CON HABILITACION API ===\n";
foreach ($bancos as $b) {
    $api = !empty($b['api_config']['habilitada']);
    if ($api) {
        printf("  ✅ %s (id=%s) | Métodos: %s\n", $b['nombre_banco'], $b['id_banco'], implode(', ', $b['metodos_pago']));
    }
}

echo "\n=== TODOS LOS BANCOS ACTIVOS ===\n";
foreach ($bancos as $b) {
    $api = !empty($b['api_config']['habilitada']);
    $activo = $b['activo'] !== false;
    printf("  %s %s (id=%s) | API: %s | Métodos: %s\n",
        $activo ? '✅' : '❌',
        $b['nombre_banco'], $b['id_banco'],
        $api ? 'SI' : 'NO',
        implode(', ', $b['metodos_pago'])
    );
}
