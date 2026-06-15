<?php
// exportar_resumen_especial.php
require 'conexion.php';

function traducir_viento($dir) {
    if (!$dir) return 'Var.';
    $mapeo = [
        'N'   => 'N',
        'S'   => 'S',
        'E'   => 'E',
        'W'   => 'O',
        'NE'  => 'NE',
        'NW'  => 'NO',
        'SE'  => 'SE',
        'SW'  => 'SO',
        'NNE' => 'NNE',
        'NNW' => 'NNO',
        'SSE' => 'SSE',
        'SSW' => 'SSO',
        'ENE' => 'ENE',
        'ESE' => 'ESE',
        'WNW' => 'ONO',
        'WSW' => 'OSO',
        'Calm' => 'Calma',
    ];
    return $mapeo[strtoupper($dir)] ?? $dir;
}

/**
 * Sanitiza el nombre de un municipio para convertirlo en una etiqueta XML válida.
 */
function sanitizar_tag_xml($nombre) {
    // Reemplazar espacios, barras, guiones, comas y paréntesis por guiones bajos
    $nombre_valido = preg_replace('/[ \/(),.-]+/', '_', $nombre);
    // Eliminar caracteres no permitidos en XML (letras Unicode, números y guiones bajos son válidos)
    $nombre_valido = preg_replace('/[^\p{L}\p{N}_]/u', '', $nombre_valido);
    // Eliminar guiones bajos al principio o final
    $nombre_valido = trim($nombre_valido, '_');
    // En XML las etiquetas no pueden empezar por número, si es así anteponer un guion bajo
    if (preg_match('/^[0-9]/', $nombre_valido)) {
        $nombre_valido = '_' . $nombre_valido;
    }
    return !empty($nombre_valido) ? $nombre_valido : 'municipio';
}


if (php_sapi_name() === 'cli') {
    $periodico = $argv[1] ?? null;
    $fecha = $argv[2] ?? date('Y-m-d');
} else {
    $periodico = $_GET['periodico'] ?? null;
    $fecha = $_GET['fecha'] ?? date('Y-m-d');
}
if (!$periodico) die("Debe especificar un periódico.");

// Configuración de municipios por periódico
// 'municipios' => array de id_municipio en orden de aparición en la ficha
// 'show_presion' => mostrar presión n/mar
// 'show_temp_agua' => mostrar temperatura del agua del mar
// 'norte_format' => mostrar temperatura como "Min / Max" (sin presión)
// 'temp_agua_ids' => solo estos municipios muestran temp_agua (si es array; true = todos)
$config = [
    'diario_el_sur_malaga' => [
        'municipios'      => ['29067', '29069', '29015', '29094', '29084'],
        'show_presion'    => true,
        'show_temp_agua'  => true,
        'temp_agua_ids'   => ['29067', '29069', '29094'], // Málaga, Marbella, Vélez-Málaga
        'norte_format'    => false,
    ],
    'norte_de_castilla' => [
        'municipios'      => ['05019', '09059', '24089', '34120', '37274', '40194', '42173', '47186', '49275'],
        'show_presion'    => false,
        'show_temp_agua'  => false,
        'norte_format'    => true,
    ],
    'las_provincias' => [
        'municipios'      => ['46250', '12040', '03014'],
        'show_presion'    => true,
        'show_temp_agua'  => true,
        'temp_agua_ids'   => ['46250', '12040', '03014'], // Todos muestran agua
        'norte_format'    => false,
    ],
    'el_ideal' => [
        'municipios'      => ['04013', '18087', '23050', '18140'],
        'show_presion'    => true,
        'show_temp_agua'  => true,
        'temp_agua_ids'   => ['04013', '18140'], // Almería, Motril
        'norte_format'    => false,
    ],
    'hoy' => [
        'municipios'      => ['06015', '10037', '06083', '10148'],
        'show_presion'    => true,
        'show_temp_agua'  => false,
        'norte_format'    => false,
    ],
    'laverdad_murcia' => [
        'municipios'      => ['30030', '30016', '03014', '03065'],
        'show_presion'    => true,
        'show_temp_agua'  => true,
        'temp_agua_ids'   => ['30016', '03014', '03065'], // Cartagena, Alicante, Elche
        'norte_format'    => false,
    ],
    'la_rioja_la_rioja' => [
        'municipios'      => ['26089', '26102', '26011', '26071'],
        'show_presion'    => true,
        'show_temp_agua'  => false,
        'norte_format'    => false,
    ],
];

if (!isset($config[$periodico])) {
    die("Este periódico no tiene un resumen especial configurado.");
}

$cfg        = $config[$periodico];
$ids        = $cfg['municipios'];
$fecha_hoy  = $fecha;

// Obtener todos los registros del periódico
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare(
    "SELECT * FROM datos_clima WHERE id_municipio IN ($placeholders) AND fecha = ? ORDER BY FIELD(id_municipio, $placeholders)"
);
$stmt->execute(array_merge($ids, [$fecha_hoy], $ids));
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Indexar por id_municipio
$datos_por_id = [];
foreach ($rows as $row) {
    $datos_por_id[$row['id_municipio']] = $row;
}

// Generar XML
header('Content-Type: application/xml; charset=utf-8');
$xml = new DOMDocument('1.0', 'utf-8');
$xml->formatOutput = true;

