<?php
/**
 * Reporte general de compras y detalle de compras.
 * Ruta: /reportes/reporte_compras.php
 * Consulta de solo lectura: no modifica compras, movimientos ni existencias.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/movimientos/compras/compras.php');

function repComEsc($valor): string
{
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function repComGs($valor): string
{
    return 'Gs. ' . number_format((float)$valor, 0, ',', '.');
}

function repComFecha(?string $fecha): string
{
    if (!$fecha) {
        return '—';
    }
    $ts = strtotime($fecha);
    return $ts ? date('d/m/Y', $ts) : '—';
}

function repComFechaValida(string $fecha): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        return false;
    }
    $objeto = DateTime::createFromFormat('!Y-m-d', $fecha);
    return $objeto instanceof DateTime && $objeto->format('Y-m-d') === $fecha;
}

function repComCsvSeguro($valor): string
{
    $texto = (string)($valor ?? '');
    if ($texto !== '' && preg_match('/^[\s\x00-\x1F]*[=+@-]/u', $texto)) {
        return "'" . $texto;
    }
    return $texto;
}

$fechaDesde = trim((string)($_GET['fecha_desde'] ?? ''));
$fechaHasta = trim((string)($_GET['fecha_hasta'] ?? ''));
$estadoFiltro = (string)($_GET['estado'] ?? '');
$proveedorFiltro = trim((string)($_GET['id_proveedor'] ?? ''));
$buscar = trim((string)($_GET['buscar'] ?? ''));
$exportar = (string)($_GET['exportar'] ?? '') === 'csv';

$estadosPermitidos = ['', 'REGISTRADA', 'ANULADA'];
if (!in_array($estadoFiltro, $estadosPermitidos, true)) {
    $estadoFiltro = '';
}
if (!preg_match('/^\d+$/', $proveedorFiltro) || (int)$proveedorFiltro <= 0) {
    $proveedorFiltro = '';
}
if (function_exists('mb_strlen') && mb_strlen($buscar, 'UTF-8') > 100) {
    $buscar = mb_substr($buscar, 0, 100, 'UTF-8');
} elseif (strlen($buscar) > 100) {
    $buscar = substr($buscar, 0, 100);
}

$errores = [];
$filas = [];
$proveedores = [];
$errorConsulta = false;

if ($fechaDesde !== '' && !repComFechaValida($fechaDesde)) {
    $errores[] = 'La fecha inicial no es válida.';
}
if ($fechaHasta !== '' && !repComFechaValida($fechaHasta)) {
    $errores[] = 'La fecha final no es válida.';
}
if ($fechaDesde !== '' && $fechaHasta !== '' && repComFechaValida($fechaDesde) && repComFechaValida($fechaHasta) && $fechaDesde > $fechaHasta) {
    $errores[] = 'La fecha inicial no puede ser posterior a la fecha final.';
}

$condiciones = [];
$parametros = [];
if ($fechaDesde !== '' && repComFechaValida($fechaDesde)) {
    $condiciones[] = 'c.fecha_compra >= :fecha_desde';
    $parametros['fecha_desde'] = $fechaDesde;
}
if ($fechaHasta !== '' && repComFechaValida($fechaHasta)) {
    $condiciones[] = 'c.fecha_compra <= :fecha_hasta';
    $parametros['fecha_hasta'] = $fechaHasta;
}
if ($estadoFiltro !== '') {
    $condiciones[] = 'c.estado = :estado';
    $parametros['estado'] = $estadoFiltro;
}
if ($proveedorFiltro !== '') {
    $condiciones[] = 'c.id_proveedor = :id_proveedor';
    $parametros['id_proveedor'] = (int)$proveedorFiltro;
}
if ($buscar !== '') {
    $condiciones[] = "(
        c.numero_compra ILIKE :buscar_numero
        OR pr.razon_social ILIKE :buscar_proveedor
        OR COALESCE(c.observaciones, '') ILIKE :buscar_observaciones
        OR EXISTS (
            SELECT 1
            FROM sublimacion.detalle_compra dcx
            INNER JOIN sublimacion.insumos ix ON ix.id_insumo = dcx.id_insumo
            WHERE dcx.id_compra = c.id_compra
              AND (ix.nombre ILIKE :buscar_insumo_nombre OR ix.codigo ILIKE :buscar_insumo_codigo)
        )
    )";
    $patron = '%' . $buscar . '%';
    $parametros['buscar_numero'] = $patron;
    $parametros['buscar_proveedor'] = $patron;
    $parametros['buscar_observaciones'] = $patron;
    $parametros['buscar_insumo_nombre'] = $patron;
    $parametros['buscar_insumo_codigo'] = $patron;
}

$where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

try {
    $stmtProveedores = $conexion->query("SELECT id_proveedor, razon_social FROM sublimacion.proveedores ORDER BY razon_social ASC");
    $proveedores = $stmtProveedores->fetchAll(PDO::FETCH_ASSOC);

    if (!$errores) {
        $sql = "
            SELECT
                c.id_compra,
                c.numero_compra,
                c.fecha_compra,
                c.total,
                c.estado,
                c.observaciones,
                pr.razon_social AS proveedor,
                COALESCE(NULLIF(BTRIM(CONCAT_WS(' ', u.nombre, u.apellido)), ''), '—') AS registrado_por,
                COALESCE(det.cantidad_lineas, 0) AS cantidad_lineas
            FROM sublimacion.compras c
            INNER JOIN sublimacion.proveedores pr ON pr.id_proveedor = c.id_proveedor
            LEFT JOIN sublimacion.usuarios u ON u.id_usuario = c.id_usuario
            LEFT JOIN (
                SELECT id_compra, COUNT(*) AS cantidad_lineas
                FROM sublimacion.detalle_compra
                GROUP BY id_compra
            ) det ON det.id_compra = c.id_compra
            $where
            ORDER BY c.fecha_compra DESC, c.id_compra DESC
        ";
        $stmt = $conexion->prepare($sql);
        $stmt->execute($parametros);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log('Reporte compras: ' . $e->getMessage());
    $errorConsulta = true;
}

$totalRegistradas = 0;
$totalAnuladas = 0;
$montoRegistradas = 0.0;
$montoAnuladas = 0.0;
foreach ($filas as $f) {
    if ($f['estado'] === 'REGISTRADA') {
        $totalRegistradas++;
        $montoRegistradas += (float)$f['total'];
    } elseif ($f['estado'] === 'ANULADA') {
        $totalAnuladas++;
        $montoAnuladas += (float)$f['total'];
    }
}

if ($exportar && !$errorConsulta && !$errores) {
    $nombreArchivo = 'reporte_compras_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    echo "\xEF\xBB\xBF";
    $salida = fopen('php://output', 'w');
    fputcsv($salida, ['Número de compra', 'Fecha', 'Proveedor', 'Estado', 'Cantidad de líneas', 'Total (Gs.)', 'Registrado por', 'Observaciones'], ';', '"', '\\');
    foreach ($filas as $f) {
        fputcsv($salida, [
            repComCsvSeguro($f['numero_compra']),
            repComFecha($f['fecha_compra']),
            repComCsvSeguro($f['proveedor']),
            $f['estado'] === 'ANULADA' ? 'Anulada' : 'Registrada',
            (string)$f['cantidad_lineas'],
            number_format((float)$f['total'], 0, ',', '.'),
            repComCsvSeguro($f['registrado_por']),
            repComCsvSeguro($f['observaciones']),
        ], ';', '"', '\\');
    }
    fclose($salida);
    exit;
}

$baseReporteUrl = rtrim((string)BASE_URL, '/') . '/reportes/reporte_compras.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de compras | Sublimación</title>
    <style>
        .print-only{display:none}
        :root{--fondo:#f3f5f9;--panel:#fff;--texto:#263247;--muted:#6b7280;--borde:#e1e6ef;--primario:#2856a6;--rojo:#b42318;--verde:#18794e;--ambar:#a15c00}
        *{box-sizing:border-box}body{margin:0;padding:24px;background:var(--fondo);color:var(--texto);font:14px/1.45 Arial,Helvetica,sans-serif}.contenedor{max-width:1500px;margin:0 auto}
        .encabezado{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;margin-bottom:20px}h1{margin:0 0 6px;font-size:28px;letter-spacing:-.4px}.subtitulo{margin:0;color:var(--muted)}.acciones{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 14px;border:1px solid transparent;border-radius:8px;text-decoration:none;font-weight:700;cursor:pointer;font-size:13px}.btn-principal{background:var(--primario);color:white}.btn-secundario{background:#fff;color:var(--texto);border-color:var(--borde)}
        .panel,.indicador{background:var(--panel);border:1px solid var(--borde);border-radius:12px;box-shadow:0 2px 8px rgba(25,40,70,.04)}.panel{padding:18px;margin-bottom:18px}.panel-titulo{margin:0 0 14px;font-size:16px}
        .filtros-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;align-items:end}label{display:block;margin-bottom:6px;font-weight:700;font-size:12px;color:#4b5563}input,select{width:100%;min-height:40px;border:1px solid #cfd6e2;border-radius:7px;padding:8px 10px;background:#fff;color:var(--texto);font:inherit}.filtro-acciones{display:flex;gap:8px;flex-wrap:wrap}
        .resumen{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin:18px 0}.indicador{padding:16px}.indicador-label{color:var(--muted);font-size:12px;font-weight:700}.indicador-valor{font-size:23px;font-weight:800;margin:7px 0 2px}.indicador-nota{color:var(--muted);font-size:11px}
        .tabla-panel{padding:0;overflow:hidden}.tabla-cabecera{padding:16px 18px;border-bottom:1px solid var(--borde);display:flex;justify-content:space-between;gap:12px;align-items:center}.tabla-cabecera h2{margin:0;font-size:16px}.tabla-scroll{overflow:auto}table{width:100%;border-collapse:collapse;min-width:1100px}th,td{text-align:left;padding:11px 12px;border-bottom:1px solid var(--borde);vertical-align:top}th{background:#f7f9fc;color:#4b5563;font-size:11px;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap}tbody tr:hover{background:#fbfcff}tbody tr:last-child td{border-bottom:0}.numero{font-weight:800;white-space:nowrap}.derecha{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}.badge{display:inline-block;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:800;white-space:nowrap}.badge-registrada{background:#dff5e9;color:var(--verde)}.badge-anulada{background:#fee4e2;color:var(--rojo)}.observaciones{max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#556070}
        .vacio,.mensaje{padding:18px;border-radius:9px;margin-bottom:16px}.vacio{color:var(--muted);text-align:center}.mensaje-error{background:#fff0ef;border:1px solid #f3b7b2;color:#8a1c13}.pie{margin-top:14px;color:var(--muted);font-size:11px;display:flex;justify-content:space-between;gap:12px}
        @media(max-width:1150px){.filtros-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.resumen{grid-template-columns:repeat(3,minmax(0,1fr))}.encabezado{flex-direction:column}.acciones{justify-content:flex-start}}
        @media(max-width:620px){body{padding:12px}.filtros-grid,.resumen{grid-template-columns:1fr}.tabla-cabecera{align-items:flex-start;flex-direction:column}}
        @media print{.print-only{display:block}@page{size:A4 landscape;margin:10mm}body{padding:0;background:#fff;font-size:10px}.contenedor{max-width:none}.no-print{display:none!important}.encabezado{margin-bottom:10px}h1{font-size:20px}.panel,.indicador{box-shadow:none}.resumen{grid-template-columns:repeat(5,minmax(0,1fr));gap:6px;margin:10px 0}.indicador{padding:8px}.indicador-valor{font-size:15px}.tabla-scroll{overflow:visible}table{min-width:0;font-size:9px}th,td{padding:6px}.badge{border:1px solid #aaa;background:#fff!important;color:#222!important}.observaciones{max-width:none;white-space:normal;overflow:visible}.pie{margin-top:8px}}
    </style>
</head>
<body>
<div class="contenedor">
    <header class="encabezado">
        <div><h1>Reporte de compras</h1><p class="subtitulo">Compras registradas, proveedores, importes y compras anuladas.</p><p class="subtitulo print-only">Generado: <?php echo date('d/m/Y H:i'); ?></p></div>
        <div class="acciones no-print"><button class="btn btn-secundario" type="button" onclick="window.print()">🖨 Imprimir</button><button class="btn btn-principal" type="submit" form="formFiltros" name="exportar" value="csv">⇩ Exportar Excel (CSV)</button><a class="btn btn-secundario" href="<?php echo repComEsc($baseReporteUrl); ?>">Limpiar filtros</a></div>
    </header>

    <?php if ($errores): ?><div class="mensaje mensaje-error" role="alert"><?php foreach ($errores as $error): ?><div><?php echo repComEsc($error); ?></div><?php endforeach; ?></div><?php endif; ?>
    <?php if ($errorConsulta): ?><div class="mensaje mensaje-error" role="alert">No se pudo consultar el reporte de compras. Revisá el log del servidor o intentá nuevamente.</div><?php endif; ?>

    <section class="panel no-print">
        <h2 class="panel-titulo">Filtros de búsqueda</h2>
        <form id="formFiltros" method="get" action="<?php echo repComEsc($baseReporteUrl); ?>">
            <div class="filtros-grid">
                <div><label for="fecha_desde">Fecha desde</label><input type="date" id="fecha_desde" name="fecha_desde" value="<?php echo repComEsc($fechaDesde); ?>"></div>
                <div><label for="fecha_hasta">Fecha hasta</label><input type="date" id="fecha_hasta" name="fecha_hasta" value="<?php echo repComEsc($fechaHasta); ?>"></div>
                <div><label for="estado">Estado de compra</label><select id="estado" name="estado"><option value="">Todos los estados</option><option value="REGISTRADA" <?php echo $estadoFiltro === 'REGISTRADA' ? 'selected' : ''; ?>>Registrada</option><option value="ANULADA" <?php echo $estadoFiltro === 'ANULADA' ? 'selected' : ''; ?>>Anulada</option></select></div>
                <div><label for="id_proveedor">Proveedor</label><select id="id_proveedor" name="id_proveedor"><option value="">Todos los proveedores</option><?php foreach ($proveedores as $proveedor): ?><option value="<?php echo (int)$proveedor['id_proveedor']; ?>" <?php echo $proveedorFiltro !== '' && (int)$proveedorFiltro === (int)$proveedor['id_proveedor'] ? 'selected' : ''; ?>><?php echo repComEsc($proveedor['razon_social']); ?></option><?php endforeach; ?></select></div>
                <div><label for="buscar">N.º compra, proveedor o insumo</label><input type="search" id="buscar" name="buscar" maxlength="100" value="<?php echo repComEsc($buscar); ?>" placeholder="Ej.: CMP-000001, taza"></div>
            </div>
            <div class="filtro-acciones" style="margin-top:12px"><button class="btn btn-principal" type="submit">Buscar compras</button></div>
        </form>
    </section>

    <section class="resumen">
        <div class="indicador"><div class="indicador-label">Compras encontradas</div><div class="indicador-valor"><?php echo number_format(count($filas), 0, ',', '.'); ?></div><div class="indicador-nota">Según los filtros aplicados</div></div>
        <div class="indicador"><div class="indicador-label">Registradas</div><div class="indicador-valor"><?php echo number_format($totalRegistradas, 0, ',', '.'); ?></div><div class="indicador-nota">Importe: <?php echo repComEsc(repComGs($montoRegistradas)); ?></div></div>
        <div class="indicador"><div class="indicador-label">Anuladas</div><div class="indicador-valor"><?php echo number_format($totalAnuladas, 0, ',', '.'); ?></div><div class="indicador-nota">Importe histórico: <?php echo repComEsc(repComGs($montoAnuladas)); ?></div></div>
        <div class="indicador"><div class="indicador-label">Total válido de compras</div><div class="indicador-valor"><?php echo repComEsc(repComGs($montoRegistradas)); ?></div><div class="indicador-nota">Excluye compras anuladas</div></div>
        <div class="indicador"><div class="indicador-label">Total histórico</div><div class="indicador-valor"><?php echo repComEsc(repComGs($montoRegistradas + $montoAnuladas)); ?></div><div class="indicador-nota">Incluye anuladas para referencia</div></div>
    </section>

    <section class="panel tabla-panel">
        <div class="tabla-cabecera"><h2>Listado de compras</h2><span class="subtitulo"><?php echo number_format(count($filas), 0, ',', '.'); ?> registro(s)</span></div>
        <?php if (!$errorConsulta && !$errores && count($filas) === 0): ?><div class="vacio">No se encontraron compras con los filtros seleccionados.</div>
        <?php elseif (!$errorConsulta && !$errores): ?>
            <div class="tabla-scroll"><table>
                <thead><tr><th>N.º de compra</th><th>Fecha</th><th>Proveedor</th><th class="derecha">Líneas</th><th class="derecha">Total</th><th>Estado</th><th>Registrado por</th><th>Observaciones</th></tr></thead>
                <tbody><?php foreach ($filas as $f): ?><tr>
                    <td class="numero"><?php echo repComEsc($f['numero_compra']); ?></td>
                    <td><?php echo repComFecha($f['fecha_compra']); ?></td>
                    <td><?php echo repComEsc($f['proveedor']); ?></td>
                    <td class="derecha"><?php echo number_format((int)$f['cantidad_lineas'], 0, ',', '.'); ?></td>
                    <td class="derecha"><strong><?php echo repComEsc(repComGs($f['total'])); ?></strong></td>
                    <td><?php if ($f['estado'] === 'ANULADA'): ?><span class="badge badge-anulada">Anulada</span><?php else: ?><span class="badge badge-registrada">Registrada</span><?php endif; ?></td>
                    <td><?php echo repComEsc($f['registrado_por']); ?></td>
                    <td class="observaciones" title="<?php echo repComEsc($f['observaciones']); ?>"><?php echo repComEsc($f['observaciones'] ?: '—'); ?></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>
    <footer class="pie"><span>Las compras anuladas se mantienen visibles como historial y no se suman al total válido.</span><span>Generado el <?php echo date('d/m/Y H:i'); ?></span></footer>
</div>
</body>
</html>
