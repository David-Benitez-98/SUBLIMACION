$(document).ready(function () {
  const DELAY_NOTIFICACION = 3000;
  var dt;

  // Inicializar DataTable
  function inicializarTabla() {
    dt = $("#tbl_colores").DataTable({
      autoWidth: false,
      ajax: {
        url: "ajax/listar_colores.php",
        dataSrc: "data",
      },
      columns: [
        { data: "id_color" },
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
  $("#tbl_colores").on("click", ".btn-editar", function () {
    var id = $(this).data("id");
    // Obtener datos de la fila desde DataTable
    var data = dt.row($(this).closest("tr")).data();
    if (!data) return;

    var $form = $("#form_colores");
    $form.find('input[name="id_color"]').val(data.id_color);
    $form.find('input[name="nombre_colores"]').val(data.nombre);
    $form.find('textarea[name="colores_descripcion"]').val(data.descripcion);

    $("#modalcolores .modal-title").html(
      '<i class="fas fa-id-card"></i> Editar Color',
    );
    $("#modalcolores .modal-footer .btn-primary").text("Actualizar");
    // Cambiar método a PUT (se enviará mediante data-ajax-tipo, pero lo manejamos con un campo oculto o data-*)
    $form.attr("data-ajax-metodo", "PUT");
    $("#modalcolores").modal("show");
  });

  // ====== Eliminar con confirmación ======
  $("#tbl_colores").on("click", ".btn-eliminar", function () {
    var id = $(this).data("id");
    var nombre = $(this).data("nombre") || "este color";

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
      url: "ajax/guardar_colores.php",
      type: "DELETE",
      data: { id_color: id },
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
  $("#modalcolores").on("show.bs.modal", function () {
    var $form = $("#form_colores");
    // Si no hay id (nuevo), resetear
    if (!$form.find('input[name="id_color"]').val()) {
      $form.find('input[name="nombre_colores"]').val("");
      $form.find('textarea[name="colores_descripcion"]').val("");
      $("#modalcolores .modal-title").html(
        '<i class="fas fa-id-card"></i> Nuevo Color',
      );
      $("#modalcolores .modal-footer .btn-primary").text("Guardar");
      $form.attr("data-ajax-metodo", "POST");
    }
  });

  // ====== Antes de enviar el formulario (para PUT) ======
  $("#form_colores").on("submit", function (e) {
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
              document.getElementById("modalcolores"),
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
  $("#modalcolores").on("hidden.bs.modal", function () {
    var $form = $("#form_colores");
    $form[0].reset();
    $form.find('input[name="id_color"]').val("");
    $form.attr("data-ajax-metodo", "POST");
    $("#modalcolores .modal-title").html(
      '<i class="fas fa-id-card"></i> Nuevo Color',
    );
    $("#modalcolores .modal-footer .btn-primary").text("Guardar");
  });

  // Limpiar modal de confirmación al cerrar
  $("#modalConfirmEliminar").on("hidden.bs.modal", function () {
    $("#mensajeConfirmEliminar").html(
      "¿Está seguro de eliminar este color?",
    );
    $("#btnConfirmarEliminar").data("id", "");
  });
});
