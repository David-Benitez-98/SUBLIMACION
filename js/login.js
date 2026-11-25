$(document).ajaxStart(function () {
  $("body").loadingModal("show");
});
$(document).ajaxStop(function () {
  $("body").loadingModal("hide");
});

$(document).ready(function () {
  $("body").loadingModal();
  $("body").loadingModal("hide");

  // Si llegó acá porque se cerró la sesión por inactividad, se lo avisamos
  if (new URLSearchParams(window.location.search).get("expirada") === "1") {
    $("#mensaje").html(
      '<div class="alert alert-warning"><strong>Sesión expirada.</strong> Por inactividad se cerró su sesión. Vuelva a iniciar sesión.</div>',
    );
  }

  window.addEventListener("pageshow", function (event) {
    if (event.persisted) {
      // La página volvió desde el caché del navegador (botón "Atrás")
      resetLoginForm();
    }
  });

  function resetLoginForm() {
    $("#btniniciar")
      .html('<i class="fas fa-sign-in-alt fa-lg fa-fw"></i>INICIAR SESIÓN')
      .prop("disabled", false);
    $("#mensaje").html("");
    $("#loginform")[0].reset(); // limpia los inputs, opcional
  }

  $("form").on("submit", function (e) {
    e.preventDefault();
    $("body").loadingModal("show");
    $form = $(this);
    $.ajax({
      url: $form.attr("action"),
      type: $form.attr("method"),
      data: $form.serialize(),
      success: function (response) {
        $("body").loadingModal("hide");
        switch (response.msg) {
          case "CORRECTO":
            $("#btniniciar").text("Redirigiendo . . .");
            $("#btniniciar").prop("disabled", true);
            $("#mensaje").html(
              '<div class="alert alert-success"><strong>¡Acceso Correcto, Bienvenido/a! </strong>' +
                response.texto +
                "</div>",
            );
            window.setTimeout(function () {
              // Si venía de otra página (sesión expirada), vuelve ahí; si no, a la portada
              location.href = response.destino || "portada/portada.php";
            }, 1000);
            break;
          case "ENVIADO":
          case "NOENVIADO":
            $("#mensaje2").html(
              '<div class="alert ' +
                response.type +
                '"><strong>Atención! </strong>' +
                response.texto +
                "</div>",
            );
            break;
          default:
            $("#mensaje").html(
              '<div class="alert ' +
                response.type +
                '"><strong>Atención! </strong>' +
                response.texto +
                "</div>",
            );
            break;
        }
      },
      error: function () {
        $("#mensaje").html(
          '<div class="alert alert-danger"><strong>Error Interno!</strong> Si el problema persiste, comunicarse con el Dpto. de Informática.</div>',
        );
      },
    });
  });
});
