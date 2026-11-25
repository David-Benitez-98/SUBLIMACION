<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';
require_once __DIR__ . '/helpers_movimientos.php';

exigirAcceso($conexion, '/modulos/movimientos/movimientos_stock/movimientos_stock.php');

header('Content-Type: application/json; charset=utf-8');

$idInsumo  = intval($_POST['id_insumo'] ?? 0);
$clase     = trim($_POST['clase'] ?? '');
$cantidad  = round((float) ($_POST['cantidad'] ?? 0), 3);
$motivo    = trim($_POST['motivo'] ?? '');
$idUsuario = $_SESSION['id_usuario'] ?? null;

$json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'No se pudo registrar el ajuste'];

// clase permitida => tipo de movimiento (el trigger usa 'entrada'/'salida' para mover el stock)
$clasesPermitidas = [
    'AJUSTE_POSITIVO' => 'entrada',
    'AJUSTE_NEGATIVO' => 'salida',
    'MERMA'           => 'salida',
];

try {
    if (!isset($clasesPermitidas[$clase])) {
        throw new Exception('Tipo de operación inválido');
    }
    if ($idInsumo <= 0) {
        throw new Exception('El insumo es obligatorio');
    }
    if ($cantidad <= 0) {
        throw new Exception('La cantidad debe ser mayor a cero');
    }
    if ($motivo === '') {
        throw new Exception('El motivo es obligatorio para ajustes y mermas');
    }
    if (mb_strlen($motivo) > 150) {
        throw new Exception('El motivo no puede superar los 150 caracteres');
    }

    $tipo = $clasesPermitidas[$clase];

    $conexion->beginTransaction();

    // Se bloquea la fila del insumo para que nadie más modifique el stock mientras calculamos
    $stInsumo = $conexion->prepare("SELECT nombre, stock_actual FROM insumos WHERE id_insumo = :id AND activo = true FOR UPDATE");
    $stInsumo->execute(['id' => $idInsumo]);
    $insumo = $stInsumo->fetch(PDO::FETCH_ASSOC);

    if (!$insumo) {
        throw new Exception('El insumo no existe o está inactivo');
    }

    $stockAnterior = (float) $insumo['stock_actual'];
    $stockNuevo = round($tipo === 'entrada' ? $stockAnterior + $cantidad : $stockAnterior - $cantidad, 3);

    if ($stockNuevo < 0) {
        throw new Exception('Stock insuficiente. El stock actual de ' . $insumo['nombre'] . ' es ' . formatearStock($stockAnterior));
    }

    // Mismo patrón que compras: se registra el movimiento y el trigger actualiza insumos.stock_actual solo
    $sqlMovimiento = "INSERT INTO movimientos_stock (id_insumo, tipo, clase, cantidad, motivo, fecha, id_usuario, stock_anterior, stock_nuevo)
                      VALUES (:id_insumo, :tipo, :clase, :cantidad, :motivo, NOW(), :id_usuario, :stock_anterior, :stock_nuevo)";
    $stMovimiento = $conexion->prepare($sqlMovimiento);
    $stMovimiento->execute([
        'id_insumo'      => $idInsumo,
        'tipo'           => $tipo,
        'clase'          => $clase,
        'cantidad'       => $cantidad,
        'motivo'         => $motivo,
        'id_usuario'     => $idUsuario,
        'stock_anterior' => $stockAnterior,
        'stock_nuevo'    => $stockNuevo
    ]);

    // Leer el saldo real después del trigger para mostrárselo al usuario
    $stSaldo = $conexion->prepare("SELECT stock_actual FROM insumos WHERE id_insumo = :id");
    $stSaldo->execute(['id' => $idInsumo]);
    $saldoFinal = (float) $stSaldo->fetchColumn();

    $conexion->commit();

    $json = [
        'msg'   => 'CORRECTO',
        'type'  => 'success',
        'texto' => 'Movimiento registrado. Nuevo stock de ' . $insumo['nombre'] . ': ' . formatearStock($saldoFinal)
    ];

} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    error_log('guardar_ajuste_stock: ' . $e->getMessage());
    $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => 'Ocurrió un error al registrar el ajuste.'];
} catch (Exception $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    $json = ['msg' => 'ERROR', 'type' => 'error', 'texto' => $e->getMessage()];
}

echo json_encode($json);
