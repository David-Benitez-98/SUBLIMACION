<!-- Modal de alta/edición de insumo del producto -->
<div class="modal fade" id="modalProductoInsumo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="form_producto_insumo" action="ajax/guardar_producto_insumo.php" method="POST" data-ajax-tipo="abm" data-tabla="#tbl_producto_insumo">
                <!-- CAMPO OCULTO PARA ID (edición) -->
                <input type="hidden" name="id_producto_insumo" value="">
                <input type="hidden" name="id_producto" value="<?php echo $producto['id_producto']; ?>">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-boxes"></i> Agregar Insumo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Insumo *</label>
                        <select class="form-select select2" id="select_insumo" name="id_insumo" style="width:100%;" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($insumosDisponibles as $ins): ?>
                                <option value="<?php echo $ins['id_insumo']; ?>" data-unidad="<?php echo htmlspecialchars($ins['nombre_unidad'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($ins['codigo'] . ' - ' . $ins['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unidad de Medida</label>
                            <input type="text" class="form-control" id="input_unidad" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cantidad Requerida *</label>
                            <input type="number" step="0.001" min="0.001" class="form-control" name="cantidad_requerida" id="input_cantidad" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
