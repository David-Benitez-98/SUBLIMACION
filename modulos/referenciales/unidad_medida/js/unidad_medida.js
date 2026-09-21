$(document).ready(function () {
  const DELAY_NOTIFICACION = 3000;
  var dt;

  // Inicializar DataTable
  function inicializarTabla() {
    dt = $("#tbl_unidad_medida").DataTable({
      autoWidth: false,
      ajax: {
        url: "ajax/listar_unidad_medida.php",
        dataSrc: "data",
      },
      columns: [
        { data: "id_unidad" },
        { data: "nombre" },
        { data: "descripcion" },
        { data: "acciones", orderable: false, searchable: false },
      ],
      language: {
        url: "/sublimacion/js/es-ES.json",
      },
    });
  }

  inicializarTabla();

  // ====== Editar ======
  $("#tbl_unidad_medida").on("click", ".btn-editar", function () {
    var id = $(this).data("id");
    // Obtener datos de la fila desde DataTable
    var data = dt.row($(this).closest("tr")).data();
    if (!data) return;

    var $form = $("#form_unidad_medida");
    $form.find('input[name="id_unidad"]').val(data.id_unidad);
    $form.find('input[name="nombre_unidad_medida"]').val(data.nombre);
    $form.find('textarea[name="unidad_medida_descripcion"]').val(data.descripcion);

    $("#modal_unidad_medida .modal-title").html(
      '<i class="fas fa-id-card"></i> Editar Unidad de Medida',
    );
    $("#modal_unidad_medida .modal-footer .btn-primary").text("Actualizar");
    // Cambiar método a PUT (se enviará mediante data-ajax-tipo, pero lo manejamos con un campo oculto o data-*)
    $form.attr("data-ajax-metodo", "PUT");
    $("#modal_unidad_medida").modal("show");
  });

  // ====== Eliminar con confirmación ======
  $("#tbl_unidad_medida").on("click", ".btn-eliminar", function () {
    var id = $(this).data("id");
    var nombre = $(this).data("nombre") || "esta Unidad de Medida";

    var mensaje = "¿Está seguro de eliminar <strong>" + nombre + "</strong>?";
    $("#mensajeConfirmEliminar").html(mensaje);
    $("#btnConfirmarEliminar").data("id", id);

    var modal = new bootstrap.Modal(
      document.getElementById("modalConfirmEliminar"),
    );
    modal.show();
  });

  // ====== Confirmar eliminación ======
  $("#btnConfirmarEliminar").on("click", function () {
    var id = $(this).data("id");
    var modal = bootstrap.Modal.getInstance(
      document.getElementById("modalConfirmEliminar"),
    );
    modal.hide();

    $.ajax({
      url: "ajax/guardar_unidad_medida.php",
      type: "DELETE",
      data: { id_unidad: id },
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
          text: "Error al eliminar",
          type: "error",
          delay: DELAY_NOTIFICACION,
          styling: "brighttheme",
        });
      },
    });
  });

  // ====== Al abrir modal para nuevo ======
  $("#modal_unidad_medida").on("show.bs.modal", function () {
    var $form = $("#form_unidad_medida");
    // Si no hay id (nuevo), resetear
    if (!$form.find('input[name="id_unidad"]').val()) {
      $form.find('input[name="nombre_unidad_medida"]').val("");
      $form.find('textarea[name="unidad_medida_descripcion"]').val("");
      $("#modal_unidad_medida .modal-title").html(
        '<i class="fas fa-id-card"></i> Nueva Unidad de Medida',
      );
      $("#modal_unidad_medida .modal-footer .btn-primary").text("Guardar");
      $form.attr("data-ajax-metodo", "POST");
    }
  });

  // ====== Antes de enviar el formulario (para PUT) ======
  $("#form_unidad_medida").on("submit", function (e) {
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
              styling: "brighttheme",
            });

            dt.ajax.reload(function () {
              dt.columns.adjust();
            }, false);

            var modal = bootstrap.Modal.getInstance(
              document.getElementById("modal_unidad_medida"),
            );

            if (modal) {
              modal.hide();
            }
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
            text: "Error al procesar",
            type: "error",
            delay: DELAY_NOTIFICACION,
            styling: "brighttheme",
          });
        },
      });
      e.preventDefault();
      return false;
    }
  });

  // ====== Reseteo al cerrar modal ======
  $("#modal_unidad_medida").on("hidden.bs.modal", function () {
    var $form = $("#form_unidad_medida");
    $form[0].reset();
    $form.find('input[name="id_unidad"]').val("");
    $form.attr("data-ajax-metodo", "POST");
    $("#modal_unidad_medida .modal-title").html(
      '<i class="fas fa-id-card"></i> Nueva Unidad de Medida',
    );
    $("#modal_unidad_medida .modal-footer .btn-primary").text("Guardar");
  });

  // Limpiar modal de confirmación al cerrar
  $("#modalConfirmEliminar").on("hidden.bs.modal", function () {
    $("#mensajeConfirmEliminar").html(
      "¿Está seguro de eliminar esta Unidad de Medida?",
    );
    $("#btnConfirmarEliminar").data("id", "");
  });
});
