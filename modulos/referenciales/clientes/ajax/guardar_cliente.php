<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/clientes/clientes.php');

header('Content-Type: application/json; charset=utf-8');

$idCliente = isset($_POST['id_cliente']) ? intval($_POST['id_cliente']) : 0;

$nombre    = trim($_POST['nombre'] ?? '');
$apellido  = trim($_POST['apellido'] ?? '');
$telefono  = trim($_POST['telefono'] ?? '');
$email     = trim($_POST['email'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$idCiudad  = $_POST['id_ciudad'] ?? null;
$idCiudad  = $idCiudad !== '' ? intval($idCiudad) : null;

$idTipoDocumento = $_POST['id_tipo_documento'] ?? null;
$idTipoDocumento = $idTipoDocumento !== '' ? intval($idTipoDocumento) : null;
$numeroDocumento = trim($_POST['numero_documento'] ?? '');

$json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'No se pudo guardar el cliente'];

try {
    if ($nombre === '') {
        throw new Exception('El nombre es obligatorio');
    }

    $conexion->beginTransaction();

    if ($idCliente > 0) {
        // UPDATE
        $sql = "UPDATE clientes SET
                    nombre = :nombre,
                    apellido = :apellido,
                    telefono = :telefono,
                    email = :email,
                    direccion = :direccion,
                    id_ciudad = :id_ciudad,
                    id_tipo_documento = :id_tipo_documento,
                    numero_documento = :numero_documento
                WHERE id_cliente = :id_cliente";
        $st = $conexion->prepare($sql);
        $st->execute([
            'nombre'            => $nombre,
            'apellido'          => $apellido ?: null,
            'telefono'          => $telefono ?: null,
            'email'             => $email ?: null,
            'direccion'         => $direccion ?: null,
            'id_ciudad'         => $idCiudad,
            'id_tipo_documento' => $idTipoDocumento,
            'numero_documento'  => $numeroDocumento ?: null,
            'id_cliente'        => $idCliente
        ]);
        $mensaje = 'Cliente actualizado correctamente';
    } else {
        // INSERT
        $sql = "INSERT INTO clientes (nombre, apellido, telefono, email, direccion, id_ciudad, id_tipo_documento, numero_documento)
                VALUES (:nombre, :apellido, :telefono, :email, :direccion, :id_ciudad, :id_tipo_documento, :numero_documento)";
        $st = $conexion->prepare($sql);
        $st->execute([
            'nombre'            => $nombre,
            'apellido'          => $apellido ?: null,
            'telefono'          => $telefono ?: null,
            'email'             => $email ?: null,
            'direccion'         => $direccion ?: null,
            'id_ciudad'         => $idCiudad,
            'id_tipo_documento' => $idTipoDocumento,
            'numero_documento'  => $numeroDocumento ?: null
        ]);
        $mensaje = 'Cliente guardado correctamente';
    }

    $conexion->commit();
    $json = ['msg' => 'CORRECTO', 'type' => 'success', 'texto' => $mensaje];
} catch (PDOException $e) {
    $conexion->rollBack();
    // Verificar si el error es por duplicado de documento
    if ($e->getCode() === '23505') { // Código de violación de clave única en PostgreSQL
        $json = [
            'msg' => 'ERROR',
            'type' => 'error',
            'texto' => 'Ya existe un cliente registrado con ese número de documento (CI, RUC o Pasaporte). Por favor, verifique.'
        ];
    } else {
        $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'Ocurrió un error al guardar el cliente. Intente nuevamente.'];
    }
} catch (Exception $e) {
    $conexion->rollBack();
    $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => $e->getMessage()];
}

echo json_encode($json);
