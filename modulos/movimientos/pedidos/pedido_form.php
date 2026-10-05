<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/movimientos/pedidos/pedidos.php');

$idPedido = intval($_GET['id_pedido'] ?? 0);
$esEdicion = $idPedido > 0;

$pedido = null;
$lineasExistentes = [];

if ($esEdicion) {
    $stPedido = $conexion->prepare("SELECT * FROM pedidos WHERE id_pedido = :id");
    $stPedido->execute(['id' => $idPedido]);
    $pedido = $stPedido->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        die('El pedido no existe.');
    }

    $stLineas = $conexion->prepare("SELECT pd.id_detalle, pd.id_producto, pd.cantidad, pd.precio_unitario, pd.subtotal,
                                            pr.codigo, pr.nombre
                                     FROM pedido_detalle pd
                                     INNER JOIN productos pr ON pr.id_producto = pd.id_producto
                                     WHERE pd.id_pedido = :id
                                     ORDER BY pd.id_detalle ASC");
    $stLineas->execute(['id' => $idPedido]);
    $lineasExistentes = $stLineas->fetchAll(PDO::FETCH_ASSOC);
}

$stCliente = $conexion->query("SELECT id_cliente, nombre, apellido, numero_documento FROM clientes WHERE activo = true ORDER BY nombre ASC");
$clientesDisponibles = $stCliente->fetchAll(PDO::FETCH_ASSOC);

$stProducto = $conexion->query("SELECT id_producto, codigo, nombre, precio_venta FROM productos WHERE activo = true ORDER BY nombre ASC");
$productosDisponibles = $stProducto->fetchAll(PDO::FETCH_ASSOC);
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

    <title>SUBLIMACION | <?php echo $esEdicion ? 'Editar Pedido' : 'Nuevo Pedido'; ?></title>
