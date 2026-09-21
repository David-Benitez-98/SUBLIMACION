<?php
session_start();

require_once dirname(__DIR__) . '/config/constantes.php';

if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}