<?php
/**
 * Reporte de inventario actual de insumos.
 * Ruta: /reportes/reporte_inventario.php
 * Consulta de solo lectura: no actualiza stock ni genera movimientos.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/referenciales/insumos/insumos.php');

function repInvEsc($valor): string
{
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function repInvCantidad($valor): string
{
    return rtrim(rtrim(number_format((float)$valor, 3, ',', '.'), '0'), ',');
}

function repInvBool($valor): bool
{
    return in_array($valor, [true, 1, '1', 't', 'true', 'TRUE', 'yes', 'on'], true);
}

function repInvCsvSeguro($valor): string
{
    $texto = (string)($valor ?? '');
    if ($texto !== '' && preg_match('/^[\s\x00-\x1F]*[=+@-]/u', $texto)) {
        return "'" . $texto;
    }
    return $texto;
}

$buscar = trim((string)($_GET['buscar'] ?? ''));
$estadoFiltro = (string)($_GET['estado'] ?? 'activos');
$stockFiltro = (string)($_GET['stock'] ?? 'todos');
$exportar = (string)($_GET['exportar'] ?? '') === 'csv';

if (!in_array($estadoFiltro, ['activos', 'inactivos', 'todos'], true)) {
    $estadoFiltro = 'activos';
}
if (!in_array($stockFiltro, ['todos', 'agotado', 'bajo', 'normal'], true)) {
    $stockFiltro = 'todos';
}
if (function_exists('mb_strlen') && mb_strlen($buscar, 'UTF-8') > 100) {
    $buscar = mb_substr($buscar, 0, 100, 'UTF-8');
} elseif (strlen($buscar) > 100) {
    $buscar = substr($buscar, 0, 100);
}

$errores = [];
$filas = [];
$errorConsulta = false;

$condiciones = [];
$parametros = [];

if ($estadoFiltro === 'activos') {
    $condiciones[] = 'i.activo IS TRUE';
} elseif ($estadoFiltro === 'inactivos') {
    $condiciones[] = 'i.activo IS FALSE';
}

if ($stockFiltro === 'agotado') {
    $condiciones[] = 'i.stock_actual <= 0';
} elseif ($stockFiltro === 'bajo') {
    $condiciones[] = 'i.stock_actual > 0 AND i.stock_actual <= i.stock_minimo';
} elseif ($stockFiltro === 'normal') {
    $condiciones[] = 'i.stock_actual > i.stock_minimo';
}

if ($buscar !== '') {
    $condiciones[] = "(
        i.nombre ILIKE :buscar_nombre
        OR i.codigo ILIKE :buscar_codigo
        OR COALESCE(i.ubicacion, '') ILIKE :buscar_ubicacion
    )";
    $patron = '%' . $buscar . '%';
    $parametros['buscar_nombre'] = $patron;
    $parametros['buscar_codigo'] = $patron;
    $parametros['buscar_ubicacion'] = $patron;
}

$where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

try {
    $sql = "
        SELECT
            i.id_insumo,
            i.codigo,
            i.nombre,
            i.stock_actual,
            i.stock_minimo,
            i.ubicacion,
            i.activo,
            COALESCE(u.nombre, 'Sin unidad') AS unidad,
            COALESCE(c.nombre, 'Sin color') AS color,
            CASE
                WHEN i.stock_actual <= 0 THEN 'agotado'
                WHEN i.stock_actual <= i.stock_minimo THEN 'bajo'
                ELSE 'normal'
            END AS stock_estado
        FROM sublimacion.insumos i
        LEFT JOIN sublimacion.unidades_medida u ON u.id_unidad = i.id_unidad
        LEFT JOIN sublimacion.colores c ON c.id_color = i.id_color
        $where
        ORDER BY
            CASE
                WHEN i.stock_actual <= 0 THEN 0
                WHEN i.stock_actual <= i.stock_minimo THEN 1
                ELSE 2
            END,
            i.activo DESC,
            i.nombre ASC
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->execute($parametros);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Reporte inventario: ' . $e->getMessage());
    $errorConsulta = true;
}

$totalInsumos = count($filas);
$totalActivos = count(array_filter($filas, static fn($f) => repInvBool($f['activo'])));
$totalInactivos = $totalInsumos - $totalActivos;
$totalAgotados = count(array_filter($filas, static fn($f) => $f['stock_estado'] === 'agotado'));
$totalBajoMinimo = count(array_filter($filas, static fn($f) => $f['stock_estado'] === 'bajo'));

if ($exportar && !$errorConsulta && !$errores) {
    $nombreArchivo = 'reporte_inventario_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    echo "\xEF\xBB\xBF";
    $salida = fopen('php://output', 'w');
    fputcsv($salida, ['Código', 'Insumo', 'Unidad', 'Color', 'Stock actual', 'Stock mínimo', 'Estado de stock', 'Ubicación', 'Activo'], ';', '"', '\\');
    foreach ($filas as $f) {
        $estadoStock = [
            'agotado' => 'Agotado',
            'bajo' => 'Bajo mínimo',
            'normal' => 'Normal',
        ][$f['stock_estado']] ?? 'Sin clasificar';
        fputcsv($salida, [
            repInvCsvSeguro($f['codigo']),
            repInvCsvSeguro($f['nombre']),
            repInvCsvSeguro($f['unidad']),
            repInvCsvSeguro($f['color']),
            (string)$f['stock_actual'],
            (string)$f['stock_minimo'],
            $estadoStock,
            repInvCsvSeguro($f['ubicacion']),
            repInvBool($f['activo']) ? 'Sí' : 'No',
        ], ';', '"', '\\');
    }
    fclose($salida);
    exit;
}

$baseReporteUrl = rtrim((string)BASE_URL, '/') . '/reportes/reporte_inventario.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de inventario | Sublimación</title>
    <style>
        .print-only{display:none}
        :root{--fondo:#f3f5f9;--panel:#fff;--texto:#263247;--muted:#6b7280;--borde:#e1e6ef;--primario:#2856a6;--rojo:#b42318;--ambar:#a15c00;--verde:#18794e}
        *{box-sizing:border-box}body{margin:0;padding:24px;background:var(--fondo);color:var(--texto);font:14px/1.45 Arial,Helvetica,sans-serif}
        .contenedor{max-width:1500px;margin:0 auto}.encabezado{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;margin-bottom:20px}
        h1{margin:0 0 6px;font-size:28px;letter-spacing:-.4px}.subtitulo{margin:0;color:var(--muted)}.acciones{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 14px;border:1px solid transparent;border-radius:8px;text-decoration:none;font-weight:700;cursor:pointer;font-size:13px}
        .btn-principal{background:var(--primario);color:white}.btn-secundario{background:#fff;color:var(--texto);border-color:var(--borde)}
        .filtros,.panel,.indicador{background:var(--panel);border:1px solid var(--borde);border-radius:12px;box-shadow:0 2px 8px rgba(25,40,70,.04)}
        .panel{padding:18px;margin-bottom:18px}.panel-titulo{margin:0 0 14px;font-size:16px}.filtros-grid{display:grid;grid-template-columns:1.5fr 1fr 1fr auto;gap:12px;align-items:end}
        label{display:block;margin-bottom:6px;font-weight:700;font-size:12px;color:#4b5563}input,select{width:100%;min-height:40px;border:1px solid #cfd6e2;border-radius:7px;padding:8px 10px;background:#fff;color:var(--texto);font:inherit}
        .filtro-acciones{display:flex;gap:8px;flex-wrap:wrap}.resumen{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin:18px 0}
        .indicador{padding:16px}.indicador-label{color:var(--muted);font-size:12px;font-weight:700}.indicador-valor{font-size:25px;font-weight:800;margin:7px 0 2px}.indicador-nota{color:var(--muted);font-size:11px}
        .tabla-panel{padding:0;overflow:hidden}.tabla-cabecera{padding:16px 18px;border-bottom:1px solid var(--borde);display:flex;justify-content:space-between;gap:12px;align-items:center}.tabla-cabecera h2{margin:0;font-size:16px}
        .tabla-scroll{overflow:auto}table{width:100%;border-collapse:collapse;min-width:980px}th,td{text-align:left;padding:11px 12px;border-bottom:1px solid var(--borde);vertical-align:top}th{background:#f7f9fc;color:#4b5563;font-size:11px;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap}tbody tr:hover{background:#fbfcff}tbody tr:last-child td{border-bottom:0}
        .codigo{font-weight:700;white-space:nowrap}.numero{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}.badge{display:inline-block;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:800;white-space:nowrap}.badge-agotado{background:#fee4e2;color:var(--rojo)}.badge-bajo{background:#fff0d2;color:var(--ambar)}.badge-normal{background:#dff5e9;color:var(--verde)}.badge-activo{background:#dff5e9;color:var(--verde)}.badge-inactivo{background:#eceff4;color:#586174}
        .vacio,.mensaje{padding:18px;border-radius:9px;margin-bottom:16px}.vacio{color:var(--muted);text-align:center}.mensaje-error{background:#fff0ef;border:1px solid #f3b7b2;color:#8a1c13}.pie{margin-top:14px;color:var(--muted);font-size:11px;display:flex;justify-content:space-between;gap:12px}
        @media(max-width:1050px){.filtros-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.resumen{grid-template-columns:repeat(3,minmax(0,1fr))}.encabezado{flex-direction:column}.acciones{justify-content:flex-start}}
        @media(max-width:620px){body{padding:12px}.filtros-grid,.resumen{grid-template-columns:1fr}.tabla-cabecera{align-items:flex-start;flex-direction:column}}
        @media print{.print-only{display:block}@page{size:A4 landscape;margin:10mm}body{padding:0;background:#fff;font-size:10px}.contenedor{max-width:none}.no-print{display:none!important}.encabezado{margin-bottom:10px}h1{font-size:20px}.panel,.indicador{box-shadow:none}.resumen{grid-template-columns:repeat(5,minmax(0,1fr));gap:6px;margin:10px 0}.indicador{padding:8px}.indicador-valor{font-size:16px}.tabla-scroll{overflow:visible}table{min-width:0;font-size:9px}th,td{padding:6px}.badge{border:1px solid #aaa;background:#fff!important;color:#222!important}.pie{margin-top:8px}}
    </style>
</head>
<body>
<div class="contenedor">
    <header class="encabezado">
        <div>
            <h1>Reporte de inventario</h1>
            <p class="subtitulo">Existencias actuales de insumos, unidades de medida y alertas de stock.</p>
            <p class="subtitulo print-only">Generado: <?php echo date('d/m/Y H:i'); ?></p>
        </div>
        <div class="acciones no-print">
            <button class="btn btn-secundario" type="button" onclick="window.print()">🖨 Imprimir</button>
            <button class="btn btn-principal" type="submit" form="formFiltros" name="exportar" value="csv">⇩ Exportar Excel (CSV)</button>
            <a class="btn btn-secundario" href="<?php echo repInvEsc($baseReporteUrl); ?>">Limpiar filtros</a>
        </div>
    </header>

    <?php if ($errorConsulta): ?>
        <div class="mensaje mensaje-error" role="alert">No se pudo consultar el inventario. Revisá el log del servidor o intentá nuevamente.</div>
    <?php endif; ?>

    <section class="panel no-print">
        <h2 class="panel-titulo">Filtros</h2>
        <form id="formFiltros" method="get" action="<?php echo repInvEsc($baseReporteUrl); ?>">
            <div class="filtros-grid">
                <div><label for="buscar">Buscar por código, insumo o ubicación</label><input id="buscar" name="buscar" type="search" maxlength="100" value="<?php echo repInvEsc($buscar); ?>" placeholder="Ej.: INS-0001, taza blanca"></div>
                <div><label for="estado">Estado del insumo</label><select id="estado" name="estado"><option value="activos" <?php echo $estadoFiltro === 'activos' ? 'selected' : ''; ?>>Solo activos</option><option value="inactivos" <?php echo $estadoFiltro === 'inactivos' ? 'selected' : ''; ?>>Solo inactivos</option><option value="todos" <?php echo $estadoFiltro === 'todos' ? 'selected' : ''; ?>>Todos</option></select></div>
                <div><label for="stock">Situación del stock</label><select id="stock" name="stock"><option value="todos" <?php echo $stockFiltro === 'todos' ? 'selected' : ''; ?>>Todas las situaciones</option><option value="agotado" <?php echo $stockFiltro === 'agotado' ? 'selected' : ''; ?>>Agotados (stock 0)</option><option value="bajo" <?php echo $stockFiltro === 'bajo' ? 'selected' : ''; ?>>Bajo mínimo</option><option value="normal" <?php echo $stockFiltro === 'normal' ? 'selected' : ''; ?>>Stock normal</option></select></div>
                <div class="filtro-acciones"><button class="btn btn-principal" type="submit">Aplicar</button></div>
            </div>
        </form>
    </section>

    <section class="resumen">
        <div class="indicador"><div class="indicador-label">Insumos en resultado</div><div class="indicador-valor"><?php echo number_format($totalInsumos, 0, ',', '.'); ?></div><div class="indicador-nota">Según filtros aplicados</div></div>
        <div class="indicador"><div class="indicador-label">Activos</div><div class="indicador-valor"><?php echo number_format($totalActivos, 0, ',', '.'); ?></div><div class="indicador-nota">Disponibles para operar</div></div>
        <div class="indicador"><div class="indicador-label">Agotados</div><div class="indicador-valor"><?php echo number_format($totalAgotados, 0, ',', '.'); ?></div><div class="indicador-nota">Stock igual o menor a cero</div></div>
        <div class="indicador"><div class="indicador-label">Bajo mínimo</div><div class="indicador-valor"><?php echo number_format($totalBajoMinimo, 0, ',', '.'); ?></div><div class="indicador-nota">Stock positivo, igual o inferior al mínimo</div></div>
        <div class="indicador"><div class="indicador-label">Inactivos</div><div class="indicador-valor"><?php echo number_format($totalInactivos, 0, ',', '.'); ?></div><div class="indicador-nota">Se conservan para historial</div></div>
    </section>

    <section class="panel tabla-panel">
        <div class="tabla-cabecera"><h2>Existencias por insumo</h2><span class="subtitulo"><?php echo number_format($totalInsumos, 0, ',', '.'); ?> registro(s)</span></div>
        <?php if (!$errorConsulta && count($filas) === 0): ?>
            <div class="vacio">No se encontraron insumos con los filtros seleccionados.</div>
        <?php elseif (!$errorConsulta): ?>
            <div class="tabla-scroll"><table>
                <thead><tr><th>Código</th><th>Insumo</th><th>Unidad</th><th>Color</th><th class="numero">Stock actual</th><th class="numero">Stock mínimo</th><th>Situación</th><th>Ubicación</th><th>Activo</th></tr></thead>
                <tbody>
                <?php foreach ($filas as $f): ?>
                    <tr>
                        <td class="codigo"><?php echo repInvEsc($f['codigo']); ?></td>
                        <td><strong><?php echo repInvEsc($f['nombre']); ?></strong></td>
                        <td><?php echo repInvEsc($f['unidad']); ?></td>
                        <td><?php echo repInvEsc($f['color']); ?></td>
                        <td class="numero"><?php echo repInvCantidad($f['stock_actual']); ?></td>
                        <td class="numero"><?php echo repInvCantidad($f['stock_minimo']); ?></td>
                        <td><?php if ($f['stock_estado'] === 'agotado'): ?><span class="badge badge-agotado">Agotado</span><?php elseif ($f['stock_estado'] === 'bajo'): ?><span class="badge badge-bajo">Bajo mínimo</span><?php else: ?><span class="badge badge-normal">Normal</span><?php endif; ?></td>
                        <td><?php echo repInvEsc($f['ubicacion'] ?: '—'); ?></td>
                        <td><?php if (repInvBool($f['activo'])): ?><span class="badge badge-activo">Sí</span><?php else: ?><span class="badge badge-inactivo">No</span><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </section>
    <footer class="pie"><span>Reporte de consulta: no modifica existencias ni registra movimientos.</span><span>Generado el <?php echo date('d/m/Y H:i'); ?></span></footer>
</div>
</body>
</html>
