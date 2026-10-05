<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/movimientos/pedidos/pedidos.php');

header('Content-Type: application/json; charset=utf-8');

$idPedido      = intval($_POST['id_pedido'] ?? 0);
$consumosJson  = $_POST['consumos'] ?? '[]';
$idUsuario     = $_SESSION['id_usuario'] ?? null;

// Solo se puede confirmar consumo real a partir de que el pedido entró en producción.
$estadosPermitidos = ['en_produccion', 'listo', 'entregado'];

$json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'No se pudo confirmar el consumo'];

try {
    if ($idPedido <= 0) {
        throw new Exception('Pedido inválido');
    }

    $consumos = json_decode($consumosJson, true);
    if (!is_array($consumos) || count($consumos) === 0) {
        throw new Exception('No hay materiales para confirmar');
    }

    // Validar y normalizar cada línea
    $consumosValidados = [];
    foreach ($consumos as $c) {
        $idInsumo = intval($c['id_insumo'] ?? 0);
        $cantidadUtilizada = (float) ($c['cantidad_utilizada'] ?? 0);

        if ($idInsumo <= 0) {
            throw new Exception('Hay un insumo inválido en la lista de consumo');
        }
        if ($cantidadUtilizada <= 0) {
            throw new Exception('La cantidad utilizada debe ser mayor a cero para cada insumo');
        }

        $consumosValidados[$idInsumo] = $cantidadUtilizada;
    }

    $conexion->beginTransaction();

    $stPedido = $conexion->prepare("SELECT estado, numero_pedido FROM pedidos WHERE id_pedido = :id FOR UPDATE");
    $stPedido->execute(['id' => $idPedido]);
    $pedido = $stPedido->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        throw new Exception('El pedido no existe');
    }
    if (!in_array($pedido['estado'], $estadosPermitidos, true)) {
        throw new Exception('Solo se puede confirmar el consumo cuando el pedido está en producción o más adelante');
    }

    $numeroPedido = $pedido['numero_pedido'];

    // ====== Guardar/actualizar pedido_insumo (consumo real declarado) ======
    $sqlUpsert = "INSERT INTO pedido_insumo (id_pedido, id_insumo, cantidad_utilizada)
                  VALUES (:id_pedido, :id_insumo, :cantidad_utilizada)
                  ON CONFLICT (id_pedido, id_insumo)
                  DO UPDATE SET cantidad_utilizada = EXCLUDED.cantidad_utilizada";
    $stUpsert = $conexion->prepare($sqlUpsert);
    foreach ($consumosValidados as $idInsumo => $cantidadUtilizada) {
        $stUpsert->execute([
            'id_pedido'          => $idPedido,
            'id_insumo'          => $idInsumo,
            'cantidad_utilizada' => $cantidadUtilizada
        ]);
    }

    // ====== Cuánto ya se había descontado ANTES para este pedido (neto) ======
    $consumidoAnterior = [];
    $stAnterior = $conexion->prepare("SELECT id_insumo,
                                              SUM(CASE WHEN tipo = 'salida' THEN cantidad ELSE -cantidad END) AS neto
                                       FROM movimientos_stock
                                       WHERE id_pedido = :id_pedido
                                       GROUP BY id_insumo");
    $stAnterior->execute(['id_pedido' => $idPedido]);
    foreach ($stAnterior->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $consumidoAnterior[$fila['id_insumo']] = (float) $fila['neto'];
    }

    // ====== Calcular la diferencia por insumo, validar stock y registrar el movimiento ======
    $stStockActual = $conexion->prepare("SELECT i.stock_actual, i.nombre, u.nombre AS nombre_unidad
                                          FROM insumos i
                                          LEFT JOIN unidades_medida u ON u.id_unidad = i.id_unidad
                                          WHERE i.id_insumo = :id
                                          FOR UPDATE OF i");
    $sqlMovimiento = "INSERT INTO movimientos_stock (id_insumo, id_pedido, tipo, cantidad, motivo, fecha, id_usuario, stock_anterior, stock_nuevo)
                      VALUES (:id_insumo, :id_pedido, :tipo, :cantidad, :motivo, NOW(), :id_usuario, :stock_anterior, :stock_nuevo)";
    $stMovimiento = $conexion->prepare($sqlMovimiento);

    $formatear = function ($valor) {
        return rtrim(rtrim(number_format((float) $valor, 3, '.', ''), '0'), '.');
    };

    $movimientosPendientes = [];
    $insumosFaltantes = [];

    foreach ($consumosValidados as $idInsumo => $nuevoConsumo) {
        $anterior = $consumidoAnterior[$idInsumo] ?? 0;
        $diferencia = round($nuevoConsumo - $anterior, 3);

        if (abs($diferencia) < 0.0001) {
            continue; // no cambió nada respecto a lo ya confirmado antes
        }

        $tipoMovimiento = $diferencia > 0 ? 'salida' : 'entrada';
        $cantidadMovimiento = abs($diferencia);

        $stStockActual->execute(['id' => $idInsumo]);
        $insumo = $stStockActual->fetch(PDO::FETCH_ASSOC);
        $stockAnterior = (float) $insumo['stock_actual'];

        if ($tipoMovimiento === 'salida' && $stockAnterior < $cantidadMovimiento) {
            $faltante = $cantidadMovimiento - $stockAnterior;
            $insumosFaltantes[] = $insumo['nombre'] . ' (faltan ' . $formatear($faltante) . ' ' . ($insumo['nombre_unidad'] ?? '') . ')';
            continue;
        }

        $stockNuevo = $tipoMovimiento === 'entrada'
            ? $stockAnterior + $cantidadMovimiento
            : $stockAnterior - $cantidadMovimiento;

        $movimientosPendientes[] = [
            'id_insumo'      => $idInsumo,
            'id_pedido'      => $idPedido,
            'tipo'           => $tipoMovimiento,
            'cantidad'       => $cantidadMovimiento,
            'motivo'         => "Consumo confirmado pedido $numeroPedido",
            'id_usuario'     => $idUsuario,
            'stock_anterior' => $stockAnterior,
            'stock_nuevo'    => $stockNuevo
        ];
    }

    if (count($insumosFaltantes) > 0) {
        throw new Exception('No hay stock suficiente para confirmar: ' . implode('; ', $insumosFaltantes) . '.');
    }

    foreach ($movimientosPendientes as $movimiento) {
        $stMovimiento->execute($movimiento);
    }

    $conexion->commit();

    $json = ['msg' => 'CORRECTO', 'type' => 'success', 'texto' => 'Consumo confirmado y stock actualizado correctamente'];

} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    if ($e->getCode() === '23514') {
        $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'Alguna cantidad utilizada no cumple las validaciones de la base.'];
    } else {
        $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'Ocurrió un error al confirmar el consumo.'];
    }
} catch (Exception $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => $e->getMessage()];
}

echo json_encode($json);