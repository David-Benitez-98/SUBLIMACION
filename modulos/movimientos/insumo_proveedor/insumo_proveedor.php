<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/movimientos/insumo_proveedor/insumo_proveedor.php');

$idInsumo = intval($_GET['id_insumo'] ?? 0);
if ($idInsumo <= 0) {
    die('Insumo no especificado.');
}

$stInsumo = $conexion->prepare("SELECT i.id_insumo, i.codigo, i.nombre, i.stock_actual, i.stock_minimo, i.activo,
                                        u.nombre AS nombre_unidad
                                 FROM insumos i
                                 LEFT JOIN unidades_medida u ON u.id_unidad = i.id_unidad
                                 WHERE i.id_insumo = :id");
$stInsumo->execute(['id' => $idInsumo]);
$insumo = $stInsumo->fetch(PDO::FETCH_ASSOC);

if (!$insumo) {
    die('Insumo no encontrado.');
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

    <title>SUBLIMACION | Proveedores del Insumo</title>
</head>
<?php include_once RUTA_PROYECTO . '/menu/menu.php'; ?>
<main class="app-content">
    <div class="app-title">
        <div>
            <h1><i class="fas fa-truck"></i> Proveedores del Insumo</h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><i class="fas fa-home fa-lg"></i></li>
            <li class="breadcrumb-item">Movimientos</li>
            <li class="breadcrumb-item">Insumo - Proveedor</li>
        </ul>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-title-w-btn line-head">
                    <div>
                        <h5 class="title">
                            <?php echo htmlspecialchars($insumo['codigo']); ?> - <?php echo htmlspecialchars($insumo['nombre']); ?>
                        </h5>
                        <small class="text-muted">
                            Unidad: <?php echo htmlspecialchars($insumo['nombre_unidad'] ?? '-'); ?> |
                            Stock actual: <?php echo (int) $insumo['stock_actual']; ?> |
                            Stock mínimo: <?php echo (int) $insumo['stock_minimo']; ?>
                        </small>
                    </div>
                    <p><a class="btn btn-primary icon-btn text-white" data-bs-toggle="modal" data-bs-target="#modalInsumoProveedor"><i class="fas fa-plus"></i> Agregar proveedor</a></p>
                </div>
                <div class="tile-body">
                    <table id="tbl_insumo_proveedor" class="table" style="width:100%;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Proveedor</th>
                                <th>Precio</th>
                                <th>Calidad</th>
                                <th>Entrega</th>
                                <th>Favorito</th>
                                <th>Estado</th>
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
$stProveedor = $conexion->query("SELECT id_proveedor, codigo, nombre FROM proveedores WHERE activo = true ORDER BY nombre ASC");
$proveedoresDisponibles = $stProveedor->fetchAll(PDO::FETCH_ASSOC);

include RUTA_PROYECTO . '/modulos/movimientos/insumo_proveedor/modales/modal_insumo_proveedor.php';
?>

<!-- Modal de confirmación de estado (fijo) -->
<div class="modal fade" id="modalConfirmEstado" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="mensajeConfirmacion">¿Está seguro?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarEstado">Sí, Desactivar</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/modulos/movimientos/insumo_proveedor/js/insumo_proveedor.js?v=<?php echo date('YmdHi'); ?>"></script>
<?php include_once RUTA_PROYECTO . '/portada/footer.php' ?>

</html>

