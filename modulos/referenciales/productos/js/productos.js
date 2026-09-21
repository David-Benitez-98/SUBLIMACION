$(document).ready(function () {
  const DELAY_NOTIFICACION = 3000;

  // Para formatear mientras el usuario escribe (input ya es solo dígitos)
  function formatearNumero(valor) {
    var num = String(valor).replace(/\D/g, "");
    if (num === "") return "";
    return parseInt(num, 10).toLocaleString("es-PY");
  }

  // Para formatear valores que vienen de la base de datos (pueden traer decimales, ej "10000.00")
  function formatearDesdeValor(valor) {
    var num = parseFloat(valor);
    if (isNaN(num)) return "";
    return Math.round(num).toLocaleString("es-PY");
  }

  function limpiarNumero(valor) {
    return String(valor).replace(/\D/g, "") || "0";
  }

  // Inicializar Select2 en el combo del modal
  $("#select_categoria").select2({
    dropdownParent: $("#modalProducto"),
  });

  // Inicializar DataTable
  var dt = $("#tbl_productos").DataTable({
    autoWidth: false,
    ajax: {
      url: "ajax/listar_productos.php",
      dataSrc: "data",
    },
    columns: [
      { data: "id_producto" },
      {
        data: "imagen_url",
        orderable: false,
        searchable: false,
        render: function (data) {
          if (!data) return "";
          return (
            '<img src="' +
            data +
            '" alt="img" style="width:50px;height:50px;object-fit:cover;">'
          );
        },
      },
      { data: "codigo" },
      { data: "nombre" },
      {
        // ====== NUEVA COLUMNA: Descripción ======
        data: "descripcion",
        defaultContent: "",
        render: function (data) {
          if (!data) return "";
          return data.length > 40 ? data.substring(0, 40) + "..." : data;
        },
      },
      { data: "nombre_categoria", defaultContent: "" },
      {
        data: "precio_venta",
        render: function (data) {
          return "Gs. " + formatearDesdeValor(data);
        },
      },
      { data: "tiempo_produccion" },
      { data: "acciones", orderable: false, searchable: false },
    ],
    language: {
      url: "/sublimacion/js/es-ES.json",
    },
  });

  // ====== Formatear precio mientras se escribe ======
  $(document).on("input", "#input_precio_venta", function () {
    var cursorAlFinal = this.selectionStart === this.value.length;
    $(this).val(formatearNumero(this.value));
    if (cursorAlFinal) {
      this.setSelectionRange(this.value.length, this.value.length);
    }
  });

  // ====== Preview de imagen al seleccionar archivo ======
  $("#input_imagen").on("change", function () {
    var file = this.files[0];
    if (file) {
      var reader = new FileReader();
      reader.onload = function (e) {
        $("#preview_imagen").attr("src", e.target.result).show();
      };
      reader.readAsDataURL(file);
    }
  });

  // ====== Editar producto ======
  $("#tbl_productos").on("click", ".btn-editar", function () {
    var id = $(this).data("id");
    $.ajax({
      url: "ajax/obtener_producto.php",
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
        var $form = $("#form_producto");
        $form.find('input[name="id_producto"]').val(data.id_producto);
        $form.find('input[name="codigo"]').val(data.codigo);
        $form.find('input[name="nombre"]').val(data.nombre);
        $form.find('#input_precio_venta').val(formatearDesdeValor(data.precio_venta));
        $form.find('input[name="tiempo_produccion"]').val(data.tiempo_produccion);
        $form.find('textarea[name="descripcion"]').val(data.descripcion);
        $form.find('input[name="imagen"]').val("");
        $("#imagen_actual").val(data.imagen_url || "");

        if (data.imagen_url) {
          $("#preview_imagen").attr("src", data.imagen_url).show();
        } else {
          $("#preview_imagen").hide().attr("src", "");
        }

        $("#select_categoria").val(data.id_categoria).trigger("change");

        $("#modalProducto .modal-title").html(
          '<i class="fas fa-box-open"></i> Editar Producto',
        );
        $("#modalProducto .modal-footer .btn-primary").text("Actualizar");
        $("#modalProducto").modal("show");
      },
      error: function () {
        PNotify.notice({
          title: "Error",
          text: "Error al cargar datos del producto",
          type: "error",
          delay: DELAY_NOTIFICACION,
          styling: "brighttheme",
        });
      },
    });
  });

  // ====== Guardar producto (submit manual por uso de FormData) ======
  $("#form_producto").on("submit", function (e) {
    e.preventDefault();
    var formData = new FormData(this);
    formData.set("precio_venta", limpiarNumero($("#input_precio_venta").val()));

    $.ajax({
      url: "ajax/guardar_producto.php",
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
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
          $("#modalProducto").modal("hide");
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
          text: "Error al guardar el producto",
          type: "error",
          delay: DELAY_NOTIFICACION,
          styling: "brighttheme",
        });
      },
    });
  });

  // ====== Cambiar estado ======
  $("#tbl_productos").on("click", ".btn-cambiar-estado", function () {
    var id = $(this).data("id");
    var nombre = $(this).data("nombre") || "este producto";
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
      url: "ajax/cambiar_estado_producto.php",
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
  $("#modalProducto").on("hidden.bs.modal", function () {
    var $form = $("#form_producto");
    $form[0].reset();
    $form.find('input[name="id_producto"]').val("");
    $("#input_precio_venta").val("0");
    $("#imagen_actual").val("");
    $("#preview_imagen").hide().attr("src", "");
    $("#select_categoria").val("").trigger("change");
    $("#modalProducto .modal-title").html(
      '<i class="fas fa-box-open"></i> Nuevo Producto',
    );
    $("#modalProducto .modal-footer .btn-primary").text("Guardar");
  });
});