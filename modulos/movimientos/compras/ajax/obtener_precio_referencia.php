<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/movimientos/compras/compras.php');

header('Content-Type: application/json; charset=utf-8');

$idProveedor = intval($_GET['id_proveedor'] ?? 0);
$idInsumo = intval($_GET['id_insumo'] ?? 0);

if ($idProveedor <= 0 || $idInsumo <= 0) {
    echo json_encode(['precio' => null]);
    exit;
}

$sql = "SELECT precio FROM insumo_proveedor
        WHERE id_proveedor = :id_proveedor AND id_insumo = :id_insumo AND activo = true";
$st = $conexion->prepare($sql);
$st->execute(['id_proveedor' => $idProveedor, 'id_insumo' => $idInsumo]);
$resultado = $st->fetch(PDO::FETCH_ASSOC);

echo json_encode(['precio' => $resultado ? (float) $resultado['precio'] : null]);