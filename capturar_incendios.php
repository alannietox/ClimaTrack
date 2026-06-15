<?php
// capturar_incendios.php
header('Content-Type: text/plain; charset=utf-8');

$archivo_cache = __DIR__ . "/cache/cache_incendios.txt";
$tiempo_espera = 1800; // 30 minutos

if (file_exists($archivo_cache)) {
    $ultima_captura = (int)file_get_contents($archivo_cache);
    if ((time() - $ultima_captura) < $tiempo_espera) {
        echo "Saltando captura Incendios: Datos recientes (menos de 30 min).\n";
        exit;
    }
}


$url = "https://www.112asturias.es/indice-incendios-asturias";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64)");
$htmlContent = curl_exec($ch);
curl_close($ch);

if (!$htmlContent) {
    die("Error al obtener la web de 112asturias.\n");
}

libxml_use_internal_errors(true);
$dom = new DOMDocument();
// Load HTML ensuring UTF-8
$dom->loadHTML('<?xml encoding="UTF-8">' . $htmlContent);
libxml_clear_errors();

$xpath = new DOMXPath($dom);
// Targeting the specific list
$nodes = $xpath->query('//ul[contains(@class, "lista_indices_concejos")]/li');

$concejos = [];

foreach ($nodes as $node) {
    // The span contains the index, e.g. <span class="indice_extremo">5</span> " PEÑAMELLERA BAJA"
    // Get all span elements within this li
    $spanNodes = $xpath->query('.//span[contains(@class, "indice_")]', $node);
    
    if ($spanNodes->length > 0) {
        $span = $spanNodes->item(0);
        $indice = trim($span->nodeValue);
        
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
        
        // Ensure name is clean, extracting text after the span or simply from li by removing the span's content
        $liText = trim($node->textContent);
        // Replace the index number to get just the name, assuming the index is at the beginning
        $nombre = trim(preg_replace('/^' . preg_quote($indice, '/') . '/', '', ltrim($liText)));
        
        if (!empty($nombre)) {
            $concejos[] = [
                'nombre' => $nombre,
                'indice' => $indice,
                'tipo'   => $tipo,
                'icon_num' => $icon_num
            ];
        }
    }
}

if (empty($concejos)) {
    die("No se encontraron concejos. Verifica la estructura del HTML.\n");
}

// Generar XML
$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;

$root = $xml->createElement('incendios');
$xml->appendChild($root);

foreach ($concejos as $c) {
    $concejoNode = $xml->createElement('concejo');
    $concejoNode->setAttribute('nombre', $c['nombre']);
    $concejoNode->setAttribute('indice', $c['indice']);
    $concejoNode->setAttribute('tipo_riesgo', $c['tipo']);
    $concejoNode->setAttribute('icono', 'incendio' . $c['icon_num'] . '.eps');
    $root->appendChild($concejoNode);
}

$xmlPath = __DIR__ . '/incendios_asturias.xml';
$xml->save($xmlPath);

echo "Archivo XML guardado exitosamente en: " . $xmlPath . "\n";
file_put_contents($archivo_cache, time());
echo "Total de concejos extraídos: " . count($concejos) . "\n";
?>
