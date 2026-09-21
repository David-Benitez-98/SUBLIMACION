<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/departamento/departamento.php');

header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT id_departamento, nombre_departamento, activo
        FROM departamento
        ORDER BY nombre_departamento ASC";
$st = $conexion->prepare($sql);
$st->execute();
$departamentos = $st->fetchAll(PDO::FETCH_ASSOC);

foreach ($departamentos as &$d) {
    $estadoTexto = $d['activo'] ? 'Desactivar' : 'Activar';
    $claseBtn = $d['activo'] ? 'btn-outline-danger' : 'btn-outline-success';
    $icono = $d['activo'] ? 'fa-times' : 'fa-check';
    $d['acciones'] = '<button class="btn btn-sm btn-outline-secondary btn-editar" data-id="' . $d['id_departamento'] . '"><i class="fas fa-edit"></i></button> '
                   . '<button class="btn btn-sm ' . $claseBtn . ' btn-cambiar-estado" data-id="' . $d['id_departamento'] . '" data-nombre="' . htmlspecialchars($d['nombre_departamento']) . '"><i class="fas ' . $icono . '"></i> ' . $estadoTexto . '</button>';
}

echo json_encode(['data' => $departamentos]);