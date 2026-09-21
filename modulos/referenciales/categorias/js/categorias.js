$(document).ready(function () {
  const DELAY_NOTIFICACION = 3000;
  var dt;

  // Inicializar DataTable
  function inicializarTabla() {
    dt = $("#tbl_categorias").DataTable({
      autoWidth: false,
      ajax: {
        url: "ajax/listar_categoria.php",
        dataSrc: "data",
      },
      columns: [
        { data: "id_categoria" },
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
  $("#tbl_categorias").on("click", ".btn-editar", function () {
    var id = $(this).data("id");
    // Obtener datos de la fila desde DataTable
    var data = dt.row($(this).closest("tr")).data();
    if (!data) return;

    var $form = $("#form_categorias");
    $form.find('input[name="id_categoria"]').val(data.id_categoria);
    $form.find('input[name="nombre_categorias"]').val(data.nombre);
    $form.find('textarea[name="categorias_descripcion"]').val(data.descripcion);

    $("#modalcategorias .modal-title").html(
      '<i class="fas fa-id-card"></i> Editar Tipo de Documento',
    );
    $("#modalcategorias .modal-footer .btn-primary").text("Actualizar");
    // Cambiar método a PUT (se enviará mediante data-ajax-tipo, pero lo manejamos con un campo oculto o data-*)
    $form.attr("data-ajax-metodo", "PUT");
    $("#modalcategorias").modal("show");
  });

  // ====== Eliminar con confirmación ======
  $("#tbl_categorias").on("click", ".btn-eliminar", function () {
    var id = $(this).data("id");
    var nombre = $(this).data("nombre") || "esta categoria";

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
      url: "ajax/guardar_categorias.php",
      type: "DELETE",
      data: { id_categoria: id },
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
  $("#modalcategorias").on("show.bs.modal", function () {
    var $form = $("#form_categorias");
    // Si no hay id (nuevo), resetear
    if (!$form.find('input[name="id_categoria"]').val()) {
      $form.find('input[name="nombre_categorias"]').val("");
      $form.find('textarea[name="categorias_descripcion"]').val("");
      $("#modalcategorias .modal-title").html(
        '<i class="fas fa-id-card"></i> Nueva Categoria',
      );
      $("#modalcategorias .modal-footer .btn-primary").text("Guardar");
      $form.attr("data-ajax-metodo", "POST");
    }
  });

  // ====== Antes de enviar el formulario (para PUT) ======
  $("#form_categorias").on("submit", function (e) {
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
              document.getElementById("modalcategorias"),
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
  $("#modalcategorias").on("hidden.bs.modal", function () {
    var $form = $("#form_categorias");
    $form[0].reset();
    $form.find('input[name="id_categoria"]').val("");
    $form.attr("data-ajax-metodo", "POST");
    $("#modalcategorias .modal-title").html(
      '<i class="fas fa-id-card"></i> Nueva Categoria',
    );
    $("#modalcategorias .modal-footer .btn-primary").text("Guardar");
  });

  // Limpiar modal de confirmación al cerrar
  $("#modalConfirmEliminar").on("hidden.bs.modal", function () {
    $("#mensajeConfirmEliminar").html(
      "¿Está seguro de eliminar este tipo de documento?",
    );
    $("#btnConfirmarEliminar").data("id", "");
  });
});
