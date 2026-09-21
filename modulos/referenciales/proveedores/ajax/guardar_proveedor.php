<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/proveedores/proveedores.php');

header('Content-Type: application/json; charset=utf-8');

$idProveedor = isset($_POST['id_proveedor']) ? intval($_POST['id_proveedor']) : 0;

$razonSocial = trim($_POST['razon_social'] ?? '');
$ruc         = trim($_POST['ruc'] ?? '');
$contacto    = trim($_POST['contacto'] ?? '');
$telefono    = trim($_POST['telefono'] ?? '');
$correo      = trim($_POST['correo'] ?? '');
$direccion   = trim($_POST['direccion'] ?? '');
$idCiudad    = $_POST['id_ciudad'] ?? null;
$idCiudad    = $idCiudad !== '' ? intval($idCiudad) : null;

$json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'No se pudo guardar el proveedor'];

try {
    if ($razonSocial === '') {
        throw new Exception('La razón social es obligatoria');
    }

    $conexion->beginTransaction();

    if ($idProveedor > 0) {
        // UPDATE
        $sql = "UPDATE proveedores SET
                    razon_social = :razon_social,
                    ruc = :ruc,
                    contacto = :contacto,
                    telefono = :telefono,
                    correo = :correo,
                    direccion = :direccion,
                    id_ciudad = :id_ciudad
                WHERE id_proveedor = :id_proveedor";
        $st = $conexion->prepare($sql);
        $st->execute([
            'razon_social' => $razonSocial,
            'ruc'          => $ruc ?: null,
            'contacto'     => $contacto ?: null,
            'telefono'     => $telefono ?: null,
            'correo'       => $correo ?: null,
            'direccion'    => $direccion ?: null,
            'id_ciudad'    => $idCiudad,
            'id_proveedor' => $idProveedor
        ]);
        $mensaje = 'Proveedor actualizado';
    } else {
        // INSERT
        $sql = "INSERT INTO proveedores (razon_social, ruc, contacto, telefono, correo, direccion, id_ciudad)
                VALUES (:razon_social, :ruc, :contacto, :telefono, :correo, :direccion, :id_ciudad)";
        $st = $conexion->prepare($sql);
        $st->execute([
            'razon_social' => $razonSocial,
            'ruc'          => $ruc ?: null,
            'contacto'     => $contacto ?: null,
            'telefono'     => $telefono ?: null,
            'correo'       => $correo ?: null,
            'direccion'    => $direccion ?: null,
            'id_ciudad'    => $idCiudad
        ]);
        $mensaje = 'Proveedor guardado';
    }

    $conexion->commit();

    $json = ['msg' => 'CORRECTO', 'type' => 'success', 'texto' => $mensaje];

} catch (PDOException $e) {
    $conexion->rollBack();
    if ($e->getCode() === '23505') {
        $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'Ya existe un proveedor registrado con ese RUC.'];
    } else {
        $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'Ocurrió un error al guardar el proveedor.'];
    }
} catch (Exception $e) {
    $conexion->rollBack();
    $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => $e->getMessage()];
}

echo json_encode($json);