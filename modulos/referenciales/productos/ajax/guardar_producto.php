<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/productos/productos.php');

header('Content-Type: application/json; charset=utf-8');

$idProducto = isset($_POST['id_producto']) ? intval($_POST['id_producto']) : 0;

$codigo           = trim($_POST['codigo'] ?? '');
$nombre           = trim($_POST['nombre'] ?? '');
$descripcion      = trim($_POST['descripcion'] ?? '');
$idCategoria      = intval($_POST['id_categoria'] ?? 0);
$precioVenta      = floatval($_POST['precio_venta'] ?? 0);
$tiempoProduccion = intval($_POST['tiempo_produccion'] ?? 0);
$imagenActual     = trim($_POST['imagen_actual'] ?? '');
$imagenUrl        = $imagenActual ?: null;

$json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'No se pudo guardar el producto'];

try {
    if ($nombre === '') {
        throw new Exception('El nombre del producto es obligatorio');
    }
    if ($codigo === '') {
        throw new Exception('El código del producto es obligatorio');
    }
    if ($idCategoria <= 0) {
        throw new Exception('La categoría es obligatoria');
    }

    // ====== Subida de imagen (si se envió un archivo nuevo) ======
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $permitidas)) {
            throw new Exception('Formato de imagen no permitido');
        }
        if ($_FILES['imagen']['size'] > 3 * 1024 * 1024) {
            throw new Exception('La imagen no debe superar 3MB');
        }

        $carpetaDestino = $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/uploads/productos/';
        if (!is_dir($carpetaDestino)) {
            mkdir($carpetaDestino, 0755, true);
        }

        $nombreArchivo = uniqid('prod_') . '.' . $ext;
        $rutaDestino = $carpetaDestino . $nombreArchivo;

        if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaDestino)) {
            throw new Exception('No se pudo subir la imagen');
        }

        $imagenAnterior = $imagenActual;
        $imagenUrl = '/sublimacion/uploads/productos/' . $nombreArchivo;

        // Borrar imagen anterior si existía y es distinta a la nueva
        if ($imagenAnterior && $imagenAnterior !== $imagenUrl) {
            $rutaAnterior = $_SERVER['DOCUMENT_ROOT'] . $imagenAnterior;
            if (file_exists($rutaAnterior)) {
                unlink($rutaAnterior);
            }
        }
    }

    $conexion->beginTransaction();

    if ($idProducto > 0) {
        // UPDATE
        $sql = "UPDATE productos SET
                    codigo = :codigo,
                    nombre = :nombre,
                    descripcion = :descripcion,
                    id_categoria = :id_categoria,
                    precio_venta = :precio_venta,
                    tiempo_produccion = :tiempo_produccion,
                    imagen_url = :imagen_url,
                    updated_at = NOW()
                WHERE id_producto = :id_producto";
        $st = $conexion->prepare($sql);
        $st->execute([
            'codigo'            => $codigo,
            'nombre'            => $nombre,
            'descripcion'       => $descripcion ?: null,
            'id_categoria'      => $idCategoria,
            'precio_venta'      => $precioVenta,
            'tiempo_produccion' => $tiempoProduccion,
            'imagen_url'        => $imagenUrl,
            'id_producto'       => $idProducto
        ]);
        $mensaje = 'Producto actualizado';
    } else {
        // INSERT
        $sql = "INSERT INTO productos (codigo, nombre, descripcion, id_categoria, precio_venta, tiempo_produccion, imagen_url)
                VALUES (:codigo, :nombre, :descripcion, :id_categoria, :precio_venta, :tiempo_produccion, :imagen_url)";
        $st = $conexion->prepare($sql);
        $st->execute([
            'codigo'            => $codigo,
            'nombre'            => $nombre,
            'descripcion'       => $descripcion ?: null,
            'id_categoria'      => $idCategoria,
            'precio_venta'      => $precioVenta,
            'tiempo_produccion' => $tiempoProduccion,
            'imagen_url'        => $imagenUrl
        ]);
        $mensaje = 'Producto guardado';
    }

    $conexion->commit();

    $json = ['msg' => 'CORRECTO', 'type' => 'success', 'texto' => $mensaje];

} catch (PDOException $e) {
    $conexion->rollBack();
    if ($e->getCode() === '23505') {
        $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'Ya existe un producto registrado con ese código.'];
    } else {
        $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'Ocurrió un error al guardar el producto.'];
    }
} catch (Exception $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => $e->getMessage()];
}

echo json_encode($json);