<?php
session_start();

require_once dirname(__DIR__) . '/config/constantes.php';

// Minutos de inactividad permitidos antes de cerrar la sesión
if (!defined('MINUTOS_INACTIVIDAD')) {
    define('MINUTOS_INACTIVIDAD', 15);
}

// jQuery envía este header en todas las llamadas $.ajax / $.post / $.get
$esAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

$sesionActiva = isset($_SESSION['autenticado']) && $_SESSION['autenticado'] === true;

$sesionExpirada = $sesionActiva
    && isset($_SESSION['ultima_actividad'])
    && (time() - $_SESSION['ultima_actividad']) > (MINUTOS_INACTIVIDAD * 60);

if (!$sesionActiva || $sesionExpirada) {

    // Guardamos la página donde estaba para volver ahí después de loguearse
    $destino = null;
    if ($esAjax) {
        // En una llamada AJAX la página real es la que hizo la llamada (Referer)
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $path    = parse_url($referer, PHP_URL_PATH);
        $query   = parse_url($referer, PHP_URL_QUERY);
        if ($path) {
            $destino = $path . ($query ? '?' . $query : '');
        }
    } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $destino = $_SERVER['REQUEST_URI'] ?? null;
    }

    // Limpiamos todo lo de la sesión anterior y generamos un id nuevo
    $_SESSION = [];
    session_regenerate_id(true);

    if ($destino !== null) {
        $_SESSION['url_destino'] = $destino;
    }

    if ($esAjax) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'msg'   => 'SESION_EXPIRADA',
            'type'  => 'error',
            'texto' => 'Su sesión expiró. Vuelva a iniciar sesión.'
        ]);
        exit;
    }

    $param = $sesionExpirada ? '?expirada=1' : '';
    header('Location: ' . BASE_URL . '/index.php' . $param);
    exit;
}

// Sesión válida: renovamos el contador de inactividad
$_SESSION['ultima_actividad'] = time();
