<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/movimientos/insumo_proveedor/insumo_proveedor.php');

header('Content-Type: application/json; charset=utf-8');

$idInsumoProveedor = isset($_POST['id_insumo_proveedor']) ? intval($_POST['id_insumo_proveedor']) : 0;

$idInsumo      = intval($_POST['id_insumo'] ?? 0);
$idProveedor   = intval($_POST['id_proveedor'] ?? 0);
$precio        = isset($_POST['precio']) ? (float) $_POST['precio'] : 0;
$calidad       = $_POST['calidad'] ?? '';
$calidad       = $calidad !== '' ? intval($calidad) : null;
$tiempoEntrega = $_POST['tiempo_entrega'] ?? '';
$tiempoEntrega = $tiempoEntrega !== '' ? intval($tiempoEntrega) : null;
$favorito      = isset($_POST['favorito']) && $_POST['favorito'] === '1';

$json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'No se pudo guardar el proveedor del insumo'];

try {
    if ($idInsumo <= 0) {
        throw new Exception('El insumo es obligatorio');
    }
    if ($idProveedor <= 0) {
        throw new Exception('El proveedor es obligatorio');
    }
    if ($precio <= 0) {
        throw new Exception('El precio debe ser mayor a cero');
    }
    if ($tiempoEntrega !== null && $tiempoEntrega < 0) {
        throw new Exception('El tiempo de entrega no puede ser negativo');
    }

    $conexion->beginTransaction();

    // Si este se marca como favorito, se desmarcan los demás favoritos del mismo insumo
    if ($favorito) {
        $stFav = $conexion->prepare("UPDATE insumo_proveedor SET favorito = false WHERE id_insumo = :id_insumo");
        $stFav->execute(['id_insumo' => $idInsumo]);
    }

    if ($idInsumoProveedor > 0) {
        // UPDATE - la fecha_cotizacion se actualiza a hoy cada vez que se guarda un cambio
        $sql = "UPDATE insumo_proveedor SET
                    id_proveedor = :id_proveedor,
                    precio = :precio,
                    calidad = :calidad,
                    tiempo_entrega = :tiempo_entrega,
                    favorito = :favorito,
                    fecha_cotizacion = CURRENT_DATE
                WHERE id_insumo_proveedor = :id_insumo_proveedor AND id_insumo = :id_insumo";
        $st = $conexion->prepare($sql);
        $st->execute([
            'id_proveedor'        => $idProveedor,
            'precio'              => $precio,
            'calidad'             => $calidad,
            'tiempo_entrega'      => $tiempoEntrega,
            'favorito'            => $favorito ? 't' : 'f',
            'id_insumo_proveedor' => $idInsumoProveedor,
            'id_insumo'           => $idInsumo
        ]);
        $mensaje = 'Proveedor actualizado';
    } else {
        // INSERT
        $sql = "INSERT INTO insumo_proveedor (id_insumo, id_proveedor, precio, calidad, tiempo_entrega, favorito)
                VALUES (:id_insumo, :id_proveedor, :precio, :calidad, :tiempo_entrega, :favorito)";
        $st = $conexion->prepare($sql);
        $st->execute([
            'id_insumo'      => $idInsumo,
            'id_proveedor'   => $idProveedor,
            'precio'         => $precio,
            'calidad'        => $calidad,
            'tiempo_entrega' => $tiempoEntrega,
            'favorito'       => $favorito ? 't' : 'f'
        ]);
        $mensaje = 'Proveedor agregado al insumo';
    }

    $conexion->commit();

    $json = ['msg' => 'CORRECTO', 'type' => 'success', 'texto' => $mensaje];

} catch (PDOException $e) {
    $conexion->rollBack();
    if ($e->getCode() === '23505') {
        $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'Ese proveedor ya está asociado a este insumo.'];
    } else {
        $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'Ocurrió un error al guardar el proveedor del insumo.'];
    }
} catch (Exception $e) {
    $conexion->rollBack();
    $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => $e->getMessage()];
}

echo json_encode($json);