$root = $xml->createElement('resumen_clima');
$root->setAttribute('periodico', $periodico);
$root->setAttribute('fecha', $fecha_hoy);

foreach ($ids as $id_mun) {
    $d = $datos_por_id[$id_mun] ?? null;

    $tag_name = sanitizar_tag_xml($d ? $d['nombre_municipio'] : $id_mun);
    $mun_el = $xml->createElement($tag_name);
    $mun_el->setAttribute('id', $id_mun);
    $mun_el->setAttribute('nombre', $d ? $d['nombre_municipio'] : $id_mun);

    if (!$d) {
        $mun_el->setAttribute('sin_datos', 'true');
        $root->appendChild($mun_el);
        continue;
    }

    // Temperatura
    if ($cfg['norte_format']) {
        $mun_el->appendChild($xml->createElement('temperatura',
            ($d['temp_min'] ?? 'N/A') . '° / ' . ($d['temp_max'] ?? 'N/A') . '°'));
    } else {
        $mun_el->appendChild($xml->createElement('temp_max', ($d['temp_max'] ?? 'N/A') . '°'));
        $mun_el->appendChild($xml->createElement('temp_min', ($d['temp_min'] ?? 'N/A') . '°'));
    }

    // Humedad, Presión, Viento y Temperatura del agua (con fallback para días futuros)
    $humedad_val   = $d['humedad'] ?? null;
    $presion_val   = $d['presion'] ?? null;
    $viento_vel    = $d['viento_vel'] ?? null;
    $viento_dir    = $d['viento_dir'] ?? null;
    $temp_agua_val = $d['temp_agua'] ?? null;

    if ($humedad_val === null || $humedad_val === 'N/D' || $humedad_val === '--') {
        $stmt_h = $pdo->prepare("SELECT humedad FROM datos_clima WHERE id_municipio = ? AND humedad IS NOT NULL AND humedad != 'N/D' AND humedad != '--' ORDER BY fecha DESC LIMIT 1");
        $stmt_h->execute([$id_mun]);
        $row_h = $stmt_h->fetch(PDO::FETCH_ASSOC);
        if ($row_h) $humedad_val = $row_h['humedad'];
    }

    if ($presion_val === null || $presion_val === 'N/D' || $presion_val === '--') {
        $stmt_p = $pdo->prepare("SELECT presion FROM datos_clima WHERE id_municipio = ? AND presion IS NOT NULL AND presion != 'N/D' AND presion != '--' ORDER BY fecha DESC LIMIT 1");
        $stmt_p->execute([$id_mun]);
        $row_p = $stmt_p->fetch(PDO::FETCH_ASSOC);
        if ($row_p) $presion_val = $row_p['presion'];
    }

    if ($viento_vel === null || $viento_vel === '--') {
        $stmt_v = $pdo->prepare("SELECT viento_vel, viento_dir FROM datos_clima WHERE id_municipio = ? AND viento_vel IS NOT NULL ORDER BY fecha DESC LIMIT 1");
        $stmt_v->execute([$id_mun]);
        $row_v = $stmt_v->fetch(PDO::FETCH_ASSOC);
        if ($row_v) {
            $viento_vel = $row_v['viento_vel'];
            $viento_dir = $row_v['viento_dir'];
        }
    }

    if ($temp_agua_val === null || $temp_agua_val === 'N/D' || $temp_agua_val === '--') {
        $stmt_a = $pdo->prepare("SELECT temp_agua FROM datos_clima WHERE id_municipio = ? AND temp_agua IS NOT NULL AND temp_agua != 'N/D' AND temp_agua != '--' ORDER BY fecha DESC LIMIT 1");
        $stmt_a->execute([$id_mun]);
        $row_a = $stmt_a->fetch(PDO::FETCH_ASSOC);
        if ($row_a) $temp_agua_val = $row_a['temp_agua'];
    }

    $mun_el->appendChild($xml->createElement('humedad', ($humedad_val ?? '--') . '%'));

    // Precipitación
    $mun_el->appendChild($xml->createElement('precipitacion', ($d['precipitacion'] ?? 0) . ' l/m²'));

    // Viento
    $viento_texto = traducir_viento($viento_dir ?? 'Var.') . ' ' . ($viento_vel ?? '0') . ' km/h';
    $mun_el->appendChild($xml->createElement('viento', $viento_texto));

    // Presión n/mar
    if ($cfg['show_presion']) {
        $mun_el->appendChild($xml->createElement('presion', ($presion_val ?? '--') . ' mb'));
    }

    // Temperatura del agua
    if ($cfg['show_temp_agua']) {
        $tiene_mar = true;
        if (isset($cfg['temp_agua_ids'])) {
            $tiene_mar = in_array($id_mun, $cfg['temp_agua_ids']);
        }
        
        $has_data = ($temp_agua_val !== null && $temp_agua_val !== '--' && $temp_agua_val !== 'N/D');

        if ($periodico === 'laverdad_murcia') {
            if ($tiene_mar && $has_data) {
                $mun_el->appendChild($xml->createElement('temp_agua', $temp_agua_val . '°'));
            }
        } else {
            $valor_agua = ($tiene_mar && $has_data) ? $temp_agua_val . '°' : '--';
            $mun_el->appendChild($xml->createElement('temp_agua', $valor_agua));
        }
    }

    $root->appendChild($mun_el);
}

$xml->appendChild($root);
echo $xml->saveXML();
?>
