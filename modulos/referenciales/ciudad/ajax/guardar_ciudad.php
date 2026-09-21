<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/ciudad/ciudad.php');

header('Content-Type: application/json; charset=utf-8');

$json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'No se pudo procesar la solicitud'];

try {
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'POST' && isset($_POST['_method']) && strtoupper($_POST['_method']) === 'PUT') {
        $method = 'PUT';
    }

    switch ($method) {
        case 'POST':
            $nombre = trim($_POST['nombre_ciudad'] ?? '');
            $idDepartamento = intval($_POST['id_departamento'] ?? 0);
            if ($nombre === '' || $idDepartamento <= 0) {
                throw new Exception('El nombre y el departamento son obligatorios');
            }
            // Verificar duplicado (nombre + departamento? o solo nombre? pero puede haber ciudades con mismo nombre en distintos deptos, así que solo nombre no basta, pero por simplicidad, asumimos nombre único global, o mejor comprobar combinación)
            $st = $conexion->prepare("SELECT id_ciudad FROM ciudad WHERE nombre_ciudad = :nombre AND id_departamento = :id_departamento");
            $st->execute(['nombre' => $nombre, 'id_departamento' => $idDepartamento]);
            if ($st->rowCount() > 0) {
                throw new Exception('Ya existe una ciudad con ese nombre en el departamento seleccionado');
            }
            $conexion->beginTransaction();
            $st = $conexion->prepare("INSERT INTO ciudad (nombre_ciudad, id_departamento, activo) VALUES (:nombre, :id_departamento, true)");
            $st->execute(['nombre' => $nombre, 'id_departamento' => $idDepartamento]);
            $conexion->commit();
            $json = ['msg' => 'CORRECTO', 'type' => 'success', 'texto' => 'Ciudad guardada correctamente'];
            break;

        case 'PUT':
            parse_str(file_get_contents('php://input'), $_PUT);
            $id = intval($_PUT['id_ciudad'] ?? 0);
            $nombre = trim($_PUT['nombre_ciudad'] ?? '');
            $idDepartamento = intval($_PUT['id_departamento'] ?? 0);
            if ($id <= 0 || $nombre === '' || $idDepartamento <= 0) {
                throw new Exception('Datos incompletos');
            }
            // Verificar duplicado excluyendo el actual
            $st = $conexion->prepare("SELECT id_ciudad FROM ciudad WHERE nombre_ciudad = :nombre AND id_departamento = :id_departamento AND id_ciudad != :id");
            $st->execute(['nombre' => $nombre, 'id_departamento' => $idDepartamento, 'id' => $id]);
            if ($st->rowCount() > 0) {
                throw new Exception('Ya existe una ciudad con ese nombre en el departamento seleccionado');
            }
            $conexion->beginTransaction();
            $st = $conexion->prepare("UPDATE ciudad SET nombre_ciudad = :nombre, id_departamento = :id_departamento WHERE id_ciudad = :id");
            $st->execute(['nombre' => $nombre, 'id_departamento' => $idDepartamento, 'id' => $id]);
            $conexion->commit();
            $json = ['msg' => 'CORRECTO', 'type' => 'success', 'texto' => 'Ciudad actualizada correctamente'];
            break;

        default:
            throw new Exception('Método no permitido');
    }
} catch (Exception $e) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }
    $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => $e->getMessage()];
}

echo json_encode($json);