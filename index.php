<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="shortcut icon" href="imgs/favicon.png">
  <link rel="stylesheet" type="text/css" href="css/nuevoestilo.css">
  <link rel="stylesheet" href="css/jquery.loadingModal.min.css">
  <title>LOGIN | SUBLIMACION</title>
</head>

<body>
  <section class="material-half-bg">
    <div class="cover"></div>
  </section>
  <section class="login-content">
    <div class="logo text-center">
      <img src="imgs/" alt="" style="width: 7%;">
    
    </div>
    <div class="login-box">
      <form id="loginform" class="login-form" action="security/autenticacion.php" method="POST">
        <h3 class="login-head"><i class="fa fa-lg fa-fw fa-user"></i>INICIO DE SESION</h3>
        <div class="form-group">
          <div id="mensaje"></div>
        </div>
        <div class="form-group">
          <label class="control-label">USUARIO</label>
          <input class="form-control" name="usuario" type="text" placeholder="Ej. jperez" autofocus>
        </div>
        <div class="form-group">
          <label class="control-label">CONTRASEÑA</label>
          <div class="input-group">
            <input class="form-control" id="azc" name="password" type="password" placeholder="************" required>
            <div class="input-group-append" onclick="mostrarpass()"><span class="input-group-text"><i class="fas fa-eye" id="1"></i></span></div>
          </div>
        </div>
        <div class="form-group btn-container">
          <button class="btn btn-primary btn-block" id="btniniciar"><i class="fas fa-sign-in-alt fa-lg fa-fw"></i>INICIAR SESIÓN</button>
        </div>
        <div class="form-group">
          <div class="utility">
            <p class="semibold-text mb-2"><a href="#" data-toggle="flip">Olvidaste tú contraseña ?</a></p>
          </div>
        </div>
      </form>
      <form class="forget-form" action="security/recuperarpassword.php">
        <h3 class="login-head"><i class="fa fa-lg fa-fw fa-lock"></i>RESETEO DE CONTRASEÑA ?</h3>
        <div class="form-group">
          <div id="mensaje2"></div>
        </div>
        <div class="form-group">
          <label class="control-label">CORREO ELECTRÓNICO</label>
          <input class="form-control" type="email" placeholder="Email" name="correoelectronico" required>
        </div>
        <div class="form-group btn-container">
          <button class="btn btn-primary btn-block"><i class="fa fa-unlock fa-lg fa-fw"></i>Enviar enlace</button>
        </div>

        <div class="form-group mt-3">
          <p class="semibold-text mb-0"><a href="#" data-toggle="flip"><i class="fa fa-angle-left fa-fw"></i> Volver al Inicio de sesión</a></p>
        </div>
      </form>
    </div>
  </section>
  <!-- Essential javascripts for application to work-->
  <script src="js/jquery-3.6.0.min.js"></script>
  <script src="boostrap5/js/bootstrap.min.js"></script>
  <script src="js/funcionesnuevo.js"></script>
  <script src="lib_fontawesome/js/all.js"></script>
  <script src="js/jquery.loadingModal.min.js"></script>
  <script src="js/login.js"></script>
  <!-- The javascript plugin to display page loading on top-->
  <!-- <script src="js/plugins/pace.min.js"></script> -->
  <script type="text/javascript">
    // Login Page Flipbox control
    $('.login-content [data-toggle="flip"]').click(function() {
      $('.login-box').toggleClass('flipped');
      return false;
    });

    function mostrarpass() {
      var x = document.getElementById("azc");
      if (x.type === "password") {
        x.type = "text";
        $("#azc").removeClass("fa-eye");
        $("#azc").addClass("fa-eye-slash");
      } else {
        x.type = "password";
        $("#azc").removeClass("fa-eye-slash");
        $("#azc").addClass("fa-eye");
      }
    }
  </script>
</body>

</html>