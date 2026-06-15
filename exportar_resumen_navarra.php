<?php
// exportar_resumen_navarra.php
require 'conexion.php';

// Estaciones de Navarra (IDs de AEMET usados en capturar_resumen_navarra.php)
$estaciones = [
    '9263D' => ['nombre' => 'Pamplona', 'ciudad' => 'Pamplona']
];

$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 1;
$fechas = [
    'hoy'  => date('Y-m-d', strtotime(($offset - 1) . ' days')),
    'ayer' => date('Y-m-d', strtotime(($offset - 2) . ' days'))
];

// 1. Obtener datos históricos de la BD
$datos_h = []; // [ciudad][fecha] => row
$placeholders = implode(',', array_fill(0, count($estaciones), '?'));
$stmt = $pdo->prepare("SELECT * FROM datos_clima WHERE id_municipio IN ($placeholders) AND fecha IN (?, ?)");
$stmt->execute(array_merge(array_keys($estaciones), [$fechas['hoy'], $fechas['ayer']]));
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $datos_h[$estaciones[$r['id_municipio']]['ciudad']][$r['fecha']] = $r;
}

// 2. Obtener Mareas de Portus (Pasaia ID 3115 en API de Mareas)
$port_id = '3115';
$api_tides = "https://poem.puertos.es/portus/TidalEnsemble/forecast?fields=Datetime,Source,High,SeaLevel&code={$port_id}&sources=101";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_tides);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
$res_p = @curl_exec($ch);
curl_close($ch);

$mareas_raw = json_decode($res_p, true);

// Offset para Pasaia (de exportar_mareas.php)
$offset_mm = 2896; 

// Generar XML
header('Content-Type: application/xml; charset=utf-8');

$xml = new DOMDocument('1.0', 'utf-8');
$xml->formatOutput = true;

$root = $xml->createElement('resumen_especial_navarra');
$root->setAttribute('fecha_generacion', date('Y-m-d H:i:s'));

// Bloque Estaciones
foreach ($estaciones as $id_est => $info) {
    $ciudad = $info['ciudad'];
    $c_node = $xml->createElement('estacion');
    $c_node->setAttribute('nombre', $info['nombre']);

    foreach ($fechas as $label => $fecha) {
        $d = $datos_h[$ciudad][$fecha] ?? null;
        
        if (empty($d)) {
            $stmt_latest = $pdo->prepare("SELECT * FROM datos_clima WHERE id_municipio = ? AND humedad IS NOT NULL ORDER BY fecha DESC LIMIT 1");
            $stmt_latest->execute([$id_est]);
            $d = $stmt_latest->fetch(PDO::FETCH_ASSOC);
        }

        $f_node = $xml->createElement($label);
        $f_node->setAttribute('fecha', $fecha);

        $f_node->appendChild($xml->createElement('humedad', $d['humedad'] ?? '--'));
        $f_node->appendChild($xml->createElement('presion', $d['presion'] ?? '--'));
        $f_node->appendChild($xml->createElement('radiacion', $d['radiacion_solar'] ?? '--'));
        $f_node->appendChild($xml->createElement('racha_viento', $d['racha_viento'] ?? '--'));

        $c_node->appendChild($f_node);
    }
    $root->appendChild($c_node);
}

// Bloque Mareas
$mar_node = $xml->createElement('mareas');
$mar_node->setAttribute('referencia', 'Pasaia');

// Agrupar por fecha
$eventos_por_dia = [];
if (is_array($mareas_raw)) {
    foreach ($mareas_raw as $item) {
        $ts = $item[0];
        $dt = new DateTime("@$ts");
        $dt->setTimezone(new DateTimeZone('Europe/Madrid'));
        $f = $dt->format('Y-m-d');
        
        $altura_real_mm = $item[3] + $offset_mm;
        $eventos_por_dia[$f][] = [
            'hora' => $dt->format('H:i'),
            'tipo' => $item[2] == 1 ? 'pleamar' : 'bajamar',
            'altura' => number_format($altura_real_mm / 1000, 2, ',', '.') . ' m'
        ];
    }
}

$count_days = 0;
foreach ($eventos_por_dia as $fecha => $eventos) {
    if ($fecha !== $fechas['hoy']) continue;
    $d_node = $xml->createElement('dia');
    $d_node->setAttribute('fecha', $fecha);

    foreach ($eventos as $ev) {
        $e_node = $xml->createElement($ev['tipo']);
        $e_node->appendChild($xml->createElement('hora', $ev['hora']));
        $d_node->appendChild($e_node);
    }
    $mar_node->appendChild($d_node);
    $count_days++;
}
$root->appendChild($mar_node);

$xml->appendChild($root);
echo $xml->saveXML();
?>
