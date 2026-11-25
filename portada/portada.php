<?php
require_once dirname(__DIR__) . '/security/verificacion_sesion.php';
require_once dirname(__DIR__) . '/config/conexion.php';
require_once dirname(__DIR__) . '/config/constantes.php';
require_once dirname(__DIR__) . '/security/permisos.php';

exigirAcceso($conexion);

/*
 * PANEL PRINCIPAL / DASHBOARD
 * - Solo lectura: no cambia estados, pagos ni stock.
 * - Los faltantes son estimaciones basadas en producto_insumo.
 * - El stock se modifica exclusivamente desde los flujos transaccionales.
 */

if (!function_exists('dashboardH')) {
    function dashboardH($valor): string
    {
        return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('dashboardFetchAll')) {
    function dashboardFetchAll(PDO $conexion, string $sql, array $parametros, array &$errores, string $contexto): array
    {
        try {
            $stmt = $conexion->prepare($sql);
            $stmt->execute($parametros);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Dashboard (' . $contexto . '): ' . $e->getMessage());
            $errores[] = $contexto;
            return [];
        }
    }
}

if (!function_exists('dashboardFetchRow')) {
    function dashboardFetchRow(PDO $conexion, string $sql, array $parametros, array &$errores, string $contexto): array
    {
        try {
            $stmt = $conexion->prepare($sql);
            $stmt->execute($parametros);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
            return $fila ?: [];
        } catch (PDOException $e) {
            error_log('Dashboard (' . $contexto . '): ' . $e->getMessage());
            $errores[] = $contexto;
            return [];
        }
    }
}

function dashboardEstadoEtiqueta(string $estado): string
{
    $etiquetas = [
        'presupuestado' => 'Presupuestado',
        'confirmado' => 'Confirmado',
        'en_produccion' => 'En producción',
        'listo' => 'Listo',
        'entregado' => 'Entregado',
        'cancelado' => 'Cancelado',
    ];
    return $etiquetas[$estado] ?? ucfirst(str_replace('_', ' ', $estado));
}

function dashboardClaseEstado(string $estado): string
{
    $clases = [
        'presupuestado' => 'badge-soft-secondary',
        'confirmado' => 'badge-soft-primary',
        'en_produccion' => 'badge-soft-info',
        'listo' => 'badge-soft-success',
        'entregado' => 'badge-soft-success',
        'cancelado' => 'badge-soft-dark',
    ];
    return $clases[$estado] ?? 'badge-soft-secondary';
}

function dashboardEtiquetaEntrega(?string $fecha): array
{
    if (empty($fecha)) {
        return ['Sin fecha', 'badge-soft-secondary'];
    }

    try {
        $entrega = new DateTimeImmutable($fecha);
        $hoy = new DateTimeImmutable('today');
        $dias = (int)$hoy->diff($entrega)->format('%r%a');

        if ($dias < 0) {
            return ['Atrasado ' . abs($dias) . ' d.', 'badge-soft-danger'];
        }
        if ($dias === 0) {
            return ['Entrega hoy', 'badge-soft-danger'];
        }
        if ($dias === 1) {
            return ['Mañana', 'badge-soft-warning'];
        }
        if ($dias <= 7) {
            return ['En ' . $dias . ' días', 'badge-soft-warning'];
        }
        return ['Programada', 'badge-soft-secondary'];
    } catch (Exception $e) {
        return ['Fecha inválida', 'badge-soft-secondary'];
    }
}

$erroresPanel = [];
$hoy = date('d/m/Y');

// Rutas conocidas del proyecto. La comprobación de permisos se basa en los links del menú dinámico.
$RUTA_PEDIDOS = '/modulos/movimientos/pedidos/pedidos.php';
$RUTA_NUEVO_PEDIDO = '/modulos/movimientos/pedidos/pedido_form.php';
$RUTA_COMPRAS = '/modulos/movimientos/compras/compras.php';
$RUTA_INSUMOS = '/modulos/referenciales/insumos/insumos.php';
$RUTA_MOVIMIENTOS = '/modulos/movimientos/movimientos_stock/movimientos_stock.php';

// 1) Indicadores de pedidos.
$estadosAbiertos = ['confirmado', 'en_produccion', 'listo'];
$marcas = implode(',', array_fill(0, count($estadosAbiertos), '?'));
$indicadores = dashboardFetchRow(
    $conexion,
    "SELECT
        COUNT(*) AS abiertos,
        COUNT(*) FILTER (WHERE fecha_entrega_estimada < CURRENT_DATE) AS atrasados,
        COUNT(*) FILTER (WHERE fecha_entrega_estimada BETWEEN CURRENT_DATE AND CURRENT_DATE + 7) AS proximos,
        COUNT(*) FILTER (WHERE fecha_entrega_estimada IS NULL) AS sin_fecha
     FROM sublimacion.pedidos
     WHERE estado IN ($marcas)",
    $estadosAbiertos,
    $erroresPanel,
    'indicadores de pedidos'
);

$abiertos = (int)($indicadores['abiertos'] ?? 0);
$atrasados = (int)($indicadores['atrasados'] ?? 0);
$proximos = (int)($indicadores['proximos'] ?? 0);
$sinFecha = (int)($indicadores['sin_fecha'] ?? 0);

$presupuestadosRow = dashboardFetchRow(
    $conexion,
    "SELECT COUNT(*) AS total FROM sublimacion.pedidos WHERE estado = ?",
    ['presupuestado'],
    $erroresPanel,
    'presupuestos pendientes'
);
$presupuestados = (int)($presupuestadosRow['total'] ?? 0);

$insumosBajoRow = dashboardFetchRow(
    $conexion,
    "SELECT COUNT(*) AS total
     FROM sublimacion.insumos
     WHERE activo = TRUE AND stock_actual <= stock_minimo",
    [],
    $erroresPanel,
    'conteo de insumos bajo mínimo'
);
$insumosBajo = (int)($insumosBajoRow['total'] ?? 0);

// 2) Pedidos pendientes de trabajo/entrega. Incluye presupuestados para mostrar solicitudes recientes.
$pedidosAtencion = dashboardFetchAll(
    $conexion,
    "SELECT
        p.id_pedido,
        p.numero_pedido,
        p.fecha_pedido,
        p.fecha_entrega_estimada,
        p.estado,
        p.total,
        p.sena_pagada,
        COALESCE(c.nombre, '') || CASE WHEN COALESCE(c.apellido, '') <> '' THEN ' ' || c.apellido ELSE '' END AS nombre_cliente,
        COALESCE(c.telefono, '') AS telefono_cliente
     FROM sublimacion.pedidos p
     INNER JOIN sublimacion.clientes c ON c.id_cliente = p.id_cliente
     WHERE p.estado NOT IN ('entregado', 'cancelado')
     ORDER BY p.fecha_entrega_estimada ASC NULLS LAST, p.fecha_pedido ASC, p.id_pedido DESC
     LIMIT 8",
    [],
    $erroresPanel,
    'listado de pedidos prioritarios'
);

// 3) Stock por debajo del mínimo para mostrar un acceso rápido al insumo.
$listaStockBajo = dashboardFetchAll(
    $conexion,
    "SELECT id_insumo, nombre, stock_actual, stock_minimo
     FROM sublimacion.insumos
     WHERE activo = TRUE AND stock_actual <= stock_minimo
     ORDER BY (stock_minimo - stock_actual) DESC, nombre ASC
     LIMIT 6",
    [],
    $erroresPanel,
    'listado de insumos bajo mínimo'
);

/*
 * 4) Planificación agregada de materiales.
 * Solo incluye pedidos confirmados/en producción. Los presupuestos no confirmados no comprometen
 * compras. Se calcula la demanda estimada mediante pedido_detalle * producto_insumo y se resta
 * el consumo ya registrado en pedido_insumo para ese pedido/insumo. Después se compara el total
 * pendiente agregado contra el stock actual una sola vez, evitando contar el mismo stock por pedido.
 * Las cantidades deben usar la misma unidad que el insumo. Es una estimación, no una receta exacta.
 */
$sqlFaltantes = "WITH demanda_estimada AS (
        SELECT
            p.id_pedido,
            pi.id_insumo,
            SUM(pd.cantidad * pi.cantidad_requerida) AS cantidad_estimada
        FROM sublimacion.pedidos p
        INNER JOIN sublimacion.pedido_detalle pd ON pd.id_pedido = p.id_pedido
        INNER JOIN sublimacion.producto_insumo pi ON pi.id_producto = pd.id_producto
        WHERE p.estado IN ('confirmado', 'en_produccion')
        GROUP BY p.id_pedido, pi.id_insumo
    ), consumo_registrado AS (
        SELECT id_pedido, id_insumo, SUM(cantidad_utilizada) AS cantidad_utilizada
        FROM sublimacion.pedido_insumo
        GROUP BY id_pedido, id_insumo
    ), demanda_pendiente_por_pedido AS (
        SELECT
            d.id_pedido,
            d.id_insumo,
            GREATEST(d.cantidad_estimada - COALESCE(c.cantidad_utilizada, 0), 0) AS cantidad_pendiente
        FROM demanda_estimada d
        LEFT JOIN consumo_registrado c
            ON c.id_pedido = d.id_pedido AND c.id_insumo = d.id_insumo
    ), demanda_pendiente AS (
        SELECT id_insumo, SUM(cantidad_pendiente) AS cantidad_necesaria
        FROM demanda_pendiente_por_pedido
        GROUP BY id_insumo
    ), pedidos_por_insumo AS (
        SELECT
            dpp.id_insumo,
            STRING_AGG(
                p.numero_pedido || ' — ' || COALESCE(
                    NULLIF(BTRIM(CONCAT_WS(' ', c.nombre, c.apellido)), ''),
                    'Cliente sin nombre'
                ),
                CHR(10) ORDER BY p.fecha_entrega_estimada NULLS LAST, p.numero_pedido
            ) AS pedidos_clientes
        FROM demanda_pendiente_por_pedido dpp
        INNER JOIN sublimacion.pedidos p ON p.id_pedido = dpp.id_pedido
        INNER JOIN sublimacion.clientes c ON c.id_cliente = p.id_cliente
        WHERE dpp.cantidad_pendiente > 0
        GROUP BY dpp.id_insumo
    )
    SELECT
        i.id_insumo,
        i.nombre,
        COALESCE(i.stock_actual, 0) AS stock_actual,
        dp.cantidad_necesaria,
        GREATEST(dp.cantidad_necesaria - COALESCE(i.stock_actual, 0), 0) AS faltante,
        COALESCE(ppi.pedidos_clientes, '—') AS pedidos_clientes
    FROM demanda_pendiente dp
    INNER JOIN sublimacion.insumos i ON i.id_insumo = dp.id_insumo
    LEFT JOIN pedidos_por_insumo ppi ON ppi.id_insumo = dp.id_insumo
    WHERE i.activo = TRUE
      AND GREATEST(dp.cantidad_necesaria - COALESCE(i.stock_actual, 0), 0) > 0
    ORDER BY faltante DESC, i.nombre ASC";

