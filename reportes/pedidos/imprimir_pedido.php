
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/sublimacion/config/constantes.php';
require_once RUTA_PROYECTO . '/security/verificacion_sesion.php';
require_once RUTA_PROYECTO . '/config/conexion.php';
require_once RUTA_PROYECTO . '/security/permisos.php';

exigirAcceso($conexion, '/modulos/movimientos/pedidos/pedidos.php');

$idPedido = intval($_GET['id_pedido'] ?? 0);

if ($idPedido <= 0) {
    die('Pedido inválido.');
}

/* =========================================================
   DATOS DEL PEDIDO 
   ========================================================= */

$st = $conexion->prepare("
    SELECT 
        p.*,
        c.nombre,
        c.apellido,
        c.numero_documento
    FROM pedidos p
    INNER JOIN clientes c 
        ON c.id_cliente = p.id_cliente
    WHERE p.id_pedido = :id
");

$st->execute(['id' => $idPedido]);

$pedido = $st->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    die('El pedido no existe.');
}

/* =========================================================
   DETALLE DEL PEDIDO
   ========================================================= */

$stLineas = $conexion->prepare("
    SELECT 
        pr.codigo,
        pr.nombre,
        pd.cantidad,
        pd.precio_unitario,
        pd.subtotal
    FROM pedido_detalle pd
    INNER JOIN productos pr 
        ON pr.id_producto = pd.id_producto
    WHERE pd.id_pedido = :id
    ORDER BY pd.id_detalle ASC
");

$stLineas->execute(['id' => $idPedido]);

$lineas = $stLineas->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   ESTADOS
   ========================================================= */

$etiquetasEstado = [
    'presupuestado' => 'Presupuestado',
    'confirmado'    => 'Confirmado',
    'en_produccion' => 'En Producción',
    'listo'         => 'Listo',
    'entregado'     => 'Entregado',
    'cancelado'     => 'Cancelado',
];

/* =========================================================
   FUNCIONES
   ========================================================= */

function gs($valor)
{
    return 'Gs. ' . number_format((float) $valor, 0, '', '.');
}

function cant($valor)
{
    $t = rtrim(
        rtrim(
            number_format((float) $valor, 2, ',', '.'),
            '0'
        ),
        ','
    );

    return $t === '' ? '0' : $t;
}

function fechaLatina($f)
{
    return $f
        ? date('d/m/Y', strtotime($f))
        : '-';
}

/* =========================================================
   TOTALES
   ========================================================= */

$total = (float) $pedido['total'];
$sena  = (float) $pedido['sena_pagada'];
$saldo = $total - $sena;

$estado = $pedido['estado'] ?? '';

$estadoTexto = $etiquetasEstado[$estado] ?? $estado;

/*
 * Clase visual según estado
 */
$estadoClase = 'estado-default';

switch ($estado) {

    case 'presupuestado':
        $estadoClase = 'estado-presupuestado';
        break;

    case 'confirmado':
        $estadoClase = 'estado-confirmado';
        break;

    case 'en_produccion':
        $estadoClase = 'estado-produccion';
        break;

    case 'listo':
        $estadoClase = 'estado-listo';
        break;

    case 'entregado':
        $estadoClase = 'estado-entregado';
        break;

    case 'cancelado':
        $estadoClase = 'estado-cancelado';
        break;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Pedido <?php echo htmlspecialchars($pedido['numero_pedido']); ?>
    </title>

    <style>

        /* =====================================================
           CONFIGURACIÓN GENERAL
           ===================================================== */

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            background: #f1f3f5;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            color: #212529;
            font-size: 13px;
        }

        .documento {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            padding: 40px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        /* =====================================================
           BOTONES
           ===================================================== */

        .acciones {
            max-width: 900px;
            margin: 0 auto 15px auto;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .btn {
            border: none;
            padding: 9px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
        }

        .btn-imprimir {
            background: #212529;
            color: #ffffff;
        }

        .btn-cerrar {
            background: #ffffff;
            color: #495057;
            border: 1px solid #ced4da;
        }

        .btn:hover {
            opacity: 0.9;
        }

        /* =====================================================
           ENCABEZADO
           ===================================================== */

        .encabezado {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 25px;
            border-bottom: 2px solid #212529;
        }

        .titulo {
            margin: 0;
            font-size: 27px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .numero-pedido {
            margin-top: 5px;
            color: #6c757d;
            font-size: 13px;
        }

        .estado-contenedor {
            text-align: right;
        }

        .estado-label {
            display: block;
            margin-bottom: 6px;
            color: #6c757d;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .estado {
            display: inline-block;
            padding: 7px 13px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        /* Estados */

        .estado-presupuestado {
            background: #fff3cd;
            color: #856404;
        }

        .estado-confirmado {
            background: #cff4fc;
            color: #055160;
        }

        .estado-produccion {
            background: #e2d9f3;
            color: #432874;
        }

        .estado-listo {
            background: #d1e7dd;
            color: #0f5132;
        }

        .estado-entregado {
            background: #d1e7dd;
            color: #0f5132;
        }

        .estado-cancelado {
            background: #f8d7da;
            color: #842029;
        }

        .estado-default {
            background: #e9ecef;
            color: #495057;
        }

        /* =====================================================
           INFORMACIÓN
           ===================================================== */

        .seccion {
            margin-top: 25px;
        }

        .seccion-titulo {
            margin: 0 0 12px 0;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: #6c757d;
            font-weight: 700;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .info-box {
            border: 1px solid #dee2e6;
            border-radius: 7px;
            padding: 15px;
            background: #fafafa;
        }

        .info-item {
            margin-bottom: 10px;
        }

        .info-item:last-child {
            margin-bottom: 0;
        }

        .info-label {
            display: block;
            color: #6c757d;
            font-size: 11px;
            margin-bottom: 3px;
            text-transform: uppercase;
        }

        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: #212529;
        }

        /* =====================================================
           TABLA DE PRODUCTOS
           ===================================================== */

        .tabla-contenedor {
            margin-top: 10px;
            border: 1px solid #dee2e6;
            border-radius: 7px;
            overflow: hidden;
        }

        table.detalle {
            width: 100%;
            border-collapse: collapse;
        }

        table.detalle th {
            background: #f8f9fa;
            color: #495057;
            padding: 11px 10px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border-bottom: 1px solid #dee2e6;
            text-align: left;
        }

        table.detalle td {
            padding: 12px 10px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }

        table.detalle tbody tr:last-child td {
            border-bottom: none;
        }

        table.detalle tbody tr:hover {
            background: #fafafa;
        }

        .codigo {
            color: #6c757d;
            font-size: 12px;
        }

        .producto {
            font-weight: 600;
        }

        .der {
            text-align: right !important;
        }

        /* =====================================================
           RESUMEN
           ===================================================== */

        .resumen-contenedor {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .totales {
            width: 330px;
            border-collapse: collapse;
        }

        .totales td {
            padding: 7px 0;
            font-size: 13px;
        }

        .totales .etiqueta {
            color: #6c757d;
        }

        .totales .valor {
            text-align: right;
            font-weight: 600;
        }

        .totales .total {
            border-top: 2px solid #212529;
            padding-top: 12px;
            font-size: 17px;
            font-weight: 700;
        }

        .totales .saldo {
            background: #f8f9fa;
        }

        .totales .saldo td {
            padding: 12px 10px;
            font-size: 15px;
            font-weight: 700;
        }

        /* =====================================================
           OBSERVACIONES
           ===================================================== */

        .observaciones {
            margin-top: 25px;
            border: 1px solid #dee2e6;
            border-left: 4px solid #495057;
            border-radius: 5px;
            padding: 14px 16px;
            background: #fafafa;
        }

        .observaciones-titulo {
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: #495057;
        }

        .observaciones-texto {
            color: #495057;
            line-height: 1.5;
        }

        /* =====================================================
           FIRMAS
           ===================================================== */

        .firmas {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 100px;
            margin-top: 80px;
        }

        .firma {
            text-align: center;
        }

        .firma-linea {
            border-top: 1px solid #343a40;
            padding-top: 7px;
            font-size: 12px;
            color: #495057;
        }

        /* =====================================================
           PIE
           ===================================================== */

        .pie {
            margin-top: 35px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            color: #adb5bd;
            font-size: 10px;
        }

        /* =====================================================
           IMPRESIÓN
           ===================================================== */

        @media print {

            @page {
                size: A4;
                margin: 12mm;
            }

            body {
                padding: 0;
                background: #ffffff;
            }

            .acciones {
                display: none;
            }

            .documento {
                max-width: none;
                width: 100%;
                padding: 0;
                box-shadow: none;
            }

            table.detalle tbody tr:hover {
                background: transparent;
            }

            .observaciones {
                break-inside: avoid;
            }

            .firmas {
                break-inside: avoid;
            }

        }

        /* =====================================================
           RESPONSIVE
           ===================================================== */

        @media screen and (max-width: 700px) {

            body {
                padding: 10px;
            }

            .documento {
                padding: 20px;
            }

            .encabezado {
                flex-direction: column;
                gap: 15px;
            }

            .estado-contenedor {
                text-align: left;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .tabla-contenedor {
                overflow-x: auto;
            }

            table.detalle {
                min-width: 650px;
            }

            .resumen-contenedor {
                justify-content: stretch;
            }

            .totales {
                width: 100%;
            }

            .firmas {
                grid-template-columns: 1fr;
                gap: 50px;
            }

        }

    </style>

</head>

<body>

    <!-- =====================================================
         ACCIONES
         ===================================================== -->

    <div class="acciones">

        <button
            type="button"
            class="btn btn-imprimir"
            onclick="window.print()"
        >
            🖨 Imprimir
        </button>

        <button
            type="button"
            class="btn btn-cerrar"
            onclick="window.close()"
        >
            Cerrar
        </button>

    </div>


    <!-- =====================================================
         DOCUMENTO
         ===================================================== -->

    <main class="documento">


        <!-- =================================================
             ENCABEZADO
             ================================================= -->

        <header class="encabezado">

            <div>

                <h1 class="titulo">
                    PEDIDO
                </h1>

                <div class="numero-pedido">
                    N.º
                    <strong>
                        <?php echo htmlspecialchars($pedido['numero_pedido']); ?>
                    </strong>
                </div>

            </div>


            <div class="estado-contenedor">

                <span class="estado-label">
                    Estado del pedido
                </span>

                <span class="estado <?php echo $estadoClase; ?>">
                    <?php echo htmlspecialchars($estadoTexto); ?>
                </span>

            </div>

        </header>


        <!-- =================================================
             INFORMACIÓN DEL PEDIDO
             ================================================= -->

        <section class="seccion">

            <h2 class="seccion-titulo">
                Información del pedido
            </h2>

            <div class="info-grid">


                <!-- Cliente -->

                <div class="info-box">

                    <div class="info-item">

                        <span class="info-label">
                            Cliente
                        </span>

                        <span class="info-value">

                            <?php
                            echo htmlspecialchars(
                                $pedido['nombre'] . ' ' . $pedido['apellido']
                            );
                            ?>

                        </span>

                    </div>


                    <div class="info-item">

                        <span class="info-label">
                            Documento
                        </span>

                        <span class="info-value">

                            <?php
                            echo htmlspecialchars(
                                $pedido['numero_documento'] ?? '-'
                            );
                            ?>

                        </span>

                    </div>

                </div>


                <!-- Fechas -->

                <div class="info-box">

                    <div class="info-item">

                        <span class="info-label">
                            Fecha del pedido
                        </span>

                        <span class="info-value">

                            <?php
                            echo fechaLatina(
                                $pedido['fecha_pedido']
                            );
                            ?>

                        </span>

                    </div>


                    <div class="info-item">

                        <span class="info-label">
                            Entrega estimada
                        </span>

                        <span class="info-value">

                            <?php
                            echo fechaLatina(
                                $pedido['fecha_entrega_estimada']
                            );
                            ?>

                        </span>

                    </div>


                    <div class="info-item">

                        <span class="info-label">
                            Entrega real
                        </span>

                        <span class="info-value">

                            <?php
                            echo fechaLatina(
                                $pedido['fecha_entrega_real']
                            );
                            ?>

                        </span>

                    </div>

                </div>

            </div>

        </section>


        <!-- =================================================
             PRODUCTOS
             ================================================= -->

        <section class="seccion">

            <h2 class="seccion-titulo">
                Detalle del pedido
            </h2>


            <div class="tabla-contenedor">

                <table class="detalle">

                    <thead>

                        <tr>

                            <th>
                                Código
                            </th>

                            <th>
                                Producto
                            </th>

                            <th class="der">
                                Cant.
                            </th>

                            <th class="der">
                                Precio unit.
                            </th>

                            <th class="der">
                                Subtotal
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach ($lineas as $l): ?>

                            <tr>

                                <td class="codigo">

                                    <?php
                                    echo htmlspecialchars(
                                        $l['codigo']
                                    );
                                    ?>

                                </td>


                                <td class="producto">

                                    <?php
                                    echo htmlspecialchars(
                                        $l['nombre']
                                    );
                                    ?>

                                </td>


                                <td class="der">

                                    <?php
                                    echo cant(
                                        $l['cantidad']
                                    );
                                    ?>

                                </td>


                                <td class="der">

                                    <?php
                                    echo gs(
                                        $l['precio_unitario']
                                    );
                                    ?>

                                </td>


                                <td class="der">

                                    <?php
                                    echo gs(
                                        $l['subtotal']
                                    );
                                    ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- =================================================
             RESUMEN ECONÓMICO
             ================================================= -->

        <section class="resumen-contenedor">

            <table class="totales">

                <tr>

                    <td class="etiqueta">
                        Total
                    </td>

                    <td class="valor total">

                        <?php
                        echo gs($total);
                        ?>

                    </td>

                </tr>


                <tr>

                    <td class="etiqueta">
                        Seña pagada
                    </td>

                    <td class="valor">

                        <?php
                        echo gs($sena);
                        ?>

                    </td>

                </tr>


                <tr class="saldo">

                    <td>
                        SALDO PENDIENTE
                    </td>

                    <td class="valor">

                        <?php
                        echo gs($saldo);
                        ?>

                    </td>

                </tr>

            </table>

        </section>


        <!-- =================================================
             OBSERVACIONES
             ================================================= -->

        <?php if (!empty($pedido['observaciones'])): ?>

            <section class="observaciones">

                <div class="observaciones-titulo">
                    Observaciones
                </div>

                <div class="observaciones-texto">

                    <?php
                    echo nl2br(
                        htmlspecialchars(
                            $pedido['observaciones']
                        )
                    );
                    ?>

                </div>

            </section>

        <?php endif; ?>


        <!-- =================================================
             FIRMAS
             ================================================= -->

        <section class="firmas">

            <div class="firma">

                <div class="firma-linea">
                    Firma del cliente
                </div>

            </div>


            <div class="firma">

                <div class="firma-linea">
                    Firma responsable
                </div>

            </div>

        </section>


        <!-- =================================================
             PIE
             ================================================= -->

        <footer class="pie">

            Documento generado por el sistema de gestión.

        </footer>


    </main>

</body>

</html>
