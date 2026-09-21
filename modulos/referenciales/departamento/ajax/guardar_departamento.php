<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/departamento/departamento.php');

header('Content-Type: application/json; charset=utf-8');

$json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'No se pudo procesar la solicitud'];

try {
    // Manejar métodos PUT (via _method) o POST
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'POST' && isset($_POST['_method']) && strtoupper($_POST['_method']) === 'PUT') {
        $method = 'PUT';
        // Los datos PUT vienen en $_POST también, ya que se envían como application/x-www-form-urlencoded
    }

    switch ($method) {
        case 'POST':
            $nombre = trim($_POST['nombre_departamento'] ?? '');
            if ($nombre === '') {
                throw new Exception('El nombre del departamento es obligatorio');
            }

            // Verificar duplicado
            $st = $conexion->prepare("SELECT id_departamento FROM departamento WHERE nombre_departamento = :nombre");
            $st->execute(['nombre' => $nombre]);
            if ($st->rowCount() > 0) {
                throw new Exception('Ya existe un departamento con ese nombre');
            }

            $conexion->beginTransaction();
            $st = $conexion->prepare("INSERT INTO departamento (nombre_departamento, activo) VALUES (:nombre, true)");
            $st->execute(['nombre' => $nombre]);
            $conexion->commit();

            $json = ['msg' => 'CORRECTO', 'type' => 'success', 'texto' => 'Departamento guardado correctamente'];
            break;

        case 'PUT':
            $id = intval($_POST['id_departamento'] ?? 0);
            $nombre = trim($_POST['nombre_departamento'] ?? '');

            if ($id <= 0) {
                throw new Exception('Departamento inválido');
            }
            if ($nombre === '') {
                throw new Exception('El nombre del departamento es obligatorio');
            }

            // Verificar duplicado, excluyendo el propio registro que se está editando
            $st = $conexion->prepare("SELECT id_departamento FROM departamento WHERE nombre_departamento = :nombre AND id_departamento != :id");
            $st->execute(['nombre' => $nombre, 'id' => $id]);
            if ($st->rowCount() > 0) {
                throw new Exception('Ya existe un departamento con ese nombre');
            }

            $conexion->beginTransaction();
            $st = $conexion->prepare("UPDATE departamento SET nombre_departamento = :nombre WHERE id_departamento = :id RETURNING id_departamento");
            $st->execute(['nombre' => $nombre, 'id' => $id]);
            $actualizado = $st->fetch(PDO::FETCH_ASSOC);
            if (!$actualizado) {
                throw new Exception('Departamento no encontrado');
            }
            $conexion->commit();

            $json = ['msg' => 'CORRECTO', 'type' => 'success', 'texto' => 'Departamento actualizado correctamente'];
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