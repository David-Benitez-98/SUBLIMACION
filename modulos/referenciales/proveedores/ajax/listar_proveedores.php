<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/proveedores/proveedores.php');

header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT p.id_proveedor, p.razon_social, p.ruc, p.contacto, p.telefono, p.correo, p.direccion, p.activo,
               c.nombre_ciudad
        FROM proveedores p
        LEFT JOIN ciudad c ON c.id_ciudad = p.id_ciudad
        ORDER BY p.razon_social ASC";
$st = $conexion->prepare($sql);
$st->execute();
$proveedores = $st->fetchAll(PDO::FETCH_ASSOC);

foreach ($proveedores as &$p) {
        $nombreCompleto = htmlspecialchars($p['razon_social']);
        $estadoTexto = $p['activo'] ? 'Desactivar' : 'Activar';
        $claseBtn = $p['activo'] ? 'btn-outline-danger' : 'btn-outline-success';
        $icono = $p['activo'] ? 'fa-times' : 'fa-check';
        $p['acciones'] = '<button class="btn btn-sm btn-outline-secondary btn-editar" data-id="' . $p['id_proveedor'] . '"><i class="fas fa-edit"></i></button> '
                . '<button class="btn btn-sm ' . $claseBtn . ' btn-cambiar-estado" data-id="' . $p['id_proveedor'] . '" data-nombre="' . $nombreCompleto . '"><i class="fas ' . $icono . '"></i> ' . $estadoTexto . '</button>';
}

echo json_encode(['data' => $proveedores]);
