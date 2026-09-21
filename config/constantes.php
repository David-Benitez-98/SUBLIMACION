<?php
/**
 * constantes.php
 * -----------------------------------------------------------------
 * Guarda constantes globales de configuración del proyecto.
 * BASE_URL: la carpeta base donde vive el proyecto dentro del dominio.
 * Si el día de mañana movés el proyecto a otra carpeta, o a producción
 * con otra estructura, este es el ÚNICO lugar que hay que tocar.
 * -----------------------------------------------------------------
 */

if (!defined('BASE_URL')) {
    define('BASE_URL', '/sublimacion');
}

define('RUTA_PROYECTO', $_SERVER['DOCUMENT_ROOT'] . '/sublimacion');