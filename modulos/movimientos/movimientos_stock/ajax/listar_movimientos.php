<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';
require_once __DIR__ . '/helpers_movimientos.php';

exigirAcceso($conexion, '/modulos/movimientos/movimientos_stock/movimientos_stock.php');

header('Content-Type: application/json; charset=utf-8');

// Filtros (todos opcionales, siempre parametrizados)
[$whereSql, $params] = construirFiltrosMovimientos($_GET);

$sql = "SELECT m.id_movimiento, m.fecha, m.tipo, m.clase, m.cantidad,
               m.stock_anterior, m.stock_nuevo, m.id_pedido,
               i.codigo, i.nombre AS nombre_insumo,
               c.numero_compra,
               u.nombre AS nombre_usuario
        FROM movimientos_stock m
        INNER JOIN insumos i ON i.id_insumo = m.id_insumo
        LEFT JOIN compras c ON c.id_compra = m.id_compra
        LEFT JOIN usuarios u ON u.id_usuario = m.id_usuario"
        . $whereSql . " ORDER BY m.id_movimiento DESC";

$st = $conexion->prepare($sql);
$st->execute($params);
$movimientos = $st->fetchAll(PDO::FETCH_ASSOC);

$data = [];
foreach ($movimientos as $m) {
    $signo = $m['tipo'] === 'entrada' ? 1 : -1;

    $data[] = [
        'id_movimiento'  => $m['id_movimiento'],
        'fecha'          => date('d/m/Y H:i', strtotime($m['fecha'])),
        'fecha_orden'    => $m['fecha'],
        'insumo'         => htmlspecialchars($m['codigo'] . ' - ' . $m['nombre_insumo']),
        'clase'          => badgeClaseMovimiento($m['clase'], $m['tipo']),
        'cantidad'       => formatearCantidadMovimiento($m['cantidad'], $m['tipo']),
        'cantidad_orden' => $signo * (float) $m['cantidad'],
        'stock_anterior' => formatearStock($m['stock_anterior']),
        'stock_anterior_orden' => (float) $m['stock_anterior'],
        'stock_nuevo'    => formatearStock($m['stock_nuevo']),
        'stock_nuevo_orden' => (float) $m['stock_nuevo'],
        'origen'         => textoOrigenMovimiento($m['numero_compra'], $m['id_pedido']),
        'usuario'        => $m['nombre_usuario'] ? htmlspecialchars($m['nombre_usuario']) : '—',
        'acciones'       => '<button class="btn btn-sm btn-outline-secondary btn-ver-detalle" data-id="' . $m['id_movimiento'] . '"><i class="fas fa-eye"></i> Ver</button>',
    ];
}

echo json_encode(['data' => $data]);
