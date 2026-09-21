<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/unidad_medida/unidad_medida.php');

header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT id_unidad, nombre, descripcion
        FROM sublimacion.unidades_medida
        ORDER BY nombre ASC";
$st = $conexion->prepare($sql);
$st->execute();
$tipos = $st->fetchAll(PDO::FETCH_ASSOC);

foreach ($tipos as &$t) {
    $t['acciones'] = '<button class="btn btn-sm btn-outline-secondary btn-editar" data-id="' . $t['id_unidad'] . '"><i class="fas fa-edit"></i></button> '
        . '<button class="btn btn-sm btn-outline-danger btn-eliminar" data-id="' . $t['id_unidad'] . '" data-nombre="' . htmlspecialchars($t['nombre']) . '"><i class="fas fa-trash"></i></button>';
}

echo json_encode(['data' => $tipos]);
