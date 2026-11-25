<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/movimientos/compras/compras.php');

header('Content-Type: application/json; charset=utf-8');

$idCompra  = intval($_POST['id_compra'] ?? 0);
$idUsuario = $_SESSION['id_usuario'] ?? null;

$json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'No se pudo anular la compra'];

try {
    if ($idCompra <= 0) {
        throw new Exception('Compra inválida');
    }

    $conexion->beginTransaction();

    $stCompra = $conexion->prepare("SELECT numero_compra, estado FROM compras WHERE id_compra = :id FOR UPDATE");
    $stCompra->execute(['id' => $idCompra]);
    $compra = $stCompra->fetch(PDO::FETCH_ASSOC);

    if (!$compra) {
        throw new Exception('La compra no existe');
    }
    if ($compra['estado'] === 'ANULADA') {
        throw new Exception('Esta compra ya se encuentra anulada');
    }

    $stLineas = $conexion->prepare("SELECT id_insumo, cantidad FROM detalle_compra WHERE id_compra = :id");
    $stLineas->execute(['id' => $idCompra]);
    $lineas = $stLineas->fetchAll(PDO::FETCH_ASSOC);

    // Mismo patrón que guardar_compra.php: se registra el movimiento y el trigger
    // actualiza insumos.stock_actual solo. Acá el tipo es 'salida' para revertir la entrada.
    $stStockActual = $conexion->prepare("SELECT nombre, stock_actual FROM insumos WHERE id_insumo = :id FOR UPDATE");
    $sqlMovimiento = "INSERT INTO movimientos_stock (id_insumo, id_compra, tipo, cantidad, motivo, fecha, id_usuario, stock_anterior, stock_nuevo)
                      VALUES (:id_insumo, :id_compra, 'salida', :cantidad, :motivo, NOW(), :id_usuario, :stock_anterior, :stock_nuevo)";
    $stMovimiento = $conexion->prepare($sqlMovimiento);

    foreach ($lineas as $linea) {
        $stStockActual->execute(['id' => $linea['id_insumo']]);
        $insumo = $stStockActual->fetch(PDO::FETCH_ASSOC);
        $stockAnterior = (float) $insumo['stock_actual'];
        $stockNuevo = round($stockAnterior - (float) $linea['cantidad'], 3);

        // No se puede revertir una entrada si parte de esa mercadería ya se utilizó
        if ($stockNuevo < 0) {
            throw new Exception(
                'No se puede anular la compra ' . $compra['numero_compra'] . ': el insumo "' . $insumo['nombre'] .
                '" tiene stock ' . number_format($stockAnterior, 3, ',', '.') .
                ' y la compra ingresó ' . number_format((float) $linea['cantidad'], 3, ',', '.') .
                ', por lo que ya se utilizó parte de lo ingresado.'
            );
        }

        $stMovimiento->execute([
            'id_insumo'      => $linea['id_insumo'],
            'id_compra'      => $idCompra,
            'cantidad'       => $linea['cantidad'],
            'motivo'         => 'Anulación compra ' . $compra['numero_compra'],
            'id_usuario'     => $idUsuario,
            'stock_anterior' => $stockAnterior,
            'stock_nuevo'    => $stockNuevo
        ]);
    }

    $stAnular = $conexion->prepare("UPDATE compras SET estado = 'ANULADA' WHERE id_compra = :id");
    $stAnular->execute(['id' => $idCompra]);

    $conexion->commit();

    $json = ['msg' => 'CORRECTO', 'type' => 'success', 'texto' => "Compra {$compra['numero_compra']} anulada correctamente"];

} catch (Exception $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => $e->getMessage()];
}

echo json_encode($json);