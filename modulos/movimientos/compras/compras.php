<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/movimientos/compras/compras.php');
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

    <title>SUBLIMACION | Compras</title>
</head>
<?php include_once RUTA_PROYECTO . '/menu/menu.php'; ?>
<main class="app-content">
    <div class="app-title">
        <div>
            <h1><i class="fas fa-shopping-cart"></i> Compras</h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><i class="fas fa-home fa-lg"></i></li>
            <li class="breadcrumb-item">Movimientos</li>
            <li class="breadcrumb-item">Compras</li>
        </ul>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-title-w-btn line-head">
                    <h5 class="title">Compras</h5>
                    <p><a class="btn btn-primary icon-btn text-white" href="nueva_compra.php"><i class="fas fa-plus"></i> Nueva Compra</a></p>
                </div>
                <div class="tile-body">
                    <table id="tbl_compras" class="table" style="width:100%;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Número</th>
                                <th>Proveedor</th>
                                <th>Fecha</th>
                                <th>Total</th>
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

<!-- Modal de ver detalle (solo lectura) -->
<div class="modal fade" id="modalDetalleCompra" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-receipt"></i> Detalle de Compra <span id="detalle_numero_compra"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Proveedor:</strong> <span id="detalle_proveedor"></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Fecha:</strong> <span id="detalle_fecha"></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Estado:</strong> <span id="detalle_estado"></span>
                    </div>
                </div>
                <table class="table table-sm" id="tbl_detalle_lineas">
                    <thead>
                        <tr>
                            <th>Insumo</th>
                            <th>Unidad</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="detalle_lineas_body"></tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-end"><strong>Total</strong></td>
                            <td id="detalle_total"><strong></strong></td>
                        </tr>
                    </tfoot>
                </table>
                <div id="detalle_observaciones_wrapper" style="display:none;">
                    <strong>Observaciones:</strong> <span id="detalle_observaciones"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmación de anulación -->
<div class="modal fade" id="modalConfirmAnular" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="mensajeConfirmacionAnular">¿Está seguro de anular esta compra? Esta acción revertirá el stock ingresado.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarAnular">Sí, Anular</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/modulos/movimientos/compras/js/compras.js?v=<?php echo date('YmdHi'); ?>"></script>
<?php include_once RUTA_PROYECTO . '/portada/footer.php' ?>

</html>