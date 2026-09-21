<?php
/**
 * Endpoint de autenticación
 * Recibe por POST: usuario, password
 * Responde JSON: { msg, type, texto }
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/config/conexion.php';

// ---------------------------------------------------------------------
// Configuración del bloqueo por intentos fallidos
// ---------------------------------------------------------------------
const MAX_INTENTOS      = 5;   // intentos fallidos permitidos
const MINUTOS_BLOQUEO   = 15;  // minutos que dura el bloqueo temporal

$usuario  = trim($_POST['usuario'] ?? '');
$password = $_POST['password'] ?? '';

$json = ['msg' => 'DENEGADO', 'type' => 'error', 'texto' => 'Usuario o contraseña incorrectos'];

try {
    if ($usuario === '' || $password === '') {
        throw new Exception('Debe completar usuario y contraseña');
    }

    // -------------------------------------------------------------
    // 1. Buscar usuario por usuario_nick
    // -------------------------------------------------------------
    $sql = "SELECT id_usuario, nombre, email, usuario_nick, password_hash, rol, activo,
                   intentos_fallidos, bloqueado_hasta
            FROM usuarios
            WHERE usuario_nick = :usuario_nick";
    $st = $conexion->prepare($sql);
    $st->execute(['usuario_nick' => $usuario]);
    $usuarioRow = $st->fetch(PDO::FETCH_ASSOC);

    // No revelamos si el usuario existe o no (mismo mensaje genérico)
    if (!$usuarioRow) {
        throw new Exception('Usuario o contraseña incorrectos');
    }

    // -------------------------------------------------------------
    // 2. Verificar si está bloqueado temporalmente
    // -------------------------------------------------------------
    if ($usuarioRow['bloqueado_hasta'] !== null && strtotime($usuarioRow['bloqueado_hasta']) > time()) {
        $minutosRestantes = ceil((strtotime($usuarioRow['bloqueado_hasta']) - time()) / 60);
        throw new Exception("Usuario bloqueado temporalmente. Intente nuevamente en {$minutosRestantes} minuto(s).");
    }

    // -------------------------------------------------------------
    // 3. Verificar que el usuario esté activo
    // -------------------------------------------------------------
    if ($usuarioRow['activo'] !== 't' && $usuarioRow['activo'] !== true) {
        throw new Exception('Usuario deshabilitado. Contacte al administrador.');
    }

    // -------------------------------------------------------------
    // 4. Verificar contraseña
    // -------------------------------------------------------------
    if (!password_verify($password, $usuarioRow['password_hash'])) {
        $intentos = $usuarioRow['intentos_fallidos'] + 1;

        if ($intentos >= MAX_INTENTOS) {
            // Se alcanzó el máximo de intentos: bloquear temporalmente
            $bloqueadoHasta = date('Y-m-d H:i:s', strtotime('+' . MINUTOS_BLOQUEO . ' minutes'));
            $sqlBloqueo = "UPDATE usuarios
                           SET intentos_fallidos = :intentos, bloqueado_hasta = :bloqueado
                           WHERE id_usuario = :id";
            $stB = $conexion->prepare($sqlBloqueo);
            $stB->execute([
                'intentos'  => $intentos,
                'bloqueado' => $bloqueadoHasta,
                'id'        => $usuarioRow['id_usuario']
            ]);
            throw new Exception("Demasiados intentos fallidos. Usuario bloqueado por " . MINUTOS_BLOQUEO . " minutos.");
        }

        // Todavía no llegó al máximo: solo sumamos el intento
        $sqlIntento = "UPDATE usuarios SET intentos_fallidos = :intentos WHERE id_usuario = :id";
        $stI = $conexion->prepare($sqlIntento);
        $stI->execute(['intentos' => $intentos, 'id' => $usuarioRow['id_usuario']]);

        $restantes = MAX_INTENTOS - $intentos;
        throw new Exception("Usuario o contraseña incorrectos. Le quedan {$restantes} intento(s).");
    }

    // -------------------------------------------------------------
    // 5. Login correcto: reiniciar intentos y abrir sesión
    // -------------------------------------------------------------
    $sqlReset = "UPDATE usuarios
                 SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW()
                 WHERE id_usuario = :id";
    $stR = $conexion->prepare($sqlReset);
    $stR->execute(['id' => $usuarioRow['id_usuario']]);

    $_SESSION['autenticado']  = true;
    $_SESSION['id_usuario']   = $usuarioRow['id_usuario'];
    $_SESSION['nombre']       = $usuarioRow['nombre'];
    $_SESSION['email']        = $usuarioRow['email'];
    $_SESSION['usuario_nick'] = $usuarioRow['usuario_nick'];
    $_SESSION['rol']          = $usuarioRow['rol'];

    $json = ['msg' => 'CORRECTO', 'type' => 'success', 'texto' => 'Redirigiendo...'];

} catch (Exception $e) {
    $json = ['msg' => 'DENEGADO', 'type' => 'error', 'texto' => $e->getMessage()];
}

echo json_encode($json);