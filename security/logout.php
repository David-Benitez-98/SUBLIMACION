<?php
session_start();

require_once dirname(__DIR__) . '/config/constantes.php';

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    // ... (sin cambios)
}

session_destroy();

header('Location: ' . BASE_URL . '/index.php');
exit;