<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/movimientos/producto_insumo/producto_insumo.php');

header('Content-Type: application/json; charset=utf-8');

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['msg' => 'ERROR', 'type' => 'error', 'texto' => 'ID inválido']);
    exit;
}

try {
    $st = $conexion->prepare("DELETE FROM producto_insumo WHERE id_producto_insumo = :id RETURNING id_producto_insumo");
    $st->execute(['id' => $id]);
    $resultado = $st->fetch(PDO::FETCH_ASSOC);

    if (!$resultado) {
        throw new Exception('El insumo del producto no existe o ya fue eliminado');
    }

    echo json_encode(['msg' => 'CORRECTO', 'type' => 'success', 'texto' => 'Insumo eliminado del producto']);
} catch (Exception $e) {
    echo json_encode(['msg' => 'ERROR', 'type' => 'error', 'texto' => $e->getMessage()]);
}