me ayudas a acomodar este codigo, al agragar la Descripcion, visualmente no se bien.
<!-- Modal de alta/edición de tipo de documento -->
<div class="modal fade" id="modalcategorias" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="form_categorias" action="ajax/guardar_categorias.php" method="POST" data-ajax-tipo="abm" data-tabla="#tbl_categorias">
                <input type="hidden" name="id_categoria" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-id-card"></i> Nuevo Tipo de Documento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" class="form-control" name="nombre_categorias" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea
                            class="form-control"
                            name="categorias_descripcion"
                            rows="4"
                            placeholder="Ingrese una descripción..."></textarea>
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

<!-- Modal de confirmación de eliminación (fijo) -->
<div class="modal fade" id="modalConfirmEliminar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar eliminación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="mensajeConfirmEliminar">¿Está seguro de eliminar este tipo de documento?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarEliminar">Sí, eliminar</button>
            </div>
        </div>
    </div>
</div>