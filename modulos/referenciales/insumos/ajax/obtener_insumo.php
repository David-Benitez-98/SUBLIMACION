<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/insumos/insumos.php');

header('Content-Type: application/json; charset=utf-8');

$idInsumo = intval($_GET['id'] ?? 0);
if ($idInsumo <= 0) {
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

$sql = "SELECT id_insumo, codigo, nombre, id_color, id_unidad, stock_actual, stock_minimo, ubicacion
        FROM insumos
        WHERE id_insumo = :id";
$st = $conexion->prepare($sql);
$st->execute(['id' => $idInsumo]);
$insumo = $st->fetch(PDO::FETCH_ASSOC);

if (!$insumo) {
    echo json_encode(['error' => 'Insumo no encontrado']);
    exit;
}

echo json_encode($insumo);