<!-- Modal de alta de producto -->
<div class="modal fade" id="modalProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="form_producto" action="ajax/guardar_producto.php" method="POST" enctype="multipart/form-data" data-tabla="#tbl_productos">
                <!-- CAMPO OCULTO PARA ID (edición) -->
                <input type="hidden" name="id_producto" value="">
                <!-- CAMPO OCULTO PARA CONSERVAR IMAGEN ACTUAL -->
                <input type="hidden" name="imagen_actual" id="imagen_actual" value="">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-box-open"></i> Nuevo Producto</h5>
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
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Categoría *</label>
                            <select class="form-select select2" id="select_categoria" name="id_categoria" style="width:100%;" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($categorias as $c): ?>
                                    <option value="<?php echo $c['id_categoria']; ?>"><?php echo $c['nombre']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tiempo Prod. (Horas)</label>
                            <input type="number" step="1" min="0" class="form-control" name="tiempo_produccion" value="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Precio Venta *</label>
                            <input type="text" class="form-control" id="input_precio_venta" name="precio_venta" inputmode="numeric" value="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Imagen</label>
                            <input type="file" class="form-control" name="imagen" id="input_imagen" accept="image/*">
                            <img id="preview_imagen" src="" alt="" style="display:none;width:60px;height:60px;object-fit:cover;margin-top:8px;">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="3"></textarea>
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