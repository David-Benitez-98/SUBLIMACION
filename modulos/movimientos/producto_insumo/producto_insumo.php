<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/movimientos/producto_insumo/producto_insumo.php');

$idProducto = intval($_GET['id_producto'] ?? 0);
if ($idProducto <= 0) {
    die('Producto no especificado.');
}

$stProducto = $conexion->prepare("SELECT id_producto, codigo, nombre, activo FROM productos WHERE id_producto = :id");
$stProducto->execute(['id' => $idProducto]);
$producto = $stProducto->fetch(PDO::FETCH_ASSOC);

if (!$producto) {
    die('Producto no encontrado.');
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include_once RUTA_PROYECTO . '/portada/cabecera.php' ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- select2 -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/select2/css/select2.min.css">
    <script src="<?php echo BASE_URL; ?>/select2/js/select2.min.js"></script>

    <!-- datatables -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/jquery.dataTables.css">
    <script src="<?php echo BASE_URL; ?>/js/jquery.dataTables.min.js"></script>

    <!-- pnotify -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/pnotify/PNotify.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/pnotify/PNotifyMobile.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/pnotify/BrightTheme.css">
    <script src="<?php echo BASE_URL; ?>/pnotify/PNotify.js"></script>
    <script src="<?php echo BASE_URL; ?>/pnotify/PNotifyMobile.js"></script>
    <script src="<?php echo BASE_URL; ?>/pnotify/PNotifyButtons.js"></script>

    <title>SUBLIMACION | Insumos del Producto</title>
</head>
<?php include_once RUTA_PROYECTO . '/menu/menu.php'; ?>
<main class="app-content">
    <div class="app-title">
        <div>
            <h1><i class="fas fa-boxes"></i> Insumos del Producto</h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><i class="fas fa-home fa-lg"></i></li>
            <li class="breadcrumb-item">Movimientos</li>
            <li class="breadcrumb-item">Producto - Insumo</li>
        </ul>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-title-w-btn line-head">
                    <div>
                        <h5 class="title">
                            <?php echo htmlspecialchars($producto['codigo']); ?> - <?php echo htmlspecialchars($producto['nombre']); ?>
                        </h5>
                        <small class="text-muted">Insumos requeridos para este producto</small>
                    </div>
                    <p><a class="btn btn-primary icon-btn text-white" data-bs-toggle="modal" data-bs-target="#modalProductoInsumo"><i class="fas fa-plus"></i> Agregar insumo</a></p>
                </div>
                <div class="tile-body">
                    <table id="tbl_producto_insumo" class="table" style="width:100%;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Insumo</th>
                                <th>Unidad</th>
                                <th>Cantidad Requerida</th>
                                <th><i class="fas fa-ellipsis-h"></i></th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
$stInsumo = $conexion->query("SELECT i.id_insumo, i.codigo, i.nombre, u.nombre AS nombre_unidad
                               FROM insumos i
                               LEFT JOIN unidades_medida u ON u.id_unidad = i.id_unidad
                               WHERE i.activo = true ORDER BY i.nombre ASC");
$insumosDisponibles = $stInsumo->fetchAll(PDO::FETCH_ASSOC);

include RUTA_PROYECTO . '/modulos/movimientos/producto_insumo/modales/modal_producto_insumo.php';
?>

<!-- Modal de confirmación de eliminación (fijo) -->
<div class="modal fade" id="modalConfirmEliminar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="mensajeConfirmacionEliminar">¿Está seguro?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarEliminar">Sí, Eliminar</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/modulos/movimientos/producto_insumo/js/producto_insumo.js?v=<?php echo date('YmdHi'); ?>"></script>
<?php include_once RUTA_PROYECTO . '/portada/footer.php' ?>

</html>