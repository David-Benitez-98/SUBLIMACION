$(document).ready(function () {
    const DELAY_NOTIFICACION = 3000;
    var dt;

    // Inicializar DataTable
    function inicializarTabla() {
        dt = $("#tbl_tipo_documento").DataTable({
            autoWidth: false,
            ajax: {
                url: "ajax/listar_tipo_documento.php",
                dataSrc: "data"
            },
            columns: [
                { data: "id_tipo_documento" },
                { data: "nombre_tipo_documento" },
                { data: "acciones", orderable: false, searchable: false }
            ],
            language: {
                url: "/sublimacion/js/es-ES.json"
            }
        });
    }

    inicializarTabla();

    // ====== Editar ======
    $("#tbl_tipo_documento").on("click", ".btn-editar", function () {
        var id = $(this).data("id");
        // Obtener datos de la fila desde DataTable
        var data = dt.row($(this).closest('tr')).data();
        if (!data) return;

        var $form = $("#form_tipo_documento");
        $form.find('input[name="id_tipo_documento"]').val(data.id_tipo_documento);
        $form.find('input[name="nombre_tipo_documento"]').val(data.nombre_tipo_documento);

        $("#modalTipoDocumento .modal-title").html('<i class="fas fa-id-card"></i> Editar Tipo de Documento');
        $("#modalTipoDocumento .modal-footer .btn-primary").text("Actualizar");
        // Cambiar método a PUT (se enviará mediante data-ajax-tipo, pero lo manejamos con un campo oculto o data-*)
        $form.attr("data-ajax-metodo", "PUT");
        $("#modalTipoDocumento").modal("show");
    });

    // ====== Eliminar con confirmación ======
    $("#tbl_tipo_documento").on("click", ".btn-eliminar", function () {
        var id = $(this).data("id");
        var nombre = $(this).data("nombre") || "este tipo de documento";

        var mensaje = "¿Está seguro de eliminar <strong>" + nombre + "</strong>?";
        $("#mensajeConfirmEliminar").html(mensaje);
        $("#btnConfirmarEliminar").data("id", id);

        var modal = new bootstrap.Modal(document.getElementById("modalConfirmEliminar"));
        modal.show();
    });

    // ====== Confirmar eliminación ======
    $("#btnConfirmarEliminar").on("click", function () {
        var id = $(this).data("id");
        var modal = bootstrap.Modal.getInstance(document.getElementById("modalConfirmEliminar"));
        modal.hide();

        $.ajax({
            url: "ajax/guardar_tipo_documento.php",
            type: "DELETE",
            data: { id_tipo_documento: id },
            dataType: "json",
            success: function (res) {
                if (res.msg === "CORRECTO") {
                    PNotify.notice({
                        title: "Éxito",
                        text: res.texto,
                        type: "success",
                        delay: DELAY_NOTIFICACION,
                        styling: "brighttheme"
                    });
                    dt.ajax.reload(function () { dt.columns.adjust(); }, false);
                } else {
                    PNotify.notice({
                        title: "Error",
                        text: res.texto,
                        type: "error",
                        delay: DELAY_NOTIFICACION,
                        styling: "brighttheme"
                    });
                }
            },
            error: function () {
                PNotify.notice({
                    title: "Error",
                    text: "Error al eliminar",
                    type: "error",
                    delay: DELAY_NOTIFICACION,
                    styling: "brighttheme"
                });
            }
        });
    });

    // ====== Al abrir modal para nuevo ======
    $("#modalTipoDocumento").on("show.bs.modal", function () {
        var $form = $("#form_tipo_documento");
        // Si no hay id (nuevo), resetear
        if (!$form.find('input[name="id_tipo_documento"]').val()) {
            $form.find('input[name="nombre_tipo_documento"]').val("");
            $("#modalTipoDocumento .modal-title").html('<i class="fas fa-id-card"></i> Nuevo Tipo de Documento');
            $("#modalTipoDocumento .modal-footer .btn-primary").text("Guardar");
            $form.attr("data-ajax-metodo", "POST");
        }
    });

    // ====== Antes de enviar el formulario (para PUT) ======
    $("#form_tipo_documento").on("submit", function (e) {
        var metodo = $(this).attr("data-ajax-metodo") || "POST";
        if (metodo === "PUT") {
            // Cambiar el action para que el archivo PHP maneje PUT
            // O podemos enviar con POST y un campo _method
            var $form = $(this);
            var data = $form.serialize();
            data += "&_method=PUT";
            // Usamos el mismo archivo pero con POST y _method
            $.ajax({
                url: $form.attr("action"),
                type: "POST",
                data: data,
                dataType: "json",
                success: function (res) {
                    if (res.msg === "CORRECTO") {
                        PNotify.notice({
                            title: "Éxito",
                            text: res.texto,
                            type: "success",
                            delay: DELAY_NOTIFICACION,
                            styling: "brighttheme"
                        });
                        dt.ajax.reload(function () { dt.columns.adjust(); }, false);
                        $("#modalTipoDocumento").modal("hide");
                    } else {
                        PNotify.notice({
                            title: "Error",
                            text: res.texto,
                            type: "error",
                            delay: DELAY_NOTIFICACION,
                            styling: "brighttheme"
                        });
                    }
                },
                error: function () {
                    PNotify.notice({
                        title: "Error",
                        text: "Error al procesar",
                        type: "error",
                        delay: DELAY_NOTIFICACION,
                        styling: "brighttheme"
                    });
                }
            });
            e.preventDefault();
            return false;
        }
    });

    // ====== Reseteo al cerrar modal ======
    $("#modalTipoDocumento").on("hidden.bs.modal", function () {
        var $form = $("#form_tipo_documento");
        $form[0].reset();
        $form.find('input[name="id_tipo_documento"]').val("");
        $form.attr("data-ajax-metodo", "POST");
        $("#modalTipoDocumento .modal-title").html('<i class="fas fa-id-card"></i> Nuevo Tipo de Documento');
        $("#modalTipoDocumento .modal-footer .btn-primary").text("Guardar");
    });

    // Limpiar modal de confirmación al cerrar
    $("#modalConfirmEliminar").on("hidden.bs.modal", function () {
        $("#mensajeConfirmEliminar").html("¿Está seguro de eliminar este tipo de documento?");
        $("#btnConfirmarEliminar").data("id", "");
    });
});