$listaFaltantes = dashboardFetchAll(
    $conexion,
    $sqlFaltantes,
    [],
    $erroresPanel,
    'planificación de faltantes'
);
$insumosPorComprar = count($listaFaltantes);

// Productos de pedidos confirmados/en producción que no tienen receta configurada.
$sinRecetaRow = dashboardFetchRow(
    $conexion,
    "SELECT COUNT(DISTINCT pd.id_producto) AS total
     FROM sublimacion.pedidos p
     INNER JOIN sublimacion.pedido_detalle pd ON pd.id_pedido = p.id_pedido
     WHERE p.estado IN ('confirmado', 'en_produccion')
       AND NOT EXISTS (
           SELECT 1
           FROM sublimacion.producto_insumo pi
           WHERE pi.id_producto = pd.id_producto
       )",
    [],
    $erroresPanel,
    'productos sin receta'
);
$productosSinReceta = (int)($sinRecetaRow['total'] ?? 0);

// 5) Últimos movimientos. Solo lectura: este panel jamás crea ni corrige movimientos.
$ultimosMovimientos = dashboardFetchAll(
    $conexion,
    "SELECT
        m.id_movimiento,
        m.fecha,
        m.tipo,
        m.motivo,
        m.cantidad,
        m.stock_anterior,
        m.stock_nuevo,
        i.nombre AS nombre_insumo
     FROM sublimacion.movimientos_stock m
     INNER JOIN sublimacion.insumos i ON i.id_insumo = m.id_insumo
     ORDER BY m.fecha DESC, m.id_movimiento DESC
     LIMIT 6",
    [],
    $erroresPanel,
    'últimos movimientos de stock'
);

