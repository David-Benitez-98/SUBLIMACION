/**
 * comun.js
 * -----------------------------------------------------------------
 * Utilidades compartidas por TODOS los módulos. Se carga una sola vez
 * en cabecera.php, igual que jQuery o Bootstrap.
 * -----------------------------------------------------------------
 */

// ---------------------------------------------------------------
// Tiempo (en milisegundos) que quedan visibles las notificaciones
// PNotify en TODO el sistema. Cambiá este único valor para ajustar
// el tiempo de todas las alertas, sin tener que buscarlas módulo
// por módulo.
// ---------------------------------------------------------------
const DELAY_NOTIFICACION = 2000;

// ---------------------------------------------------------------
// Loading global: se prende/apaga solo en cualquier petición AJAX
// de cualquier módulo, sin que cada uno lo tenga que manejar.
// ---------------------------------------------------------------
$(document)
  .on("ajaxSend", function () {
    $("body").loadingModal("show");
  })
  .on("ajaxComplete", function () {
    $("body").loadingModal("hide");
  })
  .on("ajaxError", function (event, jqxhr) {
    // Una petición ABORTADA (por ejemplo, cuando DataTable cancela un
    // reload anterior porque llega uno nuevo) NO es un error real.
    // Si no filtramos esto, se dispara una notificación de error falsa
    // cada vez que se solapan dos peticiones (ej: modal.hide() + dt.ajax.reload()).
    if (jqxhr.statusText === "abort" || jqxhr.status === 0) {
      return;
    }

    let mensaje = "Ocurrió un error interno";
    try {
      mensaje = jqxhr.responseJSON.texto || mensaje;
    } catch (e) {
      /* la respuesta no era JSON, nos quedamos con el mensaje genérico */
    }
    PNotify.error({
      title: "Error",
      text: mensaje,
      textTrusted: true,
      delay: DELAY_NOTIFICACION,
    });
  });

/**
 * Dispatcher genérico de formularios.
 * Cualquier <form data-ajax-tipo="abm"> en CUALQUIER módulo entra acá,
 * sin que cada módulo tenga que escribir su propio $.ajax de submit.
 *
 * Contrato esperado del backend (el mismo que ya venís usando):
 * { msg: 'CORRECTO'|'ERROR', type: 'success'|'error', texto: '...' }
 *
 * Atributos que puede llevar el <form>:
 * data-ajax-tipo="abm" -> alta/edición/baja genérica
 * data-tabla="#tbl_x"  -> (opcional) selector del DataTable a refrescar
 */
$(document).on("submit", "form[data-ajax-tipo]", function (e) {
  e.preventDefault();
  let form = $(this);
  let tipo = form.attr("data-ajax-tipo");

  // Evita doble envío: doble clic, Enter + clic, etc.
  if (form.data("enviando")) {
    return;
  }
  form.data("enviando", true);
  form.find('button[type="submit"]').prop("disabled", true);

  $.ajax({
    url: form.attr("action"),
    type: form.attr("method"),
    data: form.serialize(),
    dataType: "json",
    success: function (resp) {
      if (tipo === "abm") {
        if (resp.msg === "CORRECTO") {
          form.closest(".modal").modal("hide");
          form[0].reset();

          let tablaSelector = form.data("tabla");
          if (tablaSelector) {
            let tabla = $(tablaSelector);
            if (tabla.length && $.fn.DataTable.isDataTable(tabla)) {
              let dt = tabla.DataTable();
              dt.ajax.reload(function () {
                dt.columns.adjust();
              }, false);
            }
          }
        }
        if (resp.type === "success") {
          PNotify.success({
            title: "Éxito",
            text: resp.texto,
            textTrusted: true,
            delay: DELAY_NOTIFICACION,
          });
        } else {
          PNotify.error({
            title: "Atención",
            text: resp.texto,
            textTrusted: true,
            delay: DELAY_NOTIFICACION,
          });
        }
      }
    },
    complete: function () {
      form.data("enviando", false);
      form.find('button[type="submit"]').prop("disabled", false);
    },
  });
});

/**
 * Combo dependiente reutilizable.
 * Ej de uso (departamento -> ciudad):
 * initComboDependiente('#select_departamento', '#select_ciudad',
 *      '../../../ajax/obtener_ciudades.php', 'id_departamento');
 *
 * El endpoint SIEMPRE debe devolver un array de objetos {id, nombre}
 * -- así esta función sirve para cualquier combo futuro, no solo ciudades.
 *
 * @param {string} selectPadreId    selector del select "padre" (ej: departamento)
 * @param {string} selectHijoId     selector del select "hijo" (ej: ciudad)
 * @param {string} urlHijo          endpoint que devuelve las opciones del hijo
 * @param {string} nombreParametro  nombre del parámetro GET que espera el endpoint
 */
function initComboDependiente(
  selectPadreId,
  selectHijoId,
  urlHijo,
  nombreParametro,
) {
  $(selectPadreId).on("change", function () {
    let valor = $(this).val();
    let selectHijo = $(selectHijoId);

    if (!valor) {
      selectHijo
        .html('<option value="">Elija primero la opción anterior</option>')
        .prop("disabled", true);
      return;
    }

    let datos = {};
    datos[nombreParametro] = valor;

    $.get(
      urlHijo,
      datos,
      function (opciones) {
        let html = '<option value="">Seleccione...</option>';
        opciones.forEach(function (o) {
          html += `<option value="${o.id}">${o.nombre}</option>`;
        });
        selectHijo.html(html).prop("disabled", false);
      },
      "json",
    );
  });
}