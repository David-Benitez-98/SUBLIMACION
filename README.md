# Sistema de gestión para sublimación y personalización

Aplicación web desarrollada en PHP y PostgreSQL para administrar operaciones de un emprendimiento de sublimación y productos personalizados. El proyecto reúne módulos de mantenimiento, compras, pedidos, control de insumos e informes.

> **Estado del proyecto:** versión de portafolio en evolución. El código refleja el desarrollo realizado hasta la fecha; no se presenta como una implementación validada por el cliente ni como un servicio listo para producción.

## Funcionalidades

### Datos maestros

- Categorías de productos.
- Productos e insumos.
- Clientes y proveedores.
- Colores y unidades de medida.
- Departamentos, ciudades y tipos de documento.

### Operación

- **Recetas de productos:** relación entre productos e insumos para estimar materiales necesarios.
- **Insumos por proveedor:** asociación de proveedores con los insumos que comercializan y sus referencias de precio y condiciones.
- **Compras:** cabecera y detalle de compra, registro de entradas de stock y anulación con movimiento inverso.
- **Pedidos:** registro de pedidos de clientes, productos, cantidades, importes y estados del proceso.
- **Consumo de materiales:** consulta de materiales estimados y registro del consumo utilizado en producción.
- **Movimientos de stock:** historial de entradas y salidas, ajustes positivos y negativos, consulta y exportación.

### Panel e informes

- Panel principal con indicadores de pedidos, entregas próximas, stock bajo y materiales faltantes estimados.
- Reporte general de pedidos.
- Reporte de planificación de materiales.
- Reporte de inventario.
- Reporte de compras.
- Impresión de comprobantes individuales de pedidos y compras.
- Exportación de reportes a CSV compatible con hojas de cálculo.

### Acceso y permisos

- Inicio de sesión con verificación de contraseñas mediante `password_verify`.
- Bloqueo temporal luego de varios intentos fallidos.
- Cierre de sesión por inactividad.
- Menú dinámico y verificación de acceso a módulos.

Las medidas anteriores describen funciones existentes en el código; no equivalen a una auditoría de seguridad ni certifican que la aplicación esté lista para exposición pública en Internet.

## Tecnologías

- PHP con PDO.
- PostgreSQL mediante el controlador `pdo_pgsql`.
- HTML, CSS y JavaScript.
- jQuery y peticiones AJAX.
- Bootstrap.
- DataTables.
- Select2.
- Font Awesome.
- PNotify.
- Chart.js y Moment.js en los recursos del proyecto.

## Estructura general

```text
sublimacion/
├── config/             # Configuración de conexión y constantes
├── css/                # Estilos propios y de terceros
├── imgs/               # Recursos gráficos
├── js/                 # JavaScript y librerías del cliente
├── lib_fontawesome/    # Iconografía
├── menu/               # Menú dinámico
├── modulos/
│   ├── referenciales/  # Datos maestros
│   └── movimientos/    # Compras, pedidos, recetas y stock
├── portada/            # Panel principal y estructura común
├── reportes/           # Informes e impresiones
├── security/           # Autenticación, sesión y permisos
├── select2/            # Select2
└── pnotify/             # Notificaciones
```

## Requisitos de entorno

- Servidor web compatible con PHP, por ejemplo Apache.
- PHP 8.x.
- Extensión `pdo_pgsql` habilitada.
- PostgreSQL.
- Navegador moderno con JavaScript habilitado.

## Configuración local

1. Clonar el repositorio dentro del directorio público del servidor web, por ejemplo `htdocs/sublimacion`.
2. Crear una base de datos PostgreSQL vacía (por ejemplo, `sublimacion_demo`).
3. Abrir `database/schema.sql` en pgAdmin Query Tool conectado a esa base y ejecutar el script. El archivo define los esquemas `public` y `sublimacion`, tablas, relaciones, funciones y triggers.
4. Copiar `config/conexion.example.php` como `config/conexion.php` y configurar `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` y `DB_PASSWORD` en el entorno del servidor. No versionar `config/conexion.php`.
5. Revisar `config/constantes.php`. La configuración actual usa `/sublimacion` como ruta base; ajustarla cuando el proyecto se instale en otra ubicación.
6. Preparar e insertar los registros iniciales de demostración (catálogos, permisos y usuario) antes de iniciar sesión. En esta versión pública todavía no se incluye un archivo de semillas.
7. Configurar el usuario de base de datos con los permisos necesarios y acceder desde el navegador a `http://localhost/sublimacion`.

### Estado de la base de datos

El archivo [`database/schema.sql`](database/schema.sql) contiene la estructura PostgreSQL sin registros de las tablas. Incluye definiciones de esquemas, tablas, secuencias, funciones, índices, restricciones, claves foráneas y triggers. Se preparó a partir de un respaldo anterior quitando los bloques de filas (`COPY`) y los valores de secuencias; no contiene los registros originales.

**Importante:** el sistema utiliza objetos tanto en el esquema `sublimacion` como en `public`. Al generar otro esquema-only en el futuro, no excluir `public`, porque contiene tablas referenciadas por el proyecto.

El esquema no inserta usuarios, permisos, menús ni catálogos iniciales. Por eso, por sí solo crea la estructura, pero todavía no deja una instalación lista para iniciar sesión. Más adelante se puede agregar un `seed_demo.sql` con registros ficticios y una cuenta de demostración. No utilizar copias de producción ni respaldos que contengan información de clientes.

## Configuración sensible

`config/conexion.php` contiene datos de conexión locales y está excluido del seguimiento de Git. No subir ese archivo, claves, contraseñas, respaldos ni archivos de datos reales. El archivo `config/conexion.example.php` es únicamente una plantilla sin credenciales reales.

## Alcance y próximos pasos

- Incorporar un script de datos iniciales ficticios (`seed_demo.sql`) para permitir una prueba de punta a punta.
- Añadir capturas de pantalla sin nombres, teléfonos, direcciones ni datos reales.
- Completar pruebas de integración con una base de datos de demostración.
- Reforzar protección CSRF, configuración de cookies de sesión y controles de acceso antes de cualquier despliegue público.
- Revisar los recursos gráficos y retirar archivos de prueba que no formen parte del producto.

## Licencia

No se asigna una licencia de reutilización en esta versión. Antes de agregar una licencia, confirmar la titularidad del código y los permisos correspondientes para publicar y reutilizar los componentes desarrollados para el proyecto.
