<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/clientes/clientes.php');

header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT c.id_cliente, c.nombre, c.apellido, c.telefono, c.email, c.direccion,
               c.numero_documento, td.nombre_tipo_documento, c.activo,
               ci.nombre_ciudad   -- Agregamos el nombre de la ciudad
        FROM clientes c
        LEFT JOIN tipo_documento td ON td.id_tipo_documento = c.id_tipo_documento
        LEFT JOIN ciudad ci ON ci.id_ciudad = c.id_ciudad   -- Unimos con ciudad
        ORDER BY c.nombre ASC";
$st = $conexion->prepare($sql);
$st->execute();
$clientes = $st->fetchAll(PDO::FETCH_ASSOC);

foreach ($clientes as &$c) {
    $nombreCompleto = htmlspecialchars($c['nombre'] . ' ' . $c['apellido']);
    $estadoTexto = $c['activo'] ? 'Desactivar' : 'Activar';
    $claseBtn = $c['activo'] ? 'btn-outline-danger' : 'btn-outline-success';
    $icono = $c['activo'] ? 'fa-times' : 'fa-check';
    $c['acciones'] = '<button class="btn btn-sm btn-outline-secondary btn-editar" data-id="' . $c['id_cliente'] . '"><i class="fas fa-edit"></i></button> '
                    . '<button class="btn btn-sm ' . $claseBtn . ' btn-cambiar-estado" data-id="' . $c['id_cliente'] . '" data-nombre="' . $nombreCompleto . '"><i class="fas ' . $icono . '"></i> ' . $estadoTexto . '</button>';
}

echo json_encode(['data' => $clientes]);