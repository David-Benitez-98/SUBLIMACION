<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/insumos/insumos.php');

header('Content-Type: application/json; charset=utf-8');

$idInsumo = intval($_POST['id'] ?? 0);
if ($idInsumo <= 0) {
    echo json_encode(['msg' => 'ERROR', 'type' => 'error', 'texto' => 'ID inválido']);
    exit;
}

try {
    $st = $conexion->prepare("UPDATE insumos SET activo = NOT activo, updated_at = NOW() WHERE id_insumo = :id RETURNING activo, nombre");
    $st->execute(['id' => $idInsumo]);
    $resultado = $st->fetch(PDO::FETCH_ASSOC);
    if (!$resultado) {
        throw new Exception('Insumo no encontrado');
    }
    $nuevoEstado = $resultado['activo'];
    $estado = $nuevoEstado ? 'ACTIVADO' : 'DESACTIVADO';
    $nombre = $resultado['nombre'];
    echo json_encode(['msg' => 'CORRECTO', 'type' => 'success', 'texto' => "Insumo $nombre $estado"]);
} catch (Exception $e) {
    echo json_encode(['msg' => 'ERROR', 'type' => 'error', 'texto' => $e->getMessage()]);
}