<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/movimientos/producto_insumo/producto_insumo.php');

header('Content-Type: application/json; charset=utf-8');

$idProducto = intval($_GET['id_producto'] ?? 0);
if ($idProducto <= 0) {
    echo json_encode(['error' => 'Producto inválido']);
    exit;
}

$sql = "SELECT pi.id_producto_insumo, pi.id_insumo, pi.cantidad_requerida,
               i.codigo AS codigo_insumo, i.nombre AS nombre_insumo,
               u.nombre AS nombre_unidad
        FROM producto_insumo pi
        INNER JOIN insumos i ON i.id_insumo = pi.id_insumo
        LEFT JOIN unidades_medida u ON u.id_unidad = i.id_unidad
        WHERE pi.id_producto = :id_producto
        ORDER BY i.nombre ASC";
$st = $conexion->prepare($sql);
$st->execute(['id_producto' => $idProducto]);
$relaciones = $st->fetchAll(PDO::FETCH_ASSOC);

foreach ($relaciones as &$r) {
    $nombreInsumo = htmlspecialchars($r['codigo_insumo'] . ' - ' . $r['nombre_insumo']);
    $unidad = htmlspecialchars($r['nombre_unidad'] ?? '');
    $cantidadExacta = $r['cantidad_requerida'];
    $cantidadFormateada = rtrim(rtrim(number_format((float) $cantidadExacta, 4, '.', ''), '0'), '.');
    if ($cantidadFormateada === '') {
        $cantidadFormateada = '0';
    }
    $r['nombre_insumo'] = $nombreInsumo;
    $r['cantidad_requerida'] = $cantidadFormateada;
    $r['acciones'] = '<button class="btn btn-sm btn-outline-secondary btn-editar" data-id="' . $r['id_producto_insumo'] . '" data-id-insumo="' . $r['id_insumo'] . '" data-unidad="' . $unidad . '" data-cantidad="' . $cantidadExacta . '"><i class="fas fa-edit"></i></button> '
        . '<button class="btn btn-sm btn-outline-danger btn-eliminar" data-id="' . $r['id_producto_insumo'] . '" data-nombre="' . $nombreInsumo . '"><i class="fas fa-trash"></i></button>';
}

echo json_encode(['data' => $relaciones]);