// 6) Links autorizados según el menú dinámico del usuario.
$linksPermitidos = [];
try {
    $menuUsuario = obtenerMenuUsuario($conexion, $_SESSION['id_usuario']);
    foreach ($menuUsuario as $categoria) {
        if (!empty($categoria['link'])) {
            $linksPermitidos[$categoria['link']] = true;
        }
        foreach (($categoria['items'] ?? []) as $item) {
            if (!empty($item['link'])) {
                $linksPermitidos[$item['link']] = true;
            }
        }
    }
} catch (Throwable $e) {
    error_log('Dashboard: no se pudieron recuperar los permisos del menú: ' . $e->getMessage());
    $erroresPanel[] = 'permisos del menú';
}

$accesos = [
    ['Nuevo pedido', 'fa-plus-circle', $RUTA_NUEVO_PEDIDO, [$RUTA_PEDIDOS], 'primary'],
    ['Ver pedidos', 'fa-clipboard-list', $RUTA_PEDIDOS, [$RUTA_PEDIDOS], 'secondary'],
    ['Registrar compra', 'fa-shopping-cart', $RUTA_COMPRAS, [$RUTA_COMPRAS], 'success'],
    ['Ver insumos', 'fa-boxes', $RUTA_INSUMOS, [$RUTA_INSUMOS], 'info'],
    ['Movimientos de stock', 'fa-exchange-alt', $RUTA_MOVIMIENTOS, [$RUTA_MOVIMIENTOS], 'secondary'],
    ['Imprimir faltantes', 'fa-print', '@print', [$RUTA_PEDIDOS, $RUTA_INSUMOS], 'warning'],
];

