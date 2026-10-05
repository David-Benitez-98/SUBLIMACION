<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/movimientos/pedidos/pedidos.php');

header('Content-Type: application/json; charset=utf-8');

$idPedido            = intval($_POST['id_pedido'] ?? 0);
$idCliente           = intval($_POST['id_cliente'] ?? 0);
$fechaPedido         = trim($_POST['fecha_pedido'] ?? '');
$fechaEntregaEstim   = trim($_POST['fecha_entrega_estimada'] ?? '');
$fechaEntregaReal    = trim($_POST['fecha_entrega_real'] ?? '');
$estado              = trim($_POST['estado'] ?? 'presupuestado');
$senaPagada          = (float) ($_POST['sena_pagada'] ?? 0);
$observaciones       = trim($_POST['observaciones'] ?? '');
$detallesJson        = $_POST['detalles'] ?? '[]';
$reponerStock        = isset($_POST['reponer_stock']) && $_POST['reponer_stock'] === '1';
$idUsuario           = $_SESSION['id_usuario'] ?? null;

// Orden de avance normal. 'cancelado' se maneja aparte (se puede llegar desde cualquiera, menos desde sí mismo).
$ordenEstados = ['presupuestado', 'confirmado', 'en_produccion', 'listo', 'entregado'];

$json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'No se pudo guardar el pedido'];

