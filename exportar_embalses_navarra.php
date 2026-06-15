<?php
// exportar_embalses_navarra.php
require 'conexion.php';

$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 1;
$fecha = date('Y-m-d', strtotime('+' . ($offset - 1) . ' day'));
$baseUrl = getenv('ICONS_BASE_URL') ?: 'iconos/iconos_diario_navarra/';

// Obtener los datos más recientes de los embalses
$stmt = $pdo->prepare("SELECT nombre, volumen_actual, capacidad_total, porcentaje FROM embalses WHERE fecha = ?");
$stmt->execute([$fecha]);
$datos = $stmt->fetchAll();

// Si no hay datos de hoy, probar con el último día disponible
if (empty($datos)) {
    $stmt = $pdo->query("SELECT nombre, volumen_actual, capacidad_total, porcentaje FROM embalses ORDER BY fecha DESC LIMIT 7");
    $datos = $stmt->fetchAll();
}

$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;

$root = $xml->createElement('embalses');
$xml->appendChild($root);

foreach ($datos as $d) {
    $embalseNode = $xml->createElement('embalse');
    
    // Nombre con CDATA
    $nombreNode = $xml->createElement('nombre');
    $nombreNode->appendChild($xml->createCDATASection($d['nombre']));
    $embalseNode->appendChild($nombreNode);

    $pNode = $xml->createElement('porcentaje');
    $pNode->appendChild($xml->createTextNode(str_replace('.', ',', $d['porcentaje'])));
    $embalseNode->appendChild($pNode);

    // Icono según porcentaje: los archivos se llaman 0.jpg, 1.jpg ... 100.jpg
    $pct_icon = max(0, min(100, (int) round((float) $d['porcentaje'])));
    $iconoNode = $xml->createElement('icono');
    $iconoNode->appendChild($xml->createTextNode($baseUrl . $pct_icon . '.jpg'));
    $embalseNode->appendChild($iconoNode);

    $root->appendChild($embalseNode);
}

$xmlPath = __DIR__ . '/embalses_navarra.xml';
$xml->save($xmlPath);

// Configurar cabeceras para descarga
header('Content-Type: application/xml; charset=utf-8');
echo $xml->saveXML();
exit;
?>
