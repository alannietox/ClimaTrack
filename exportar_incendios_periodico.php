<?php
require 'conexion.php';
require 'periodicos_mapping.php';

$periodico_name = $_GET['periodico'] ?? '';
$main_ids = [];
if (!empty($periodico_name)) {
    $main_ids = getIdsByPeriodico($periodico_name);
}
if (!empty($_GET['ids'])) {
    $main_ids = explode(',', $_GET['ids']);
}

$baseUrl = getenv('ICONS_BASE_URL') ?: 'iconos/iconos_vocento/';

$url = 'https://www.112asturias.es/indice-incendios-asturias#';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64)");
$html = curl_exec($ch);
curl_close($ch);

if (!$html) {
    die("No se pudo obtener la página del 112");
}

libxml_use_internal_errors(true);
$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
libxml_clear_errors();

$xpath = new DOMXPath($dom);
$nodes = $xpath->query('//ul[contains(@class, "lista_indices_concejos")]/li');

$riesgos = [];
foreach ($nodes as $node) {
    $spanNodes = $xpath->query('.//span[contains(@class, "indice_")]', $node);
    if ($spanNodes->length > 0) {
        $span = $spanNodes->item(0);
        $riesgo = (int)trim($span->nodeValue);
        
        $tipo = 'Desconocido';
        if ($span instanceof DOMElement) {
            $class = $span->getAttribute('class');
            if (strpos($class, 'indice_bajo') !== false)    { $tipo = 'Bajo'; $icon_num = 1; }
            if (strpos($class, 'indice_moderado') !== false){ $tipo = 'Moderado'; $icon_num = 2; }
            if (strpos($class, 'indice_alto') !== false)    { $tipo = 'Alto'; $icon_num = 3; }
            if (strpos($class, 'indice_muy_alto') !== false){ $tipo = 'Muy Alto'; $icon_num = 4; }
            if (strpos($class, 'indice_extremo') !== false) { $tipo = 'Extremo'; $icon_num = 5; }
            if (strpos($class, 'indice_recurrencia') !== false) { $tipo = 'Extremo: Recurrencia'; $icon_num = 6; }
            if (strpos($class, 'indice_contaminacion') !== false) { $tipo = 'Extremo: Contaminación'; $icon_num = 7; }
        }

        $liText = trim($node->textContent);
        $nombre = trim(preg_replace('/^' . preg_quote($riesgo, '/') . '/', '', ltrim($liText)));
        
        if (!empty($nombre)) {
            $riesgos[mb_strtoupper($nombre, 'UTF-8')] = [
                'indice'   => $riesgo,
                'tipo'     => $tipo,
                'icon_num' => $icon_num
            ];
        }
    }
}

function format_tag($name) {
    $unwanted = array('Á'=>'A', 'É'=>'E', 'Í'=>'I', 'Ó'=>'O', 'Ú'=>'U', 'Ñ'=>'N',
                      'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ñ'=>'n', 'Ü'=>'U', 'ü'=>'u');
    $name = strtr($name, $unwanted);
    
    // Capitalize properly without accents
    $words = explode(' ', mb_strtolower($name));
    foreach ($words as &$w) {
        $w = ucfirst($w);
    }
    $title = implode(' ', $words);
    
    return [
        'tag' => str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9_ ]/', '', $title)),
        'id'  => $title
    ];
}

// Generar XML con formato
$xml_output = new DOMDocument('1.0', 'UTF-8');
$xml_output->formatOutput = true;

$root = $xml_output->createElement('indice_de_riesgo_de_incendio');
$xml_output->appendChild($root);

foreach ($riesgos as $nombre => $data) {
    if (!empty($nombre)) {
        $fmt = format_tag($nombre);
        
        $muni_node = $xml_output->createElement($fmt['tag']);
        $muni_node->setAttribute('id', $fmt['id']);
        
        $muni_node->appendChild($xml_output->createElement('indice_de_riesgo', (string)$data['indice']));
        $muni_node->appendChild($xml_output->createElement('tipo_riesgo', $data['tipo']));
        $muni_node->appendChild($xml_output->createElement('icono', $baseUrl . 'incendio' . $data['icon_num'] . '.eps'));
        
        $root->appendChild($muni_node);
    }
}

if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/xml; charset=utf-8');
}

echo $xml_output->saveXML();
?>
