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
    echo json_encode(['materiales' => []]);
    exit;
}

// Por cada insumo usado en la receta (producto_insumo) de los productos de este pedido,
// se suma cuánto hace falta en total (cantidad pedida x cantidad_requerida de la receta).
// Esto es solo informativo: NO descuenta stock ni genera movimientos.
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

$materiales = [];
foreach ($filas as $f) {
    $necesario = (float) $f['necesario'];
    $stockActual = (float) $f['stock_actual'];
    $faltante = $necesario - $stockActual;

    $materiales[] = [
        'nombre_insumo' => htmlspecialchars($f['nombre_insumo']),
        'nombre_unidad' => htmlspecialchars($f['nombre_unidad'] ?? ''),
        'necesario'     => limpiarDecimales($necesario),
        'stock_actual'  => limpiarDecimales($stockActual),
        'faltante'      => $faltante > 0 ? limpiarDecimales($faltante) : 0,
    ];
}

echo json_encode(['materiales' => $materiales]);