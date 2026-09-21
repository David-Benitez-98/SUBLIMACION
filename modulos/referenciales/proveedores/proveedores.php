<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/proveedores/proveedores.php');
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

    <title>SUBLIMACION | Proveedores</title>
</head>
<?php include_once RUTA_PROYECTO . '/menu/menu.php'; ?>
<main class="app-content">
    <div class="app-title">
        <div>
            <h1><i class="fas fa-truck"></i> Proveedores</h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><i class="fas fa-home fa-lg"></i></li>
            <li class="breadcrumb-item">Referenciales</li>
            <li class="breadcrumb-item">Proveedores</li>
        </ul>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-title-w-btn line-head">
                    <h5 class="title">Proveedores</h5>
                    <p><a class="btn btn-primary icon-btn text-white" data-bs-toggle="modal" data-bs-target="#modalProveedor"><i class="fas fa-plus"></i> Agregar</a></p>
                </div>
                <div class="tile-body">
                    <table id="tbl_proveedores" class="table" style="width:100%;">
                        <thead>
                            <tr>
                                <th>Codigo</th>
                                <th>RUC</th>
                                <th>Razón Social</th>
                                <th>Contacto</th>
                                <th>Teléfono</th>
                                <th>Correo</th>
                                <th>Dirección</th>
                                <th>Ciudad</th>
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
$stDep = $conexion->query("SELECT id_departamento, nombre_departamento FROM departamento ORDER BY nombre_departamento ASC");
$departamentos = $stDep->fetchAll(PDO::FETCH_ASSOC);
include RUTA_PROYECTO . '/modulos/referenciales/proveedores/modales/modal_proveedores.php';
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

<script src="<?php echo BASE_URL; ?>/modulos/referenciales/proveedores/js/proveedores.js?v=<?php echo date('YmdHi'); ?>"></script>
<?php include_once RUTA_PROYECTO . '/portada/footer.php' ?>

</html>