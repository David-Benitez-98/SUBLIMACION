$(document).ready(function () {
    const DELAY_NOTIFICACION = 3000;
    var dt;

    function inicializarTabla() {
        dt = $("#tbl_ciudad").DataTable({
            autoWidth: true,
            ajax: {
                url: "ajax/listar_ciudades.php",
                dataSrc: "data"
            },
            columns: [
                { data: "id_ciudad", width: "10%" },
                { data: "nombre_ciudad", width: "30%" },
                { data: "nombre_departamento", defaultContent: "", width: "30%" },
                { data: "acciones", orderable: false, searchable: false, width: "30%", className: "text-center" }
            ],
            language: {
                url: "/sublimacion/js/es-ES.json"
            },
            drawCallback: function () {
                // Fuerza el recálculo de anchos después de cada render de datos
                this.api().columns.adjust();
            }
        });
    }
    inicializarTabla();

    // Recalcula anchos si cambia el tamaño de ventana o el sidebar se colapsa/expande
    $(window).on("resize", function () {
        if (dt) {
            dt.columns.adjust();
        }
    });

    // Editar
    $("#tbl_ciudad").on("click", ".btn-editar", function () {
        var id = $(this).data("id");
        var data = dt.row($(this).closest('tr')).data();
        if (!data) return;
        var $form = $("#form_ciudad");
        $form.find('input[name="id_ciudad"]').val(data.id_ciudad);
        $form.find('input[name="nombre_ciudad"]').val(data.nombre_ciudad);
        // Necesitamos obtener el departamento asociado a esta ciudad para preseleccionarlo.
        // Lo obtenemos mediante AJAX
        $.ajax({
            url: "ajax/obtener_ciudad.php",
            type: "GET",
            data: { id: data.id_ciudad },
            dataType: "json",
            success: function (res) {
                if (res.id_departamento) {
                    $form.find('select[name="id_departamento"]').val(res.id_departamento);
                }
            },
            error: function () {
                // Si falla, no preseleccionamos
            }
        });
        $("#modalCiudad .modal-title").html('<i class="fas fa-city"></i> Editar Ciudad');
        $("#modalCiudad .modal-footer .btn-primary").text("Actualizar");

        // Remover cualquier _method previo antes de agregar uno nuevo (evita duplicados)
        $form.find('input[name="_method"]').remove();
        $form.append('<input type="hidden" name="_method" value="PUT">');

        $("#modalCiudad").modal("show");
    });

    // Al abrir modal para nuevo, remover _method y resetear combo
    $("#modalCiudad").on("show.bs.modal", function () {
        var $form = $("#form_ciudad");
        if (!$form.find('input[name="id_ciudad"]').val()) {
            $form.find('input[name="_method"]').remove();
            $form.find('select[name="id_departamento"]').val('');
            $("#modalCiudad .modal-title").html('<i class="fas fa-city"></i> Nueva Ciudad');
            $("#modalCiudad .modal-footer .btn-primary").text("Guardar");
        }
    });

    // Submit del formulario (igual que departamento)
    // Se usa .off("submit") antes de re-enlazar para evitar handlers duplicados
    $("#form_ciudad").off("submit").on("submit", function (e) {
        e.preventDefault();
        var $form = $(this);
        var $btnSubmit = $("#modalCiudad .modal-footer .btn-primary");

        // Evita doble envío si ya hay una petición en curso
        if ($btnSubmit.prop("disabled")) {
            return;
        }
        $btnSubmit.prop("disabled", true);

        var data = $form.serialize();
        var url = $form.attr('action');

        $.ajax({
            url: url,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function (res) {
                if (res.msg === "CORRECTO") {
                    PNotify.notice({ title: "Éxito", text: res.texto, type: "success", delay: DELAY_NOTIFICACION, styling: "brighttheme" });
                    dt.ajax.reload(null, false);
                    $("#modalCiudad").modal("hide");
                } else {
                    PNotify.notice({ title: "Error", text: res.texto, type: "error", delay: DELAY_NOTIFICACION, styling: "brighttheme" });
                }
            },
            error: function () {
                PNotify.notice({ title: "Error", text: "Error al procesar", type: "error", delay: DELAY_NOTIFICACION, styling: "brighttheme" });
            },
            complete: function () {
                $btnSubmit.prop("disabled", false);
            }
        });
    });

    // Cambiar estado
    $("#tbl_ciudad").on("click", ".btn-cambiar-estado", function () {
        var id = $(this).data("id");
        var nombre = $(this).data("nombre") || "esta ciudad";
        var estadoActual = $(this).text().trim();
        var nuevoEstado = estadoActual === "Activar" ? "activar" : "desactivar";
        var accion = nuevoEstado === "activar" ? "Activar" : "Desactivar";
        var mensaje = "¿Está seguro de " + nuevoEstado + " a <strong>" + nombre + "</strong>?";
        $("#mensajeConfirmEstadoCiudad").html(mensaje);
        var $btn = $("#btnConfirmarEstadoCiudad");
        $btn.data("id", id);
        $btn.text("Sí, " + accion)
            .removeClass("btn-success btn-danger")
            .addClass(accion === "Activar" ? "btn-success" : "btn-danger");
        var modal = new bootstrap.Modal(document.getElementById("modalConfirmEstadoCiudad"));
        modal.show();
    });

    // Confirmar estado
    $("#btnConfirmarEstadoCiudad").on("click", function () {
        var id = $(this).data("id");
        var modal = bootstrap.Modal.getInstance(document.getElementById("modalConfirmEstadoCiudad"));
        modal.hide();
        $.ajax({
            url: "ajax/cambiar_estado_ciudad.php",
            type: "POST",
            data: { id: id },
            dataType: "json",
            success: function (res) {
                if (res.msg === "CORRECTO") {
                    var tipo = res.texto.includes("DESACTIVADO") ? "error" : "success";
                    PNotify.notice({ title: "Éxito", text: res.texto, type: tipo, delay: DELAY_NOTIFICACION, styling: "brighttheme" });
                    dt.ajax.reload(null, false);
                } else {
                    PNotify.notice({ title: "Error", text: res.texto, type: "error", delay: DELAY_NOTIFICACION, styling: "brighttheme" });
                }
            },
            error: function () {
                PNotify.notice({ title: "Error", text: "Error al cambiar estado", type: "error", delay: DELAY_NOTIFICACION, styling: "brighttheme" });
            }
        });
    });

    // Limpiar al cerrar modal de confirmación
    $("#modalConfirmEstadoCiudad").on("hidden.bs.modal", function () {
        $("#mensajeConfirmEstadoCiudad").html("¿Está seguro?");
        $("#btnConfirmarEstadoCiudad").data("id", "");
    });
});