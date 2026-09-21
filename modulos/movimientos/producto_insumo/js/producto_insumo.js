$(document).ready(function () {
  const DELAY_NOTIFICACION = 3000;
  const idProducto = $('#form_producto_insumo input[name="id_producto"]').val();

  // Inicializar Select2 en el combo del modal
  $("#select_insumo").select2({
    dropdownParent: $("#modalProductoInsumo"),
  });

  // Autocompletar la unidad de medida al elegir un insumo
  $("#select_insumo").on("select2:select", function (e) {
    var unidad = $(e.params.data.element).data("unidad") || "";
    $("#input_unidad").val(unidad);
  });
  $("#select_insumo").on("select2:clear", function () {
    $("#input_unidad").val("");
  });

  // Inicializar DataTable
  var dt = $("#tbl_producto_insumo").DataTable({
    autoWidth: false,
    ajax: {
      url: "ajax/listar_insumos_producto.php",
      data: { id_producto: idProducto },
      dataSrc: "data",
    },
    columns: [
      { data: "id_producto_insumo" },
      { data: "nombre_insumo" },
      { data: "nombre_unidad", defaultContent: "" },
      { data: "cantidad_requerida" },
      { data: "acciones", orderable: false, searchable: false },
    ],
    language: {
      url: "/sublimacion/js/es-ES.json",
    },
  });

  // ====== Editar insumo del producto ======
  $("#tbl_producto_insumo").on("click", ".btn-editar", function () {
    var id = $(this).data("id");
    var idInsumo = $(this).data("id-insumo");
    var unidad = $(this).data("unidad") || "";
    var cantidad = $(this).data("cantidad");

    var $form = $("#form_producto_insumo");
    $form.find('input[name="id_producto_insumo"]').val(id);
    $("#select_insumo").val(idInsumo).trigger("change");
    $("#input_unidad").val(unidad);
    $("#input_cantidad").val(cantidad);

    $("#modalProductoInsumo .modal-title").html(
      '<i class="fas fa-boxes"></i> Editar Insumo',
    );
    $("#modalProductoInsumo .modal-footer .btn-primary").text("Actualizar");
    $("#modalProductoInsumo").modal("show");
  });

  // ====== Eliminar insumo del producto ======
  $("#tbl_producto_insumo").on("click", ".btn-eliminar", function () {
    var id = $(this).data("id");
    var nombre = $(this).data("nombre") || "este insumo";
    var mensaje =
      "¿Está seguro de eliminar <strong>" + nombre + "</strong> de este producto?";

    $("#mensajeConfirmacionEliminar").html(mensaje);
    $("#btnConfirmarEliminar").data("id", id);

    var modalElement = document.getElementById("modalConfirmEliminar");
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

  // ====== Evento confirmar eliminación (modal fijo) ======
  $("#btnConfirmarEliminar").on("click", function () {
    var id = $(this).data("id");
    var modal = bootstrap.Modal.getInstance(
      document.getElementById("modalConfirmEliminar"),
    );
    if (modal) {
      modal.hide();
    }

    $.ajax({
      url: "ajax/eliminar_producto_insumo.php",
      type: "POST",
      data: { id: id },
      dataType: "json",
      success: function (res) {
        if (res.msg === "CORRECTO") {
          PNotify.notice({
            title: "Éxito",
            text: res.texto,
            type: "success",
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
          text: "Error al eliminar el insumo",
          type: "error",
          delay: DELAY_NOTIFICACION,
          styling: "brighttheme",
        });
      },
    });
  });

  // ====== Guardar (alta / edición) vía submit del form ======
  $("#form_producto_insumo").on("submit", function (e) {
    e.preventDefault();
    var $form = $(this);

    $.ajax({
      url: $form.attr("action"),
      type: "POST",
      data: $form.serialize(),
      dataType: "json",
      success: function (res) {
        if (res.msg === "CORRECTO") {
          PNotify.notice({
            title: "Éxito",
            text: res.texto,
            type: "success",
            delay: DELAY_NOTIFICACION,
            styling: "brighttheme",
          });
          $("#modalProductoInsumo").modal("hide");
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
          text: "Error al guardar el insumo",
          type: "error",
          delay: DELAY_NOTIFICACION,
          styling: "brighttheme",
        });
      },
    });
  });

  // ====== Resetear modal al cerrar ======
  $("#modalProductoInsumo").on("hidden.bs.modal", function () {
    var $form = $("#form_producto_insumo");
    $form[0].reset();
    $form.find('input[name="id_producto_insumo"]').val("");
    $("#select_insumo").val("").trigger("change");
    $("#input_unidad").val("");
    $("#modalProductoInsumo .modal-title").html(
      '<i class="fas fa-boxes"></i> Agregar Insumo',
    );
    $("#modalProductoInsumo .modal-footer .btn-primary").text("Guardar");
  });
});