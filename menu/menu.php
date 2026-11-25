<body class="app">
    <header class="app-header">
        <a class="app-header__logo" href="<?php echo BASE_URL; ?>/portada/portada.php">
            <img src="<?php echo BASE_URL; ?>/imgs/logo_transparente5.png"
                 alt="Logo"
                 style="width: 50px;">
        </a>

        <a class="app-sidebar__toggle"
           data-toggle="sidebar"
           aria-label="Hide Sidebar"
           style="color: white">
            <i class="fas fa-bars fa-lg"></i>
        </a>

        <ul class="app-nav">
            <li class="app-search dropdown">
                <input class="app-search__input"
                       type="search"
                       placeholder="Buscar"
                       id="seachfilter"
                       onblur="buscar()"
                       minlength="5">

                <button class="app-search__button dropdown-toggle"
                        type="button"
                        role="button"
                        id="dropdownMenuLink3"
                        data-bs-toggle="dropdown"
                        aria-label="Resultados">
                </button>

                <ul class="dropdown-menu settings-menu dropdown-menu-right"
                    aria-labelledby="dropdownMenuLink3"
                    id="dropdownMenuLink2">
                </ul>
            </li>

            <li class="dropdown">
                <a class="app-nav__item dropdown-toggle"
                   id="dropdownMenuButton1"
                   data-bs-toggle="dropdown"
                   href="#"
                   aria-label="Open Profile Menu">
                    <i class="fa fa-user fa-lg"></i>
                </a>

                <ul class="dropdown-menu settings-menu dropdown-menu-right"
                    aria-labelledby="dropdownMenuButton1">

                    <li>
                        <a class="dropdown-item"
                           href="<?php echo BASE_URL; ?>/usuario_interno/perfil.php">
                            <i class="fa fa-user fa-lg"></i> Perfil
                        </a>
                    </li>

                    <li>
                        <a class="dropdown-item"
                           href="<?php echo BASE_URL; ?>/security/logout.php">
                            <i class="fas fa-sign-out-alt fa-lg"></i>
                            Cerrar sesión
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </header>

    <div class="app-sidebar__overlay" data-toggle="sidebar"></div>

    <aside class="app-sidebar">
        <div class="app-sidebar__user">
            <img class="app-sidebar__user-avatar"
                 src="<?php echo BASE_URL; ?>/imgs/eventos/happiness.png"
                 alt="Imagen de usuario"
                 style="width: 40px;">

            <div>
                <p class="app-sidebar__user-name">
                    <?php echo $_SESSION['nombre']; ?>
                </p>

                <p class="app-sidebar__user-designation">
                    <?php echo $_SESSION['rol']; ?>
                </p>
            </div>
        </div>

        <ul class="app-menu">
            <?php
            // Menú dinámico: cada usuario ve solo lo que tiene permitido
            // según la configuración de menu_sistema.
            $menuUsuario = obtenerMenuUsuario(
                $conexion,
                $_SESSION['id_usuario']
            );

            // Reportes que deben abrirse en una nueva pestaña.
            $reportesNuevaVentana = [
                'reportes/reporte_pedidos.php',
                'reportes/reporte_materiales.php',
                'reportes/reporte_inventario.php',
                'reportes/reporte_compras.php'
            ];

            foreach ($menuUsuario as $categoria):

                if (empty($categoria['items'])):
                    // Caso 1: menú de un solo nivel (ej.: Dashboard).
                    // Se mantiene el comportamiento habitual.
            ?>

                    <li>
                        <a class="app-menu__item"
                           href="<?php echo BASE_URL . $categoria['link']; ?>">

                            <i class="app-menu__icon <?php echo $categoria['icono']; ?>"></i>
                            &nbsp;&nbsp;

                            <span class="app-menu__label">
                                <?php echo $categoria['titulo']; ?>
                            </span>
                        </a>
                    </li>

                <?php
                else:
                    // Caso 2: menú contenedor con submenús desplegables.
                ?>

                    <li class="treeview">
                        <a class="app-menu__item"
                           href="#"
                           data-toggle="treeview">

                            <i class="app-menu__icon <?php echo $categoria['icono']; ?>"></i>
                            &nbsp;&nbsp;

                            <span class="app-menu__label">
                                <?php echo $categoria['titulo']; ?>
                            </span>

                            <i class="treeview-indicator fas fa-angle-right"></i>
                        </a>

                        <ul class="treeview-menu">

                            <?php foreach ($categoria['items'] as $item): ?>

                                <?php
                                // Normalizar la ruta por si tiene "/" inicial.
                                $rutaItem = ltrim(
                                    (string)($item['link'] ?? ''),
                                    '/'
                                );

                                // Determinar si el enlace corresponde a un reporte
                                // que debe abrirse en una nueva pestaña.
                                $abrirNuevaVentana = in_array(
                                    $rutaItem,
                                    $reportesNuevaVentana,
                                    true
                                );
                                ?>

                                <li>
                                    <a class="treeview-item"
                                       href="<?php echo BASE_URL . $item['link']; ?>"
                                       <?php if ($abrirNuevaVentana): ?>
                                           target="_blank"
                                           rel="noopener noreferrer"
                                       <?php endif; ?>>

                                        <i class="icon fas fa-circle"></i>

                                        <?php
                                        echo htmlspecialchars(
                                            $item['titulo'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                    </a>
                                </li>

                            <?php endforeach; ?>

                        </ul>
                    </li>

            <?php
                endif;

            endforeach;
            ?>
        </ul>
    </aside>