try {
    if ($idCliente <= 0) {
        throw new Exception('El cliente es obligatorio');
    }
    if ($fechaPedido === '') {
        $fechaPedido = date('Y-m-d');
    }
    if (!in_array($estado, array_merge($ordenEstados, ['cancelado']), true)) {
        throw new Exception('Estado inválido');
    }
    if ($senaPagada < 0) {
        throw new Exception('La seña pagada no puede ser negativa');
    }

    $detalles = json_decode($detallesJson, true);
    if (!is_array($detalles) || count($detalles) === 0) {
        throw new Exception('El pedido debe tener al menos una línea de detalle');
    }

    // Validar y normalizar cada línea (nunca confiar en el subtotal del cliente)
    $lineasValidadas = [];
    $total = 0;
    foreach ($detalles as $linea) {
        $idProducto = intval($linea['id_producto'] ?? 0);
        $cantidad = (float) ($linea['cantidad'] ?? 0);
        $precioUnitario = (float) ($linea['precio_unitario'] ?? 0);

        if ($idProducto <= 0) {
            throw new Exception('Hay una línea con producto inválido');
        }
        if ($cantidad <= 0) {
            throw new Exception('La cantidad de cada línea debe ser mayor a cero');
        }
        if ($precioUnitario < 0) {
            throw new Exception('El precio unitario no puede ser negativo');
        }

        $total += round($cantidad * $precioUnitario, 2);

        $lineasValidadas[] = [
            'id_producto'     => $idProducto,
            'cantidad'        => $cantidad,
            'precio_unitario' => $precioUnitario,
        ];
    }
    $total = round($total, 2);

    if ($senaPagada > $total) {
        throw new Exception('La seña pagada no puede ser mayor al total del pedido');
    }

    $conexion->beginTransaction();

    $estadoAnterior = null;
    $numeroPedido = null;

    if ($idPedido > 0) {
        // ====== Traer estado actual para validar la transición ======
        $stActual = $conexion->prepare("SELECT estado, numero_pedido FROM pedidos WHERE id_pedido = :id FOR UPDATE");
        $stActual->execute(['id' => $idPedido]);
        $actual = $stActual->fetch(PDO::FETCH_ASSOC);

        if (!$actual) {
            throw new Exception('El pedido no existe');
        }

        $estadoAnterior = $actual['estado'];
        $numeroPedido = $actual['numero_pedido'];

        // ====== Un pedido cancelado queda congelado: no se puede modificar nada ======
        if ($estadoAnterior === 'cancelado') {
            throw new Exception('Este pedido está cancelado y no se puede modificar');
        }

        // ====== Validar que la transición de estado sea válida (solo avanzar, o ir a cancelado) ======
        if ($estado !== 'cancelado') {
            $posAnterior = array_search($estadoAnterior, $ordenEstados, true);
            $posNueva = array_search($estado, $ordenEstados, true);
            if ($posNueva === false || $posAnterior === false || $posNueva < $posAnterior) {
                throw new Exception('No se puede retroceder de "' . $estadoAnterior . '" a "' . $estado . '"');
            }
        }

        // ====== Actualizar encabezado ======
        $sqlUpdate = "UPDATE pedidos SET
                          id_cliente = :id_cliente,
                          fecha_pedido = :fecha_pedido,
                          fecha_entrega_estimada = :fecha_entrega_estimada,
                          fecha_entrega_real = :fecha_entrega_real,
                          estado = :estado,
                          total = :total,
                          sena_pagada = :sena_pagada,
                          observaciones = :observaciones
                      WHERE id_pedido = :id_pedido";
        $stUpdate = $conexion->prepare($sqlUpdate);
        $stUpdate->execute([
            'id_cliente'              => $idCliente,
            'fecha_pedido'            => $fechaPedido,
            'fecha_entrega_estimada'  => $fechaEntregaEstim ?: null,
            'fecha_entrega_real'      => $fechaEntregaReal ?: null,
            'estado'                  => $estado,
            'total'                   => $total,
            'sena_pagada'             => $senaPagada,
            'observaciones'           => $observaciones ?: null,
            'id_pedido'               => $idPedido
        ]);

        // Reemplazar todas las líneas: se borran las anteriores y se insertan las nuevas
        $stBorrar = $conexion->prepare("DELETE FROM pedido_detalle WHERE id_pedido = :id_pedido");
        $stBorrar->execute(['id_pedido' => $idPedido]);

        $mensaje = 'Pedido actualizado correctamente';

    } else {
        // ====== ALTA ======
        $numeroProvisorio = 'TEMP-' . uniqid();
        $sqlInsert = "INSERT INTO pedidos (id_cliente, id_usuario, numero_pedido, fecha_pedido, fecha_entrega_estimada,
                                            fecha_entrega_real, estado, total, sena_pagada, observaciones)
                      VALUES (:id_cliente, :id_usuario, :numero_pedido, :fecha_pedido, :fecha_entrega_estimada,
                              :fecha_entrega_real, :estado, :total, :sena_pagada, :observaciones)
                      RETURNING id_pedido";
        $stInsert = $conexion->prepare($sqlInsert);
        $stInsert->execute([
            'id_cliente'              => $idCliente,
            'id_usuario'              => $idUsuario,
            'numero_pedido'           => $numeroProvisorio,
            'fecha_pedido'            => $fechaPedido,
            'fecha_entrega_estimada'  => $fechaEntregaEstim ?: null,
            'fecha_entrega_real'      => $fechaEntregaReal ?: null,
            'estado'                  => $estado,
            'total'                   => $total,
            'sena_pagada'             => $senaPagada,
            'observaciones'           => $observaciones ?: null
        ]);
        $idPedido = $stInsert->fetchColumn();

        $numeroPedido = 'PED-' . str_pad($idPedido, 6, '0', STR_PAD_LEFT);
        $stNumero = $conexion->prepare("UPDATE pedidos SET numero_pedido = :numero WHERE id_pedido = :id");
        $stNumero->execute(['numero' => $numeroPedido, 'id' => $idPedido]);

        $mensaje = "Pedido $numeroPedido registrado correctamente";
    }

    // ====== Insertar todas las líneas de detalle (subtotal es columna GENERATED) ======
    $sqlDetalle = "INSERT INTO pedido_detalle (id_pedido, id_producto, cantidad, precio_unitario)
                   VALUES (:id_pedido, :id_producto, :cantidad, :precio_unitario)";
    $stDetalle = $conexion->prepare($sqlDetalle);
    foreach ($lineasValidadas as $linea) {
        $stDetalle->execute([
            'id_pedido'       => $idPedido,
            'id_producto'     => $linea['id_producto'],
            'cantidad'        => $linea['cantidad'],
            'precio_unitario' => $linea['precio_unitario']
        ]);
    }

    // =====================================================================
    // Reversión de stock SOLO al cancelar. El consumo real ahora se confirma
    // aparte, vía confirmar_consumo_pedido.php + tabla pedido_insumo
    // (ajax/confirmar_consumo_pedido.php) — entrar a 'en_produccion' u otro
    // estado que no sea 'cancelado' ya NO toca el stock acá.
    // =====================================================================
    if ($estado === 'cancelado') {
        $saltarReversion = in_array($estadoAnterior, ['listo', 'entregado'], true) && !$reponerStock;

        if (!$saltarReversion) {
            // Cuánto hay confirmado (neto) para este pedido, según movimientos_stock
            // ya generados por confirmar_consumo_pedido.php.
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

            $stStockActual = $conexion->prepare("SELECT stock_actual FROM insumos WHERE id_insumo = :id FOR UPDATE");
            $sqlMovimiento = "INSERT INTO movimientos_stock (id_insumo, id_pedido, tipo, cantidad, motivo, fecha, id_usuario, stock_anterior, stock_nuevo)
                              VALUES (:id_insumo, :id_pedido, 'entrada', :cantidad, :motivo, NOW(), :id_usuario, :stock_anterior, :stock_nuevo)";
            $stMovimiento = $conexion->prepare($sqlMovimiento);

            foreach ($consumidoAnterior as $idInsumo => $cantidadConfirmada) {
                if ($cantidadConfirmada <= 0.0001) {
                    continue;
                }
                $stStockActual->execute(['id' => $idInsumo]);
                $stockAnterior = (float) $stStockActual->fetchColumn();
                $stockNuevo = $stockAnterior + $cantidadConfirmada;

                $stMovimiento->execute([
                    'id_insumo'      => $idInsumo,
                    'id_pedido'      => $idPedido,
                    'cantidad'       => $cantidadConfirmada,
                    'motivo'         => "Reversión por cancelación pedido $numeroPedido",
                    'id_usuario'     => $idUsuario,
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo'    => $stockNuevo
                ]);
            }
        }
    }

    $conexion->commit();

    $json = ['msg' => 'CORRECTO', 'type' => 'success', 'texto' => $mensaje, 'id_pedido' => $idPedido];

} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    if ($e->getCode() === '23505') {
        $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'Ya existe un pedido con ese número.'];
    } elseif ($e->getCode() === '23514') {
        $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'Los datos no cumplen las validaciones de la base (revisá seña, total o estado).'];
    } else {
        $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'Ocurrió un error al guardar el pedido.'];
    }
} catch (Exception $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => $e->getMessage()];
}

echo json_encode($json);