<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

// Reutilizamos el permiso de compras: si puede ver compras, puede imprimir su reporte.
exigirAcceso($conexion, '/modulos/movimientos/compras/compras.php');

function formatearGs($valor)
{
    return 'Gs. ' . number_format((float) $valor, 0, '', '.');
}

$idCompra = intval($_GET['id_compra'] ?? 0);
if ($idCompra <= 0) {
    die('Compra no especificada.');
}

$sqlCompra = "SELECT c.numero_compra, c.fecha_compra, c.total, c.estado, c.observaciones,
                     p.razon_social AS nombre_proveedor, p.ruc, p.telefono, p.correo, p.direccion
              FROM compras c
              INNER JOIN proveedores p ON p.id_proveedor = c.id_proveedor
              WHERE c.id_compra = :id_compra";
$stCompra = $conexion->prepare($sqlCompra);
$stCompra->execute(['id_compra' => $idCompra]);
$compra = $stCompra->fetch(PDO::FETCH_ASSOC);

if (!$compra) {
    die('La compra no existe.');
}

$sqlLineas = "SELECT i.codigo, i.nombre AS nombre_insumo, u.nombre AS nombre_unidad,
                     dc.cantidad, dc.precio_unitario, dc.subtotal
              FROM detalle_compra dc
              INNER JOIN insumos i ON i.id_insumo = dc.id_insumo
              LEFT JOIN unidades_medida u ON u.id_unidad = i.id_unidad
              WHERE dc.id_compra = :id_compra
              ORDER BY dc.id_detalle_compra ASC";
$stLineas = $conexion->prepare($sqlLineas);
$stLineas->execute(['id_compra' => $idCompra]);
$lineas = $stLineas->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Compra <?php echo htmlspecialchars($compra['numero_compra']); ?></title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #222;
            margin: 30px;
            font-size: 14px;
        }
        .encabezado {
            display: flex;
            justify-content: space-between;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .encabezado h1 {
            font-size: 20px;
            margin: 0;
        }
        .encabezado .numero {
            font-size: 16px;
            font-weight: bold;
            text-align: right;
        }
        .datos-compra {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .datos-compra div {
            width: 48%;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #999;
            padding: 6px 8px;
            text-align: left;
        }
        th {
            background: #f0f0f0;
        }
        td.numero, th.numero {
            text-align: right;
        }
        .total-row td {
            font-weight: bold;
            background: #f7f7f7;
        }
        .estado {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 4px;
            font-weight: bold;
            color: #fff;
        }
        .estado.registrada { background: #2e7d32; }
        .estado.anulada { background: #757575; }
        .observaciones {
            margin-top: 10px;
        }
        .btn-imprimir {
            margin-bottom: 20px;
        }
        @media print {
            .btn-imprimir {
                display: none;
            }
            body {
                margin: 10mm;
            }
        }
    </style>
</head>
<body>

    <div class="btn-imprimir">
        <button onclick="window.print()">🖨️ Imprimir / Guardar como PDF</button>
    </div>

    <div class="encabezado">
        <h1>SUBLIMACIÓN<br><small style="font-weight:normal;">Reporte de Compra</small></h1>
        <div class="numero">
            <?php echo htmlspecialchars($compra['numero_compra']); ?><br>
            <span class="estado <?php echo strtolower($compra['estado']); ?>">
                <?php echo htmlspecialchars($compra['estado']); ?>
            </span>
        </div>
    </div>

    <div class="datos-compra">
        <div>
            <strong>Proveedor:</strong> <?php echo htmlspecialchars($compra['nombre_proveedor']); ?><br>
            <?php if ($compra['ruc']): ?>RUC: <?php echo htmlspecialchars($compra['ruc']); ?><br><?php endif; ?>
            <?php if ($compra['telefono']): ?>Tel: <?php echo htmlspecialchars($compra['telefono']); ?><br><?php endif; ?>
            <?php if ($compra['direccion']): ?>Dirección: <?php echo htmlspecialchars($compra['direccion']); ?><?php endif; ?>
        </div>
        <div style="text-align:right;">
            <strong>Fecha de Compra:</strong> <?php echo htmlspecialchars($compra['fecha_compra']); ?><br>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Insumo</th>
                <th>Unidad</th>
                <th class="numero">Cantidad</th>
                <th class="numero">Precio Unitario</th>
                <th class="numero">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lineas as $linea): ?>
                <tr>
                    <td><?php echo htmlspecialchars($linea['codigo']); ?></td>
                    <td><?php echo htmlspecialchars($linea['nombre_insumo']); ?></td>
                    <td><?php echo htmlspecialchars($linea['nombre_unidad'] ?? ''); ?></td>
                    <td class="numero"><?php echo rtrim(rtrim(number_format((float) $linea['cantidad'], 3, '.', ''), '0'), '.'); ?></td>
                    <td class="numero"><?php echo formatearGs($linea['precio_unitario']); ?></td>
                    <td class="numero"><?php echo formatearGs($linea['subtotal']); ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="5" style="text-align:right;">TOTAL</td>
                <td class="numero"><?php echo formatearGs($compra['total']); ?></td>
            </tr>
        </tbody>
    </table>

    <?php if ($compra['observaciones']): ?>
        <div class="observaciones">
            <strong>Observaciones:</strong> <?php echo nl2br(htmlspecialchars($compra['observaciones'])); ?>
        </div>
    <?php endif; ?>

</body>
</html>