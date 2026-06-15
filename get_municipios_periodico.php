<?php
// get_municipios_periodico.php
require 'conexion.php';
require 'periodicos_mapping.php';

header('Content-Type: application/json');

$periodico_nombre = $_GET['periodico'] ?? '';

if (empty($periodico_nombre) || !isset($periodicos[$periodico_nombre])) {
    echo json_encode([]);
    exit;
}

$ids = $periodicos[$periodico_nombre];
if (empty($ids)) {
    echo json_encode([]);
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

$sql = "SELECT Id_Loc_Aemet, Nombre FROM localidades 
        WHERE Activado = 1 AND Id_Loc_Aemet IN ($placeholders) 
        ORDER BY Nombre ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($ids);
$municipios = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($municipios);
?>
