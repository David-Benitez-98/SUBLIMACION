$(document).ready(function () {
  const DELAY_NOTIFICACION = 3000;

  function notificar(tipo, titulo, texto) {
    PNotify.notice({
      title: titulo,
      text: texto,
      type: tipo,
      delay: DELAY_NOTIFICACION,
      styling: "brighttheme",
    });
  }

  function formatearNumero(numero) {
    return numero.toLocaleString("es-PY", { minimumFractionDigits: 0, maximumFractionDigits: 3 });
  }

  // ====== Select2 de los filtros ======
  $("#f_insumo, #f_compra, #f_usuario").select2({ width: "100%" });

  // ====== Leer los filtros actuales ======
  function filtrosActuales() {
    return {
      id_insumo: $("#f_insumo").val(),
      clase: $("#f_clase").val(),
      desde: $("#f_desde").val(),
      hasta: $("#f_hasta").val(),
      id_compra: $("#f_compra").val(),
      id_pedido: $("#f_pedido").val(),
      id_usuario: $("#f_usuario").val(),
    };
  }

  // ====== Inicializar DataTable ======
  const tabla = $("#tbl_movimientos").DataTable({
    autoWidth: false,
    ajax: {
      url: "ajax/listar_movimientos.php",
      type: "GET",
      data: function (d) {
        return $.extend(d, filtrosActuales());
      },
      dataSrc: "data",
    },
    columns: [
      { data: "id_movimiento" },
      { data: { _: "fecha", sort: "fecha_orden" } },
      { data: "insumo" },
      { data: "clase" },
      { data: { _: "cantidad", sort: "cantidad_orden" } },
      { data: { _: "stock_anterior", sort: "stock_anterior_orden" } },
      { data: { _: "stock_nuevo", sort: "stock_nuevo_orden" } },
      { data: "origen" },
      { data: "usuario" },
      { data: "acciones", orderable: false, searchable: false },
    ],
    order: [[0, "desc"]],
    language: {
      url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json",
    },
  });

  // ====== Validar el rango de fechas de los filtros ======
  function rangoFechasValido() {
    const desde = $("#f_desde").val();
    const hasta = $("#f_hasta").val();

    if (desde && hasta && desde > hasta) {
      notificar("error", "Error", "La fecha 'Desde' no puede ser mayor que 'Hasta'");
      return false;
    }
    return true;
  }

  // ====== Buscar ======
  $("#btnBuscar").on("click", function () {
    if (!rangoFechasValido()) {
      return;
    }
    tabla.ajax.reload();
  });

  // ====== Exportar (CSV con los mismos filtros que se ven en pantalla) ======
  $("#btnExportar").on("click", function () {
    if (!rangoFechasValido()) {
      return;
    }
    window.location.href = "ajax/exportar_movimientos.php?" + $.param(filtrosActuales());
  });

  // ====== Limpiar ======
  $("#btnLimpiar").on("click", function () {
    $("#f_insumo, #f_compra, #f_usuario").val("").trigger("change");
    $("#f_clase").val("");
    $("#f_desde, #f_hasta, #f_pedido").val("");
    tabla.ajax.reload();
  });

  // ====== Ver detalle de un movimiento ======
  $("#tbl_movimientos tbody").on("click", ".btn-ver-detalle", function () {
    const idMovimiento = $(this).data("id");

    $.ajax({
      url: "ajax/obtener_detalle_movimiento.php",
      type: "GET",
      data: { id_movimiento: idMovimiento },
      dataType: "json",
      success: function (res) {
        if (!res || res.msg === "ERROR") {
          notificar("error", "Error", (res && res.texto) || "No se pudo obtener el detalle del movimiento");
          return;
        }

        $("#det_id").text(res.id_movimiento);
        $("#det_fecha").text(res.fecha);
        $("#det_insumo").html(res.insumo);
        $("#det_unidad").html(res.unidad || "—");
        $("#det_clase").html(res.clase);
        $("#det_cantidad").text(res.cantidad);
        $("#det_stock_anterior").text(res.stock_anterior);
        $("#det_stock_nuevo").text(res.stock_nuevo);
        $("#det_origen").html(res.origen);
        $("#det_usuario").html(res.usuario);
        $("#det_motivo").html(res.motivo || "—");

        const modalDetalle = new bootstrap.Modal(document.getElementById("modalDetalleMovimiento"));
        modalDetalle.show();
      },
      error: function () {
        notificar("error", "Error", "Error al obtener el detalle del movimiento");
      },
    });
  });

  // =====================================================================
  // AJUSTE / MERMA
  // =====================================================================
  const modalAjusteEl = document.getElementById("modalAjuste");
  const modalAjuste = new bootstrap.Modal(modalAjusteEl);

  // El select2 dentro de un modal necesita dropdownParent para poder escribir en el buscador
  $("#aj_insumo").select2({ width: "100%", dropdownParent: $("#modalAjuste") });

  // Stock actual (referencial) del insumo elegido
  function stockActualElegido() {
    const valor = $("#aj_insumo").find(":selected").attr("data-stock");
    return valor !== undefined && valor !== "" ? parseFloat(valor) : null;
  }

  // Muestra el stock resultante según tipo y cantidad
  function recalcularResultante() {
    const stock = stockActualElegido();
    const clase = $("#aj_clase").val();
    const cantidad = parseFloat($("#aj_cantidad").val());
    const $res = $("#aj_stock_resultante");

    $res.removeClass("text-danger");

    if (stock === null || !clase || isNaN(cantidad) || cantidad <= 0) {
      $res.text("—");
      return;
    }

    const resultante = clase === "AJUSTE_POSITIVO" ? stock + cantidad : stock - cantidad;
    $res.text(formatearNumero(resultante));
    if (resultante < 0) {
      $res.addClass("text-danger").append(" (stock insuficiente)");
    }
  }

  function limpiarFormularioAjuste() {
    $("#aj_insumo").val("").trigger("change");
    $("#aj_clase").val("");
    $("#aj_cantidad").val("");
    $("#aj_unidad").val("");
    $("#aj_motivo").val("");
    $("#aj_stock_actual").text("—");
    $("#aj_stock_resultante").removeClass("text-danger").text("—");
  }

  $("#btnNuevoAjuste").on("click", function () {
    limpiarFormularioAjuste();
    modalAjuste.show();
  });

  $("#aj_insumo").on("change", function () {
    const stock = stockActualElegido();
    const unidad = $(this).find(":selected").attr("data-unidad") || "";
    $("#aj_unidad").val(unidad);
    $("#aj_stock_actual").text(stock === null ? "—" : formatearNumero(stock));
    recalcularResultante();
  });

  $("#aj_clase").on("change", recalcularResultante);
  $("#aj_cantidad").on("input", recalcularResultante);

  // ====== Registrar el ajuste ======
  $("#form_ajuste").on("submit", function (e) {
    e.preventDefault();

    const idInsumo = $("#aj_insumo").val();
    const clase = $("#aj_clase").val();
    const cantidad = parseFloat($("#aj_cantidad").val());
    const motivo = $("#aj_motivo").val().trim();

    if (!idInsumo) {
      notificar("error", "Error", "Debe seleccionar un insumo");
      return;
    }
    if (!clase) {
      notificar("error", "Error", "Debe seleccionar el tipo de operación");
      return;
    }
    if (isNaN(cantidad) || cantidad <= 0) {
      notificar("error", "Error", "La cantidad debe ser mayor a 0");
      return;
    }
    if (motivo === "") {
      notificar("error", "Error", "El motivo es obligatorio para ajustes y mermas");
      return;
    }

    const $btn = $("#btnGuardarAjuste");
    $btn.prop("disabled", true);

    $.ajax({
      url: "ajax/guardar_ajuste_stock.php",
      type: "POST",
      data: { id_insumo: idInsumo, clase: clase, cantidad: cantidad, motivo: motivo },
      dataType: "json",
      success: function (res) {
        $btn.prop("disabled", false);

        if (res.msg === "CORRECTO") {
          modalAjuste.hide();
          tabla.ajax.reload(null, false);
          notificar("success", "Éxito", res.texto);
        } else {
          notificar("error", "Error", res.texto);
        }
      },
      error: function () {
        $btn.prop("disabled", false);
        notificar("error", "Error", "Error al registrar el ajuste");
      },
    });
  });
});
