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
               c.nombre AS nombre_cliente, c.apellido AS apellido_cliente,
               COALESCE(f.insumos_faltantes, 0) AS insumos_faltantes
        FROM pedidos p
        INNER JOIN clientes c ON c.id_cliente = p.id_cliente
        LEFT JOIN (
            SELECT x.id_pedido, COUNT(*) AS insumos_faltantes
            FROM (
                SELECT pd.id_pedido, i.id_insumo, i.stock_actual,
                       SUM(pd.cantidad * pi.cantidad_requerida) AS necesario
                FROM pedido_detalle pd
                INNER JOIN producto_insumo pi ON pi.id_producto = pd.id_producto
                INNER JOIN insumos i ON i.id_insumo = pi.id_insumo
                GROUP BY pd.id_pedido, i.id_insumo, i.stock_actual
            ) x
            WHERE x.necesario > x.stock_actual
            GROUP BY x.id_pedido
        ) f ON f.id_pedido = p.id_pedido";
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
    $estadoCrudo = $p['estado'];
    $cantFaltantes = (int) $p['insumos_faltantes'];
    $p['nombre_cliente'] = htmlspecialchars($p['nombre_cliente'] . ' ' . $p['apellido_cliente']);

    $totalSinCeros = rtrim(rtrim(number_format((float) $p['total'], 2, '.', ''), '0'), '.');
    $saldo = (float) $p['total'] - (float) $p['sena_pagada'];
    $saldoSinCeros = rtrim(rtrim(number_format($saldo, 2, '.', ''), '0'), '.');

    $p['total'] = 'Gs. ' . number_format((float) $totalSinCeros, 0, '', '.');
    $p['saldo'] = 'Gs. ' . number_format((float) $saldoSinCeros, 0, '', '.');

    // fecha_entrega_estimada cruda para que el JS calcule la urgencia
    $p['fecha_entrega_estimada_raw'] = $p['fecha_entrega_estimada'];

    // Faltantes de insumos: solo se controlan en pedidos confirmados (todavía no descontaron stock)
    if ($estadoCrudo === 'confirmado') {
        $p['faltantes'] = $cantFaltantes > 0
            ? '<span class="badge bg-danger">Faltan ' . $cantFaltantes . ' insumo(s)</span>'
            : '<span class="badge bg-success">Completo</span>';
    } else {
        $p['faltantes'] = '-';
    }

    [$textoEstado, $colorEstado] = $etiquetasEstado[$p['estado']] ?? [$p['estado'], 'secondary'];
    $p['estado'] = '<span class="badge bg-' . $colorEstado . '">' . $textoEstado . '</span>';

    $p['acciones'] = '<a href="pedido_form.php?id_pedido=' . $p['id_pedido'] . '" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i> Ver / Editar</a> '
        . '<a href="' . BASE_URL . '/reportes/pedidos/imprimir_pedido.php?id_pedido=' . $p['id_pedido'] . '" target="_blank" class="btn btn-sm btn-outline-dark"><i class="fas fa-print"></i></a>';
}

echo json_encode(['data' => $pedidos]);
