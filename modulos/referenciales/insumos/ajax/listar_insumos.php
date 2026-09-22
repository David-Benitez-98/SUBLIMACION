<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/insumos/insumos.php');

header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT i.id_insumo, i.codigo, i.nombre, i.stock_actual, i.stock_minimo, i.ubicacion, i.activo,
               c.nombre AS nombre_color,
               u.nombre AS nombre_unidad
        FROM insumos i
        LEFT JOIN colores c ON c.id_color = i.id_color
        LEFT JOIN unidades_medida u ON u.id_unidad = i.id_unidad
        ORDER BY i.nombre ASC";
$st = $conexion->prepare($sql);
$st->execute();
$insumos = $st->fetchAll(PDO::FETCH_ASSOC);

foreach ($insumos as &$i) {
        $nombreCompleto = htmlspecialchars($i['nombre']);
        $estadoTexto = $i['activo'] ? 'Desactivar' : 'Activar';
        $claseBtn = $i['activo'] ? 'btn-outline-danger' : 'btn-outline-success';
        $icono = $i['activo'] ? 'fa-times' : 'fa-check';
        $i['stock_actual'] = (int) $i['stock_actual'];
        $i['stock_minimo'] = (int) $i['stock_minimo'];
        $urlProveedores = BASE_URL . '/modulos/movimientos/insumo_proveedor/insumo_proveedor.php?id_insumo=' . $i['id_insumo'];
        $i['acciones'] = '<a href="' . $urlProveedores . '" class="btn btn-sm btn-outline-primary" title="Proveedores del insumo"><i class="fas fa-truck"></i></a> '
                . '<button class="btn btn-sm btn-outline-secondary btn-editar" data-id="' . $i['id_insumo'] . '"><i class="fas fa-edit"></i></button> '
                . '<button class="btn btn-sm ' . $claseBtn . ' btn-cambiar-estado" data-id="' . $i['id_insumo'] . '" data-nombre="' . $nombreCompleto . '"><i class="fas ' . $icono . '"></i> ' . $estadoTexto . '</button>';
}

echo json_encode(['data' => $insumos]);