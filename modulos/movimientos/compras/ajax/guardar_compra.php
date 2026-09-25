<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/movimientos/compras/compras.php');

header('Content-Type: application/json; charset=utf-8');

$idProveedor   = intval($_POST['id_proveedor'] ?? 0);
$fechaCompra   = trim($_POST['fecha_compra'] ?? '');
$observaciones = trim($_POST['observaciones'] ?? '');
$detallesJson  = $_POST['detalles'] ?? '[]';
$idUsuario     = $_SESSION['id_usuario'] ?? null;

$json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'No se pudo registrar la compra'];

try {
    if ($idProveedor <= 0) {
        throw new Exception('El proveedor es obligatorio');
    }
    if ($fechaCompra === '') {
        $fechaCompra = date('Y-m-d');
    }

    $detalles = json_decode($detallesJson, true);
    if (!is_array($detalles) || count($detalles) === 0) {
        throw new Exception('La compra debe tener al menos una línea de detalle');
    }

    // Validar y normalizar cada línea (nunca confiar en el subtotal del cliente)
    $lineasValidadas = [];
    $total = 0;
    foreach ($detalles as $linea) {
        $idInsumo = intval($linea['id_insumo'] ?? 0);
        $cantidad = (float) ($linea['cantidad'] ?? 0);
        $precioUnitario = (float) ($linea['precio_unitario'] ?? 0);

        if ($idInsumo <= 0) {
            throw new Exception('Hay una línea con insumo inválido');
        }
        if ($cantidad <= 0) {
            throw new Exception('La cantidad de cada línea debe ser mayor a cero');
        }
        if ($precioUnitario < 0) {
            throw new Exception('El precio unitario no puede ser negativo');
        }

        $subtotal = round($cantidad * $precioUnitario, 2);
        $total += $subtotal;

        $lineasValidadas[] = [
            'id_insumo'       => $idInsumo,
            'cantidad'        => $cantidad,
            'precio_unitario' => $precioUnitario,
            'subtotal'        => $subtotal,
        ];
    }
    $total = round($total, 2);

    $conexion->beginTransaction();

    // 1) Insertar encabezado de compra (numero_compra provisorio, se corrige abajo)
    $numeroProvisorio = 'TEMP-' . uniqid();
    $sqlCompra = "INSERT INTO compras (id_proveedor, id_usuario, numero_compra, fecha_compra, total, estado, observaciones)
                  VALUES (:id_proveedor, :id_usuario, :numero_compra, :fecha_compra, :total, 'REGISTRADA', :observaciones)
                  RETURNING id_compra";
    $stCompra = $conexion->prepare($sqlCompra);
    $stCompra->execute([
        'id_proveedor'  => $idProveedor,
        'id_usuario'    => $idUsuario,
        'numero_compra' => $numeroProvisorio,
        'fecha_compra'  => $fechaCompra,
        'total'         => $total,
        'observaciones' => $observaciones ?: null
    ]);
    $idCompra = $stCompra->fetchColumn();

    // 2) Asignar el número definitivo, legible, basado en el ID
    $numeroCompra = 'CMP-' . str_pad($idCompra, 6, '0', STR_PAD_LEFT);
    $stNumero = $conexion->prepare("UPDATE compras SET numero_compra = :numero WHERE id_compra = :id");
    $stNumero->execute(['numero' => $numeroCompra, 'id' => $idCompra]);

    // 3) Insertar cada línea de detalle
    $sqlDetalle = "INSERT INTO detalle_compra (id_compra, id_insumo, cantidad, precio_unitario, subtotal)
                   VALUES (:id_compra, :id_insumo, :cantidad, :precio_unitario, :subtotal)";
    $stDetalle = $conexion->prepare($sqlDetalle);

    // 4) Por cada línea, registrar el movimiento de stock (el trigger actualiza insumos.stock_actual solo)
    $stStockActual = $conexion->prepare("SELECT stock_actual FROM insumos WHERE id_insumo = :id FOR UPDATE");
    $sqlMovimiento = "INSERT INTO movimientos_stock (id_insumo, id_compra, tipo, cantidad, motivo, fecha, id_usuario, stock_anterior, stock_nuevo)
                      VALUES (:id_insumo, :id_compra, 'entrada', :cantidad, :motivo, NOW(), :id_usuario, :stock_anterior, :stock_nuevo)";
    $stMovimiento = $conexion->prepare($sqlMovimiento);

    foreach ($lineasValidadas as $linea) {
        $stDetalle->execute([
            'id_compra'       => $idCompra,
            'id_insumo'       => $linea['id_insumo'],
            'cantidad'        => $linea['cantidad'],
            'precio_unitario' => $linea['precio_unitario'],
            'subtotal'        => $linea['subtotal']
        ]);

        $stStockActual->execute(['id' => $linea['id_insumo']]);
        $stockAnterior = (float) $stStockActual->fetchColumn();
        $stockNuevo = $stockAnterior + $linea['cantidad'];

        $stMovimiento->execute([
            'id_insumo'      => $linea['id_insumo'],
            'id_compra'      => $idCompra,
            'cantidad'       => $linea['cantidad'],
            'motivo'         => 'Compra ' . $numeroCompra,
            'id_usuario'     => $idUsuario,
            'stock_anterior' => $stockAnterior,
            'stock_nuevo'    => $stockNuevo
        ]);
    }

    $conexion->commit();

    $json = ['msg' => 'CORRECTO', 'type' => 'success', 'texto' => "Compra $numeroCompra registrada correctamente"];

} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    if ($e->getCode() === '23505') {
        $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'Ya existe una compra con ese número.'];
    } else {
        $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'Ocurrió un error al registrar la compra.'];
    }
} catch (Exception $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => $e->getMessage()];
}

echo json_encode($json);