<?php
require_once dirname(__DIR__) . '/security/verificacion_sesion.php';
require_once dirname(__DIR__) . '/config/conexion.php';
require_once dirname(__DIR__) . '/config/constantes.php';
require_once dirname(__DIR__) . '/security/permisos.php';

exigirAcceso($conexion);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include_once dirname(__DIR__) . '/portada/cabecera.php' ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SUBLIMACION</title>
</head>
<?php include_once dirname(__DIR__) . '/menu/menu.php'; ?>
<main class="app-content">
     <div class="app-title">
        <div>
            <h1><i class="fas fa-home"></i> Menú Principal</h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><i class="fas fa-home fa-lg"></i></li>
            <li class="breadcrumb-item">Menú Principal</li>
        </ul>
    </div>
    <div class="row" id="cargainfo">
    </div>
</main>
<?php include_once dirname(__DIR__) . '/portada/footer.php'; ?>