$(document).ready(function () {
  const DELAY_NOTIFICACION = 3000;

  // Fecha de hoy por defecto
  (function fechaHoy() {
    var hoy = new Date();
    var mes = String(hoy.getMonth() + 1).padStart(2, "0");
    var dia = String(hoy.getDate()).padStart(2, "0");
    $("#input_fecha_compra").val(hoy.getFullYear() + "-" + mes + "-" + dia);
  })();

  $("#select_proveedor_compra").select2({ width: "100%" });

  function idProveedorActual() {
    return $("#select_proveedor_compra").val();
  }

  function formatearGs(numero) {
    return "Gs. " + Math.round(numero).toLocaleString("es-PY");
  }

  // ====== Recalcular subtotal de una fila ======
  function recalcularFila($fila) {
    var cantidad = parseFloat($fila.find(".input-cantidad").val()) || 0;
    var precio = parseFloat($fila.find(".input-precio").val()) || 0;
    var subtotal = cantidad * precio;
    $fila.find(".input-subtotal").val(formatearGs(subtotal));
    recalcularTotal();
  }

  // ====== Recalcular el total de la compra ======
  function recalcularTotal() {
    var total = 0;
    $("#detalle_compra_body .fila-detalle").each(function () {
      var cantidad = parseFloat($(this).find(".input-cantidad").val()) || 0;
      var precio = parseFloat($(this).find(".input-precio").val()) || 0;
      total += cantidad * precio;
    });
    $("#total_compra").html("<strong>" + formatearGs(total) + "</strong>");
  }

  // ====== Autocompletar precio de referencia desde insumo_proveedor ======
  function autocompletarPrecio($fila) {
    var idProveedor = idProveedorActual();
    var idInsumo = $fila.find(".select2-insumo").val();
    if (!idProveedor || !idInsumo) {
      return;
    }
    $.ajax({
      url: "ajax/obtener_precio_referencia.php",
      type: "GET",
      data: { id_proveedor: idProveedor, id_insumo: idInsumo },
      dataType: "json",
      success: function (res) {
        if (res.precio !== null && $fila.find(".input-precio").val() === "") {
          $fila.find(".input-precio").val(res.precio);
          recalcularFila($fila);
        }
      },
    });
  }

  // ====== Agregar una fila nueva ======
  function agregarFila() {
    var plantilla = document.getElementById("plantilla_fila_detalle");
    var clon = document.importNode(plantilla.content, true);
    $("#detalle_compra_body").append(clon);

    var $fila = $("#detalle_compra_body .fila-detalle").last();
    $fila.find(".select2-insumo").select2({ width: "100%" });
  }

  $("#btnAgregarLinea").on("click", function () {
    agregarFila();
  });

  // Arrancar con una fila vacía
  agregarFila();

  // ====== Al elegir insumo en una fila: autocompletar unidad y precio ======
  $("#detalle_compra_body").on("select2:select", ".select2-insumo", function (e) {
    var $fila = $(this).closest(".fila-detalle");
    var unidad = $(e.params.data.element).data("unidad") || "";
    $fila.find(".input-unidad").val(unidad);
    autocompletarPrecio($fila);
  });

  // ====== Si cambia el proveedor, intentar autocompletar precio en filas que ya tengan insumo elegido ======
  $("#select_proveedor_compra").on("change", function () {
    $("#detalle_compra_body .fila-detalle").each(function () {
      var $fila = $(this);
      if ($fila.find(".input-precio").val() === "" && $fila.find(".select2-insumo").val()) {
        autocompletarPrecio($fila);
      }
    });
  });

  // ====== Recalcular al tipear cantidad o precio ======
  $("#detalle_compra_body").on("input", ".input-cantidad, .input-precio", function () {
    recalcularFila($(this).closest(".fila-detalle"));
  });

  // ====== Quitar una fila ======
  $("#detalle_compra_body").on("click", ".btn-quitar-linea", function () {
    var $filas = $("#detalle_compra_body .fila-detalle");
    if ($filas.length <= 1) {
      PNotify.notice({
        title: "Atención",
        text: "La compra debe tener al menos una línea de detalle",
        type: "warning",
        delay: DELAY_NOTIFICACION,
        styling: "brighttheme",
      });
      return;
    }
    $(this).closest(".fila-detalle").remove();
    recalcularTotal();
  });

  // ====== Registrar la compra ======
  $("#form_compra").on("submit", function (e) {
    e.preventDefault();

    var idProveedor = idProveedorActual();
    if (!idProveedor) {
      PNotify.notice({
        title: "Error",
        text: "Debe seleccionar un proveedor",
        type: "error",
        delay: DELAY_NOTIFICACION,
        styling: "brighttheme",
      });
      return;
    }

    var detalles = [];
    var huboError = false;
    $("#detalle_compra_body .fila-detalle").each(function () {
      var idInsumo = $(this).find(".select2-insumo").val();
      var cantidad = parseFloat($(this).find(".input-cantidad").val());
      var precio = parseFloat($(this).find(".input-precio").val());

      if (!idInsumo || !cantidad || cantidad <= 0 || isNaN(precio) || precio < 0) {
        huboError = true;
        return;
      }
      detalles.push({ id_insumo: idInsumo, cantidad: cantidad, precio_unitario: precio });
    });

    if (huboError || detalles.length === 0) {
      PNotify.notice({
        title: "Error",
        text: "Revisá que todas las líneas tengan insumo, cantidad (mayor a 0) y precio cargados",
        type: "error",
        delay: DELAY_NOTIFICACION,
        styling: "brighttheme",
      });
      return;
    }

    var $btn = $("#btnRegistrarCompra");
    $btn.prop("disabled", true);

    $.ajax({
      url: "ajax/guardar_compra.php",
      type: "POST",
      data: {
        id_proveedor: idProveedor,
        fecha_compra: $("#input_fecha_compra").val(),
        observaciones: $("[name='observaciones']").val(),
        detalles: JSON.stringify(detalles),
      },
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
          setTimeout(function () {
            window.location.href = "compras.php";
          }, 1200);
        } else {
          $btn.prop("disabled", false);
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
        $btn.prop("disabled", false);
        PNotify.notice({
          title: "Error",
          text: "Error al registrar la compra",
          type: "error",
          delay: DELAY_NOTIFICACION,
          styling: "brighttheme",
        });
      },
    });
  });
});