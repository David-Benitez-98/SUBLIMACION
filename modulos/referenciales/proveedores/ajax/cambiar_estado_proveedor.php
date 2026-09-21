<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/proveedores/proveedores.php');

header('Content-Type: application/json; charset=utf-8');

$idProveedor = intval($_POST['id'] ?? 0);
if ($idProveedor <= 0) {
    echo json_encode(['msg' => 'ERROR', 'type' => 'error', 'texto' => 'ID inválido']);
    exit;
}

try {
    $st = $conexion->prepare("UPDATE proveedores SET activo = NOT activo WHERE id_proveedor = :id RETURNING activo, razon_social");
    $st->execute(['id' => $idProveedor]);
    $resultado = $st->fetch(PDO::FETCH_ASSOC);
    if (!$resultado) {
        throw new Exception('Proveedor no encontrado');
    }
    $nuevoEstado = $resultado['activo'];
    $estado = $nuevoEstado ? 'ACTIVADO' : 'DESACTIVADO';
    $nombre = $resultado['razon_social'];
    echo json_encode(['msg' => 'CORRECTO', 'type' => 'success', 'texto' => "Proveedor $nombre $estado"]);
} catch (Exception $e) {
    echo json_encode(['msg' => 'ERROR', 'type' => 'error', 'texto' => $e->getMessage()]);
}