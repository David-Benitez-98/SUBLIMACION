<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/clientes/clientes.php');

header('Content-Type: application/json; charset=utf-8');

$idCliente = intval($_POST['id'] ?? 0);
if ($idCliente <= 0) {
    echo json_encode(['msg' => 'ERROR', 'type' => 'error', 'texto' => 'ID inválido']);
    exit;
}

try {
    // Usamos RETURNING para obtener el nuevo estado y el nombre
    $st = $conexion->prepare("UPDATE clientes SET activo = NOT activo WHERE id_cliente = :id RETURNING activo, nombre, apellido");
    $st->execute(['id' => $idCliente]);
    $resultado = $st->fetch(PDO::FETCH_ASSOC);
    if (!$resultado) {
        throw new Exception('Cliente no encontrado');
    }
    $nuevoEstado = $resultado['activo'];
    $estado = $nuevoEstado ? 'ACTIVADO' : 'DESACTIVADO';
    $nombre = $resultado['nombre'] . ' ' . $resultado['apellido'];
    echo json_encode(['msg' => 'CORRECTO', 'type' => 'success', 'texto' => "Cliente $nombre $estado"]);
} catch (Exception $e) {
    echo json_encode(['msg' => 'ERROR', 'type' => 'error', 'texto' => $e->getMessage()]);
}
