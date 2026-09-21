<!-- Modal de alta de cliente -->
<div class="modal fade" id="modalCliente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="form_cliente" action="ajax/guardar_cliente.php" method="POST" data-ajax-tipo="abm" data-tabla="#tbl_clientes">
                <!-- CAMPO OCULTO PARA ID (edición) -->
                <input type="hidden" name="id_cliente" value="">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Nuevo Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label class="form-label">Tipo de documento</label>
                            <select class="form-select" name="id_tipo_documento">
                                <option value="">Seleccione...</option>
                                <?php foreach ($tiposDocumento as $td): ?>
                                    <option value="<?php echo $td['id_tipo_documento']; ?>"><?php echo $td['nombre_tipo_documento']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-7 mb-3">
                            <label class="form-label">N° Documento</label>
                            <input type="text" class="form-control" name="numero_documento">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" class="form-control" name="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Apellido</label>
                        <input type="text" class="form-control" name="apellido">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Teléfono</label>
                        <input type="text" class="form-control" name="telefono">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email">
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