<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/movimientos/compras/compras.php');

header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT c.id_compra, c.numero_compra, c.fecha_compra, c.total, c.estado,
               p.razon_social AS nombre_proveedor
        FROM compras c
        INNER JOIN proveedores p ON p.id_proveedor = c.id_proveedor
        ORDER BY c.id_compra DESC";
$st = $conexion->prepare($sql);
$st->execute();
$compras = $st->fetchAll(PDO::FETCH_ASSOC);

foreach ($compras as &$c) {
    $totalSinCeros = rtrim(rtrim(number_format((float) $c['total'], 2, '.', ''), '0'), '.');
    $c['total'] = 'Gs. ' . number_format((float) $totalSinCeros, 0, '', '.');
    $c['nombre_proveedor'] = htmlspecialchars($c['nombre_proveedor']);

    $esAnulada = $c['estado'] === 'ANULADA';
    $badgeEstado = $esAnulada
        ? '<span class="badge bg-secondary">ANULADA</span>'
        : '<span class="badge bg-success">REGISTRADA</span>';
    $c['estado'] = $badgeEstado;

    $btnAnular = $esAnulada
        ? ''
        : ' <button class="btn btn-sm btn-outline-danger btn-anular" data-id="' . $c['id_compra'] . '" data-numero="' . $c['numero_compra'] . '"><i class="fas fa-ban"></i> Anular</button>';

    $c['acciones'] = '<button class="btn btn-sm btn-outline-secondary btn-ver-detalle" data-id="' . $c['id_compra'] . '"><i class="fas fa-eye"></i> Ver</button>'
        . ' <a href="' . BASE_URL . '/reportes/compras/reporte_compra.php?id_compra=' . $c['id_compra'] . '" target="_blank" class="btn btn-sm btn-outline-primary" title="Imprimir reporte"><i class="fas fa-print"></i></a>'
        . $btnAnular;
}

echo json_encode(['data' => $compras]);