<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/movimientos/compras/compras.php');

$stProveedor = $conexion->query("SELECT id_proveedor, ruc, razon_social FROM proveedores WHERE activo = true ORDER BY razon_social ASC");
$proveedoresDisponibles = $stProveedor->fetchAll(PDO::FETCH_ASSOC);

$stInsumo = $conexion->query("SELECT i.id_insumo, i.codigo, i.nombre, u.nombre AS nombre_unidad
                               FROM insumos i
                               LEFT JOIN unidades_medida u ON u.id_unidad = i.id_unidad
                               WHERE i.activo = true ORDER BY i.nombre ASC");
$insumosDisponibles = $stInsumo->fetchAll(PDO::FETCH_ASSOC);
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

    <!-- pnotify -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/pnotify/PNotify.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/pnotify/PNotifyMobile.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/pnotify/BrightTheme.css">
    <script src="<?php echo BASE_URL; ?>/pnotify/PNotify.js"></script>
    <script src="<?php echo BASE_URL; ?>/pnotify/PNotifyMobile.js"></script>
    <script src="<?php echo BASE_URL; ?>/pnotify/PNotifyButtons.js"></script>

    <title>SUBLIMACION | Nueva Compra</title>
</head>
<?php include_once RUTA_PROYECTO . '/menu/menu.php'; ?>
<main class="app-content">
    <div class="app-title">
        <div>
            <h1><i class="fas fa-shopping-cart"></i> Nueva Compra</h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><i class="fas fa-home fa-lg"></i></li>
            <li class="breadcrumb-item">Movimientos</li>
            <li class="breadcrumb-item"><a href="compras.php">Compras</a></li>
            <li class="breadcrumb-item">Nueva</li>
        </ul>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-body">
                    <form id="form_compra">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Proveedor *</label>
                                <select class="form-select select2" id="select_proveedor_compra" name="id_proveedor" style="width:100%;" required>
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($proveedoresDisponibles as $prov): ?>
                                        <option value="<?php echo $prov['id_proveedor']; ?>">
                                            <?php echo htmlspecialchars($prov['razon_social'] . ($prov['ruc'] ? ' (' . $prov['ruc'] . ')' : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Fecha de Compra *</label>
                                <input type="date" class="form-control" name="fecha_compra" id="input_fecha_compra" required>
                            </div>
                        </div>

                        <hr>
                        <h6>Detalle de la Compra</h6>
                        <table class="table table-sm" id="tbl_detalle_compra">
                            <thead>
                                <tr>
                                    <th style="width:28%">Insumo</th>
                                    <th style="width:10%">Unidad</th>
                                    <th style="width:14%">Cantidad</th>
                                    <th style="width:16%">Precio Unitario</th>
                                    <th style="width:16%">Subtotal</th>
                                    <th style="width:5%"></th>
                                </tr>
                            </thead>
                            <tbody id="detalle_compra_body">
                                <!-- Las filas se agregan dinámicamente con JS -->
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Total</strong></td>
                                    <td id="total_compra"><strong>Gs. 0</strong></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnAgregarLinea">
                            <i class="fas fa-plus"></i> Agregar insumo
                        </button>

                        <div class="mb-3 mt-4">
                            <label class="form-label">Observaciones</label>
                            <textarea class="form-control" name="observaciones" rows="2"></textarea>
                        </div>

                        <div class="mt-4">
                            <a href="compras.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary" id="btnRegistrarCompra">
                                <i class="fas fa-save"></i> Registrar Compra
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Plantilla de fila de detalle, oculta, clonada por JS -->
<template id="plantilla_fila_detalle">
    <tr class="fila-detalle">
        <td>
            <select class="form-select select2-insumo" name="detalle_insumo[]" style="width:100%;" required>
                <option value="">Seleccione...</option>
                <?php foreach ($insumosDisponibles as $ins): ?>
                    <option value="<?php echo $ins['id_insumo']; ?>" data-unidad="<?php echo htmlspecialchars($ins['nombre_unidad'] ?? ''); ?>">
                        <?php echo htmlspecialchars($ins['codigo'] . ' - ' . $ins['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input type="text" class="form-control input-unidad" readonly></td>
        <td><input type="number" step="0.001" min="0.001" class="form-control input-cantidad" name="detalle_cantidad[]" required></td>
        <td><input type="number" step="0.01" min="0" class="form-control input-precio" name="detalle_precio[]" required></td>
        <td><input type="text" class="form-control input-subtotal" readonly value="Gs. 0"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger btn-quitar-linea"><i class="fas fa-trash"></i></button></td>
    </tr>
</template>

<script src="<?php echo BASE_URL; ?>/modulos/movimientos/compras/js/nueva_compra.js?v=<?php echo date('YmdHi'); ?>"></script>
<?php include_once RUTA_PROYECTO . '/portada/footer.php' ?>

</html>