# Publicación del sistema en alojamiento PHP

## Alojamiento escogido

InfinityFree anuncia un plan gratuito que incluye PHP, MySQL/MariaDB, subdominio y SSL. El alojamiento no ofrece terminal Git/SSH en el plan gratuito; la transferencia de archivos se hace con FTP o su administrador de archivos. Consulte las limitaciones actuales del proveedor antes de crear la cuenta.

## Datos que debes reunir en tu cuenta

El panel de alojamiento muestra los datos de FTP y MySQL. No los compartas por chat ni los subas a GitHub.

- Dominio o subdominio asignado y estado del certificado HTTPS.
- Host MySQL.
- Nombre de base de datos.
- Usuario MySQL.
- Contraseña MySQL.
- Usuario, servidor y contraseña FTP.

## Preparar la base

1. En el panel, crea una base de datos MySQL.
2. Abre phpMyAdmin desde el panel y selecciona esa base.
3. Importa `database/schema_hosting.sql`.
4. Verifica que existan `equipos`, `prestamos` y `usuarios` y que `equipos` tenga cinco registros ficticios.

El archivo para hosting no ejecuta `CREATE DATABASE` ni `USE`, porque el proveedor asigna el nombre de la base.

## Subir el sitio

1. Usa el Administrador de archivos del proveedor o FTP con FileZilla.
2. Sube el contenido del paquete de publicación a la carpeta web indicada por el panel (InfinityFree la llama `htdocs`). Debe quedar `index.html` en la raíz y las carpetas `api/` y `assets/` junto a él.
3. En el servidor, duplica `api/db.local.php.example` como `api/db.local.php`.
4. Edita `api/db.local.php` en el servidor e introduce los cinco datos de MySQL que muestra el panel. El archivo está excluido de Git por `.gitignore` y `api/.htaccess` impide solicitarlo directamente por HTTP.
5. No pongas la contraseña real en `api/db.php`, GitHub, una captura o un mensaje.

## Comprobar la publicación

1. Abre la URL HTTPS asignada al sitio. Debe aparecer la configuración para crear la cuenta inicial.
2. Crea la cuenta administrativa directamente en el sitio; escribe y confirma tu contraseña tú mismo.
3. Inicia sesión y verifica el panel, equipos, préstamo, devolución e importación/exportación CSV.
4. Confirma que el aviso de seguridad `api/db.local.php` no se puede abrir en el navegador.
5. En XAMPP local, no agregues `api/db.local.php`; la app usa los valores por defecto `localhost`, `inventario_colegio`, `root` y contraseña vacía.

## WebAssembly

Los archivos `assets/wasm/inventario.js` y `assets/wasm/inventario.wasm` están compilados y comprobados en el proyecto. El estudiante confirma que ya se subieron a `htdocs/assets/wasm/` y que la interfaz indica que el módulo está activo.

