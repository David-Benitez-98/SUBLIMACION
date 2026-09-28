<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/movimientos/pedidos/pedidos.php');

header('Content-Type: application/json; charset=utf-8');

$estadoFiltro = trim($_GET['estado'] ?? '');

$sql = "SELECT p.id_pedido, p.numero_pedido, p.fecha_pedido, p.fecha_entrega_estimada,
               p.total, p.sena_pagada, p.estado,
               c.nombre AS nombre_cliente, c.apellido AS apellido_cliente
        FROM pedidos p
        INNER JOIN clientes c ON c.id_cliente = p.id_cliente";
$params = [];
if ($estadoFiltro !== '') {
    $sql .= " WHERE p.estado = :estado";
    $params['estado'] = $estadoFiltro;
}
$sql .= " ORDER BY p.fecha_entrega_estimada ASC NULLS LAST, p.id_pedido DESC";

$st = $conexion->prepare($sql);
$st->execute($params);
$pedidos = $st->fetchAll(PDO::FETCH_ASSOC);

$etiquetasEstado = [
    'presupuestado'  => ['Presupuestado', 'secondary'],
    'confirmado'     => ['Confirmado', 'info'],
    'en_produccion'  => ['En Producción', 'primary'],
    'listo'          => ['Listo', 'warning'],
    'entregado'      => ['Entregado', 'success'],
    'cancelado'      => ['Cancelado', 'dark'],
];

foreach ($pedidos as &$p) {
    $p['nombre_cliente'] = htmlspecialchars($p['nombre_cliente'] . ' ' . $p['apellido_cliente']);

    $totalSinCeros = rtrim(rtrim(number_format((float) $p['total'], 2, '.', ''), '0'), '.');
    $saldo = (float) $p['total'] - (float) $p['sena_pagada'];
    $saldoSinCeros = rtrim(rtrim(number_format($saldo, 2, '.', ''), '0'), '.');

    $p['total'] = 'Gs. ' . number_format((float) $totalSinCeros, 0, '', '.');
    $p['saldo'] = 'Gs. ' . number_format((float) $saldoSinCeros, 0, '', '.');

    // fecha_entrega_estimada cruda para que el JS calcule la urgencia
    $p['fecha_entrega_estimada_raw'] = $p['fecha_entrega_estimada'];

    [$textoEstado, $colorEstado] = $etiquetasEstado[$p['estado']] ?? [$p['estado'], 'secondary'];
    $p['estado'] = '<span class="badge bg-' . $colorEstado . '">' . $textoEstado . '</span>';

    $p['acciones'] = '<a href="pedido_form.php?id_pedido=' . $p['id_pedido'] . '" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i> Ver / Editar</a>';
}

echo json_encode(['data' => $pedidos]);