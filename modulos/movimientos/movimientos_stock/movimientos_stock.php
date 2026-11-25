<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';
require_once __DIR__ . '/ajax/helpers_movimientos.php';

exigirAcceso($conexion, '/modulos/movimientos/movimientos_stock/movimientos_stock.php');

// Listas para los filtros (el historial incluye insumos que hoy estén inactivos)
$stInsumos = $conexion->query("SELECT id_insumo, codigo, nombre FROM insumos ORDER BY nombre ASC");
$insumosFiltro = $stInsumos->fetchAll(PDO::FETCH_ASSOC);

$stCompras = $conexion->query("SELECT id_compra, numero_compra FROM compras ORDER BY id_compra DESC");
$comprasFiltro = $stCompras->fetchAll(PDO::FETCH_ASSOC);

$stUsuarios = $conexion->query("SELECT id_usuario, nombre FROM usuarios ORDER BY nombre ASC");
$usuariosFiltro = $stUsuarios->fetchAll(PDO::FETCH_ASSOC);

// Insumos para el formulario de ajuste (solo activos, con stock y unidad para mostrar como referencia)
$stInsumosAjuste = $conexion->query("SELECT i.id_insumo, i.codigo, i.nombre, i.stock_actual, u.nombre AS nombre_unidad
                                      FROM insumos i
                                      LEFT JOIN unidades_medida u ON u.id_unidad = i.id_unidad
                                      WHERE i.activo = true ORDER BY i.nombre ASC");
$insumosAjuste = $stInsumosAjuste->fetchAll(PDO::FETCH_ASSOC);
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

    <title>SUBLIMACION | Movimientos de Stock</title>
</head>
<?php include_once RUTA_PROYECTO . '/menu/menu.php'; ?>
<main class="app-content">
    <div class="app-title">
        <div>
            <h1><i class="fas fa-exchange-alt"></i> Movimientos de Stock</h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><i class="fas fa-home fa-lg"></i></li>
            <li class="breadcrumb-item">Movimientos</li>
            <li class="breadcrumb-item">Movimientos de Stock</li>
        </ul>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-title-w-btn line-head">
                    <h5 class="title">Historial de inventario</h5>
                    <p><button type="button" class="btn btn-primary icon-btn text-white" id="btnNuevoAjuste"><i class="fas fa-plus"></i> Nuevo ajuste / merma</button></p>
                </div>
                <div class="tile-body">

                    <!-- Filtros -->
                    <div class="row mb-3">
                        <div class="col-md-3 mb-2">
                            <label class="form-label">Insumo</label>
                            <select class="form-select" id="f_insumo" style="width:100%;">
                                <option value="">Todos</option>
                                <?php foreach ($insumosFiltro as $ins): ?>
                                    <option value="<?php echo $ins['id_insumo']; ?>">
                                        <?php echo htmlspecialchars($ins['codigo'] . ' - ' . $ins['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label">Tipo</label>
                            <select class="form-select" id="f_clase" style="width:100%;">
                                <option value="">Todos</option>
                                <?php foreach (clasesMovimiento() as $codigo => $datos): ?>
                                    <option value="<?php echo $codigo; ?>"><?php echo $datos[0]; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label">Desde</label>
                            <input type="date" class="form-control" id="f_desde">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label">Hasta</label>
                            <input type="date" class="form-control" id="f_hasta">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label">Compra</label>
                            <select class="form-select" id="f_compra" style="width:100%;">
                                <option value="">Todas</option>
                                <?php foreach ($comprasFiltro as $cmp): ?>
                                    <option value="<?php echo $cmp['id_compra']; ?>">
                                        <?php echo htmlspecialchars($cmp['numero_compra']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label">Pedido (N°)</label>
                            <input type="number" min="1" class="form-control" id="f_pedido" placeholder="Ej: 150">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label">Usuario</label>
                            <select class="form-select" id="f_usuario" style="width:100%;">
                                <option value="">Todos</option>
                                <?php foreach ($usuariosFiltro as $usr): ?>
                                    <option value="<?php echo $usr['id_usuario']; ?>">
                                        <?php echo htmlspecialchars($usr['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-2">
                            <button type="button" class="btn btn-primary" id="btnBuscar"><i class="fas fa-search"></i> Buscar</button>
                            <button type="button" class="btn btn-secondary" id="btnLimpiar"><i class="fas fa-eraser"></i> Limpiar</button>
                            <button type="button" class="btn btn-success" id="btnExportar"><i class="fas fa-file-csv"></i> Exportar</button>
                        </div>
                    </div>

                    <table id="tbl_movimientos" class="table" style="width:100%;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Fecha/Hora</th>
                                <th>Insumo</th>
                                <th>Tipo</th>
                                <th>Cantidad</th>
                                <th>Stock anterior</th>
                                <th>Stock nuevo</th>
                                <th>Origen</th>
                                <th>Usuario</th>
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

<!-- Modal de detalle de movimiento (solo lectura) -->
<div class="modal fade" id="modalDetalleMovimiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-info-circle"></i> Movimiento #<span id="det_id"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm">
                    <tbody>
                        <tr><th style="width:35%">Fecha y hora</th><td id="det_fecha"></td></tr>
                        <tr><th>Insumo</th><td id="det_insumo"></td></tr>
                        <tr><th>Unidad de medida</th><td id="det_unidad"></td></tr>
                        <tr><th>Tipo</th><td id="det_clase"></td></tr>
                        <tr><th>Cantidad</th><td id="det_cantidad"></td></tr>
                        <tr><th>Stock anterior</th><td id="det_stock_anterior"></td></tr>
                        <tr><th>Stock nuevo</th><td id="det_stock_nuevo"></td></tr>
                        <tr><th>Origen</th><td id="det_origen"></td></tr>
                        <tr><th>Usuario</th><td id="det_usuario"></td></tr>
                        <tr><th>Motivo / Observación</th><td id="det_motivo"></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de ajuste / merma -->
<div class="modal fade" id="modalAjuste" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="form_ajuste">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-sliders-h"></i> Ajuste / Merma de stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Insumo *</label>
                        <select class="form-select" id="aj_insumo" style="width:100%;" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($insumosAjuste as $ins): ?>
                                <option value="<?php echo $ins['id_insumo']; ?>"
                                    data-stock="<?php echo number_format((float) $ins['stock_actual'], 3, '.', ''); ?>"
                                    data-unidad="<?php echo htmlspecialchars($ins['nombre_unidad'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($ins['codigo'] . ' - ' . $ins['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo de operación *</label>
                        <select class="form-select" id="aj_clase" required>
                            <option value="">Seleccione...</option>
                            <option value="AJUSTE_POSITIVO">Ajuste positivo (sobran unidades en el conteo físico)</option>
                            <option value="AJUSTE_NEGATIVO">Ajuste negativo (faltan unidades en el conteo físico)</option>
                            <option value="MERMA">Merma (material dañado o perdido)</option>
                        </select>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Cantidad *</label>
                            <input type="number" step="0.001" min="0.001" class="form-control" id="aj_cantidad" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Unidad</label>
                            <input type="text" class="form-control" id="aj_unidad" readonly>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Stock actual:</strong> <span id="aj_stock_actual">—</span>
                        </div>
                        <div class="col-md-6">
                            <strong>Stock resultante:</strong> <span id="aj_stock_resultante">—</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Motivo *</label>
                        <textarea class="form-control" id="aj_motivo" rows="2" maxlength="150" required placeholder="Ej: Inventario físico del 15/10, material dañado, etc."></textarea>
                    </div>
                    <small class="text-muted">El movimiento queda registrado en el historial y no se puede editar ni borrar. Para corregirlo hay que registrar un nuevo ajuste.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarAjuste"><i class="fas fa-save"></i> Registrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/modulos/movimientos/movimientos_stock/js/movimientos_stock.js?v=<?php echo date('YmdHi'); ?>"></script>
<?php include_once RUTA_PROYECTO . '/portada/footer.php' ?>

</html>
