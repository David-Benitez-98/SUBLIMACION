<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/productos/productos.php');

header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT p.id_producto, p.codigo, p.nombre,p.descripcion, p.precio_venta, p.tiempo_produccion, p.imagen_url, p.activo,
               c.nombre AS nombre_categoria
        FROM productos p
        LEFT JOIN categorias c ON c.id_categoria = p.id_categoria
        ORDER BY p.nombre ASC";
$st = $conexion->prepare($sql);
$st->execute();
$productos = $st->fetchAll(PDO::FETCH_ASSOC);

foreach ($productos as &$p) {
        $nombreCompleto = htmlspecialchars($p['nombre']);
        $estadoTexto = $p['activo'] ? 'Desactivar' : 'Activar';
        $claseBtn = $p['activo'] ? 'btn-outline-danger' : 'btn-outline-success';
        $icono = $p['activo'] ? 'fa-times' : 'fa-check';
        $urlInsumos = BASE_URL . '/modulos/movimientos/producto_insumo/producto_insumo.php?id_producto=' . $p['id_producto'];
        $p['acciones'] = '<a href="' . $urlInsumos . '" class="btn btn-sm btn-outline-primary" title="Insumos del producto"><i class="fas fa-boxes"></i></a> '
                . '<button class="btn btn-sm btn-outline-secondary btn-editar" data-id="' . $p['id_producto'] . '"><i class="fas fa-edit"></i></button> '
                . '<button class="btn btn-sm ' . $claseBtn . ' btn-cambiar-estado" data-id="' . $p['id_producto'] . '" data-nombre="' . $nombreCompleto . '"><i class="fas ' . $icono . '"></i> ' . $estadoTexto . '</button>';
}

echo json_encode(['data' => $productos]);