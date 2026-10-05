$(document).ready(function () {
  const DELAY_NOTIFICACION = 3000;

  // Quitar/agregar líneas solo cambia la pantalla: se aplica en la base recién al presionar "Guardar Pedido".
  // Este flag avisa si intentan salir de la página con cambios pendientes.
  var hayCambiosSinGuardar = false;
  window.addEventListener("beforeunload", function (e) {
    if (hayCambiosSinGuardar) {
      e.preventDefault();
      e.returnValue = "";
    }
  });
  $("#form_pedido").on("input change", function () {
    hayCambiosSinGuardar = true;
  });

  // Fecha de hoy por defecto (solo si es un pedido nuevo)
  if (!ES_EDICION) {
    var hoy = new Date();
    var mes = String(hoy.getMonth() + 1).padStart(2, "0");
    var dia = String(hoy.getDate()).padStart(2, "0");
    $("#input_fecha_pedido").val(hoy.getFullYear() + "-" + mes + "-" + dia);
  }

  $("#select_cliente").select2({ width: "100%" });

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

  // ====== Recalcular total y saldo ======
  function recalcularTotal() {
    var total = 0;
    $("#detalle_pedido_body .fila-detalle").each(function () {
      var cantidad = parseFloat($(this).find(".input-cantidad").val()) || 0;
      var precio = parseFloat($(this).find(".input-precio").val()) || 0;
      total += cantidad * precio;
    });
    $("#total_pedido").html("<strong>" + formatearGs(total) + "</strong>");

    var sena = parseFloat($("#input_sena_pagada").val()) || 0;
    var saldo = total - sena;
    $("#input_saldo").val(formatearGs(saldo));
  }

  // ====== Inicializar select2 en una fila (nueva o existente) y calcular su subtotal inicial ======
  function inicializarFila($fila) {
    $fila.find(".select2-producto").select2({ width: "100%" });
    recalcularFila($fila);
  }

  // ====== Agregar una fila nueva vacía ======
  function agregarFila() {
    var plantilla = document.getElementById("plantilla_fila_detalle_pedido");
    var clon = document.importNode(plantilla.content, true);
    $("#detalle_pedido_body").append(clon);

    var $fila = $("#detalle_pedido_body .fila-detalle").last();
    inicializarFila($fila);
  }

  $("#btnAgregarLinea").on("click", function () {
    agregarFila();
    hayCambiosSinGuardar = true;
  });

  // ====== Inicializar filas ya existentes (modo edición) o arrancar con una vacía (modo nuevo) ======
  var $filasExistentes = $("#detalle_pedido_body .fila-detalle");
  if ($filasExistentes.length > 0) {
    $filasExistentes.each(function () {
      inicializarFila($(this));
    });
  } else {
    agregarFila();
  }

  // ====== Al elegir producto en una fila: autocompletar precio (solo si el campo está vacío) ======
  $("#detalle_pedido_body").on("select2:select", ".select2-producto", function (e) {
    var $fila = $(this).closest(".fila-detalle");
    var precio = $(e.params.data.element).data("precio");
    if ($fila.find(".input-precio").val() === "" || $fila.find(".input-precio").val() === "0") {
      $fila.find(".input-precio").val(precio);
    }
    recalcularFila($fila);
  });

  // ====== Recalcular al tipear cantidad, precio o seña ======
  $("#detalle_pedido_body").on("input", ".input-cantidad, .input-precio", function () {
    recalcularFila($(this).closest(".fila-detalle"));
  });
  $("#input_sena_pagada").on("input", function () {
    recalcularTotal();
  });

  // ====== Restricciones de estado al editar (solo avanzar, o cancelar) ======
  const ORDEN_ESTADOS = ["presupuestado", "confirmado", "en_produccion", "listo", "entregado"];
  // Estados donde puede existir consumo real confirmado (pedido_insumo) que haya que revertir al cancelar.
  const ESTADOS_QUE_CONSUMEN = ["en_produccion", "listo", "entregado"];

  if (ES_EDICION && ESTADO_ANTERIOR) {
    if (ESTADO_ANTERIOR === "cancelado") {
      // Pedido congelado: se bloquea todo el formulario
      $("#aviso_cancelado").show();
      $("#form_pedido").find("input, select, textarea, button").prop("disabled", true);
      $("#form_pedido").find("a.btn").removeClass("disabled"); // el botón "Volver" sigue activo
    } else {
      var posAnterior = ORDEN_ESTADOS.indexOf(ESTADO_ANTERIOR);
      $("#select_estado option").each(function () {
        var valor = $(this).val();
        if (valor === "cancelado") {
          return; // cancelar siempre está disponible
        }
        if (ORDEN_ESTADOS.indexOf(valor) < posAnterior) {
          $(this).prop("disabled", true); // no se puede retroceder
        }
      });
    }
  }

  // ====== Mostrar el checkbox de reposición solo al cancelar un pedido que ya consumía stock ======
  function actualizarBloqueReposicion() {
    var estadoNuevo = $("#select_estado").val();
    var aplica =
      ES_EDICION &&
      estadoNuevo === "cancelado" &&
      ESTADOS_QUE_CONSUMEN.indexOf(ESTADO_ANTERIOR) !== -1;

    if (aplica) {
      // Por defecto se repone si venía de en_produccion (recién empezando); NO si venía de listo/entregado
      var reponerPorDefecto = ESTADO_ANTERIOR === "en_produccion";
      if ($("#bloque_reponer_stock").is(":hidden")) {
        $("#check_reponer_stock").prop("checked", reponerPorDefecto);
      }
      $("#bloque_reponer_stock").show();
    } else {
      $("#bloque_reponer_stock").hide();
    }
  }
  $("#select_estado").on("change", actualizarBloqueReposicion);

  // ====== Si el estado pasa a "entregado", sugerir la fecha de entrega real de hoy si está vacía ======
  $("#select_estado").on("change", function () {
    if ($(this).val() === "entregado" && $("#input_fecha_entrega_real").val() === "") {
      var hoy = new Date();
      var mes = String(hoy.getMonth() + 1).padStart(2, "0");
      var dia = String(hoy.getDate()).padStart(2, "0");
      $("#input_fecha_entrega_real").val(hoy.getFullYear() + "-" + mes + "-" + dia);
    }
  });

  // ====== Quitar una fila ======
  $("#detalle_pedido_body").on("click", ".btn-quitar-linea", function () {
    var $filas = $("#detalle_pedido_body .fila-detalle");
    if ($filas.length <= 1) {
      PNotify.notice({
        title: "Atención",
        text: "El pedido debe tener al menos una línea de detalle",
        type: "warning",
        delay: DELAY_NOTIFICACION,
        styling: "brighttheme",
      });
      return;
    }
    $(this).closest(".fila-detalle").remove();
    recalcularTotal();
    hayCambiosSinGuardar = true;
    PNotify.notice({
      title: "Línea quitada",
      text: "El cambio se aplica al presionar «Guardar Pedido»",
      type: "info",
      delay: DELAY_NOTIFICACION,
      styling: "brighttheme",
    });
  });

  // ====== Cargar el panel de materiales (solo en edición) ======
  function cargarMaterialesNecesarios() {
    if (!ES_EDICION) {
      return;
    }
    $.ajax({
      url: "ajax/obtener_materiales_pedido.php",
      type: "GET",
      data: { id_pedido: ID_PEDIDO },
      dataType: "json",
      success: function (res) {
        var $body = $("#materiales_necesarios_body").empty();
        var puedeConfirmar = !!res.puede_confirmar;
        var colspan = puedeConfirmar ? 7 : 6;

        $("#col_utilizado").toggle(puedeConfirmar);
        $("#btnConfirmarConsumo").toggle(puedeConfirmar);
        $("#texto_ayuda_materiales").text(
          puedeConfirmar
            ? 'Corregí la columna "Utilizado" si el consumo real fue distinto al estimado, y confirmá para descontar el stock de verdad.'
            : "Según la receta de cada producto (producto_insumo) y las líneas ya guardadas. Todavía no descuenta stock: eso se confirma recién en producción."
        );

        if (!res.materiales || res.materiales.length === 0) {
          $body.append('<tr><td colspan="' + colspan + '" class="text-center text-muted">Los productos de este pedido no tienen insumos cargados en su receta (producto_insumo)</td></tr>');
          return;
        }

        var hayFaltantes = false;
        res.materiales.forEach(function (m) {
          if (m.faltante > 0) {
            hayFaltantes = true;
          }
          var claseFila = m.faltante > 0 && !puedeConfirmar ? "table-danger" : "";
          var celdaUtilizado = "";
          if (puedeConfirmar) {
            celdaUtilizado =
              "<td><input type='number' step='0.001' min='0.001' class='form-control form-control-sm input-utilizado' " +
              "data-id-insumo='" + m.id_insumo + "' value='" + m.utilizado + "'></td>";
          }
          $body.append(
            "<tr class='" + claseFila + "'>" +
              "<td>" + m.nombre_insumo + "</td>" +
              "<td>" + m.nombre_unidad + "</td>" +
              "<td>" + m.necesario + "</td>" +
              "<td>" + m.ya_descontado + "</td>" +
              "<td>" + m.stock_actual + "</td>" +
              "<td>" + (m.faltante > 0 ? m.faltante : "-") + "</td>" +
              celdaUtilizado +
              "</tr>"
          );
        });

        // Antes de producción: avisar si faltan insumos (informativo; el bloqueo real ocurre al confirmar consumo)
        if (hayFaltantes && !puedeConfirmar && (ESTADO_ANTERIOR === "presupuestado" || ESTADO_ANTERIOR === "confirmado")) {
          $("#aviso_faltantes").show();
        }
      },
      error: function () {
        $("#materiales_necesarios_body").html('<tr><td colspan="7" class="text-center text-danger">Error al calcular los materiales</td></tr>');
      },
    });
  }
  cargarMaterialesNecesarios();

  // ====== Confirmar el consumo real (recién acá se toca el stock) ======
  $("#btnConfirmarConsumo").on("click", function () {
    var consumos = [];
    var huboError = false;

    $("#materiales_necesarios_body .input-utilizado").each(function () {
      var idInsumo = $(this).data("id-insumo");
      var cantidad = parseFloat($(this).val());
      if (!cantidad || cantidad <= 0) {
        huboError = true;
        return;
      }
      consumos.push({ id_insumo: idInsumo, cantidad_utilizada: cantidad });
    });

    if (huboError || consumos.length === 0) {
      PNotify.notice({
        title: "Error",
        text: "Todas las cantidades utilizadas deben ser mayores a cero",
        type: "error",
        delay: DELAY_NOTIFICACION,
        styling: "brighttheme",
      });
      return;
    }

    var $btn = $(this);
    $btn.prop("disabled", true);

    $.ajax({
      url: "ajax/confirmar_consumo_pedido.php",
      type: "POST",
      data: {
        id_pedido: ID_PEDIDO,
        consumos: JSON.stringify(consumos),
      },
      dataType: "json",
      success: function (res) {
        $btn.prop("disabled", false);
        if (res.msg === "CORRECTO") {
          PNotify.notice({
            title: "Éxito",
            text: res.texto,
            type: "success",
            delay: DELAY_NOTIFICACION,
            styling: "brighttheme",
          });
          cargarMaterialesNecesarios();
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
        $btn.prop("disabled", false);
        PNotify.notice({
          title: "Error",
          text: "Error al confirmar el consumo",
          type: "error",
          delay: DELAY_NOTIFICACION,
          styling: "brighttheme",
        });
      },
    });
  });

  // ====== Guardar el pedido ======
  $("#form_pedido").on("submit", function (e) {
    e.preventDefault();

    var idCliente = $("#select_cliente").val();
    if (!idCliente) {
      PNotify.notice({
        title: "Error",
        text: "Debe seleccionar un cliente",
        type: "error",
        delay: DELAY_NOTIFICACION,
        styling: "brighttheme",
      });
      return;
    }

    var detalles = [];
    var huboError = false;
    $("#detalle_pedido_body .fila-detalle").each(function () {
      var idProducto = $(this).find(".select2-producto").val();
      var cantidad = parseFloat($(this).find(".input-cantidad").val());
      var precio = parseFloat($(this).find(".input-precio").val());

      if (!idProducto || !cantidad || cantidad <= 0 || isNaN(precio) || precio < 0) {
        huboError = true;
        return;
      }
      detalles.push({ id_producto: idProducto, cantidad: cantidad, precio_unitario: precio });
    });

    if (huboError || detalles.length === 0) {
      PNotify.notice({
        title: "Error",
        text: "Revisá que todas las líneas tengan producto, cantidad (mayor a 0) y precio cargados",
        type: "error",
        delay: DELAY_NOTIFICACION,
        styling: "brighttheme",
      });
      return;
    }

    var $btn = $("#btnGuardarPedido");
    $btn.prop("disabled", true);

    $.ajax({
      url: "ajax/guardar_pedido.php",
      type: "POST",
      data: {
        id_pedido: ID_PEDIDO,
        id_cliente: idCliente,
        fecha_pedido: $("#input_fecha_pedido").val(),
        fecha_entrega_estimada: $("[name='fecha_entrega_estimada']").val(),
        fecha_entrega_real: $("#input_fecha_entrega_real").val(),
        estado: $("#select_estado").val(),
        sena_pagada: $("#input_sena_pagada").val() || 0,
        observaciones: $("[name='observaciones']").val(),
        reponer_stock: $("#check_reponer_stock").is(":checked") ? "1" : "0",
        detalles: JSON.stringify(detalles),
      },
      dataType: "json",
      success: function (res) {
        if (res.msg === "CORRECTO") {
          hayCambiosSinGuardar = false;
          PNotify.notice({
            title: "Éxito",
            text: res.texto,
            type: "success",
            delay: DELAY_NOTIFICACION,
            styling: "brighttheme",
          });
          setTimeout(function () {
            window.location.href = "pedidos.php";
          }, 1000);
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
          text: "Error al guardar el pedido",
          type: "error",
          delay: DELAY_NOTIFICACION,
          styling: "brighttheme",
        });
      },
    });
  });
});