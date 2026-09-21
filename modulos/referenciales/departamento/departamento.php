<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/departamento/departamento.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include_once RUTA_PROYECTO . '/portada/cabecera.php' ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- datatables -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/jquery.dataTables.css">
    <script src="<?php echo BASE_URL; ?>/js/jquery.dataTables.min.js"></script>
    <!-- pnotify -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/pnotify/PNotify.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/pnotify/PNotifyMobile.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/pnotify/BrightTheme.css">
    <script src="<?php echo BASE_URL; ?>/pnotify/PNotify.js"></script>
    <script src="<?php echo BASE_URL; ?>/pnotify/PNotifyMobile.js"></script>
    <title>SUBLIMACION | Departamentos</title>
</head>
<?php include_once RUTA_PROYECTO . '/menu/menu.php'; ?>
<main class="app-content">
    <div class="app-title">
        <div>
            <h1><i class="fas fa-map-marker-alt"></i> Departamentos</h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><i class="fas fa-home fa-lg"></i></li>
            <li class="breadcrumb-item">Referenciales</li>
            <li class="breadcrumb-item">Departamentos</li>
        </ul>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-title-w-btn line-head">
                    <h5 class="title">Departamentos</h5>
                    <p><a class="btn btn-primary icon-btn text-white" data-bs-toggle="modal" data-bs-target="#modalDepartamento"><i class="fas fa-plus"></i> Agregar</a></p>
                </div>
                <div class="tile-body">
                    <table id="tbl_departamento" class="table" style="width:100%;">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
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

<?php include RUTA_PROYECTO . '/modulos/referenciales/departamento/modales/modal_departamento.php'; ?>

<script src="<?php echo BASE_URL; ?>/modulos/referenciales/departamento/js/departamento.js?v=<?php echo date('YmdHi'); ?>"></script>
<?php include_once RUTA_PROYECTO . '/portada/footer.php'; ?>
</html>