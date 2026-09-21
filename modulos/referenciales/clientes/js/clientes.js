$(document).ready(function () {
  // Inicializar DataTable
  $("#tbl_clientes").DataTable({
    autoWidth: false,
    ajax: {
      url: "ajax/listar_clientes.php",
      dataSrc: "data",
    },
    columns: [
      { data: "id_cliente" },
      { data: "nombre_tipo_documento", defaultContent: "" },
      { data: "numero_documento", defaultContent: "" },
      { data: "nombre" },
      { data: "apellido" },
      { data: "telefono" },
      { data: "email" },
      { data: "direccion" },
      { data: "nombre_ciudad", defaultContent: "" }, 
      { data: "acciones", orderable: false, searchable: false },
    ],
    language: {
      url: "/sublimacion/js/es-ES.json",
    },
  });

  // Combo dependiente: departamento → ciudad
  $("#select_departamento").on("change", function () {
    var idDep = $(this).val();
    var $ciudad = $("#select_ciudad");
    $ciudad
      .prop("disabled", true)
      .html('<option value="">Cargando...</option>');

    if (idDep) {
      $.ajax({
        url: "ajax/obtener_ciudades.php",
        type: "GET",
        data: { id_departamento: idDep },
        dataType: "json",
        success: function (data) {
          $ciudad
            .prop("disabled", false)
            .html('<option value="">Seleccione...</option>');
          $.each(data, function (index, ciudad) {
            $ciudad.append(
              '<option value="' +
                ciudad.id +
                '">' +
                ciudad.nombre +
                "</option>",
            );
          });
        },
        error: function () {
          $ciudad
            .prop("disabled", true)
            .html('<option value="">Error al cargar</option>');
        },
      });
    } else {
      $ciudad
        .prop("disabled", true)
        .html('<option value="">Elija primero un departamento</option>');
    }
  });

  // ====== Editar cliente ======
  $("#tbl_clientes").on("click", ".btn-editar", function () {
    var id = $(this).data("id");
    $.ajax({
      url: "ajax/obtener_cliente.php",
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
        var $form = $("#form_cliente");
        $form.find('input[name="id_cliente"]').val(data.id_cliente);
        $form.find('input[name="nombre"]').val(data.nombre);
        $form.find('input[name="apellido"]').val(data.apellido);
        $form.find('input[name="telefono"]').val(data.telefono);
        $form.find('input[name="email"]').val(data.email);
        $form.find('input[name="direccion"]').val(data.direccion);
        $form.find('input[name="numero_documento"]').val(data.numero_documento);
        $form
          .find('select[name="id_tipo_documento"]')
          .val(data.id_tipo_documento);

        // Preseleccionar departamento y ciudad
        $("#select_departamento").val(data.id_departamento).trigger("change");
        // Esperar a que cargue ciudades y seleccionar la ciudad
        setTimeout(function () {
          $("#select_ciudad").val(data.id_ciudad);
        }, 500);

        $("#modalCliente .modal-title").html(
          '<i class="fas fa-user-edit"></i> Editar Cliente',
        );
        $("#modalCliente .modal-footer .btn-primary").text("Actualizar");
        $("#modalCliente").modal("show");
      },
      error: function () {
        PNotify.error({
          text: "Error al cargar datos del cliente",
          delay: DELAY_NOTIFICACION,
        });
      },
    });
  });

  // ====== Cambiar estado con modal Bootstrap ======
  $("#tbl_clientes").on("click", ".btn-cambiar-estado", function () {
    var id = $(this).data("id");
    var nombre = $(this).data("nombre") || "este cliente";
    var estadoActual = $(this).text().trim(); // "Activar" o "Desactivar"
    var nuevoEstado = estadoActual === "Activar" ? "activar" : "desactivar";
    var accion = nuevoEstado === "activar" ? "Activar" : "Desactivar";
    var mensaje =
      "¿Está seguro de " + nuevoEstado + " a <strong>" + nombre + "</strong>?";

    // Crear modal dinámico
    var modalHtml = `
        <div class="modal fade" id="modalConfirmEstado" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirmación</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>${mensaje}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-${accion === "Activar" ? "success" : "danger"}" id="btnConfirmarEstado">Sí, ${accion}</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Eliminar modal previo si existe
    $("#modalConfirmEstado").remove();
    $("body").append(modalHtml);
    var modal = new bootstrap.Modal(
      document.getElementById("modalConfirmEstado"),
    );
    modal.show();

    // Manejar el click en "Sí"
    $("#btnConfirmarEstado").on("click", function () {
      modal.hide();
      // Ejecutar AJAX
      $.ajax({
        url: "ajax/cambiar_estado_cliente.php",
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
            var dt = $("#tbl_clientes").DataTable();
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

    // Eliminar modal del DOM al cerrarlo
    $("#modalConfirmEstado").on("hidden.bs.modal", function () {
      $(this).remove();
    });
  });
});
