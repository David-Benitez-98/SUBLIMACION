<?php
/**
 * Reporte general de pedidos.
 * Ruta sugerida: /reportes/reporte_pedidos.php
 * Consulta de solo lectura: no modifica pedidos, pagos ni stock.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

// Reutiliza el permiso del módulo de pedidos mientras se define uno específico para reportes.
exigirAcceso($conexion, '/modulos/movimientos/pedidos/pedidos.php');

$estadosPermitidos = [
    'presupuestado',
    'confirmado',
    'en_produccion',
    'listo',
    'entregado',
    'cancelado',
];

$etiquetasEstado = [
    'presupuestado' => 'Presupuestado',
    'confirmado'    => 'Confirmado',
    'en_produccion' => 'En producción',
    'listo'         => 'Listo',
    'entregado'     => 'Entregado',
    'cancelado'     => 'Cancelado',
];

function reporteEscapar($valor): string
{
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function reporteGs($valor): string
{
    return 'Gs. ' . number_format((float)$valor, 0, ',', '.');
}

function reporteCantidad($valor): string
{
    return rtrim(rtrim(number_format((float)$valor, 2, ',', '.'), '0'), ',');
}

function reporteFecha(?string $fecha): string
{
    if (!$fecha) {
        return '—';
    }

    $timestamp = strtotime($fecha);
    return $timestamp ? date('d/m/Y', $timestamp) : '—';
}

function reporteFechaValida(string $fecha): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        return false;
    }

    $objeto = DateTime::createFromFormat('!Y-m-d', $fecha);
    return $objeto instanceof DateTime && $objeto->format('Y-m-d') === $fecha;
}

function reporteTextoSeguroCsv($valor): string
{
    $texto = (string)($valor ?? '');
    // Mitiga fórmulas CSV al abrir texto controlado por usuarios en Excel.
    if ($texto !== '' && preg_match('/^[\s\x00-\x1F]*[=+@-]/u', $texto)) {
        return "'" . $texto;
    }
    return $texto;
}

function reporteClaseEstado(string $estado): string
{
    $clases = [
        'presupuestado' => 'estado-presupuestado',
        'confirmado'    => 'estado-confirmado',
        'en_produccion' => 'estado-produccion',
        'listo'         => 'estado-listo',
        'entregado'     => 'estado-entregado',
        'cancelado'     => 'estado-cancelado',
    ];
    return $clases[$estado] ?? 'estado-default';
}

$fechaDesde = trim((string)($_GET['fecha_desde'] ?? ''));
$fechaHasta = trim((string)($_GET['fecha_hasta'] ?? ''));
$estadoFiltro = trim((string)($_GET['estado'] ?? ''));
$buscar = trim((string)($_GET['buscar'] ?? ''));
$exportar = (string)($_GET['exportar'] ?? '') === 'csv';

$errores = [];
$filas = [];
$errorConsulta = false;

if ($fechaDesde !== '' && !reporteFechaValida($fechaDesde)) {
    $errores[] = 'La fecha inicial no es válida.';
}
if ($fechaHasta !== '' && !reporteFechaValida($fechaHasta)) {
    $errores[] = 'La fecha final no es válida.';
}
if ($fechaDesde !== '' && $fechaHasta !== '' && reporteFechaValida($fechaDesde) && reporteFechaValida($fechaHasta) && $fechaDesde > $fechaHasta) {
    $errores[] = 'La fecha inicial no puede ser posterior a la fecha final.';
}
if ($estadoFiltro !== '' && !in_array($estadoFiltro, $estadosPermitidos, true)) {
    $estadoFiltro = '';
}
if (function_exists('mb_strlen') && mb_strlen($buscar, 'UTF-8') > 100) {
    $buscar = mb_substr($buscar, 0, 100, 'UTF-8');
} elseif (!function_exists('mb_strlen') && strlen($buscar) > 100) {
    $buscar = substr($buscar, 0, 100);
}

if (empty($errores)) {
    $condiciones = [];
    $parametros = [];

    if ($fechaDesde !== '') {
        $condiciones[] = 'p.fecha_pedido >= :fecha_desde';
        $parametros['fecha_desde'] = $fechaDesde;
    }
    if ($fechaHasta !== '') {
        $condiciones[] = 'p.fecha_pedido <= :fecha_hasta';
        $parametros['fecha_hasta'] = $fechaHasta;
    }
    if ($estadoFiltro !== '') {
        $condiciones[] = 'p.estado = :estado';
        $parametros['estado'] = $estadoFiltro;
    }
    if ($buscar !== '') {
        $condiciones[] = "(
            p.numero_pedido ILIKE :buscar_numero
            OR (COALESCE(c.nombre, '') || ' ' || COALESCE(c.apellido, '')) ILIKE :buscar_cliente
            OR COALESCE(c.telefono, '') ILIKE :buscar_telefono
        )";
        $patron = '%' . $buscar . '%';
        $parametros['buscar_numero'] = $patron;
        $parametros['buscar_cliente'] = $patron;
        $parametros['buscar_telefono'] = $patron;
    }

    $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

    $sql = "
        SELECT
            p.id_pedido,
            p.numero_pedido,
            p.fecha_pedido,
            p.fecha_entrega_estimada,
            p.fecha_entrega_real,
            p.estado,
            p.total,
            p.sena_pagada,
            p.observaciones,
            c.nombre AS cliente_nombre,
            c.apellido AS cliente_apellido,
            c.telefono AS cliente_telefono,
            COALESCE(det.cantidad_lineas, 0) AS cantidad_lineas,
            COALESCE(det.unidades, 0) AS total_unidades
        FROM sublimacion.pedidos p
        INNER JOIN sublimacion.clientes c
            ON c.id_cliente = p.id_cliente
        LEFT JOIN (
            SELECT
                id_pedido,
                COUNT(*) AS cantidad_lineas,
                COALESCE(SUM(cantidad), 0) AS unidades
            FROM sublimacion.pedido_detalle
            GROUP BY id_pedido
        ) det ON det.id_pedido = p.id_pedido
        $where
        ORDER BY
            CASE WHEN p.estado = 'cancelado' THEN 1 ELSE 0 END,
            p.fecha_entrega_estimada ASC NULLS LAST,
            p.fecha_pedido DESC,
            p.id_pedido DESC
    ";

    try {
        $stmt = $conexion->prepare($sql);
        $stmt->execute($parametros);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Reporte pedidos: ' . $e->getMessage());
        $errorConsulta = true;
    }
}

// Resumen calculado sobre el resultado filtrado. Los cancelados se muestran en la tabla,
// pero no se incluyen en los totales económicos operativos.
$totalActivos = 0.0;
$totalSenias = 0.0;
$totalSaldo = 0.0;
$conteoPorEstado = array_fill_keys($estadosPermitidos, 0);

foreach ($filas as $fila) {
    $estadoActual = (string)$fila['estado'];
    if (isset($conteoPorEstado[$estadoActual])) {
        $conteoPorEstado[$estadoActual]++;
    }
    if ($estadoActual !== 'cancelado') {
        $total = (float)$fila['total'];
        $sena = (float)$fila['sena_pagada'];
        $totalActivos += $total;
        $totalSenias += $sena;
        $totalSaldo += max(0, $total - $sena);
    }
}

// Exportación CSV compatible con Excel. Se exportan únicamente las filas filtradas.
if ($exportar && !$errorConsulta && empty($errores)) {
    $nombreArchivo = 'reporte_pedidos_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo "\xEF\xBB\xBF"; // BOM para que Excel interprete UTF-8 correctamente.

    $salida = fopen('php://output', 'w');
    fputcsv($salida, [
        'N.º pedido', 'Fecha pedido', 'Cliente', 'Teléfono', 'Fecha entrega estimada',
        'Fecha entrega real', 'Estado', 'Líneas de productos', 'Unidades', 'Total (Gs.)',
        'Seña registrada (Gs.)', 'Saldo estimado (Gs.)', 'Observaciones'
    ], ';', '"', '\\');

    foreach ($filas as $fila) {
        $saldo = max(0, (float)$fila['total'] - (float)$fila['sena_pagada']);
        fputcsv($salida, [
            reporteTextoSeguroCsv($fila['numero_pedido']),
            $fila['fecha_pedido'],
            reporteTextoSeguroCsv(trim($fila['cliente_nombre'] . ' ' . $fila['cliente_apellido'])),
            reporteTextoSeguroCsv($fila['cliente_telefono']),
            $fila['fecha_entrega_estimada'] ?? '',
            $fila['fecha_entrega_real'] ?? '',
            $etiquetasEstado[$fila['estado']] ?? $fila['estado'],
            (int)$fila['cantidad_lineas'],
            (float)$fila['total_unidades'],
            (float)$fila['total'],
            (float)$fila['sena_pagada'],
            $saldo,
            reporteTextoSeguroCsv($fila['observaciones']),
        ], ';', '"', '\\');
    }
    fclose($salida);
    exit;
}

$baseReporteUrl = rtrim(BASE_URL, '/') . '/reportes/reporte_pedidos.php';
$urlPedidoImprimir = rtrim(BASE_URL, '/') . '/reportes/pedidos/imprimir_pedido.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de pedidos | Sublimación</title>
    <style>
        :root {
            --verde: #07864b;
            --verde-oscuro: #06683b;
            --fondo: #f4f6f8;
            --texto: #263238;
            --muted: #687782;
            --borde: #e0e6eb;
            --blanco: #ffffff;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 24px; background: var(--fondo); color: var(--texto);
            font: 14px/1.45 Arial, Helvetica, sans-serif;
        }
        .contenedor { max-width: 1450px; margin: 0 auto; }
        .encabezado {
            display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;
            margin-bottom: 20px; padding-bottom: 18px; border-bottom: 3px solid var(--verde);
        }
        h1 { margin: 0 0 5px; font-size: 27px; font-weight: 700; letter-spacing: -.4px; }
        .subtitulo { color: var(--muted); margin: 0; }
        .acciones { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 7px;
            border: 1px solid transparent; border-radius: 7px; padding: 9px 13px;
            font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none;
        }
        .btn-principal { color: white; background: var(--verde); }
        .btn-principal:hover { background: var(--verde-oscuro); }
        .btn-secundario { background: white; color: #35434b; border-color: #cfd8df; }
        .btn-secundario:hover { background: #f8fafb; }
        .panel { background: white; border: 1px solid var(--borde); border-radius: 12px; padding: 18px; margin-bottom: 18px; box-shadow: 0 2px 7px rgba(25, 42, 53, .035); }
        .panel-titulo { margin: 0 0 14px; font-size: 16px; }
        .filtros { display: grid; grid-template-columns: repeat(4, minmax(150px, 1fr)); gap: 13px; align-items: end; }
        label { display: block; margin-bottom: 5px; font-size: 12px; color: #4d5b64; font-weight: 700; }
        input, select { width: 100%; padding: 10px 11px; border: 1px solid #cfd8df; border-radius: 7px; background: white; color: var(--texto); font: inherit; }
        input:focus, select:focus { outline: 2px solid rgba(7, 134, 75, .16); border-color: var(--verde); }
        .filtro-acciones { display: flex; flex-wrap: wrap; gap: 8px; grid-column: 1 / -1; }
        .mensaje { padding: 12px 14px; border-radius: 8px; margin-bottom: 15px; }
        .mensaje-error { color: #842029; background: #f8d7da; border: 1px solid #f1aeb5; }
        .mensaje-aviso { color: #664d03; background: #fff3cd; border: 1px solid #ffecb5; }
        .resumen { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
        .indicador { min-width: 0; background: white; border: 1px solid var(--borde); border-radius: 10px; padding: 15px 17px; }
        .indicador-label { color: var(--muted); font-size: 12px; margin-bottom: 6px; }
        .indicador-valor { font-size: clamp(18px, 2vw, 23px); font-weight: 700; overflow-wrap: anywhere; }
        .indicador-nota { margin-top: 5px; color: var(--muted); font-size: 11px; }
        .tabla-cabecera { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 12px; }
        .tabla-cabecera h2 { margin: 0; font-size: 17px; }
        .cantidad-resultados { font-size: 12px; color: var(--muted); }
        .tabla-scroll { overflow-x: auto; border: 1px solid var(--borde); border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 11px 12px; border-bottom: 1px solid #e8edf1; text-align: left; vertical-align: middle; }
        th { background: #f7f9fa; color: #52616a; font-size: 11px; text-transform: uppercase; letter-spacing: .35px; white-space: nowrap; }
        tbody tr:hover { background: #fbfdfc; }
        tbody tr:last-child td { border-bottom: 0; }
        .numero { font-weight: 700; white-space: nowrap; }
        .cliente { min-width: 175px; font-weight: 600; }
        .telefono { color: var(--muted); white-space: nowrap; font-size: 12px; }
        .fecha { white-space: nowrap; }
        .numero-derecha { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .estado { display: inline-block; padding: 5px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; white-space: nowrap; }
        .estado-presupuestado { background: #fff3cd; color: #805f00; }
        .estado-confirmado { background: #cff4fc; color: #055160; }
        .estado-produccion { background: #e2d9f3; color: #432874; }
        .estado-listo { background: #d1e7dd; color: #0f5132; }
        .estado-entregado { background: #e2e3e5; color: #41464b; }
        .estado-cancelado { background: #f8d7da; color: #842029; }
        .estado-default { background: #e9ecef; color: #495057; }
        .btn-ver { padding: 6px 9px; font-size: 12px; white-space: nowrap; }
        .vacio { padding: 32px 16px; text-align: center; color: var(--muted); }
        .nota-pie { margin-top: 12px; font-size: 11px; color: var(--muted); }
        .print-only { display: none; }
        @media (max-width: 1000px) {
            .filtros { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .resumen { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 620px) {
            body { padding: 12px; }
            .encabezado { flex-direction: column; }
            .acciones { justify-content: flex-start; }
            .filtros, .resumen { grid-template-columns: 1fr; }
            .filtro-acciones { grid-column: auto; }
        }
        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            body { padding: 0; background: white; font-size: 10px; }
            .contenedor { max-width: none; }
            .no-print, .acciones-columna { display: none !important; }
            .print-only { display: block; }
            .encabezado { padding-bottom: 10px; margin-bottom: 12px; }
            h1 { font-size: 20px; }
            .panel, .indicador { box-shadow: none; border-color: #ddd; }
            .resumen { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 6px; margin-bottom: 10px; }
            .indicador { padding: 8px 10px; }
            .indicador-valor { font-size: 14px; }
            .tabla-scroll { overflow: visible; border: 0; }
            th, td { padding: 6px 7px; }
            th { background: #f0f0f0 !important; print-color-adjust: exact; }
            tr { break-inside: avoid; }
            .panel { padding: 0; border: 0; margin-bottom: 10px; }
            .nota-pie { margin-top: 8px; }
        }
    </style>
</head>
<body>
<div class="contenedor">
    <header class="encabezado">
        <div>
            <h1>Reporte de pedidos</h1>
            <p class="subtitulo">Consulta y seguimiento de pedidos, clientes e importes.</p>
            <div class="print-only">Generado: <?php echo date('d/m/Y H:i'); ?></div>
        </div>
        <div class="acciones no-print">
            <button class="btn btn-secundario" type="button" onclick="window.print()">🖨 Imprimir</button>
            <button class="btn btn-principal" type="submit" form="formFiltros" name="exportar" value="csv">⇩ Exportar Excel (CSV)</button>
            <a class="btn btn-secundario" href="<?php echo reporteEscapar($baseReporteUrl); ?>">Limpiar filtros</a>
        </div>
    </header>

    <?php if (!empty($errores)): ?>
        <div class="mensaje mensaje-error" role="alert">
            <?php foreach ($errores as $error): ?>
                <div><?php echo reporteEscapar($error); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($errorConsulta): ?>
        <div class="mensaje mensaje-error" role="alert">No se pudo consultar el reporte. Revisá el log del servidor o intentá nuevamente.</div>
    <?php endif; ?>

    <section class="panel no-print">
        <h2 class="panel-titulo">Filtros de búsqueda</h2>
        <form id="formFiltros" method="get" action="<?php echo reporteEscapar($baseReporteUrl); ?>">
            <div class="filtros">
                <div>
                    <label for="fecha_desde">Fecha del pedido desde</label>
                    <input type="date" id="fecha_desde" name="fecha_desde" value="<?php echo reporteEscapar($fechaDesde); ?>">
                </div>
                <div>
                    <label for="fecha_hasta">Fecha del pedido hasta</label>
                    <input type="date" id="fecha_hasta" name="fecha_hasta" value="<?php echo reporteEscapar($fechaHasta); ?>">
                </div>
                <div>
                    <label for="estado">Estado del pedido</label>
                    <select id="estado" name="estado">
                        <option value="">Todos los estados</option>
                        <?php foreach ($estadosPermitidos as $estadoOpcion): ?>
                            <option value="<?php echo reporteEscapar($estadoOpcion); ?>" <?php echo $estadoFiltro === $estadoOpcion ? 'selected' : ''; ?>>
                                <?php echo reporteEscapar($etiquetasEstado[$estadoOpcion]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="buscar">Pedido, cliente o teléfono</label>
                    <input type="search" id="buscar" name="buscar" maxlength="100" value="<?php echo reporteEscapar($buscar); ?>" placeholder="Ej.: PED-00012 o Ana Gómez">
                </div>
                <div class="filtro-acciones">
                    <button class="btn btn-principal" type="submit">Buscar pedidos</button>
                    <button class="btn btn-secundario" type="submit" name="exportar" value="csv">Exportar resultado a Excel</button>
                </div>
            </div>
        </form>
    </section>

    <section class="resumen">
        <div class="indicador">
            <div class="indicador-label">Pedidos encontrados</div>
            <div class="indicador-valor"><?php echo number_format(count($filas), 0, ',', '.'); ?></div>
            <div class="indicador-nota">Incluye cancelados, si coinciden con los filtros.</div>
        </div>
        <div class="indicador">
            <div class="indicador-label">Importe de pedidos no cancelados</div>
            <div class="indicador-valor"><?php echo reporteGs($totalActivos); ?></div>
            <div class="indicador-nota">Suma del importe registrado en el resultado.</div>
        </div>
        <div class="indicador">
            <div class="indicador-label">Señas en pedidos no cancelados</div>
            <div class="indicador-valor"><?php echo reporteGs($totalSenias); ?></div>
            <div class="indicador-nota">Según el campo sena_pagada del pedido.</div>
        </div>
        <div class="indicador">
            <div class="indicador-label">Saldo estimado pendiente</div>
            <div class="indicador-valor"><?php echo reporteGs($totalSaldo); ?></div>
            <div class="indicador-nota">Total menos seña; no reemplaza el informe de pagos.</div>
        </div>
    </section>

    <section class="panel">
        <div class="tabla-cabecera">
            <h2>Listado de pedidos</h2>
            <span class="cantidad-resultados"><?php echo number_format(count($filas), 0, ',', '.'); ?> resultado(s)</span>
        </div>

        <?php if (empty($filas)): ?>
            <div class="vacio">
                <?php if ($errorConsulta || !empty($errores)): ?>
                    No hay resultados para mostrar con el estado actual de la consulta.
                <?php else: ?>
                    No se encontraron pedidos con los filtros seleccionados.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="tabla-scroll">
                <table>
                    <thead>
                    <tr>
                        <th>N.º pedido</th>
                        <th>Fecha</th>
                        <th>Cliente / teléfono</th>
                        <th>Entrega estimada</th>
                        <th>Estado</th>
                        <th class="numero-derecha">Líneas</th>
                        <th class="numero-derecha">Unidades</th>
                        <th class="numero-derecha">Total</th>
                        <th class="numero-derecha">Seña</th>
                        <th class="numero-derecha">Saldo est.</th>
                        <th class="acciones-columna">Acción</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($filas as $fila): ?>
                        <?php
                            $estadoActual = (string)$fila['estado'];
                            $saldoFila = max(0, (float)$fila['total'] - (float)$fila['sena_pagada']);
                            $nombreCliente = trim((string)$fila['cliente_nombre'] . ' ' . (string)$fila['cliente_apellido']);
                            $entrega = $fila['fecha_entrega_estimada'];
                            $entregaAtrasada = $entrega && $entrega < date('Y-m-d') && !in_array($estadoActual, ['entregado', 'cancelado'], true);
                        ?>
                        <tr>
                            <td class="numero"><?php echo reporteEscapar($fila['numero_pedido']); ?></td>
                            <td class="fecha"><?php echo reporteFecha($fila['fecha_pedido']); ?></td>
                            <td>
                                <div class="cliente"><?php echo reporteEscapar($nombreCliente); ?></div>
                                <div class="telefono"><?php echo reporteEscapar($fila['cliente_telefono'] ?: 'Sin teléfono'); ?></div>
                            </td>
                            <td class="fecha">
                                <?php echo reporteFecha($entrega); ?>
                                <?php if ($entregaAtrasada): ?><div style="color:#b02a37;font-size:11px;font-weight:700;">Atrasado</div><?php endif; ?>
                            </td>
                            <td><span class="estado <?php echo reporteEscapar(reporteClaseEstado($estadoActual)); ?>"><?php echo reporteEscapar($etiquetasEstado[$estadoActual] ?? $estadoActual); ?></span></td>
                            <td class="numero-derecha"><?php echo (int)$fila['cantidad_lineas']; ?></td>
                            <td class="numero-derecha"><?php echo reporteEscapar(reporteCantidad($fila['total_unidades'])); ?></td>
                            <td class="numero-derecha"><?php echo reporteGs($fila['total']); ?></td>
                            <td class="numero-derecha"><?php echo reporteGs($fila['sena_pagada']); ?></td>
                            <td class="numero-derecha"><?php echo reporteGs($saldoFila); ?></td>
                            <td class="acciones-columna">
                                <a class="btn btn-secundario btn-ver" target="_blank" rel="noopener noreferrer" href="<?php echo reporteEscapar($urlPedidoImprimir . '?id_pedido=' . (int)$fila['id_pedido']); ?>">Ver / imprimir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <p class="nota-pie">
            El saldo se calcula como total menos seña registrada. Para conocer los cobros parciales detallados, se deberá consultar la tabla de pagos.
            Los reportes son de solo lectura y no modifican el stock.
        </p>
    </section>

    <footer class="nota-pie">Reporte generado el <?php echo date('d/m/Y H:i'); ?> · Sistema de gestión de sublimación</footer>
</div>
</body>
</html>
