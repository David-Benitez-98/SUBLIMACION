<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/departamento/departamento.php');

header('Content-Type: application/json; charset=utf-8');

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['msg' => 'ERROR', 'type' => 'error', 'texto' => 'ID inválido']);
    exit;
}

try {
    $st = $conexion->prepare("UPDATE departamento SET activo = NOT activo WHERE id_departamento = :id RETURNING activo, nombre_departamento");
    $st->execute(['id' => $id]);
    $resultado = $st->fetch(PDO::FETCH_ASSOC);
    if (!$resultado) {
        throw new Exception('Departamento no encontrado');
    }
    $nuevoEstado = $resultado['activo'];
    $estado = $nuevoEstado ? 'ACTIVADO' : 'DESACTIVADO';
    $nombre = $resultado['nombre_departamento'];
    echo json_encode(['msg' => 'CORRECTO', 'type' => 'success', 'texto' => "Departamento $nombre $estado"]);
} catch (Exception $e) {
    echo json_encode(['msg' => 'ERROR', 'type' => 'error', 'texto' => $e->getMessage()]);
}