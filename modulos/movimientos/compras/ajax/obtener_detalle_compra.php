<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/movimientos/compras/compras.php');

header('Content-Type: application/json; charset=utf-8');

function formatearGs($valor) {
    return 'Gs. ' . number_format((float) $valor, 0, '', '.');
}

$idCompra = intval($_GET['id_compra'] ?? 0);

if ($idCompra <= 0) {
    echo json_encode(['msg' => 'ERROR', 'texto' => 'Compra inválida']);
    exit;
}

$sqlCompra = "SELECT c.numero_compra, c.fecha_compra, c.total, c.estado, c.observaciones,
                     p.razon_social AS nombre_proveedor
              FROM compras c
              INNER JOIN proveedores p ON p.id_proveedor = c.id_proveedor
              WHERE c.id_compra = :id_compra";
$stCompra = $conexion->prepare($sqlCompra);
$stCompra->execute(['id_compra' => $idCompra]);
$compra = $stCompra->fetch(PDO::FETCH_ASSOC);

if (!$compra) {
    echo json_encode(['msg' => 'ERROR', 'texto' => 'La compra no existe']);
    exit;
}

$esAnulada = $compra['estado'] === 'ANULADA';
$badgeEstado = $esAnulada
    ? '<span class="badge bg-secondary">ANULADA</span>'
    : '<span class="badge bg-success">REGISTRADA</span>';

// Ajustá el nombre de la PK (dc.id_detalle_compra) si en tu tabla se llama distinto
$sqlLineas = "SELECT i.nombre AS nombre_insumo, u.nombre AS nombre_unidad,
                     dc.cantidad, dc.precio_unitario, dc.subtotal
              FROM detalle_compra dc
              INNER JOIN insumos i ON i.id_insumo = dc.id_insumo
              LEFT JOIN unidades_medida u ON u.id_unidad = i.id_unidad
              WHERE dc.id_compra = :id_compra
              ORDER BY dc.id_detalle_compra ASC";
$stLineas = $conexion->prepare($sqlLineas);
$stLineas->execute(['id_compra' => $idCompra]);
$lineas = $stLineas->fetchAll(PDO::FETCH_ASSOC);

foreach ($lineas as &$linea) {
    $linea['nombre_insumo'] = htmlspecialchars($linea['nombre_insumo']);
    $linea['nombre_unidad'] = htmlspecialchars($linea['nombre_unidad'] ?? '');
    $linea['precio_unitario'] = formatearGs($linea['precio_unitario']);
    $linea['subtotal'] = formatearGs($linea['subtotal']);
}
unset($linea);

echo json_encode([
    'msg'              => 'CORRECTO',
    'numero_compra'    => $compra['numero_compra'],
    'nombre_proveedor' => htmlspecialchars($compra['nombre_proveedor']),
    'fecha_compra'     => $compra['fecha_compra'],
    'estado'           => $badgeEstado,
    'total'            => formatearGs($compra['total']),
    'observaciones'    => $compra['observaciones'] ? htmlspecialchars($compra['observaciones']) : null,
    'lineas'           => $lineas,
]);