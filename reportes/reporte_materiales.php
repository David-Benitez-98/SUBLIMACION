<?php
/**
 * Reporte de planificación de materiales para pedidos activos.
 * Ruta: /reportes/reporte_materiales.php
 * Estima la demanda mediante pedido_detalle * producto_insumo y descuenta
 * el consumo que ya figure en pedido_insumo. No reserva ni modifica stock.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/movimientos/pedidos/pedidos.php');

function repMatEsc($valor): string
{
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function repMatCantidad($valor): string
{
    return rtrim(rtrim(number_format((float)$valor, 4, ',', '.'), '0'), ',');
}

function repMatBool($valor): bool
{
    return in_array($valor, [true, 1, '1', 't', 'true', 'TRUE', 'yes', 'on'], true);
}

function repMatFecha(?string $fecha): string
{
    if (!$fecha) {
        return '—';
    }
    $ts = strtotime($fecha);
    return $ts ? date('d/m/Y', $ts) : '—';
}

function repMatFechaValida(string $fecha): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        return false;
    }
    $objeto = DateTime::createFromFormat('!Y-m-d', $fecha);
    return $objeto instanceof DateTime && $objeto->format('Y-m-d') === $fecha;
}

function repMatCsvSeguro($valor): string
{
    $texto = (string)($valor ?? '');
    if ($texto !== '' && preg_match('/^[\s\x00-\x1F]*[=+@-]/u', $texto)) {
        return "'" . $texto;
    }
    return $texto;
}

$fechaDesde = trim((string)($_GET['fecha_desde'] ?? ''));
$fechaHasta = trim((string)($_GET['fecha_hasta'] ?? ''));
$estadoFiltro = (string)($_GET['estado'] ?? 'todos');
$buscarMaterial = trim((string)($_GET['buscar_material'] ?? ''));
$buscarPedido = trim((string)($_GET['buscar_pedido'] ?? ''));
$soloFaltantes = (string)($_GET['solo_faltantes'] ?? '') === '1';
$exportar = (string)($_GET['exportar'] ?? '') === 'csv';

if (!in_array($estadoFiltro, ['todos', 'confirmado', 'en_produccion'], true)) {
    $estadoFiltro = 'todos';
}
if (function_exists('mb_strlen') && mb_strlen($buscarMaterial, 'UTF-8') > 100) {
    $buscarMaterial = mb_substr($buscarMaterial, 0, 100, 'UTF-8');
} elseif (strlen($buscarMaterial) > 100) {
    $buscarMaterial = substr($buscarMaterial, 0, 100);
}
if (function_exists('mb_strlen') && mb_strlen($buscarPedido, 'UTF-8') > 100) {
    $buscarPedido = mb_substr($buscarPedido, 0, 100, 'UTF-8');
} elseif (strlen($buscarPedido) > 100) {
    $buscarPedido = substr($buscarPedido, 0, 100);
}

$errores = [];
$filas = [];
$filasMostrar = [];
$pedidosAnalizados = 0;
$productosSinReceta = 0;
$errorConsulta = false;

if ($fechaDesde !== '' && !repMatFechaValida($fechaDesde)) {
    $errores[] = 'La fecha inicial no es válida.';
}
if ($fechaHasta !== '' && !repMatFechaValida($fechaHasta)) {
    $errores[] = 'La fecha final no es válida.';
}
if ($fechaDesde !== '' && $fechaHasta !== '' && repMatFechaValida($fechaDesde) && repMatFechaValida($fechaHasta) && $fechaDesde > $fechaHasta) {
    $errores[] = 'La fecha inicial no puede ser posterior a la fecha final.';
}

$condicionesPedidos = [];
$parametrosPedidos = [];
if ($estadoFiltro === 'confirmado' || $estadoFiltro === 'en_produccion') {
    $condicionesPedidos[] = 'p.estado = :estado_pedido';
    $parametrosPedidos['estado_pedido'] = $estadoFiltro;
} else {
    $condicionesPedidos[] = "p.estado IN ('confirmado', 'en_produccion')";
}
if ($fechaDesde !== '' && repMatFechaValida($fechaDesde)) {
    $condicionesPedidos[] = 'p.fecha_pedido >= :fecha_desde';
    $parametrosPedidos['fecha_desde'] = $fechaDesde;
}
if ($fechaHasta !== '' && repMatFechaValida($fechaHasta)) {
    $condicionesPedidos[] = 'p.fecha_pedido <= :fecha_hasta';
    $parametrosPedidos['fecha_hasta'] = $fechaHasta;
}
if ($buscarPedido !== '') {
    $condicionesPedidos[] = "(
        p.numero_pedido ILIKE :buscar_pedido_numero
        OR (COALESCE(c.nombre, '') || ' ' || COALESCE(c.apellido, '')) ILIKE :buscar_pedido_cliente
        OR COALESCE(c.telefono, '') ILIKE :buscar_pedido_telefono
    )";
    $patronPedido = '%' . $buscarPedido . '%';
    $parametrosPedidos['buscar_pedido_numero'] = $patronPedido;
    $parametrosPedidos['buscar_pedido_cliente'] = $patronPedido;
    $parametrosPedidos['buscar_pedido_telefono'] = $patronPedido;
}
$wherePedidos = $condicionesPedidos ? 'WHERE ' . implode(' AND ', $condicionesPedidos) : '';

$condicionesMaterial = [];
$parametrosMaterial = [];
if ($buscarMaterial !== '') {
    $condicionesMaterial[] = '(i.nombre ILIKE :buscar_material_nombre OR i.codigo ILIKE :buscar_material_codigo)';
    $patronMaterial = '%' . $buscarMaterial . '%';
    $parametrosMaterial['buscar_material_nombre'] = $patronMaterial;
    $parametrosMaterial['buscar_material_codigo'] = $patronMaterial;
}
$whereMaterial = $condicionesMaterial ? 'WHERE ' . implode(' AND ', $condicionesMaterial) : '';

if (!$errores) {
    try {
        // Conteo de todos los pedidos incluidos, también los que no tienen receta de materiales.
        $sqlConteoPedidos = "
            SELECT COUNT(*)
            FROM sublimacion.pedidos p
            INNER JOIN sublimacion.clientes c ON c.id_cliente = p.id_cliente
            $wherePedidos
        ";
        $stmtPedidos = $conexion->prepare($sqlConteoPedidos);
        $stmtPedidos->execute($parametrosPedidos);
        $pedidosAnalizados = (int)$stmtPedidos->fetchColumn();

        // Advertencia de catálogo: pedidos activos que contienen productos sin receta configurada.
        $sqlSinReceta = "
            SELECT COUNT(DISTINCT pd.id_producto)
            FROM sublimacion.pedidos p
            INNER JOIN sublimacion.clientes c ON c.id_cliente = p.id_cliente
            INNER JOIN sublimacion.pedido_detalle pd ON pd.id_pedido = p.id_pedido
            $wherePedidos
              AND NOT EXISTS (
                  SELECT 1 FROM sublimacion.producto_insumo pi
                  WHERE pi.id_producto = pd.id_producto
              )
        ";
        $stmtSinReceta = $conexion->prepare($sqlSinReceta);
        $stmtSinReceta->execute($parametrosPedidos);
        $productosSinReceta = (int)$stmtSinReceta->fetchColumn();

        $sql = "
            WITH pedidos_filtrados AS (
                SELECT
                    p.id_pedido,
                    p.numero_pedido,
                    p.fecha_pedido,
                    p.fecha_entrega_estimada,
                    p.estado,
                    c.nombre AS cliente_nombre,
                    c.apellido AS cliente_apellido
                FROM sublimacion.pedidos p
                INNER JOIN sublimacion.clientes c ON c.id_cliente = p.id_cliente
                $wherePedidos
            ), demanda_estimada AS (
                SELECT
                    pf.id_pedido,
                    pi.id_insumo,
                    SUM(pd.cantidad * pi.cantidad_requerida) AS cantidad_estimada
                FROM pedidos_filtrados pf
                INNER JOIN sublimacion.pedido_detalle pd ON pd.id_pedido = pf.id_pedido
                INNER JOIN sublimacion.producto_insumo pi ON pi.id_producto = pd.id_producto
                GROUP BY pf.id_pedido, pi.id_insumo
            ), consumo_registrado AS (
                SELECT id_pedido, id_insumo, SUM(cantidad_utilizada) AS cantidad_utilizada
                FROM sublimacion.pedido_insumo
                GROUP BY id_pedido, id_insumo
            ), pendiente_por_pedido AS (
                SELECT
                    d.id_pedido,
                    d.id_insumo,
                    GREATEST(d.cantidad_estimada - COALESCE(cr.cantidad_utilizada, 0), 0) AS cantidad_pendiente
                FROM demanda_estimada d
                LEFT JOIN consumo_registrado cr
                    ON cr.id_pedido = d.id_pedido AND cr.id_insumo = d.id_insumo
            ), demanda_por_insumo AS (
                SELECT id_insumo, SUM(cantidad_pendiente) AS cantidad_necesaria
                FROM pendiente_por_pedido
                GROUP BY id_insumo
                HAVING SUM(cantidad_pendiente) > 0
            ), pedidos_por_insumo AS (
                SELECT
                    pp.id_insumo,
                    STRING_AGG(
                        pf.numero_pedido || ' — ' || COALESCE(
                            NULLIF(BTRIM(CONCAT_WS(' ', pf.cliente_nombre, pf.cliente_apellido)), ''),
                            'Cliente sin nombre'
                        ),
                        CHR(10) ORDER BY pf.fecha_entrega_estimada NULLS LAST, pf.numero_pedido
                    ) AS pedidos_clientes
                FROM pendiente_por_pedido pp
                INNER JOIN pedidos_filtrados pf ON pf.id_pedido = pp.id_pedido
                WHERE pp.cantidad_pendiente > 0
                GROUP BY pp.id_insumo
            )
            SELECT
                i.id_insumo,
                i.codigo,
                i.nombre,
                COALESCE(um.nombre, 'Sin unidad') AS unidad,
                COALESCE(i.stock_actual, 0) AS stock_actual,
                dp.cantidad_necesaria,
                GREATEST(dp.cantidad_necesaria - COALESCE(i.stock_actual, 0), 0) AS faltante,
                GREATEST(COALESCE(i.stock_actual, 0) - dp.cantidad_necesaria, 0) AS sobrante_estimado,
                COALESCE(ppi.pedidos_clientes, '—') AS pedidos_clientes,
                i.activo
            FROM demanda_por_insumo dp
            INNER JOIN sublimacion.insumos i ON i.id_insumo = dp.id_insumo
            LEFT JOIN sublimacion.unidades_medida um ON um.id_unidad = i.id_unidad
            LEFT JOIN pedidos_por_insumo ppi ON ppi.id_insumo = i.id_insumo
            $whereMaterial
            ORDER BY
                CASE WHEN GREATEST(dp.cantidad_necesaria - COALESCE(i.stock_actual, 0), 0) > 0 THEN 0 ELSE 1 END,
                GREATEST(dp.cantidad_necesaria - COALESCE(i.stock_actual, 0), 0) DESC,
                i.nombre ASC
        ";
        $stmt = $conexion->prepare($sql);
        $stmt->execute(array_merge($parametrosPedidos, $parametrosMaterial));
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $filasMostrar = $soloFaltantes
            ? array_values(array_filter($filas, static fn($f) => (float)$f['faltante'] > 0))
            : $filas;
    } catch (PDOException $e) {
        error_log('Reporte materiales: ' . $e->getMessage());
        $errorConsulta = true;
    }
}

$materialesConFaltante = count(array_filter($filas, static fn($f) => (float)$f['faltante'] > 0));

if ($exportar && !$errorConsulta && !$errores) {
    $nombreArchivo = 'reporte_materiales_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    echo "\xEF\xBB\xBF";
    $salida = fopen('php://output', 'w');
    fputcsv($salida, ['Código', 'Material', 'Unidad', 'Stock actual', 'Necesidad pendiente estimada', 'Faltante estimado', 'Disponible después de cubrir la demanda', 'Pedidos / clientes', 'Insumo activo'], ';', '"', '\\');
    foreach ($filasMostrar as $f) {
        fputcsv($salida, [
            repMatCsvSeguro($f['codigo']),
            repMatCsvSeguro($f['nombre']),
            repMatCsvSeguro($f['unidad']),
            repMatCantidad($f['stock_actual']),
            repMatCantidad($f['cantidad_necesaria']),
            repMatCantidad($f['faltante']),
            repMatCantidad($f['sobrante_estimado']),
            repMatCsvSeguro($f['pedidos_clientes']),
            repMatBool($f['activo']) ? 'Sí' : 'No',
        ], ';', '"', '\\');
    }
    fclose($salida);
    exit;
}

$baseReporteUrl = rtrim((string)BASE_URL, '/') . '/reportes/reporte_materiales.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de materiales | Sublimación</title>
    <style>
        .print-only{display:none}
        :root{--fondo:#f3f5f9;--panel:#fff;--texto:#263247;--muted:#6b7280;--borde:#e1e6ef;--primario:#2856a6;--rojo:#b42318;--verde:#18794e;--ambar:#a15c00}
        *{box-sizing:border-box}body{margin:0;padding:24px;background:var(--fondo);color:var(--texto);font:14px/1.45 Arial,Helvetica,sans-serif}.contenedor{max-width:1600px;margin:0 auto}
        .encabezado{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;margin-bottom:20px}h1{margin:0 0 6px;font-size:28px;letter-spacing:-.4px}.subtitulo{margin:0;color:var(--muted)}.acciones{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 14px;border:1px solid transparent;border-radius:8px;text-decoration:none;font-weight:700;cursor:pointer;font-size:13px}.btn-principal{background:var(--primario);color:white}.btn-secundario{background:#fff;color:var(--texto);border-color:var(--borde)}
        .panel,.indicador{background:var(--panel);border:1px solid var(--borde);border-radius:12px;box-shadow:0 2px 8px rgba(25,40,70,.04)}.panel{padding:18px;margin-bottom:18px}.panel-titulo{margin:0 0 14px;font-size:16px}.filtros-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;align-items:end}
        label{display:block;margin-bottom:6px;font-weight:700;font-size:12px;color:#4b5563}input,select{width:100%;min-height:40px;border:1px solid #cfd6e2;border-radius:7px;padding:8px 10px;background:#fff;color:var(--texto);font:inherit}.checkbox-linea{display:flex;align-items:center;gap:8px;min-height:40px;font-weight:700}.checkbox-linea input{width:17px;min-height:17px}
        .filtro-acciones{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.resumen{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:18px 0}.indicador{padding:16px}.indicador-label{color:var(--muted);font-size:12px;font-weight:700}.indicador-valor{font-size:25px;font-weight:800;margin:7px 0 2px}.indicador-nota{color:var(--muted);font-size:11px}
        .nota{padding:12px 14px;background:#edf4ff;border:1px solid #c9daf8;border-radius:9px;color:#28446e;font-size:12px;margin:0 0 16px}.alerta{padding:12px 14px;background:#fff0d2;border:1px solid #f0d09a;border-radius:9px;color:#7c4a00;font-size:12px;margin:0 0 16px}
        .tabla-panel{padding:0;overflow:hidden}.tabla-cabecera{padding:16px 18px;border-bottom:1px solid var(--borde);display:flex;justify-content:space-between;gap:12px;align-items:center}.tabla-cabecera h2{margin:0;font-size:16px}.tabla-scroll{overflow:auto}table{width:100%;border-collapse:collapse;min-width:1150px}th,td{text-align:left;padding:11px 12px;border-bottom:1px solid var(--borde);vertical-align:top}th{background:#f7f9fc;color:#4b5563;font-size:11px;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap}tbody tr:hover{background:#fbfcff}tbody tr:last-child td{border-bottom:0}.codigo{font-weight:800;white-space:nowrap}.numero{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}.lista-pedidos{min-width:190px;white-space:pre-line;font-size:12px;color:#4b5563}.badge{display:inline-block;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:800;white-space:nowrap}.badge-faltante{background:#fee4e2;color:var(--rojo)}.badge-cubierto{background:#dff5e9;color:var(--verde)}.badge-inactivo{background:#eceff4;color:#586174}
        .vacio,.mensaje{padding:18px;border-radius:9px;margin-bottom:16px}.vacio{color:var(--muted);text-align:center}.mensaje-error{background:#fff0ef;border:1px solid #f3b7b2;color:#8a1c13}.pie{margin-top:14px;color:var(--muted);font-size:11px;display:flex;justify-content:space-between;gap:12px}
        @media(max-width:1050px){.filtros-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.resumen{grid-template-columns:repeat(2,minmax(0,1fr))}.encabezado{flex-direction:column}.acciones{justify-content:flex-start}}
        @media(max-width:620px){body{padding:12px}.filtros-grid,.resumen{grid-template-columns:1fr}.tabla-cabecera{align-items:flex-start;flex-direction:column}}
        @media print{.print-only{display:block}@page{size:A4 landscape;margin:9mm}body{padding:0;background:#fff;font-size:9px}.contenedor{max-width:none}.no-print{display:none!important}.encabezado{margin-bottom:8px}h1{font-size:20px}.panel,.indicador{box-shadow:none}.resumen{grid-template-columns:repeat(4,minmax(0,1fr));gap:6px;margin:8px 0}.indicador{padding:7px}.indicador-valor{font-size:15px}.tabla-scroll{overflow:visible}table{min-width:0;font-size:8px}th,td{padding:5px}.lista-pedidos{min-width:120px;font-size:8px}.badge{border:1px solid #aaa;background:#fff!important;color:#222!important}.nota,.alerta{padding:6px;margin-bottom:7px}.pie{margin-top:7px}}
    </style>
</head>
<body>
<div class="contenedor">
    <header class="encabezado">
        <div><h1>Reporte de materiales</h1><p class="subtitulo">Demanda estimada de insumos para pedidos confirmados y en producción.</p><p class="subtitulo print-only">Generado: <?php echo date('d/m/Y H:i'); ?></p></div>
        <div class="acciones no-print"><button class="btn btn-secundario" type="button" onclick="window.print()">🖨 Imprimir</button><button class="btn btn-principal" type="submit" form="formFiltros" name="exportar" value="csv">⇩ Exportar Excel (CSV)</button><a class="btn btn-secundario" href="<?php echo repMatEsc($baseReporteUrl); ?>">Limpiar filtros</a></div>
    </header>

    <div class="nota"><strong>Cómo se calcula:</strong> se estima la necesidad a partir de la cantidad de productos de cada pedido multiplicada por la receta configurada en <code>producto_insumo</code>. Luego se descuenta el consumo registrado en <code>pedido_insumo</code> y se compara la demanda pendiente con el stock actual. Es una estimación: los diseños personalizados pueden variar.</div>
    <?php if ($productosSinReceta > 0 && !$errorConsulta && !$errores): ?><div class="alerta"><strong>Atención:</strong> <?php echo number_format($productosSinReceta, 0, ',', '.'); ?> producto(s) incluido(s) en los pedidos analizados no tienen receta en <code>producto_insumo</code>; esos productos no pueden generar una estimación fiable de materiales.</div><?php endif; ?>
    <?php if ($errores): ?><div class="mensaje mensaje-error" role="alert"><?php foreach ($errores as $error): ?><div><?php echo repMatEsc($error); ?></div><?php endforeach; ?></div><?php endif; ?>
    <?php if ($errorConsulta): ?><div class="mensaje mensaje-error" role="alert">No se pudo calcular la demanda de materiales. Revisá el log del servidor o intentá nuevamente.</div><?php endif; ?>

    <section class="panel no-print">
        <h2 class="panel-titulo">Filtros de planificación</h2>
        <form id="formFiltros" method="get" action="<?php echo repMatEsc($baseReporteUrl); ?>">
            <div class="filtros-grid">
                <div><label for="fecha_desde">Fecha del pedido desde</label><input type="date" id="fecha_desde" name="fecha_desde" value="<?php echo repMatEsc($fechaDesde); ?>"></div>
                <div><label for="fecha_hasta">Fecha del pedido hasta</label><input type="date" id="fecha_hasta" name="fecha_hasta" value="<?php echo repMatEsc($fechaHasta); ?>"></div>
                <div><label for="estado">Estado de los pedidos</label><select id="estado" name="estado"><option value="todos" <?php echo $estadoFiltro === 'todos' ? 'selected' : ''; ?>>Confirmados y en producción</option><option value="confirmado" <?php echo $estadoFiltro === 'confirmado' ? 'selected' : ''; ?>>Solo confirmados</option><option value="en_produccion" <?php echo $estadoFiltro === 'en_produccion' ? 'selected' : ''; ?>>Solo en producción</option></select></div>
                <div><label for="buscar_material">Material o código</label><input type="search" id="buscar_material" name="buscar_material" maxlength="100" value="<?php echo repMatEsc($buscarMaterial); ?>" placeholder="Ej.: taza blanca, INS-001"></div>
                <div><label for="buscar_pedido">N.º de pedido, cliente o teléfono</label><input type="search" id="buscar_pedido" name="buscar_pedido" maxlength="100" value="<?php echo repMatEsc($buscarPedido); ?>" placeholder="Ej.: PED-00012"></div>
                <div><label class="checkbox-linea" for="solo_faltantes"><input type="checkbox" id="solo_faltantes" name="solo_faltantes" value="1" <?php echo $soloFaltantes ? 'checked' : ''; ?>>Mostrar solo materiales faltantes</label></div>
            </div>
            <div class="filtro-acciones"><button class="btn btn-principal" type="submit">Calcular materiales</button></div>
        </form>
    </section>

    <section class="resumen">
        <div class="indicador"><div class="indicador-label">Pedidos analizados</div><div class="indicador-valor"><?php echo number_format($pedidosAnalizados, 0, ',', '.'); ?></div><div class="indicador-nota">Solo confirmados y/o en producción</div></div>
        <div class="indicador"><div class="indicador-label">Materiales con demanda</div><div class="indicador-valor"><?php echo number_format(count($filas), 0, ',', '.'); ?></div><div class="indicador-nota">Con necesidad pendiente estimada</div></div>
        <div class="indicador"><div class="indicador-label">Materiales con faltante</div><div class="indicador-valor"><?php echo number_format($materialesConFaltante, 0, ',', '.'); ?></div><div class="indicador-nota">La demanda supera el stock actual</div></div>
        <div class="indicador"><div class="indicador-label">Productos sin receta</div><div class="indicador-valor"><?php echo number_format($productosSinReceta, 0, ',', '.'); ?></div><div class="indicador-nota">Requieren configurar producto_insumo</div></div>
    </section>

    <section class="panel tabla-panel">
        <div class="tabla-cabecera"><h2>Necesidad estimada por material</h2><span class="subtitulo"><?php echo number_format(count($filasMostrar), 0, ',', '.'); ?> material(es) visibles</span></div>
        <?php if (!$errorConsulta && !$errores && count($filasMostrar) === 0): ?><div class="vacio">No se encontraron materiales para los filtros seleccionados. Puede que no haya pedidos activos o que no se hayan configurado las recetas.</div>
        <?php elseif (!$errorConsulta && !$errores): ?>
            <div class="tabla-scroll"><table>
                <thead><tr><th>Código</th><th>Material</th><th>Unidad</th><th class="numero">Stock actual</th><th class="numero">Necesidad pendiente</th><th class="numero">Faltante estimado</th><th>Estado</th><th>Pedidos / clientes asociados</th></tr></thead>
                <tbody><?php foreach ($filasMostrar as $f): ?><tr>
                    <td class="codigo"><?php echo repMatEsc($f['codigo']); ?></td>
                    <td><strong><?php echo repMatEsc($f['nombre']); ?></strong><?php if (!repMatBool($f['activo'])): ?><br><span class="badge badge-inactivo">Insumo inactivo</span><?php endif; ?></td>
                    <td><?php echo repMatEsc($f['unidad']); ?></td>
                    <td class="numero"><?php echo repMatCantidad($f['stock_actual']); ?></td>
                    <td class="numero"><?php echo repMatCantidad($f['cantidad_necesaria']); ?></td>
                    <td class="numero"><strong><?php echo repMatCantidad($f['faltante']); ?></strong></td>
                    <td><?php if ((float)$f['faltante'] > 0): ?><span class="badge badge-faltante">Comprar</span><?php else: ?><span class="badge badge-cubierto">Stock suficiente</span><?php endif; ?></td>
                    <td class="lista-pedidos"><?php echo repMatEsc($f['pedidos_clientes']); ?></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>
    <footer class="pie"><span>Este reporte es informativo: no reserva materiales ni actualiza existencias.</span><span>Generado el <?php echo date('d/m/Y H:i'); ?></span></footer>
</div>
</body>
</html>
