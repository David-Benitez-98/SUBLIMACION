<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/productos/productos.php');

header('Content-Type: application/json; charset=utf-8');

$idProducto = intval($_GET['id'] ?? 0);
if ($idProducto <= 0) {
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

$sql = "SELECT id_producto, codigo, nombre, descripcion, id_categoria, precio_venta, tiempo_produccion, imagen_url
        FROM productos
        WHERE id_producto = :id";
$st = $conexion->prepare($sql);
$st->execute(['id' => $idProducto]);
$producto = $st->fetch(PDO::FETCH_ASSOC);

if (!$producto) {
    echo json_encode(['error' => 'Producto no encontrado']);
    exit;
}

echo json_encode($producto);