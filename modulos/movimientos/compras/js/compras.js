$(document).ready(function () {
  const DELAY_NOTIFICACION = 3000;
  let idCompraSeleccionada = null;

  // ====== Inicializar DataTable ======
  const tabla = $("#tbl_compras").DataTable({
    autoWidth: false,
    ajax: {
      url: "ajax/listar_compras.php",
      dataSrc: "data",
    },
    columns: [
      { data: "id_compra" },
      { data: "numero_compra" },
      { data: "nombre_proveedor" },
      { data: "fecha_compra" },
      { data: "total" },
      { data: "estado" },
      { data: "acciones", orderable: false, searchable: false },
    ],
    order: [[0, "desc"]],
    language: {
      url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json",
    },
  });

  // ====== Ver detalle de una compra ======
  $("#tbl_compras tbody").on("click", ".btn-ver-detalle", function () {
    const idCompra = $(this).data("id");

    $.ajax({
      url: "ajax/obtener_detalle_compra.php",
      type: "GET",
      data: { id_compra: idCompra },
      dataType: "json",
      success: function (res) {
        if (!res || res.msg === "ERROR") {
          PNotify.notice({
            title: "Error",
            text: (res && res.texto) || "No se pudo obtener el detalle de la compra",
            type: "error",
            delay: DELAY_NOTIFICACION,
            styling: "brighttheme",
          });
          return;
        }

        $("#detalle_numero_compra").text(res.numero_compra);
        $("#detalle_proveedor").text(res.nombre_proveedor);
        $("#detalle_fecha").text(res.fecha_compra);
        $("#detalle_estado").html(res.estado);
        $("#detalle_total").html("<strong>" + res.total + "</strong>");

        const $body = $("#detalle_lineas_body").empty();
        res.lineas.forEach(function (linea) {
          $body.append(
            "<tr>" +
              "<td>" + linea.nombre_insumo + "</td>" +
              "<td>" + linea.nombre_unidad + "</td>" +
              "<td>" + linea.cantidad + "</td>" +
              "<td>" + linea.precio_unitario + "</td>" +
              "<td>" + linea.subtotal + "</td>" +
              "</tr>"
          );
        });

        if (res.observaciones) {
          $("#detalle_observaciones").text(res.observaciones);
          $("#detalle_observaciones_wrapper").show();
        } else {
          $("#detalle_observaciones_wrapper").hide();
        }

        const modalDetalle = new bootstrap.Modal(document.getElementById("modalDetalleCompra"));
        modalDetalle.show();
      },
      error: function () {
        PNotify.notice({
          title: "Error",
          text: "Error al obtener el detalle de la compra",
          type: "error",
          delay: DELAY_NOTIFICACION,
          styling: "brighttheme",
        });
      },
    });
  });

  // ====== Preparar anulación (abre el modal de confirmación) ======
  $("#tbl_compras tbody").on("click", ".btn-anular", function () {
    idCompraSeleccionada = $(this).data("id");
    const numeroCompra = $(this).data("numero");

    $("#mensajeConfirmacionAnular").text(
      "¿Está seguro de anular la compra " + numeroCompra + "? Esta acción revertirá el stock ingresado."
    );

    const modalConfirmar = new bootstrap.Modal(document.getElementById("modalConfirmAnular"));
    modalConfirmar.show();
  });

  // ====== Confirmar anulación ======
  $("#btnConfirmarAnular").on("click", function () {
    if (!idCompraSeleccionada) {
      return;
    }

    const $btn = $(this);
    $btn.prop("disabled", true);

    $.ajax({
      url: "ajax/anular_compra.php",
      type: "POST",
      data: { id_compra: idCompraSeleccionada },
      dataType: "json",
      success: function (res) {
        $btn.prop("disabled", false);

        if (res.msg === "CORRECTO") {
          bootstrap.Modal.getInstance(document.getElementById("modalConfirmAnular")).hide();
          tabla.ajax.reload(null, false);
          PNotify.notice({
            title: "Éxito",
            text: res.texto,
            type: "success",
            delay: DELAY_NOTIFICACION,
            styling: "brighttheme",
          });
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
          text: "Error al anular la compra",
          type: "error",
          delay: DELAY_NOTIFICACION,
          styling: "brighttheme",
        });
      },
      complete: function () {
        idCompraSeleccionada = null;
      },
    });
  });
});