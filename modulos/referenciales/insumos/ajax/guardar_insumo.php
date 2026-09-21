<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/insumos/insumos.php');

header('Content-Type: application/json; charset=utf-8');

$idInsumo = isset($_POST['id_insumo']) ? intval($_POST['id_insumo']) : 0;

$codigo       = trim($_POST['codigo'] ?? '');
$nombre       = trim($_POST['nombre'] ?? '');
$idColor      = $_POST['id_color'] ?? null;
$idColor      = $idColor !== '' ? intval($idColor) : null;
$idUnidad     = intval($_POST['id_unidad'] ?? 0);
$stockActual  = intval($_POST['stock_actual'] ?? 0);
$stockMinimo  = intval($_POST['stock_minimo'] ?? 0);
$ubicacion    = trim($_POST['ubicacion'] ?? '');

$json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'No se pudo guardar el insumo'];

try {
    if ($nombre === '') {
        throw new Exception('El nombre del insumo es obligatorio');
    }
    if ($codigo === '') {
        throw new Exception('El código del insumo es obligatorio');
    }
    if ($idUnidad <= 0) {
        throw new Exception('La unidad de medida es obligatoria');
    }

    $conexion->beginTransaction();

    if ($idInsumo > 0) {
        // UPDATE
        $sql = "UPDATE insumos SET
                    codigo = :codigo,
                    nombre = :nombre,
                    id_color = :id_color,
                    id_unidad = :id_unidad,
                    stock_actual = :stock_actual,
                    stock_minimo = :stock_minimo,
                    ubicacion = :ubicacion,
                    updated_at = NOW()
                WHERE id_insumo = :id_insumo";
        $st = $conexion->prepare($sql);
        $st->execute([
            'codigo'       => $codigo,
            'nombre'       => $nombre,
            'id_color'     => $idColor,
            'id_unidad'    => $idUnidad,
            'stock_actual' => $stockActual,
            'stock_minimo' => $stockMinimo,
            'ubicacion'    => $ubicacion ?: null,
            'id_insumo'    => $idInsumo
        ]);
        $mensaje = 'Insumo actualizado';
    } else {
        // INSERT
        $sql = "INSERT INTO insumos (codigo, nombre, id_color, id_unidad, stock_actual, stock_minimo, ubicacion)
                VALUES (:codigo, :nombre, :id_color, :id_unidad, :stock_actual, :stock_minimo, :ubicacion)";
        $st = $conexion->prepare($sql);
        $st->execute([
            'codigo'       => $codigo,
            'nombre'       => $nombre,
            'id_color'     => $idColor,
            'id_unidad'    => $idUnidad,
            'stock_actual' => $stockActual,
            'stock_minimo' => $stockMinimo,
            'ubicacion'    => $ubicacion ?: null
        ]);
        $mensaje = 'Insumo guardado';
    }

    $conexion->commit();

    $json = ['msg' => 'CORRECTO', 'type' => 'success', 'texto' => $mensaje];

} catch (PDOException $e) {
    $conexion->rollBack();
    if ($e->getCode() === '23505') {
        $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'Ya existe un insumo registrado con ese código.'];
    } else {
        $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'Ocurrió un error al guardar el insumo.'];
    }
} catch (Exception $e) {
    $conexion->rollBack();
    $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => $e->getMessage()];
}

echo json_encode($json);