function dashboardAccesoPermitido(array $permisosRequeridos, array $linksPermitidos): bool
{
    foreach ($permisosRequeridos as $permiso) {
        if (empty($linksPermitidos[$permiso])) {
            return false;
        }
    }
    return true;
}

function dashboardFecha(?string $fecha): string
{
    if (empty($fecha)) {
        return '—';
    }
    $timestamp = strtotime($fecha);
    return $timestamp ? date('d/m/Y', $timestamp) : dashboardH($fecha);
}

function dashboardNumero($valor, int $decimales = 3): string
{
    $numero = (float)($valor ?? 0);
    $formateado = number_format($numero, $decimales, ',', '.');
    return rtrim(rtrim($formateado, '0'), ',');
}

function dashboardGs($valor): string
{
    return 'Gs. ' . number_format((float)($valor ?? 0), 0, ',', '.');
}

$errorPanel = !empty($erroresPanel);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include_once dirname(__DIR__) . '/portada/cabecera.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel principal | Sublimación</title>
    <style>
        .dashboard-intro { display:flex; justify-content:space-between; align-items:flex-end; gap:1rem; flex-wrap:wrap; }
        .dashboard-muted { color:#6c757d; }
        .dashboard-shortcut { display:flex; align-items:center; gap:.65rem; min-height:46px; margin:0 .45rem .5rem 0; }
        .dashboard-shortcut i { width:18px; }
        .dashboard-panel { border:1px solid #e8ebef; border-radius:10px; background:#fff; box-shadow:0 2px 8px rgba(30,45,60,.035); margin-bottom:1.25rem; }
        .dashboard-panel__header { display:flex; justify-content:space-between; align-items:center; gap:1rem; padding:1rem 1.1rem; border-bottom:1px solid #edf0f3; flex-wrap:wrap; }
        .dashboard-panel__header h3 { margin:0; font-size:1rem; font-weight:600; }
        .dashboard-panel__body { padding:1rem 1.1rem; }
        .dashboard-table { margin-bottom:0; vertical-align:middle; }
        .dashboard-table th { white-space:nowrap; color:#647080; font-size:.78rem; text-transform:uppercase; letter-spacing:.025em; border-top:0; }
        .dashboard-table td { font-size:.88rem; }
        .dashboard-order-list { min-width:210px; white-space:normal; line-height:1.45; }
        .dashboard-order-list br { content:""; display:block; margin-bottom:.12rem; }
        /* KPI cards: altura y estructura uniformes, independientes de widget-small de la plantilla */
        .dashboard-kpi-grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:16px; margin:0 0 1.35rem; }
        .dashboard-kpi-card { display:flex; align-items:stretch; min-width:0; min-height:112px; background:#fff; border:1px solid #e7ebf0; border-radius:12px; overflow:hidden; box-shadow:0 3px 12px rgba(25,42,62,.055); transition:transform .15s ease, box-shadow .15s ease; }
        .dashboard-kpi-card:hover { transform:translateY(-2px); box-shadow:0 7px 18px rgba(25,42,62,.09); }
        .dashboard-kpi-card__icon { flex:0 0 68px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.55rem; }
        .dashboard-kpi-card__content { display:flex; flex:1; flex-direction:column; justify-content:center; min-width:0; padding:.9rem 1rem; }
        .dashboard-kpi-card__label { margin:0 0 .35rem; color:#596575; font-size:.76rem; font-weight:700; line-height:1.3; text-transform:uppercase; letter-spacing:.045em; }
        .dashboard-kpi-card__value { margin:0; color:#202a36; font-size:1.85rem; font-weight:700; line-height:1.05; }
        .dashboard-kpi-card__hint { display:block; margin-top:.45rem; color:#758091; font-size:.73rem; line-height:1.35; }
        .dashboard-kpi-card--green .dashboard-kpi-card__icon { background:#159667; }
        .dashboard-kpi-card--blue .dashboard-kpi-card__icon { background:#159bb5; }
        .dashboard-kpi-card--amber .dashboard-kpi-card__icon { background:#e6a500; }
        .dashboard-kpi-card--red .dashboard-kpi-card__icon { background:#df3547; }
        .dashboard-kpi-card--neutral .dashboard-kpi-card__icon { background:#62748a; }
        @media (max-width:1199px) { .dashboard-kpi-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); } }
        @media (max-width:575px) { .dashboard-kpi-grid { grid-template-columns:1fr; gap:10px; } .dashboard-kpi-card { min-height:96px; } .dashboard-kpi-card__icon { flex-basis:60px; } }
        .dashboard-badge { display:inline-block; padding:.3rem .55rem; border-radius:20px; font-size:.76rem; font-weight:600; white-space:nowrap; }
        .badge-soft-secondary { background:#edf0f3; color:#4d5966; }
        .badge-soft-primary { background:#e7efff; color:#2456a6; }
        .badge-soft-info { background:#e2f6fb; color:#14758c; }
        .badge-soft-success { background:#e3f5e9; color:#217346; }
        .badge-soft-warning { background:#fff2d6; color:#895c00; }
        .badge-soft-danger { background:#fde8e7; color:#a52822; }
        .badge-soft-dark { background:#e5e7eb; color:#343a40; }
        .dashboard-empty { padding:1rem; color:#6c757d; text-align:center; }
        .dashboard-section-title { margin:0 0 .2rem; font-weight:600; }
        .dashboard-stock-low { color:#a52822; font-weight:600; }
        #reporte-faltantes-print { display:none; }
        @media (max-width:767px) {
            .dashboard-panel__body { padding:.75rem; }
            .dashboard-panel__header { padding:.85rem; }
            .dashboard-table { min-width:680px; }
        }
    </style>
</head>
<?php include_once dirname(__DIR__) . '/menu/menu.php'; ?>
<main class="app-content">
    <div class="app-title dashboard-intro">
        <div>
            <h1><i class="fas fa-home"></i> Menú principal</h1>
            <p class="dashboard-muted mb-0">Resumen operativo para organizar pedidos, entregas y materiales.</p>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><i class="fas fa-home fa-lg"></i></li>
            <li class="breadcrumb-item">Menú principal</li>
        </ul>
    </div>

    <?php if ($errorPanel): ?>
        <div class="alert alert-warning" role="alert">
            Algunos indicadores no pudieron cargarse. El problema quedó registrado en el log del servidor; el resto del panel sigue disponible.
        </div>
    <?php endif; ?>

    <!-- Indicadores operativos: tarjetas de dimensiones uniformes -->
    <section class="dashboard-kpi-grid" aria-label="Indicadores principales">
        <article class="dashboard-kpi-card dashboard-kpi-card--green">
            <div class="dashboard-kpi-card__icon" aria-hidden="true"><i class="fas fa-clipboard-list"></i></div>
            <div class="dashboard-kpi-card__content">
                <p class="dashboard-kpi-card__label">Pedidos abiertos</p>
                <p class="dashboard-kpi-card__value"><?php echo $abiertos; ?></p>
                <small class="dashboard-kpi-card__hint"><?php echo $presupuestados; ?> presupuesto(s) por confirmar</small>
            </div>
        </article>
        <article class="dashboard-kpi-card dashboard-kpi-card--blue">
            <div class="dashboard-kpi-card__icon" aria-hidden="true"><i class="fas fa-calendar-alt"></i></div>
            <div class="dashboard-kpi-card__content">
                <p class="dashboard-kpi-card__label">Entregas en 7 días</p>
                <p class="dashboard-kpi-card__value"><?php echo $proximos; ?></p>
                <small class="dashboard-kpi-card__hint"><?php echo $atrasados; ?> atrasado(s) · <?php echo $sinFecha; ?> sin fecha</small>
            </div>
        </article>
        <article class="dashboard-kpi-card dashboard-kpi-card--amber">
            <div class="dashboard-kpi-card__icon" aria-hidden="true"><i class="fas fa-boxes"></i></div>
            <div class="dashboard-kpi-card__content">
                <p class="dashboard-kpi-card__label">Insumos bajo mínimo</p>
                <p class="dashboard-kpi-card__value"><?php echo $insumosBajo; ?></p>
                <small class="dashboard-kpi-card__hint">Según el mínimo configurado</small>
            </div>
        </article>
        <article class="dashboard-kpi-card dashboard-kpi-card--red">
            <div class="dashboard-kpi-card__icon" aria-hidden="true"><i class="fas fa-shopping-basket"></i></div>
            <div class="dashboard-kpi-card__content">
                <p class="dashboard-kpi-card__label">Insumos a comprar</p>
                <p class="dashboard-kpi-card__value"><?php echo $insumosPorComprar; ?></p>
                <small class="dashboard-kpi-card__hint">Faltantes estimados para pedidos en curso</small>
            </div>
        </article>
    </section>

    <?php if ($productosSinReceta > 0): ?>
        <div class="alert alert-warning" role="alert">
            <i class="fas fa-exclamation-triangle"></i>
            Hay <strong><?php echo $productosSinReceta; ?> producto(s)</strong> en pedidos confirmados o en producción sin receta configurada en <code>producto_insumo</code>.
            La planificación de materiales puede quedar incompleta. Revisá sus recetas antes de generar la lista de compras.
        </div>
    <?php endif; ?>

    <!-- Accesos rápidos según permisos -->
    <div class="dashboard-panel dashboard-no-print">
        <div class="dashboard-panel__header">
            <h3><i class="fas fa-bolt"></i> Accesos rápidos</h3>
        </div>
        <div class="dashboard-panel__body">
            <?php
            $hayAccesos = false;
            foreach ($accesos as $acceso):
                if (!dashboardAccesoPermitido($acceso[3], $linksPermitidos)) {
                    continue;
                }
                $hayAccesos = true;
                $esAccionImprimir = ($acceso[2] === '@print');
                $href = $esAccionImprimir ? '' : (($acceso[2] !== '' && $acceso[2][0] === '#') ? $acceso[2] : BASE_URL . $acceso[2]);
            ?>
                <?php if ($esAccionImprimir): ?>
                    <button type="button" class="btn btn-<?php echo dashboardH($acceso[4]); ?> dashboard-shortcut" onclick="imprimirFaltantes()">
                        <i class="fas <?php echo dashboardH($acceso[1]); ?>"></i>
                        <?php echo dashboardH($acceso[0]); ?>
                    </button>
                <?php else: ?>
                    <a class="btn btn-<?php echo dashboardH($acceso[4]); ?> dashboard-shortcut" href="<?php echo dashboardH($href); ?>">
                        <i class="fas <?php echo dashboardH($acceso[1]); ?>"></i>
                        <?php echo dashboardH($acceso[0]); ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (!$hayAccesos): ?>
                <p class="dashboard-muted mb-0">No hay accesos rápidos disponibles para tu usuario.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <!-- Pedidos que requieren atención -->
        <div class="col-xl-8">
            <section class="dashboard-panel dashboard-no-print">
                <div class="dashboard-panel__header">
                    <div>
                        <h3><i class="fas fa-calendar-check"></i> Pedidos que requieren atención</h3>
                        <small class="dashboard-muted">Ordenados por fecha de entrega; primero los más próximos.</small>
                    </div>
                    <?php if (!empty($linksPermitidos[$RUTA_PEDIDOS])): ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo dashboardH(BASE_URL . $RUTA_PEDIDOS); ?>">Ver todos</a>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover dashboard-table">
                        <thead>
                            <tr>
                                <th>Pedido / Cliente</th>
                                <th>Entrega</th>
                                <th>Estado</th>
                                <th>Saldo estimado</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($pedidosAtencion)): ?>
                            <tr><td colspan="4" class="dashboard-empty">No hay pedidos pendientes para mostrar.</td></tr>
                        <?php else: foreach ($pedidosAtencion as $pedido):
                            $entrega = dashboardEtiquetaEntrega($pedido['fecha_entrega_estimada'] ?? null);
                            $saldoEstimado = max(0, (float)$pedido['total'] - (float)$pedido['sena_pagada']);
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo dashboardH($pedido['numero_pedido']); ?></strong><br>
                                    <span><?php echo dashboardH(trim($pedido['nombre_cliente'])); ?></span>
                                    <?php if (!empty($pedido['telefono_cliente'])): ?><br><small class="dashboard-muted"><i class="fas fa-phone"></i> <?php echo dashboardH($pedido['telefono_cliente']); ?></small><?php endif; ?>
                                </td>
                                <td>
                                    <?php echo dashboardH(dashboardFecha($pedido['fecha_entrega_estimada'] ?? null)); ?><br>
                                    <span class="dashboard-badge <?php echo dashboardH($entrega[1]); ?>"><?php echo dashboardH($entrega[0]); ?></span>
                                </td>
                                <td><span class="dashboard-badge <?php echo dashboardH(dashboardClaseEstado((string)$pedido['estado'])); ?>"><?php echo dashboardH(dashboardEstadoEtiqueta((string)$pedido['estado'])); ?></span></td>
                                <td><?php echo dashboardGs($saldoEstimado); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Stock bajo -->
        <div class="col-xl-4">
            <section class="dashboard-panel dashboard-no-print">
                <div class="dashboard-panel__header">
                    <h3><i class="fas fa-exclamation-triangle"></i> Stock bajo</h3>
                    <?php if (!empty($linksPermitidos[$RUTA_INSUMOS])): ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo dashboardH(BASE_URL . $RUTA_INSUMOS); ?>">Ver insumos</a>
                    <?php endif; ?>
                </div>
                <div class="dashboard-panel__body">
                    <?php if (empty($listaStockBajo)): ?>
                        <p class="dashboard-muted mb-0">No hay insumos activos por debajo del mínimo configurado.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm dashboard-table">
                                <thead><tr><th>Insumo</th><th>Actual</th><th>Mínimo</th></tr></thead>
                                <tbody>
                                <?php foreach ($listaStockBajo as $insumo): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo dashboardH($insumo['nombre']); ?></strong>
                                        </td>
                                        <td class="dashboard-stock-low"><?php echo dashboardH(dashboardNumero($insumo['stock_actual'])); ?></td>
                                        <td><?php echo dashboardH(dashboardNumero($insumo['stock_minimo'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>

    <!-- Faltantes estimados para planificación de compras -->
    <section class="dashboard-panel dashboard-no-print" id="faltantes">
        <div class="dashboard-panel__header">
            <div>
                <h3><i class="fas fa-cart-arrow-down"></i> Planificación de materiales y compras</h3>
                <small class="dashboard-muted">Necesidad pendiente estimada − stock actual. No reserva ni descuenta materiales.</small>
            </div>
            <div>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="imprimirFaltantes()">
                    <i class="fas fa-print"></i> Imprimir faltantes
                </button>
                <?php if (!empty($linksPermitidos[$RUTA_COMPRAS])): ?>
                    <a class="btn btn-sm btn-primary" href="<?php echo dashboardH(BASE_URL . $RUTA_COMPRAS); ?>"><i class="fas fa-shopping-cart"></i> Ir a compras</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="dashboard-panel__body">
            <p class="small dashboard-muted">Se consideran pedidos <strong>confirmados</strong> y <strong>en producción</strong>. Los presupuestos todavía no confirmados no se incluyen. El consumo registrado en <code>pedido_insumo</code> se descuenta de la estimación por pedido. La columna de pedidos/clientes indica qué trabajos generan la necesidad de cada material; el stock se compara una sola vez contra la necesidad total acumulada.</p>
            <?php if (empty($listaFaltantes)): ?>
                <div class="dashboard-empty"><i class="fas fa-check-circle"></i> No se detectan faltantes estimados para los pedidos confirmados/en producción.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover dashboard-table">
                        <thead>
                            <tr><th>Insumo</th><th>Pedidos / clientes que lo requieren</th><th>Stock actual</th><th>Necesidad pendiente</th><th>Faltante estimado</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($listaFaltantes as $faltante): ?>
                            <tr>
                                <td><strong><?php echo dashboardH($faltante['nombre']); ?></strong></td>
                                <td class="dashboard-order-list"><?php echo nl2br(dashboardH($faltante['pedidos_clientes'] ?? '—')); ?></td>
                                <td><?php echo dashboardH(dashboardNumero($faltante['stock_actual'])); ?></td>
                                <td><?php echo dashboardH(dashboardNumero($faltante['cantidad_necesaria'])); ?></td>
                                <td><span class="dashboard-badge badge-soft-danger"><?php echo dashboardH(dashboardNumero($faltante['faltante'])); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <small class="dashboard-muted">Las cantidades se expresan en la unidad configurada para cada insumo. La lista es una ayuda de planificación; verificá medidas, variantes y diseños antes de comprar.</small>
            <?php endif; ?>
        </div>
    </section>

    <!-- Área exclusiva para impresión -->
    <section id="reporte-faltantes-print">
        <h1>Lista de materiales faltantes para compra</h1>
        <p>Emprendimiento de sublimación · Generado el <?php echo dashboardH($hoy); ?></p>
        <p>Estimación de materiales pendientes para pedidos confirmados/en producción. Verificar cantidades y unidades antes de realizar la compra.</p>
        <table>
            <thead><tr><th>Insumo</th><th>Pedidos / clientes que lo requieren</th><th>Stock actual</th><th>Necesidad pendiente</th><th>Faltante estimado</th><th>Cantidad a comprar / observaciones</th></tr></thead>
            <tbody>
            <?php if (empty($listaFaltantes)): ?>
                <tr><td colspan="6">No se detectan faltantes estimados.</td></tr>
            <?php else: foreach ($listaFaltantes as $faltante): ?>
                <tr>
                    <td><?php echo dashboardH($faltante['nombre']); ?></td>
                    <td><?php echo nl2br(dashboardH($faltante['pedidos_clientes'] ?? '—')); ?></td>
                    <td><?php echo dashboardH(dashboardNumero($faltante['stock_actual'])); ?></td>
                    <td><?php echo dashboardH(dashboardNumero($faltante['cantidad_necesaria'])); ?></td>
                    <td><?php echo dashboardH(dashboardNumero($faltante['faltante'])); ?></td>
                    <td></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        <p style="margin-top:25px">Proveedor seleccionado: _________________________________</p>
        <p>Observaciones: __________________________________________________________________</p>
    </section>

    <!-- Auditoría visual / últimos movimientos -->
    <section class="dashboard-panel dashboard-no-print">
        <div class="dashboard-panel__header">
            <div>
                <h3><i class="fas fa-history"></i> Últimos movimientos de stock</h3>
                <small class="dashboard-muted">Consulta rápida del historial; el dashboard no edita movimientos.</small>
            </div>
            <?php if (!empty($linksPermitidos[$RUTA_MOVIMIENTOS])): ?>
                <a class="btn btn-sm btn-outline-primary" href="<?php echo dashboardH(BASE_URL . $RUTA_MOVIMIENTOS); ?>">Ver historial</a>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-hover dashboard-table">
                <thead><tr><th>Fecha</th><th>Insumo</th><th>Tipo</th><th>Cantidad</th><th>Stock anterior</th><th>Stock nuevo</th></tr></thead>
                <tbody>
                <?php if (empty($ultimosMovimientos)): ?>
                    <tr><td colspan="6" class="dashboard-empty">Todavía no hay movimientos para mostrar.</td></tr>
                <?php else: foreach ($ultimosMovimientos as $mov): ?>
                    <tr>
                        <td><?php echo dashboardH(dashboardFecha($mov['fecha'] ?? null)); ?></td>
                        <td><?php echo dashboardH($mov['nombre_insumo']); ?></td>
                        <td><?php echo dashboardH(str_replace('_', ' ', (string)$mov['tipo'])); ?></td>
                        <td><?php echo dashboardH(dashboardNumero($mov['cantidad'])); ?></td>
                        <td><?php echo dashboardH(dashboardNumero($mov['stock_anterior'])); ?></td>
                        <td><?php echo dashboardH(dashboardNumero($mov['stock_nuevo'])); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Se conserva el contenedor utilizado por posibles cargas AJAX preexistentes. -->
    <div class="row" id="cargainfo"></div>
</main>

<script>
/**
 * Abre una ventana de impresión independiente. Así no dependemos de las reglas
 * @media print del layout principal (que incluye menú, cabecera y footer).
 * El contenido se obtiene de una sección ya escapada por PHP.
 */
function imprimirFaltantes() {
    const reporte = document.getElementById('reporte-faltantes-print');
    if (!reporte) {
        window.alert('No se encontró el reporte de faltantes para imprimir.');
        return;
    }

    const ventana = window.open('', '_blank', 'width=1000,height=750');
    if (!ventana) {
        window.alert('El navegador bloqueó la ventana de impresión. Permití las ventanas emergentes para este sitio e intentá nuevamente.');
        return;
    }

    const estilos = `
        @page { size: A4 landscape; margin: 12mm; }
        * { box-sizing: border-box; }
        body { color:#111; background:#fff; font-family:Arial,Helvetica,sans-serif; font-size:12px; }
        h1 { font-size:20px; margin:0 0 6px; }
        p { margin:0 0 10px; line-height:1.45; }
        table { width:100%; border-collapse:collapse; margin-top:16px; }
        th, td { border:1px solid #777; padding:7px; text-align:left; vertical-align:top; }
        th { background:#eee; font-weight:700; }
        td { white-space:normal; }
        .print-note { margin-top:20px; }
        @media print { body { print-color-adjust:exact; -webkit-print-color-adjust:exact; } }
    `;

    let impresionIniciada = false;
    const iniciarImpresion = function () {
        if (impresionIniciada || ventana.closed) return;
        impresionIniciada = true;
        ventana.focus();
        ventana.print();
    };

    // Registrar los eventos antes de cerrar el documento para no perder el evento load.
    ventana.onload = iniciarImpresion;
    ventana.document.open();
    ventana.document.write('<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Lista de materiales faltantes</title><style>' + estilos + '</style></head><body>' + reporte.innerHTML + '</body></html>');
    ventana.document.close();

    // Respaldo para navegadores donde load no se dispare con document.write.
    window.setTimeout(iniciarImpresion, 700);
}
</script>

<?php include_once dirname(__DIR__) . '/portada/footer.php'; ?>
