<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/movimientos/pedidos/pedidos.php');

header('Content-Type: application/json; charset=utf-8');

function limpiarDecimales($valor)
{
    $texto = rtrim(rtrim(number_format((float) $valor, 3, '.', ''), '0'), '.');
    return $texto === '' ? '0' : $texto;
}

$idPedido = intval($_GET['id_pedido'] ?? 0);
if ($idPedido <= 0) {
    echo json_encode(['materiales' => [], 'estado_pedido' => null, 'puede_confirmar' => false]);
    exit;
}

$stPedido = $conexion->prepare("SELECT estado FROM pedidos WHERE id_pedido = :id");
$stPedido->execute(['id' => $idPedido]);
$estadoPedido = $stPedido->fetchColumn();

// Solo se puede confirmar consumo real a partir de que el pedido entró en producción.
$estadosQuePuedenConfirmar = ['en_produccion', 'listo', 'entregado'];
$puedeConfirmar = in_array($estadoPedido, $estadosQuePuedenConfirmar, true);

// Por cada insumo usado en la receta (producto_insumo) de los productos de este pedido,
// se suma cuánto hace falta en total (cantidad pedida x cantidad_requerida de la receta).
// Esto es solo informativo/estimado: NO descuenta stock por sí solo.
$sql = "SELECT i.id_insumo, i.nombre AS nombre_insumo, i.stock_actual, u.nombre AS nombre_unidad,
               SUM(pd.cantidad * pi.cantidad_requerida) AS necesario
        FROM pedido_detalle pd
        INNER JOIN producto_insumo pi ON pi.id_producto = pd.id_producto
        INNER JOIN insumos i ON i.id_insumo = pi.id_insumo
        LEFT JOIN unidades_medida u ON u.id_unidad = i.id_unidad
        WHERE pd.id_pedido = :id_pedido
        GROUP BY i.id_insumo, i.nombre, i.stock_actual, u.nombre
        ORDER BY i.nombre ASC";
$st = $conexion->prepare($sql);
$st->execute(['id_pedido' => $idPedido]);
$filas = $st->fetchAll(PDO::FETCH_ASSOC);

// Neto ya descontado de stock para este pedido (salidas - entradas de reversión),
// generado únicamente por confirmar_consumo_pedido.php.
$stConsumido = $conexion->prepare("SELECT id_insumo,
                                          SUM(CASE WHEN tipo = 'salida' THEN cantidad ELSE -cantidad END) AS neto
                                   FROM movimientos_stock
                                   WHERE id_pedido = :id_pedido
                                   GROUP BY id_insumo");
$stConsumido->execute(['id_pedido' => $idPedido]);
$consumido = [];
foreach ($stConsumido->fetchAll(PDO::FETCH_ASSOC) as $c) {
    $consumido[$c['id_insumo']] = (float) $c['neto'];
}

// Consumo real ya confirmado antes en pedido_insumo (para precargar el campo "Utilizado").
$confirmadoPrevio = [];
$stConfirmado = $conexion->prepare("SELECT id_insumo, cantidad_utilizada FROM pedido_insumo WHERE id_pedido = :id_pedido");
$stConfirmado->execute(['id_pedido' => $idPedido]);
foreach ($stConfirmado->fetchAll(PDO::FETCH_ASSOC) as $fila) {
    $confirmadoPrevio[$fila['id_insumo']] = (float) $fila['cantidad_utilizada'];
}

$materiales = [];
foreach ($filas as $f) {
    $idInsumo = $f['id_insumo'];
    $necesario = (float) $f['necesario'];
    $stockActual = (float) $f['stock_actual'];
    $yaDescontado = $consumido[$idInsumo] ?? 0;
    $pendiente = max(0, $necesario - $yaDescontado);
    $faltante = $pendiente - $stockActual;

    // Si ya se confirmó consumo antes, se precarga ese valor; si no, se sugiere el estimado.
    $utilizadoSugerido = $confirmadoPrevio[$idInsumo] ?? $necesario;

    $materiales[] = [
        'id_insumo'     => $idInsumo,
        'nombre_insumo' => htmlspecialchars($f['nombre_insumo']),
        'nombre_unidad' => htmlspecialchars($f['nombre_unidad'] ?? ''),
        'necesario'     => limpiarDecimales($necesario),
        'ya_descontado' => limpiarDecimales($yaDescontado),
        'stock_actual'  => limpiarDecimales($stockActual),
        'faltante'      => $faltante > 0 ? limpiarDecimales($faltante) : 0,
        'utilizado'     => limpiarDecimales($utilizadoSugerido),
    ];
}

echo json_encode([
    'materiales'      => $materiales,
    'estado_pedido'   => $estadoPedido,
    'puede_confirmar' => $puedeConfirmar,
]);