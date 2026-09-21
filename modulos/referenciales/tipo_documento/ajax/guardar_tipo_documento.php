<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/tipo_documento/tipo_documento.php');

header('Content-Type: application/json; charset=utf-8');

$json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'No se pudo procesar la solicitud'];

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST' && isset($_POST['_method'])) {
        $method = strtoupper($_POST['_method']);
    }

    switch ($method) {
        case 'POST':
            // INSERT
            $nombre = trim($_POST['nombre_tipo_documento'] ?? '');
            if ($nombre === '') {
                throw new Exception('El nombre del tipo de documento es obligatorio');
            }

            // Verificar duplicado
            $st = $conexion->prepare("SELECT id_tipo_documento FROM tipo_documento WHERE nombre_tipo_documento = :nombre");
            $st->execute(['nombre' => $nombre]);
            if ($st->rowCount() > 0) {
                throw new Exception('Ya existe un tipo de documento con ese nombre');
            }

            $conexion->beginTransaction();
            $st = $conexion->prepare("INSERT INTO tipo_documento (nombre_tipo_documento) VALUES (:nombre)");
            $st->execute(['nombre' => $nombre]);
            $conexion->commit();

            $json = ['msg' => 'CORRECTO', 'type' => 'success', 'texto' => 'Tipo de documento guardado correctamente'];
            break;

        case 'PUT':
            parse_str(file_get_contents('php://input'), $_PUT);
            $id = intval($_PUT['id_tipo_documento'] ?? 0);
            $nombre = trim($_PUT['nombre_tipo_documento'] ?? '');

            if ($id <= 0 || $nombre === '') {
                throw new Exception('Datos incompletos');
            }

            // Verificar duplicado (excluyendo el actual)
            $st = $conexion->prepare("SELECT id_tipo_documento FROM tipo_documento WHERE nombre_tipo_documento = :nombre AND id_tipo_documento != :id");
            $st->execute(['nombre' => $nombre, 'id' => $id]);
            if ($st->rowCount() > 0) {
                throw new Exception('Ya existe un tipo de documento con ese nombre');
            }

            $conexion->beginTransaction();
            $st = $conexion->prepare("UPDATE tipo_documento SET nombre_tipo_documento = :nombre WHERE id_tipo_documento = :id");
            $st->execute(['nombre' => $nombre, 'id' => $id]);
            $conexion->commit();

            $json = ['msg' => 'CORRECTO', 'type' => 'success', 'texto' => 'Tipo de documento actualizado correctamente'];
            break;

        case 'DELETE':
            parse_str(file_get_contents('php://input'), $_DELETE);
            $id = intval($_DELETE['id_tipo_documento'] ?? 0);
            if ($id <= 0) {
                throw new Exception('ID inválido');
            }

            // Verificar si está siendo utilizado en otras tablas
            // Consultar en clientes y proveedores (y cualquier otra tabla que lo referencie)
            $tablas = ['clientes' => 'id_tipo_documento'];
            $usado = false;
            $mensaje = '';

            foreach ($tablas as $tabla => $campo) {
                $st = $conexion->prepare("SELECT COUNT(*) FROM $tabla WHERE $campo = :id");
                $st->execute(['id' => $id]);
                $count = $st->fetchColumn();
                if ($count > 0) {
                    $usado = true;
                    $mensaje .= " - $count registro(s) en $tabla\n";
                }
            }

            if ($usado) {
                throw new Exception("No se puede eliminar porque está siendo utilizado en:\n" . $mensaje);
            }

            $conexion->beginTransaction();
            $st = $conexion->prepare("DELETE FROM tipo_documento WHERE id_tipo_documento = :id");
            $st->execute(['id' => $id]);
            $conexion->commit();

            $json = ['msg' => 'CORRECTO', 'type' => 'success', 'texto' => 'Tipo de documento eliminado correctamente'];
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
