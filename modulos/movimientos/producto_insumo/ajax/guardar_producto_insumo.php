<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/movimientos/producto_insumo/producto_insumo.php');

header('Content-Type: application/json; charset=utf-8');

$idProductoInsumo = isset($_POST['id_producto_insumo']) ? intval($_POST['id_producto_insumo']) : 0;

$idProducto  = intval($_POST['id_producto'] ?? 0);
$idInsumo    = intval($_POST['id_insumo'] ?? 0);
$cantidad    = isset($_POST['cantidad_requerida']) ? (float) $_POST['cantidad_requerida'] : 0;

$json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'No se pudo guardar el insumo del producto'];

try {
    if ($idProducto <= 0) {
        throw new Exception('El producto es obligatorio');
    }
    if ($idInsumo <= 0) {
        throw new Exception('El insumo es obligatorio');
    }
    if ($cantidad <= 0) {
        throw new Exception('La cantidad requerida debe ser mayor a cero');
    }

    $conexion->beginTransaction();

    if ($idProductoInsumo > 0) {
        // UPDATE
        $sql = "UPDATE producto_insumo SET
                    id_insumo = :id_insumo,
                    cantidad_requerida = :cantidad_requerida
                WHERE id_producto_insumo = :id_producto_insumo AND id_producto = :id_producto";
        $st = $conexion->prepare($sql);
        $st->execute([
            'id_insumo'           => $idInsumo,
            'cantidad_requerida'  => $cantidad,
            'id_producto_insumo'  => $idProductoInsumo,
            'id_producto'         => $idProducto
        ]);
        $mensaje = 'Insumo actualizado';
    } else {
        // INSERT
        $sql = "INSERT INTO producto_insumo (id_producto, id_insumo, cantidad_requerida)
                VALUES (:id_producto, :id_insumo, :cantidad_requerida)";
        $st = $conexion->prepare($sql);
        $st->execute([
            'id_producto'         => $idProducto,
            'id_insumo'           => $idInsumo,
            'cantidad_requerida'  => $cantidad
        ]);
        $mensaje = 'Insumo agregado al producto';
    }

    $conexion->commit();

    $json = ['msg' => 'CORRECTO', 'type' => 'success', 'texto' => $mensaje];

} catch (PDOException $e) {
    $conexion->rollBack();
    if ($e->getCode() === '23505') {
        $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'Ese insumo ya está asociado a este producto.'];
    } else {
        $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'Ocurrió un error al guardar el insumo del producto.'];
    }
} catch (Exception $e) {
    $conexion->rollBack();
    $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => $e->getMessage()];
}

echo json_encode($json);