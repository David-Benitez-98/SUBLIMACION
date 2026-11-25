<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';
require_once __DIR__ . '/helpers_movimientos.php';

exigirAcceso($conexion, '/modulos/movimientos/movimientos_stock/movimientos_stock.php');

// Mismos filtros que el listado, así lo exportado coincide con lo que se ve en pantalla
[$whereSql, $params] = construirFiltrosMovimientos($_GET);

$sql = "SELECT m.id_movimiento, m.fecha, m.tipo, m.clase, m.cantidad,
               m.stock_anterior, m.stock_nuevo, m.motivo, m.id_pedido,
               i.codigo, i.nombre AS nombre_insumo,
               um.nombre AS nombre_unidad,
               c.numero_compra,
               u.nombre AS nombre_usuario
        FROM movimientos_stock m
        INNER JOIN insumos i ON i.id_insumo = m.id_insumo
        LEFT JOIN unidades_medida um ON um.id_unidad = i.id_unidad
        LEFT JOIN compras c ON c.id_compra = m.id_compra
        LEFT JOIN usuarios u ON u.id_usuario = m.id_usuario"
        . $whereSql . " ORDER BY m.id_movimiento DESC";

$st = $conexion->prepare($sql);
$st->execute($params);

// Evita que Excel interprete como fórmula un texto que empiece con = + - @
function textoSeguroCsv($texto)
{
    $texto = (string) $texto;
    if ($texto !== '' && in_array($texto[0], ['=', '+', '-', '@'], true)) {
        return "'" . $texto;
    }
    return $texto;
}

// Número con coma decimal y sin separador de miles (lo que espera Excel en configuración regional es-PY)
function numeroCsv($valor)
{
    return number_format((float) $valor, 3, ',', '');
}

$nombreArchivo = 'movimientos_stock_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');

$salida = fopen('php://output', 'w');
fwrite($salida, "\xEF\xBB\xBF"); // BOM para que Excel respete los acentos

$clases = clasesMovimiento();
fputcsv($salida, ['ID', 'Fecha/Hora', 'Codigo', 'Insumo', 'Unidad', 'Tipo', 'Cantidad', 'Stock anterior', 'Stock nuevo', 'Origen', 'Usuario', 'Motivo'], ';');

while ($m = $st->fetch(PDO::FETCH_ASSOC)) {
    $etiquetaClase = ($m['clase'] !== null && isset($clases[$m['clase']]))
        ? $clases[$m['clase']][0]
        : ucfirst($m['tipo']);

    if (!empty($m['numero_compra'])) {
        $origen = $m['numero_compra'];
    } elseif (!empty($m['id_pedido'])) {
        $origen = 'Pedido #' . (int) $m['id_pedido'];
    } else {
        $origen = '';
    }

    $signo = $m['tipo'] === 'entrada' ? '+' : '-';

    fputcsv($salida, [
        $m['id_movimiento'],
        date('d/m/Y H:i:s', strtotime($m['fecha'])),
        textoSeguroCsv($m['codigo']),
        textoSeguroCsv($m['nombre_insumo']),
        textoSeguroCsv($m['nombre_unidad'] ?? ''),
        $etiquetaClase,
        $signo . numeroCsv($m['cantidad']),
        numeroCsv($m['stock_anterior']),
        numeroCsv($m['stock_nuevo']),
        textoSeguroCsv($origen),
        textoSeguroCsv($m['nombre_usuario'] ?? ''),
        textoSeguroCsv($m['motivo'] ?? ''),
    ], ';');
}

fclose($salida);
