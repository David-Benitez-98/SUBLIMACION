<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/categorias/categorias.php');

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
            //"Buscá el campo nombre que viene del formulario, quitale espacios innecesarios y guardalo en $nombre y $descripcion
            $nombre = trim($_POST['nombre_categorias'] ?? '');
            $descripcion = trim($_POST['categorias_descripcion'] ?? '');
            if ($nombre === '') {
                throw new Exception('El nombre de la categoria es obligatorio');
            }

            // Verificar duplicado
            $st = $conexion->prepare("SELECT id_categoria FROM categorias WHERE nombre = :nombre");
            $st->execute(['nombre' => $nombre]);
            if ($st->rowCount() > 0) {
                throw new Exception('Ya existe una categoria con ese nombre');
            }

            $conexion->beginTransaction();
            $st = $conexion->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (:nombre, :descripcion)");
            $st->execute([
                'nombre' => $nombre,
                'descripcion' => $descripcion
            ]);
            $conexion->commit();

            $json = ['msg' => 'CORRECTO', 'type' => 'success', 'texto' => 'Tipo de documento guardado correctamente'];
            break;

        case 'PUT':
            parse_str(file_get_contents('php://input'), $_PUT);
            $id = intval($_PUT['id_categoria'] ?? 0);
            $nombre = trim($_PUT['nombre_categorias'] ?? '');
            $descripcion = trim($_PUT['categorias_descripcion'] ?? '');

            if ($id <= 0 || $nombre === '') {
                throw new Exception('Datos incompletos');
            }

            // Verificar duplicado (excluyendo el actual)
            //AND id_categoria != :id, significa buscá otra categoría con ese nombre, pero ignorá la que estoy editando.
            $st = $conexion->prepare("SELECT id_categoria 
                                    FROM categorias 
                                    WHERE nombre = :nombre 
                                    AND id_categoria != :id");
            $st->execute(['nombre' => $nombre, 'id' => $id]);
            if ($st->rowCount() > 0) {
                throw new Exception('Ya existe una categoria con ese nombre');
            }

            $conexion->beginTransaction();
            $st = $conexion->prepare("UPDATE categorias SET nombre = :nombre, descripcion = :descripcion WHERE id_categoria = :id");
            $st->execute([
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'id' => $id
            ]);
            $conexion->commit();

            $json = ['msg' => 'CORRECTO', 'type' => 'success', 'texto' => 'Categoria actualizada correctamente'];
            break;

        case 'DELETE':
            parse_str(file_get_contents('php://input'), $_DELETE);
            $id = intval($_DELETE['id_categoria'] ?? 0);
            if ($id <= 0) {
                throw new Exception('ID inválido');
            }

            // Verificar si está siendo utilizado en otras tablas
            // Consultar en clientes y proveedores (y cualquier otra tabla que lo referencie)
            $tablas = ['productos' => 'id_categoria'];
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
            $st = $conexion->prepare("DELETE FROM categorias WHERE id_categoria = :id");
            $st->execute(['id' => $id]);
            $conexion->commit();

            $json = ['msg' => 'CORRECTO', 'type' => 'success', 'texto' => 'Categoria eliminada correctamente'];
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
