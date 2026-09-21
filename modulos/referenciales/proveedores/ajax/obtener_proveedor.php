<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/proveedores/proveedores.php');

header('Content-Type: application/json; charset=utf-8');

$idProveedor = intval($_GET['id'] ?? 0);
if ($idProveedor <= 0) {
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

$sql = "SELECT id_proveedor, razon_social, ruc, contacto, telefono, correo, direccion, id_ciudad
        FROM proveedores
        WHERE id_proveedor = :id";
$st = $conexion->prepare($sql);
$st->execute(['id' => $idProveedor]);
$proveedor = $st->fetch(PDO::FETCH_ASSOC);

if (!$proveedor) {
    echo json_encode(['error' => 'Proveedor no encontrado']);
    exit;
}

// Obtener departamento asociado a la ciudad
$sqlCiudad = "SELECT id_departamento FROM ciudad WHERE id_ciudad = :idCiudad";
$stCiudad = $conexion->prepare($sqlCiudad);
$stCiudad->execute(['idCiudad' => $proveedor['id_ciudad']]);
$ciudad = $stCiudad->fetch(PDO::FETCH_ASSOC);
$proveedor['id_departamento'] = $ciudad ? $ciudad['id_departamento'] : null;

echo json_encode($proveedor);