</head>
<?php include_once RUTA_PROYECTO . '/menu/menu.php'; ?>
<main class="app-content">
    <div class="app-title">
        <div>
            <h1><i class="fas fa-clipboard-list"></i> <?php echo $esEdicion ? 'Editar Pedido ' . htmlspecialchars($pedido['numero_pedido']) : 'Nuevo Pedido'; ?></h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><i class="fas fa-home fa-lg"></i></li>
            <li class="breadcrumb-item">Movimientos</li>
            <li class="breadcrumb-item"><a href="pedidos.php">Pedidos</a></li>
            <li class="breadcrumb-item"><?php echo $esEdicion ? 'Editar' : 'Nuevo'; ?></li>
        </ul>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-body">
                    <form id="form_pedido">
                        <input type="hidden" name="id_pedido" value="<?php echo $idPedido; ?>">

                        <div class="row">
                            <div class="col-md-5 mb-3">
                                <label class="form-label">Cliente *</label>
                                <select class="form-select select2" id="select_cliente" name="id_cliente" style="width:100%;" required>
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($clientesDisponibles as $cli): ?>
                                        <option value="<?php echo $cli['id_cliente']; ?>" <?php echo ($esEdicion && $pedido['id_cliente'] == $cli['id_cliente']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cli['nombre'] . ' ' . $cli['apellido'] . ($cli['numero_documento'] ? ' (' . $cli['numero_documento'] . ')' : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Fecha de Pedido *</label>
                                <input type="date" class="form-control" name="fecha_pedido" id="input_fecha_pedido"
                                    value="<?php echo $esEdicion ? $pedido['fecha_pedido'] : ''; ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Estado</label>
                                <?php
                                $estadoActual = $esEdicion ? $pedido['estado'] : 'presupuestado';
                                $estados = [
                                    'presupuestado' => 'Presupuestado',
                                    'confirmado' => 'Confirmado',
                                    'en_produccion' => 'En Producción',
                                    'listo' => 'Listo',
                                    'entregado' => 'Entregado',
                                    'cancelado' => 'Cancelado',
                                ];
                                ?>
                                <select class="form-select" id="select_estado" name="estado">
                                    <?php foreach ($estados as $valor => $etiqueta): ?>
                                        <option value="<?php echo $valor; ?>" <?php echo $estadoActual === $valor ? 'selected' : ''; ?>><?php echo $etiqueta; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div id="aviso_cancelado" class="alert alert-danger" style="display:none;">
                            Este pedido está cancelado y ya no se puede modificar. Si el cliente vuelve, cargá un pedido nuevo.
                        </div>
                        <div id="bloque_reponer_stock" class="alert alert-warning" style="display:none;">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="check_reponer_stock">
                                <label class="form-check-label" for="check_reponer_stock">
                                    Reponer automáticamente el stock de este pedido.
                                    <small class="d-block text-muted">Desmarcalo si los insumos ya fueron usados y no corresponde devolverlos.</small>
                                </label>
                            </div>
                        </div>
                        <div id="aviso_faltantes" class="alert alert-danger" style="display:none;">
                            <i class="fas fa-exclamation-triangle"></i>
                            Hay insumos faltantes (ver <strong>Materiales del Pedido</strong> más abajo). Al confirmar el consumo en producción, si no alcanza el stock, el sistema lo va a bloquear.
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Entrega Estimada</label>
                                <input type="date" class="form-control" name="fecha_entrega_estimada"
                                    value="<?php echo $esEdicion ? $pedido['fecha_entrega_estimada'] : ''; ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Entrega Real</label>
                                <input type="date" class="form-control" name="fecha_entrega_real" id="input_fecha_entrega_real"
                                    value="<?php echo $esEdicion ? $pedido['fecha_entrega_real'] : ''; ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Seña Pagada</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="sena_pagada" id="input_sena_pagada"
                                    value="<?php echo $esEdicion ? (float) $pedido['sena_pagada'] : 0; ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Saldo</label>
                                <input type="text" class="form-control" id="input_saldo" readonly>
                            </div>
                        </div>

                        <hr>
                        <h6>Detalle del Pedido</h6>
                        <table class="table table-sm" id="tbl_detalle_pedido">
                            <thead>
                                <tr>
                                    <th style="width:32%">Producto</th>
                                    <th style="width:14%">Cantidad</th>
                                    <th style="width:18%">Precio Unitario</th>
                                    <th style="width:18%">Subtotal</th>
                                    <th style="width:5%"></th>
                                </tr>
                            </thead>
                            <tbody id="detalle_pedido_body">
                                <?php foreach ($lineasExistentes as $linea): ?>
                                    <tr class="fila-detalle">
                                        <td>
                                            <select class="form-select select2-producto" name="detalle_producto[]" style="width:100%;" required>
                                                <option value="">Seleccione...</option>
                                                <?php foreach ($productosDisponibles as $prod): ?>
                                                    <option value="<?php echo $prod['id_producto']; ?>" data-precio="<?php echo $prod['precio_venta']; ?>"
                                                        <?php echo $prod['id_producto'] == $linea['id_producto'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($prod['codigo'] . ' - ' . $prod['nombre']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td><input type="number" step="0.01" min="0.01" class="form-control input-cantidad" name="detalle_cantidad[]" value="<?php echo (float) $linea['cantidad']; ?>" required></td>
                                        <td><input type="number" step="0.01" min="0" class="form-control input-precio" name="detalle_precio[]" value="<?php echo (float) $linea['precio_unitario']; ?>" required></td>
                                        <td><input type="text" class="form-control input-subtotal" readonly value="Gs. 0"></td>
                                        <td><button type="button" class="btn btn-sm btn-outline-danger btn-quitar-linea"><i class="fas fa-trash"></i></button></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Total</strong></td>
                                    <td id="total_pedido"><strong>Gs. 0</strong></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnAgregarLinea">
                            <i class="fas fa-plus"></i> Agregar producto
                        </button>

                        <div class="mb-3 mt-4">
                            <label class="form-label">Observaciones</label>
                            <textarea class="form-control" name="observaciones" rows="2"><?php echo $esEdicion ? htmlspecialchars($pedido['observaciones'] ?? '') : ''; ?></textarea>
                        </div>

                        <div class="mt-4">
                            <a href="pedidos.php" class="btn btn-secondary">Volver</a>
                            <?php if ($esEdicion): ?>
                                <a href="<?php echo BASE_URL; ?>/reportes/pedidos/imprimir_pedido.php?id_pedido=<?php echo $idPedido; ?>" target="_blank" class="btn btn-outline-dark"> <i class="fas fa-print"></i> Imprimir
                                </a>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary" id="btnGuardarPedido">
                                <i class="fas fa-save"></i> Guardar Pedido
                            </button>
                        </div>
                    </form>

                    <?php if ($esEdicion): ?>
                        <hr class="mt-4">
                        <h6><i class="fas fa-boxes"></i> Materiales del Pedido</h6>
                        <p class="text-muted small" id="texto_ayuda_materiales">Según la receta de cada producto (producto_insumo) y las líneas ya guardadas.</p>
                        <table class="table table-sm" id="tbl_materiales_necesarios">
                            <thead>
                                <tr>
                                    <th>Insumo</th>
                                    <th>Unidad</th>
                                    <th>Necesario</th>
                                    <th>Ya descontado</th>
                                    <th>Stock Actual</th>
                                    <th>Faltante</th>
                                    <th id="col_utilizado" style="display:none;">Utilizado</th>
                                </tr>
                            </thead>
                            <tbody id="materiales_necesarios_body">
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Cargando...</td>
                                </tr>
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-success btn-sm" id="btnConfirmarConsumo" style="display:none;">
                            <i class="fas fa-check-double"></i> Confirmar consumo
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Plantilla de fila de detalle, oculta, clonada por JS para líneas nuevas -->
<template id="plantilla_fila_detalle_pedido">
    <tr class="fila-detalle">
        <td>
            <select class="form-select select2-producto" name="detalle_producto[]" style="width:100%;" required>
                <option value="">Seleccione...</option>
                <?php foreach ($productosDisponibles as $prod): ?>
                    <option value="<?php echo $prod['id_producto']; ?>" data-precio="<?php echo $prod['precio_venta']; ?>">
                        <?php echo htmlspecialchars($prod['codigo'] . ' - ' . $prod['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input type="number" step="0.01" min="0.01" class="form-control input-cantidad" name="detalle_cantidad[]" required></td>
        <td><input type="number" step="0.01" min="0" class="form-control input-precio" name="detalle_precio[]" required></td>
        <td><input type="text" class="form-control input-subtotal" readonly value="Gs. 0"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger btn-quitar-linea"><i class="fas fa-trash"></i></button></td>
    </tr>
</template>

<script>
    const ES_EDICION = <?php echo $esEdicion ? 'true' : 'false'; ?>;
    const ID_PEDIDO = <?php echo $idPedido; ?>;
    const ESTADO_ANTERIOR = <?php echo $esEdicion ? json_encode($pedido['estado']) : 'null'; ?>;
</script>
<script src="<?php echo BASE_URL; ?>/modulos/movimientos/pedidos/js/pedido_form.js?v=<?php echo date('YmdHi'); ?>"></script>
<?php include_once RUTA_PROYECTO . '/portada/footer.php' ?>

</html>