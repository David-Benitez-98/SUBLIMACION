<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';
require_once __DIR__ . '/helpers_movimientos.php';

exigirAcceso($conexion, '/modulos/movimientos/movimientos_stock/movimientos_stock.php');

header('Content-Type: application/json; charset=utf-8');

$idMovimiento = intval($_GET['id_movimiento'] ?? 0);

if ($idMovimiento <= 0) {
    echo json_encode(['msg' => 'ERROR', 'texto' => 'Movimiento inválido']);
    exit;
}

$sql = "SELECT m.id_movimiento, m.fecha, m.tipo, m.clase, m.cantidad,
               m.stock_anterior, m.stock_nuevo, m.motivo, m.id_pedido,
               i.codigo, i.nombre AS nombre_insumo,
               um.nombre AS nombre_unidad,
               c.numero_compra,
               u.nombre AS nombre_usuario
        FROM movimientos_stock m
        INNER JOIN insumos i ON i.id_insumo = m.id_insumo
        LEFT JOIN unidades_medida um ON um.id_unidad = i.id_unidad
        LEFT JOIN compras c ON c.id_compra = m.id_compra
        LEFT JOIN usuarios u ON u.id_usuario = m.id_usuario
        WHERE m.id_movimiento = :id_movimiento";
$st = $conexion->prepare($sql);
$st->execute(['id_movimiento' => $idMovimiento]);
$m = $st->fetch(PDO::FETCH_ASSOC);

if (!$m) {
    echo json_encode(['msg' => 'ERROR', 'texto' => 'El movimiento no existe']);
    exit;
}

echo json_encode([
    'msg'            => 'CORRECTO',
    'id_movimiento'  => $m['id_movimiento'],
    'fecha'          => date('d/m/Y H:i:s', strtotime($m['fecha'])),
    'insumo'         => htmlspecialchars($m['codigo'] . ' - ' . $m['nombre_insumo']),
    'unidad'         => htmlspecialchars($m['nombre_unidad'] ?? ''),
    'clase'          => badgeClaseMovimiento($m['clase'], $m['tipo']),
    'cantidad'       => formatearCantidadMovimiento($m['cantidad'], $m['tipo']),
    'stock_anterior' => formatearStock($m['stock_anterior']),
    'stock_nuevo'    => formatearStock($m['stock_nuevo']),
    'origen'         => textoOrigenMovimiento($m['numero_compra'], $m['id_pedido']),
    'usuario'        => $m['nombre_usuario'] ? htmlspecialchars($m['nombre_usuario']) : '—',
    'motivo'         => $m['motivo'] ? htmlspecialchars($m['motivo']) : null,
]);
