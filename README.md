# Neon Burger Club — Pedidos de hamburguesas

Aplicación web de gestión de pedidos en PHP + MySQL con estética retro de restaurante de los años 80: neón, rosa eléctrico, turquesa, amarillo y una hamburguesa ilustrada en CSS.

## Funcionalidades
- Registrar pedidos con cliente, tipo de hamburguesa, cantidad, dirección de entrega y propina.
- Editar pedidos, incluyendo hamburguesa, dirección, propina, cantidad y estado.
- Eliminar pedidos con confirmación.
- Buscar por cliente, dirección o número de pedido y filtrar por estado.
- Tarjetas de resumen de pedidos, pedidos activos y propinas acumuladas.
- Creación automática de la tabla `pedidos` si no existe.
- Consultas preparadas PDO, validación básica y token CSRF en formularios.

## Requisitos
- PHP 7.4+ (recomendado PHP 8.1+), extensiones PDO y pdo_mysql.
- Base de datos MySQL disponible desde el servidor donde se ejecuta PHP.
- La base de datos `danigt_hambugers` debe existir previamente y el usuario debe tener permisos para crear tablas. El aplicativo crea las tablas, pero no crea la base de datos en el servidor.

## Instalación
1. Descomprime `retro_burger_80s.zip`.
2. Sube la carpeta y su contenido a tu hosting con PHP.
3. Comprueba las credenciales en `config.php`.
4. Abre `index.php` desde el navegador. La tabla se crea automáticamente al entrar.

## Estructura
- `index.php`: interfaz, formularios, filtros y operaciones CRUD.
- `config.php`: conexión PDO a MySQL.
- `db_setup.php`: creación automática de tablas.
- `assets/style.css`: diseño retro 80s adaptable a móviles.

## Seguridad antes de publicar
Las credenciales se incluyeron según lo solicitado para facilitar la puesta en marcha. No publiques el archivo `config.php` en repositorios públicos. Como las credenciales se compartieron en una conversación, es recomendable cambiar la contraseña de la base de datos y actualizarla en `config.php` antes de usar el sitio con datos reales. Configura HTTPS en el hosting.
