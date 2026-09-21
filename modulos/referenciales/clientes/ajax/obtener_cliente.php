<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/clientes/clientes.php');

header('Content-Type: application/json; charset=utf-8');

$idCliente = intval($_GET['id'] ?? 0);
if ($idCliente <= 0) {
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

$sql = "SELECT id_cliente, nombre, apellido, telefono, email, direccion, 
               id_ciudad, id_tipo_documento, numero_documento
        FROM clientes
        WHERE id_cliente = :id";
$st = $conexion->prepare($sql);
$st->execute(['id' => $idCliente]);
$cliente = $st->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    echo json_encode(['error' => 'Cliente no encontrado']);
    exit;
}

// Obtener departamento asociado a la ciudad
$sqlCiudad = "SELECT id_departamento FROM ciudad WHERE id_ciudad = :idCiudad";
$stCiudad = $conexion->prepare($sqlCiudad);
$stCiudad->execute(['idCiudad' => $cliente['id_ciudad']]);
$ciudad = $stCiudad->fetch(PDO::FETCH_ASSOC);
$cliente['id_departamento'] = $ciudad ? $ciudad['id_departamento'] : null;

echo json_encode($cliente);