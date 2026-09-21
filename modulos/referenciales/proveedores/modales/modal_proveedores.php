<!-- Modal de alta de proveedor -->
<div class="modal fade" id="modalProveedor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="form_proveedor" action="ajax/guardar_proveedor.php" method="POST" data-ajax-tipo="abm" data-tabla="#tbl_proveedores">
                <!-- CAMPO OCULTO PARA ID (edición) -->
                <input type="hidden" name="id_proveedor" value="">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-truck-loading"></i> Nuevo Proveedor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Razón Social *</label>
                        <input type="text" class="form-control" name="razon_social" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">RUC</label>
                        <input type="text" class="form-control" name="ruc">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contacto</label>
                        <input type="text" class="form-control" name="contacto">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Teléfono</label>
                        <input type="text" class="form-control" name="telefono">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo</label>
                        <input type="email" class="form-control" name="correo">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Dirección</label>
                        <input type="text" class="form-control" name="direccion">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Departamento</label>
                            <select class="form-select" id="select_departamento" name="id_departamento">
                                <option value="">Seleccione...</option>
                                <?php foreach ($departamentos as $d): ?>
                                    <option value="<?php echo $d['id_departamento']; ?>"><?php echo $d['nombre_departamento']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ciudad</label>
                            <select class="form-select" id="select_ciudad" name="id_ciudad" disabled>
                                <option value="">Elija primero un departamento</option>
                            </select>
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