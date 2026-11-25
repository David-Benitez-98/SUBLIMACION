<link rel="stylesheet" href="<?php echo BASE_URL; ?>/boostrap5/css/bootstrap.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/jquery.loadingModal.min.css">
<link rel="shortcut icon" href="<?php echo BASE_URL; ?>/imgs/favicon.png">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/nuevoestilo.css">
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="<?php echo BASE_URL; ?>/js/jquery-3.6.0.min.js"></script>
<script src="<?php echo BASE_URL; ?>/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo BASE_URL; ?>/lib_fontawesome/js/all.js"></script>
<script src="<?php echo BASE_URL; ?>/js/jquery.loadingModal.min.js"></script>
<script src="<?php echo BASE_URL; ?>/js/comun.js"></script>
<script>
    // Sesión expirada: si cualquier llamada AJAX responde 401, volvemos al login.
    // El servidor ya guardó la página actual para volver a ella después de loguearse.
    (function () {
        var urlLogin = '<?php echo BASE_URL; ?>/index.php?expirada=1';

        $(document).ajaxError(function (event, jqxhr) {
            if (jqxhr.status === 401) {
                window.location.href = urlLogin;
            }
        });

        // DataTables muestra su propio cartel "Ajax error" antes del handler global.
        // Al devolver true le indicamos que el error ya fue manejado (evita el cartel).
        $(document).on('xhr.dt', function (e, settings, json, xhr) {
            if (xhr && xhr.status === 401) {
                window.location.href = urlLogin;
                return true;
            }
        });
    })();
</script>
