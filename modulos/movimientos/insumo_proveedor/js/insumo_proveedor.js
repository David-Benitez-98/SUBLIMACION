$(document).ready(function () {
  const DELAY_NOTIFICACION = 3000;
  const idInsumo = $('#form_insumo_proveedor input[name="id_insumo"]').val();

  function fechaHoy() {
    var hoy = new Date();
    var mes = String(hoy.getMonth() + 1).padStart(2, "0");
    var dia = String(hoy.getDate()).padStart(2, "0");
    return hoy.getFullYear() + "-" + mes + "-" + dia;
  }

  // Inicializar Select2 en el combo del modal
  $("#select_proveedor").select2({
    dropdownParent: $("#modalInsumoProveedor"),
  });

  // Inicializar DataTable
  var dt = $("#tbl_insumo_proveedor").DataTable({
    autoWidth: false,
    ajax: {
      url: "ajax/listar_proveedores_insumo.php",
      data: { id_insumo: idInsumo },
      dataSrc: "data",
    },
    columns: [
      { data: "id_insumo_proveedor" },
      { data: "nombre_proveedor" },
      { data: "precio" },
      { data: "fecha_cotizacion", defaultContent: "-" },
      { data: "calidad", defaultContent: "-" },
      { data: "tiempo_entrega", defaultContent: "-" },
      { data: "favorito", defaultContent: "" },
      {
        data: "activo",
        render: function (data) {
          return data
            ? '<span class="badge bg-success">Activo</span>'
            : '<span class="badge bg-secondary">Inactivo</span>';
        },
      },
      { data: "acciones", orderable: false, searchable: false },
    ],
    language: {
      url: "/sublimacion/js/es-ES.json",
    },
  });

  // ====== Editar proveedor del insumo ======
  $("#tbl_insumo_proveedor").on("click", ".btn-editar", function () {
    var id = $(this).data("id");
    var idProveedor = $(this).data("id-proveedor");
    var precio = $(this).data("precio");
    var calidad = $(this).data("calidad");
    var fecha = $(this).data("fecha");
    var entrega = $(this).data("entrega");
    var favorito = $(this).data("favorito");

    var $form = $("#form_insumo_proveedor");
    $form.find('input[name="id_insumo_proveedor"]').val(id);
    $("#select_proveedor").val(idProveedor).trigger("change");
    $("#input_precio").val(parseFloat(precio));
    $("#select_calidad").val(calidad || "");
    $("#input_fecha_cotizacion").val(fecha);
    $("#input_tiempo_entrega").val(entrega);
    $("#switch_favorito").prop("checked", favorito == 1);

    $("#modalInsumoProveedor .modal-title").html(
      '<i class="fas fa-truck"></i> Editar Proveedor',
    );
    $("#modalInsumoProveedor .modal-footer .btn-primary").text("Actualizar");
    $("#modalInsumoProveedor").modal("show");
  });

  // ====== Cambiar estado (activar/desactivar) ======
  $("#tbl_insumo_proveedor").on("click", ".btn-cambiar-estado", function () {
    var id = $(this).data("id");
    var nombre = $(this).data("nombre") || "este proveedor";
    var estadoActual = $(this).text().trim();
    var nuevoEstado = estadoActual === "Activar" ? "activar" : "desactivar";
    var accion = nuevoEstado === "activar" ? "Activar" : "Desactivar";
    var mensaje =
      "¿Está seguro de " + nuevoEstado + " a <strong>" + nombre + "</strong> para este insumo?";

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
      url: "ajax/cambiar_estado_insumo_proveedor.php",
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

  // ====== Guardar (alta / edición) vía submit del form ======
  $("#form_insumo_proveedor").on("submit", function (e) {
    e.preventDefault();
    var $form = $(this);
    var datos = $form.serializeArray();

    // El checkbox de favorito solo viaja en serializeArray si está marcado; forzamos 0/1
    datos = datos.filter(function (campo) {
      return campo.name !== "favorito";
    });
    datos.push({
      name: "favorito",
      value: $("#switch_favorito").is(":checked") ? "1" : "0",
    });

    $.ajax({
      url: $form.attr("action"),
      type: "POST",
      data: $.param(datos),
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
          $("#modalInsumoProveedor").modal("hide");
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
          text: "Error al guardar el proveedor",
          type: "error",
          delay: DELAY_NOTIFICACION,
          styling: "brighttheme",
        });
      },
    });
  });

  // ====== Al abrir el modal para "Nuevo" (no editar), autocompletar fecha de hoy ======
  $('[data-bs-target="#modalInsumoProveedor"]').on("click", function () {
    $("#input_fecha_cotizacion").val(fechaHoy());
  });

  // ====== Resetear modal al cerrar ======
  $("#modalInsumoProveedor").on("hidden.bs.modal", function () {
    var $form = $("#form_insumo_proveedor");
    $form[0].reset();
    $form.find('input[name="id_insumo_proveedor"]').val("");
    $("#select_proveedor").val("").trigger("change");
    $("#switch_favorito").prop("checked", false);
    $("#modalInsumoProveedor .modal-title").html(
      '<i class="fas fa-truck"></i> Agregar Proveedor',
    );
    $("#modalInsumoProveedor .modal-footer .btn-primary").text("Guardar");
  });
});