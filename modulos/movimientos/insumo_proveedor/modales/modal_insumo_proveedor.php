<!-- Modal de alta/edición de proveedor del insumo -->
<div class="modal fade" id="modalInsumoProveedor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="form_insumo_proveedor" action="ajax/guardar_insumo_proveedor.php" method="POST" data-ajax-tipo="abm" data-tabla="#tbl_insumo_proveedor">
                <!-- CAMPO OCULTO PARA ID (edición) -->
                <input type="hidden" name="id_insumo_proveedor" value="">
                <input type="hidden" name="id_insumo" value="<?php echo $insumo['id_insumo']; ?>">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-truck"></i> Agregar Proveedor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Proveedor *</label>
                        <select class="form-select select2" id="select_proveedor" name="id_proveedor" style="width:100%;" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($proveedoresDisponibles as $prov): ?>
                                <option value="<?php echo $prov['id_proveedor']; ?>">
                                    <?php echo htmlspecialchars($prov['razon_social'] . ($prov['ruc'] ? ' (' . $prov['ruc'] . ')' : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Precio *</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" name="precio" id="input_precio" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Calidad</label>
                            <select class="form-select" name="calidad" id="select_calidad">
                                <option value="">Seleccione...</option>
                                <option value="1">Baja</option>
                                <option value="2">Media</option>
                                <option value="3">Alta</option>
                                <option value="4">Premium</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha de Cotización</label>
                            <input type="date" class="form-control" name="fecha_cotizacion" id="input_fecha_cotizacion">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tiempo de Entrega (días)</label>
                            <input type="number" step="1" min="0" class="form-control" name="tiempo_entrega" id="input_tiempo_entrega">
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="favorito" id="switch_favorito" value="1">
                        <label class="form-check-label" for="switch_favorito">Marcar como proveedor favorito para este insumo</label>
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