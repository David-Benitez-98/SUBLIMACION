$(document).ready(function () {
  const DELAY_NOTIFICACION = 3000;

  // Inicializar DataTable
  var dt = $("#tbl_proveedores").DataTable({
    autoWidth: false,
    ajax: {
      url: "ajax/listar_proveedores.php",
      dataSrc: "data",
    },
    columns: [
      { data: "id_proveedor" },
      { data: "ruc", defaultContent: "" },
      { data: "razon_social" },
      { data: "contacto", defaultContent: "" },
      { data: "telefono", defaultContent: "" },
      { data: "correo", defaultContent: "" },
      { data: "direccion", defaultContent: "" },
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

  // ====== Editar proveedor ======
  $("#tbl_proveedores").on("click", ".btn-editar", function () {
    var id = $(this).data("id");
    $.ajax({
      url: "ajax/obtener_proveedor.php",
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
        var $form = $("#form_proveedor");
        $form.find('input[name="id_proveedor"]').val(data.id_proveedor);
        $form.find('input[name="razon_social"]').val(data.razon_social);
        $form.find('input[name="ruc"]').val(data.ruc);
        $form.find('input[name="contacto"]').val(data.contacto);
        $form.find('input[name="telefono"]').val(data.telefono);
        $form.find('input[name="correo"]').val(data.correo);
        $form.find('input[name="direccion"]').val(data.direccion);

        $("#select_departamento").val(data.id_departamento).trigger("change");
        setTimeout(function () {
          $("#select_ciudad").val(data.id_ciudad);
        }, 500);

        $("#modalProveedor .modal-title").html(
          '<i class="fas fa-truck-loading"></i> Editar Proveedor',
        );
        $("#modalProveedor .modal-footer .btn-primary").text("Actualizar");
        $("#modalProveedor").modal("show");
      },
      error: function () {
        PNotify.notice({
          title: "Error",
          text: "Error al cargar datos del proveedor",
          type: "error",
          delay: DELAY_NOTIFICACION,
          styling: "brighttheme",
        });
      },
    });
  });

  // ====== Cambiar estado ======
  $("#tbl_proveedores").on("click", ".btn-cambiar-estado", function () {
    var id = $(this).data("id");
    var nombre = $(this).data("nombre") || "este proveedor";
    var estadoActual = $(this).text().trim();
    var nuevoEstado = estadoActual === "Activar" ? "activar" : "desactivar";
    var accion = nuevoEstado === "activar" ? "Activar" : "Desactivar";
    var mensaje =
      "¿Está seguro de " + nuevoEstado + " a <strong>" + nombre + "</strong>?";

    // Actualizar el contenido del modal fijo
    $("#mensajeConfirmacion").html(mensaje);
    var $btnConfirmar = $("#btnConfirmarEstado");
    $btnConfirmar
      .text("Sí, " + accion)
      .removeClass("btn-success btn-danger")
      .addClass(accion === "Activar" ? "btn-success" : "btn-danger")
      .data("id", id);

    // Mostrar el modal
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
      url: "ajax/cambiar_estado_proveedor.php",
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
  $("#modalProveedor").on("hidden.bs.modal", function () {
    var $form = $("#form_proveedor");
    $form[0].reset();
    $form.find('input[name="id_proveedor"]').val("");
    $("#select_ciudad")
      .prop("disabled", true)
      .html('<option value="">Elija primero un departamento</option>');
    $("#modalProveedor .modal-title").html(
      '<i class="fas fa-truck-loading"></i> Nuevo Proveedor',
    );
    $("#modalProveedor .modal-footer .btn-primary").text("Guardar");
  });
});
