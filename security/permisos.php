<?php
require_once dirname(__DIR__) . '/config/constantes.php';
function tieneAcceso(PDO $pdo, ?string $link = null): bool
{
    if ($link === null) {
        $link = $_SERVER['PHP_SELF'];
    }

    // Sacamos el prefijo de carpeta base para comparar contra las
    // rutas "limpias" que guardamos en la base (ej: /portada/portada.php)
    if (strpos($link, BASE_URL) === 0) {
        $link = substr($link, strlen(BASE_URL));
    }

    $sqlDirecto = "SELECT 1 FROM menu WHERE menu_link = :link";
    $stD = $pdo->prepare($sqlDirecto);
    $stD->execute(['link' => $link]);
    if ($stD->rowCount() > 0) {
        return true;
    }

    $sql = "SELECT 1
            FROM menu_sistema ms
            JOIN sub_menu sm ON sm.sub_menu_codigo = ms.sub_menu_codigo
            WHERE ms.usu_sis_codigo = :usuario
              AND sm.sub_menu_link = :link
              AND sm.submenu_estado = TRUE";

    $st = $pdo->prepare($sql);
    $st->execute([
        'usuario' => $_SESSION['id_usuario'],
        'link'    => $link
    ]);

    return $st->rowCount() > 0;
}


function exigirAcceso(PDO $pdo, ?string $link = null): void
{
    if (!tieneAcceso($pdo, $link)) {
        header('Location: /security/404.php');
        exit;
    }
}

/**
 * obtenerMenuUsuario()
 * Devuelve un array con el menú completo al que tiene acceso el usuario
 * logueado, para pintar el sidebar dinámicamente en vez de tenerlo
 * hardcodeado en el HTML.
 *
 * CONTEMPLA DOS CASOS:
 * 1. Menú "contenedor" (menu_link = NULL): agrupa sub_menu hijos.
 *    Ej: "Stock" agrupa a "Insumos" y "Proveedores".
 * 2. Menú de un solo nivel (menu_link con valor): es clickeable
 *    directo, sin hijos. Ej: "Dashboard".
 *
 * ESTRUCTURA DEL RESULTADO:
 * [
 *   ['titulo' => 'Dashboard', 'icono' => 'fa-gauge', 'link' => '/dashboard.php', 'items' => []],
 *   ['titulo' => 'Stock', 'icono' => 'fa-boxes', 'link' => null, 'items' => [
 *       ['titulo' => 'Insumos', 'link' => '/modulos/stock/insumos.php'],
 *       ...
 *   ]],
 * ]
 *
 * NOTA IMPORTANTE: los menús de un solo nivel (caso 2) hoy se muestran
 * para CUALQUIER usuario logueado, porque el permiso vive en
 * menu_sistema atado a sub_menu_codigo, no a menu_codigo. Si más
 * adelante necesitás restringir el Dashboard mismo por usuario, hay
 * que sumarle una fila "espejo" en sub_menu o extender menu_sistema
 * para que también pueda referenciar menu_codigo directamente.
 *
 * @param PDO $pdo
 * @param int $idUsuario
 * @return array
 */
function obtenerMenuUsuario(PDO $pdo, int $idUsuario): array
{
    // 1. Menús de un solo nivel (tienen su propio link, no necesitan permiso por sub_menu)
    $sqlDirectos = "SELECT menu_codigo, menu_titulo, menu_link, icono
                    FROM menu
                    WHERE menu_link IS NOT NULL
                    ORDER BY menu_codigo";
    $stD = $pdo->prepare($sqlDirectos);
    $stD->execute();
    $directos = $stD->fetchAll(PDO::FETCH_ASSOC);

    // 2. Menús contenedores + sus sub_menu, filtrados por permiso del usuario
    $sqlContenedores = "SELECT m.menu_codigo, m.menu_titulo, m.icono,
                                sm.sub_menu_titulo, sm.sub_menu_link
                         FROM menu_sistema ms
                         JOIN sub_menu sm ON sm.sub_menu_codigo = ms.sub_menu_codigo
                         JOIN menu m ON m.menu_codigo = sm.menu_codigo
                         WHERE ms.usu_sis_codigo = :usuario
                           AND sm.submenu_estado = TRUE
                           AND sm.visible = TRUE
                         ORDER BY m.menu_codigo, sm.sub_menu_orden";
    $stC = $pdo->prepare($sqlContenedores);
    $stC->execute(['usuario' => $idUsuario]);
    $filas = $stC->fetchAll(PDO::FETCH_ASSOC);

    $menu = [];

    // Armamos primero los menús directos (Dashboard, etc.)
    foreach ($directos as $fila) {
        $menu[$fila['menu_codigo']] = [
            'titulo' => $fila['menu_titulo'],
            'icono'  => $fila['icono'],
            'link'   => $fila['menu_link'],
            'items'  => []
        ];
    }

    // Agrupamos las filas planas de contenedores en la estructura de árbol
    foreach ($filas as $fila) {
        $codigo = $fila['menu_codigo'];
        if (!isset($menu[$codigo])) {
            $menu[$codigo] = [
                'titulo' => $fila['menu_titulo'],
                'icono'  => $fila['icono'],
                'link'   => null,
                'items'  => []
            ];
        }
        $menu[$codigo]['items'][] = [
            'titulo' => $fila['sub_menu_titulo'],
            'link'   => $fila['sub_menu_link']
        ];
    }

    return array_values($menu); // reindexamos para que sea un array limpio
}