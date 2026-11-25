<?php
// Funciones auxiliares del módulo Movimientos de Stock (solo se incluye, no se llama directo)

function clasesMovimiento()
{
    return [
        'COMPRA'           => ['Compra',              'bg-success'],
        'ANULACION_COMPRA' => ['Anulación de compra', 'bg-secondary'],
        'CONSUMO'          => ['Consumo',             'bg-warning text-dark'],
        'REPOSICION'       => ['Reposición',          'bg-info text-dark'],
        'AJUSTE_POSITIVO'  => ['Ajuste (+)',          'bg-primary'],
        'AJUSTE_NEGATIVO'  => ['Ajuste (-)',          'bg-dark'],
        'MERMA'            => ['Merma',               'bg-danger'],
    ];
}

function badgeClaseMovimiento($clase, $tipo)
{
    $clases = clasesMovimiento();
    if ($clase !== null && isset($clases[$clase])) {
        return '<span class="badge ' . $clases[$clase][1] . '">' . $clases[$clase][0] . '</span>';
    }
    // Movimientos sin clase (no deberían existir, pero por si acaso)
    return $tipo === 'entrada'
        ? '<span class="badge bg-success">Entrada</span>'
        : '<span class="badge bg-danger">Salida</span>';
}

function formatearStock($valor)
{
    // Hasta 3 decimales, pero sin los ceros que sobran: 12,000 -> 12 | 1,500 -> 1,5 | 0,250 -> 0,25
    $texto = number_format((float) $valor, 3, ',', '.');
    return rtrim(rtrim($texto, '0'), ',');
}

function formatearCantidadMovimiento($valor, $tipo)
{
    return ($tipo === 'entrada' ? '+' : '-') . formatearStock($valor);
}

function textoOrigenMovimiento($numeroCompra, $idPedido)
{
    if (!empty($numeroCompra)) {
        return htmlspecialchars($numeroCompra);
    }
    if (!empty($idPedido)) {
        return 'Pedido #' . (int) $idPedido;
    }
    return '—';
}

// Arma el WHERE y los parámetros a partir de los filtros (los usan el listado y la exportación)
function construirFiltrosMovimientos(array $origen)
{
    $idInsumo  = intval($origen['id_insumo'] ?? 0);
    $clase     = trim($origen['clase'] ?? '');
    $desde     = trim($origen['desde'] ?? '');
    $hasta     = trim($origen['hasta'] ?? '');
    $idCompra  = intval($origen['id_compra'] ?? 0);
    $idPedido  = intval($origen['id_pedido'] ?? 0);
    $idUsuario = intval($origen['id_usuario'] ?? 0);

    $where  = [];
    $params = [];

    if ($idInsumo > 0) {
        $where[] = 'm.id_insumo = :id_insumo';
        $params['id_insumo'] = $idInsumo;
    }
    if ($clase !== '' && isset(clasesMovimiento()[$clase])) {
        $where[] = 'm.clase = :clase';
        $params['clase'] = $clase;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
        $where[] = 'm.fecha >= CAST(:desde AS date)';
        $params['desde'] = $desde;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
        // "hasta" inclusivo: hasta el final de ese día
        $where[] = 'm.fecha < CAST(:hasta AS date) + 1';
        $params['hasta'] = $hasta;
    }
    if ($idCompra > 0) {
        $where[] = 'm.id_compra = :id_compra';
        $params['id_compra'] = $idCompra;
    }
    if ($idPedido > 0) {
        $where[] = 'm.id_pedido = :id_pedido';
        $params['id_pedido'] = $idPedido;
    }
    if ($idUsuario > 0) {
        $where[] = 'm.id_usuario = :id_usuario';
        $params['id_usuario'] = $idUsuario;
    }

    $whereSql = count($where) > 0 ? ' WHERE ' . implode(' AND ', $where) : '';
    return [$whereSql, $params];
}
