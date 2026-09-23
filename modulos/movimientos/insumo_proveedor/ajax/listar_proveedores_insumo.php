<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/movimientos/insumo_proveedor/insumo_proveedor.php');

header('Content-Type: application/json; charset=utf-8');

$idInsumo = intval($_GET['id_insumo'] ?? 0);
if ($idInsumo <= 0) {
    echo json_encode(['error' => 'Insumo inválido']);
    exit;
}

$etiquetasCalidad = [1 => 'Baja', 2 => 'Media', 3 => 'Alta', 4 => 'Premium'];

$sql = "SELECT ip.id_insumo_proveedor, ip.id_proveedor, ip.precio, ip.calidad, ip.fecha_cotizacion,
               ip.tiempo_entrega, ip.favorito, ip.activo,
               p.ruc AS ruc_proveedor, p.razon_social AS nombre_proveedor
        FROM insumo_proveedor ip
        INNER JOIN proveedores p ON p.id_proveedor = ip.id_proveedor
        WHERE ip.id_insumo = :id_insumo
        ORDER BY ip.favorito DESC, p.razon_social ASC";
$st = $conexion->prepare($sql);
$st->execute(['id_insumo' => $idInsumo]);
$relaciones = $st->fetchAll(PDO::FETCH_ASSOC);

foreach ($relaciones as &$r) {
    $nombreProveedor = htmlspecialchars($r['nombre_proveedor'] . ($r['ruc_proveedor'] ? ' (' . $r['ruc_proveedor'] . ')' : ''));

    // Valores originales, sin formatear (van en los data-* para precargar el form al editar)
    $precioOriginal = $r['precio'];
    $calidadOriginal = $r['calidad'];
    $entregaOriginal = $r['tiempo_entrega'];
    $favoritoOriginal = $r['favorito'];

    // Valores formateados, solo para mostrar en la tabla
    $precioSinCeros = rtrim(rtrim(number_format((float) $precioOriginal, 2, '.', ''), '0'), '.');
    $precioFormateado = 'Gs. ' . number_format((float) $precioSinCeros, 0, '', '.');
    if (strpos($precioSinCeros, '.') !== false) {
        // Si quedaron centavos reales (ej: 25.50), los mostramos aparte
        $partes = explode('.', $precioSinCeros);
        $precioFormateado = 'Gs. ' . number_format((float) $partes[0], 0, '', '.') . ',' . $partes[1];
    }
    $calidadTexto = $calidadOriginal !== null ? ($etiquetasCalidad[(int) $calidadOriginal] ?? '-') : '-';
    $entregaTexto = $entregaOriginal !== null ? $entregaOriginal . ' día(s)' : '-';
    $favoritoTexto = $favoritoOriginal ? '<i class="fas fa-star text-warning"></i>' : '';
    $estadoTexto = $r['activo'] ? 'Desactivar' : 'Activar';
    $claseBtn = $r['activo'] ? 'btn-outline-danger' : 'btn-outline-success';
    $icono = $r['activo'] ? 'fa-times' : 'fa-check';

    $r['nombre_proveedor'] = $nombreProveedor;
    $r['precio'] = $precioFormateado;
    $r['calidad'] = $calidadTexto;
    $r['tiempo_entrega'] = $entregaTexto;
    $r['favorito'] = $favoritoTexto;
    $r['acciones'] = '<button class="btn btn-sm btn-outline-secondary btn-editar" data-id="' . $r['id_insumo_proveedor'] . '" data-id-proveedor="' . $r['id_proveedor'] . '" data-precio="' . $precioOriginal . '" data-calidad="' . ($calidadOriginal ?? '') . '" data-fecha="' . $r['fecha_cotizacion'] . '" data-entrega="' . ($entregaOriginal ?? '') . '" data-favorito="' . ($favoritoOriginal ? '1' : '0') . '"><i class="fas fa-edit"></i></button> '
        . '<button class="btn btn-sm ' . $claseBtn . ' btn-cambiar-estado" data-id="' . $r['id_insumo_proveedor'] . '" data-nombre="' . $nombreProveedor . '"><i class="fas ' . $icono . '"></i> ' . $estadoTexto . '</button>';
}

echo json_encode(['data' => $relaciones]);