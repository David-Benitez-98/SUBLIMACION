<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/ciudad/ciudad.php');

header('Content-Type: application/json; charset=utf-8');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

$sql = "SELECT id_ciudad, nombre_ciudad, id_departamento FROM ciudad WHERE id_ciudad = :id";
$st = $conexion->prepare($sql);
$st->execute(['id' => $id]);
$ciudad = $st->fetch(PDO::FETCH_ASSOC);
if (!$ciudad) {
    echo json_encode(['error' => 'Ciudad no encontrada']);
    exit;
}
echo json_encode($ciudad);