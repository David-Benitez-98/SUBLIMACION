<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/ciudad/ciudad.php');

header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT c.id_ciudad, c.nombre_ciudad, c.activo, d.nombre_departamento
        FROM ciudad c
        LEFT JOIN departamento d ON d.id_departamento = c.id_departamento
        ORDER BY c.nombre_ciudad ASC";
$st = $conexion->prepare($sql);
$st->execute();
$ciudades = $st->fetchAll(PDO::FETCH_ASSOC);

foreach ($ciudades as &$c) {
    $estadoTexto = $c['activo'] ? 'Desactivar' : 'Activar';
    $claseBtn = $c['activo'] ? 'btn-outline-danger' : 'btn-outline-success';
    $icono = $c['activo'] ? 'fa-times' : 'fa-check';
    $c['acciones'] = '<button class="btn btn-sm btn-outline-secondary btn-editar" data-id="' . $c['id_ciudad'] . '"><i class="fas fa-edit"></i></button> '
                   . '<button class="btn btn-sm ' . $claseBtn . ' btn-cambiar-estado" data-id="' . $c['id_ciudad'] . '" data-nombre="' . htmlspecialchars($c['nombre_ciudad']) . '"><i class="fas ' . $icono . '"></i> ' . $estadoTexto . '</button>';
}

echo json_encode(['data' => $ciudades]);