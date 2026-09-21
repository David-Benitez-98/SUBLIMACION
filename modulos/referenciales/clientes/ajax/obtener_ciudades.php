<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php'; // solo pedimos estar logueado
require_once RUTA_PROYECTO . '/config/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$idDepartamento = intval($_GET['id_departamento'] ?? 0);

if ($idDepartamento <= 0) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT id_ciudad AS id, nombre_ciudad AS nombre
        FROM ciudad
        WHERE id_departamento = :id
        ORDER BY nombre_ciudad ASC";
$st = $conexion->prepare($sql);
$st->execute(['id' => $idDepartamento]);

echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));