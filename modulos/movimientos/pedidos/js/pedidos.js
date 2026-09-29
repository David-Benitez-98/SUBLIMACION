$(document).ready(function () {
  // ====== Calcular urgencia a partir de fecha_entrega_estimada ======
  function calcularUrgencia(fechaStr) {
    if (!fechaStr) {
      return '<span class="badge bg-light text-dark">Sin fecha</span>';
    }
    var hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    var partes = fechaStr.split("-");
    var fechaEntrega = new Date(partes[0], partes[1] - 1, partes[2]);
    var diffDias = Math.round((fechaEntrega - hoy) / (1000 * 60 * 60 * 24));

    if (diffDias < 0) {
      return '<span class="badge bg-dark">VENCIDA</span>';
    } else if (diffDias === 0) {
      return '<span class="badge bg-danger">CRÍTICA</span>';
    } else if (diffDias === 1) {
      return '<span class="badge bg-warning text-dark">ALTA</span>';
    } else if (diffDias <= 4) {
      return '<span class="badge bg-info text-dark">MEDIA</span>';
    } else {
      return '<span class="badge bg-secondary">NORMAL</span>';
    }
  }

  // ====== Inicializar DataTable ======
  var dt = $("#tbl_pedidos").DataTable({
    autoWidth: false,
    ajax: {
      url: "ajax/listar_pedidos.php",
      data: function (d) {
        d.estado = $("#filtro_estado").val();
      },
      dataSrc: "data",
    },
    columns: [
      { data: "id_pedido" },
      { data: "numero_pedido" },
      { data: "nombre_cliente" },
      { data: "fecha_pedido" },
      { data: "fecha_entrega_estimada", defaultContent: "-" },
      {
        data: "fecha_entrega_estimada_raw",
        render: function (data) {
          return calcularUrgencia(data);
        },
      },
      { data: "total" },
      { data: "saldo" },
      { data: "estado" },
      { data: "faltantes" },
      { data: "acciones", orderable: false, searchable: false },
    ],
    order: [], // el orden ya viene resuelto del backend (por fecha de entrega)
    language: {
      url: "/sublimacion/js/es-ES.json",
    },
  });

  // ====== Filtro por estado ======
  $("#filtro_estado").on("change", function () {
    dt.ajax.reload();
  });
});