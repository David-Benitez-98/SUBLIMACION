$(document).ready(function () {
    const DELAY_NOTIFICACION = 3000;
    var dt;

    function inicializarTabla() {
        dt = $("#tbl_departamento").DataTable({
            autoWidth: true,
            ajax: {
                url: "ajax/listar_departamentos.php",
                dataSrc: "data"
            },
            columns: [
                { data: "id_departamento", width: "15%" },
                { data: "nombre_departamento", width: "65%" },
                { data: "acciones", orderable: false, searchable: false, width: "20%", className: "text-center" }
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
    $("#tbl_departamento").on("click", ".btn-editar", function () {
        var id = $(this).data("id");
        var data = dt.row($(this).closest('tr')).data();
        if (!data) return;
        var $form = $("#form_departamento");
        $form.find('input[name="id_departamento"]').val(data.id_departamento);
        $form.find('input[name="nombre_departamento"]').val(data.nombre_departamento);
        $("#modalDepartamento .modal-title").html('<i class="fas fa-map-marker-alt"></i> Editar Departamento');
        $("#modalDepartamento .modal-footer .btn-primary").text("Actualizar");

        // Remover cualquier _method previo antes de agregar uno nuevo (evita duplicados)
        $form.find('input[name="_method"]').remove();
        $form.append('<input type="hidden" name="_method" value="PUT">');

        $("#modalDepartamento").modal("show");
    });

    // Al abrir modal para nuevo, remover _method si existe
    $("#modalDepartamento").on("show.bs.modal", function () {
        var $form = $("#form_departamento");
        if (!$form.find('input[name="id_departamento"]').val()) {
            $form.find('input[name="_method"]').remove();
            $("#modalDepartamento .modal-title").html('<i class="fas fa-map-marker-alt"></i> Nuevo Departamento');
            $("#modalDepartamento .modal-footer .btn-primary").text("Guardar");
        }
    });

    // Manejar el submit del formulario con AJAX (para PUT y POST)
    // Se usa .off("submit") antes de re-enlazar para evitar handlers duplicados
    $("#form_departamento").off("submit").on("submit", function (e) {
        e.preventDefault();
        var $form = $(this);
        var $btnSubmit = $("#modalDepartamento .modal-footer .btn-primary");

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
                    $("#modalDepartamento").modal("hide");
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
    $("#tbl_departamento").on("click", ".btn-cambiar-estado", function () {
        var id = $(this).data("id");
        var nombre = $(this).data("nombre") || "este departamento";
        var estadoActual = $(this).text().trim();
        var nuevoEstado = estadoActual === "Activar" ? "activar" : "desactivar";
        var accion = nuevoEstado === "activar" ? "Activar" : "Desactivar";
        var mensaje = "¿Está seguro de " + nuevoEstado + " a <strong>" + nombre + "</strong>?";
        $("#mensajeConfirmEstadoDep").html(mensaje);
        var $btn = $("#btnConfirmarEstadoDep");
        $btn.data("id", id);
        $btn.text("Sí, " + accion)
            .removeClass("btn-success btn-danger")
            .addClass(accion === "Activar" ? "btn-success" : "btn-danger");
        var modal = new bootstrap.Modal(document.getElementById("modalConfirmEstadoDep"));
        modal.show();
    });

    // Confirmar estado
    $("#btnConfirmarEstadoDep").on("click", function () {
        var id = $(this).data("id");
        var modal = bootstrap.Modal.getInstance(document.getElementById("modalConfirmEstadoDep"));
        modal.hide();
        $.ajax({
            url: "ajax/cambiar_estado_departamento.php",
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
    $("#modalConfirmEstadoDep").on("hidden.bs.modal", function () {
        $("#mensajeConfirmEstadoDep").html("¿Está seguro?");
        $("#btnConfirmarEstadoDep").data("id", "");
    });
});