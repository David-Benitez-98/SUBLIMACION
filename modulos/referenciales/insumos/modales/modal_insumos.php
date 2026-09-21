<!-- Modal de alta de insumo -->
<div class="modal fade" id="modalInsumo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="form_insumo" action="ajax/guardar_insumo.php" method="POST" data-ajax-tipo="abm" data-tabla="#tbl_insumos">
                <!-- CAMPO OCULTO PARA ID (edición) -->
                <input type="hidden" name="id_insumo" value="">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-boxes"></i> Nuevo Insumo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Código *</label>
                            <input type="text" class="form-control" name="codigo" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre *</label>
                            <input type="text" class="form-control" name="nombre" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Color</label>
                            <select class="form-select select2" id="select_color" name="id_color" style="width:100%;">
                                <option value="">Seleccione...</option>
                                <?php foreach ($colores as $c): ?>
                                    <option value="<?php echo $c['id_color']; ?>"><?php echo $c['nombre']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unidad de Medida *</label>
                            <select class="form-select select2" id="select_unidad" name="id_unidad" style="width:100%;" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($unidades as $u): ?>
                                    <option value="<?php echo $u['id_unidad']; ?>"><?php echo $u['nombre']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stock Actual</label>
                            <input type="number" step="1" min="0" class="form-control" name="stock_actual" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stock Mínimo</label>
                            <input type="number" step="1" min="0" class="form-control" name="stock_minimo" value="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ubicación</label>
                        <input type="text" class="form-control" name="ubicacion">
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