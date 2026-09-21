$(document).ready(function () {
  const DELAY_NOTIFICACION = 3000;

  // Inicializar Select2 en los combos del modal
  $("#select_color, #select_unidad").select2({
    dropdownParent: $("#modalInsumo"),
  });

  // Inicializar DataTable
  var dt = $("#tbl_insumos").DataTable({
    autoWidth: false,
    ajax: {
      url: "ajax/listar_insumos.php",
      dataSrc: "data",
    },
    columns: [
      { data: "id_insumo" },
      { data: "codigo" },
      { data: "nombre" },
      { data: "nombre_color", defaultContent: "" },
      { data: "nombre_unidad", defaultContent: "" },
      { data: "stock_actual" },
      { data: "stock_minimo" },
      { data: "ubicacion", defaultContent: "" },
      { data: "acciones", orderable: false, searchable: false },
    ],
    language: {
      url: "/sublimacion/js/es-ES.json",
    },
  });

  // ====== Editar insumo ======
  $("#tbl_insumos").on("click", ".btn-editar", function () {
    var id = $(this).data("id");
    $.ajax({
      url: "ajax/obtener_insumo.php",
      type: "GET",
      data: { id: id },
      dataType: "json",
      success: function (data) {
        if (data.error) {
          PNotify.notice({
            title: "Error",
            text: data.error,
            type: "error",
            delay: DELAY_NOTIFICACION,
            styling: "brighttheme",
          });
          return;
        }
        var $form = $("#form_insumo");
        $form.find('input[name="id_insumo"]').val(data.id_insumo);
        $form.find('input[name="codigo"]').val(data.codigo);
        $form.find('input[name="nombre"]').val(data.nombre);
        $form.find('input[name="stock_actual"]').val(data.stock_actual);
        $form.find('input[name="stock_minimo"]').val(data.stock_minimo);
        $form.find('input[name="ubicacion"]').val(data.ubicacion);

        $("#select_color").val(data.id_color).trigger("change");
        $("#select_unidad").val(data.id_unidad).trigger("change");

        $("#modalInsumo .modal-title").html(
          '<i class="fas fa-boxes"></i> Editar Insumo',
        );
        $("#modalInsumo .modal-footer .btn-primary").text("Actualizar");
        $("#modalInsumo").modal("show");
      },
      error: function () {
        PNotify.notice({
          title: "Error",
          text: "Error al cargar datos del insumo",
          type: "error",
          delay: DELAY_NOTIFICACION,
          styling: "brighttheme",
        });
      },
    });
  });

  // ====== Cambiar estado ======
  $("#tbl_insumos").on("click", ".btn-cambiar-estado", function () {
    var id = $(this).data("id");
    var nombre = $(this).data("nombre") || "este insumo";
    var estadoActual = $(this).text().trim();
    var nuevoEstado = estadoActual === "Activar" ? "activar" : "desactivar";
    var accion = nuevoEstado === "activar" ? "Activar" : "Desactivar";
    var mensaje =
      "¿Está seguro de " + nuevoEstado + " a <strong>" + nombre + "</strong>?";

    $("#mensajeConfirmacion").html(mensaje);
    var $btnConfirmar = $("#btnConfirmarEstado");
    $btnConfirmar
      .text("Sí, " + accion)
      .removeClass("btn-success btn-danger")
      .addClass(accion === "Activar" ? "btn-success" : "btn-danger")
      .data("id", id);

    var modalElement = document.getElementById("modalConfirmEstado");
    if (modalElement) {
      var modal = new bootstrap.Modal(modalElement);
      modal.show();
    } else {
      PNotify.notice({
        title: "Error",
        text: "No se pudo mostrar la confirmación",
        type: "error",
        delay: DELAY_NOTIFICACION,
        styling: "brighttheme",
      });
    }
  });

  // ====== Evento confirmar del modal fijo ======
  $("#btnConfirmarEstado").on("click", function () {
    var id = $(this).data("id");
    var modal = bootstrap.Modal.getInstance(
      document.getElementById("modalConfirmEstado"),
    );
    if (modal) {
      modal.hide();
    }

    $.ajax({
      url: "ajax/cambiar_estado_insumo.php",
      type: "POST",
      data: { id: id },
      dataType: "json",
      success: function (res) {
        if (res.msg === "CORRECTO") {
          var tipo = res.texto.includes("DESACTIVADO") ? "error" : "success";
          PNotify.notice({
            title: "Éxito",
            text: res.texto,
            type: tipo,
            delay: DELAY_NOTIFICACION,
            styling: "brighttheme",
          });
          dt.ajax.reload(function () {
            dt.columns.adjust();
          }, false);
        } else {
          PNotify.notice({
            title: "Error",
            text: res.texto,
            type: "error",
            delay: DELAY_NOTIFICACION,
            styling: "brighttheme",
          });
        }
      },
      error: function () {
        PNotify.notice({
          title: "Error",
          text: "Error al cambiar estado",
          type: "error",
          delay: DELAY_NOTIFICACION,
          styling: "brighttheme",
        });
      },
    });
  });

  // ====== Resetear modal principal al cerrar ======
  $("#modalInsumo").on("hidden.bs.modal", function () {
    var $form = $("#form_insumo");
    $form[0].reset();
    $form.find('input[name="id_insumo"]').val("");
    $("#select_color").val("").trigger("change");
    $("#select_unidad").val("").trigger("change");
    $("#modalInsumo .modal-title").html(
      '<i class="fas fa-boxes"></i> Nuevo Insumo',
    );
    $("#modalInsumo .modal-footer .btn-primary").text("Guardar");
  });
});