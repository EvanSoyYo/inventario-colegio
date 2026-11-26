# Manual técnico

## Arquitectura

- **Cliente:** HTML semántico, CSS adaptable y JavaScript con `fetch`.
- **Servidor:** PHP con endpoints JSON y consultas preparadas mediante PDO.
- **Datos:** MariaDB incluida en XAMPP, compatible con la sintaxis usada para MySQL.
- **Acceso:** sesión PHP, contraseña con `password_hash`/`password_verify` y token CSRF para cambios.
- **C++/WebAssembly:** el módulo fuente calcula días de atraso y valida códigos; el script y los binarios generados se incluyen. Las cuatro funciones exportadas se comprobaron localmente. El servidor público requiere los archivos `assets/wasm/inventario.js` y `assets/wasm/inventario.wasm` para mostrar el módulo activo.

## Modelo de datos

### `equipos`
Identificador, código único, nombre, categoría, marca, modelo, estado físico, observaciones y marcas de tiempo. El estado `prestado` se calcula desde préstamos abiertos, no se guarda en esta tabla.

### `prestamos`
Equipo, persona responsable, fecha de préstamo, fecha límite, fecha real de devolución y observaciones. `fecha_devolucion IS NULL` indica que el equipo sigue prestado.

### `usuarios`
Nombre de usuario único y hash de contraseña. La cuenta inicial se crea desde la pantalla solamente cuando la tabla está vacía.

## Endpoints

- `GET api/auth.php`: estado de sesión y si falta crear la cuenta inicial.
- `POST api/auth.php`: `setup`, `login` o `logout`.
- `GET api/equipos.php?q=...&estado=...`: lista/busca equipos. Estados consultables: disponible, prestado, mantenimiento, inactivo.
- `POST api/equipos.php`: crea un equipo desde JSON.
- `PUT api/equipos.php?id=...`: actualiza sus datos descriptivos.
- `PATCH api/equipos.php?id=...`: cambia a disponible, mantenimiento o inactivo.
- `DELETE api/equipos.php?id=...`: desactivación lógica.
- `GET/POST api/prestamos.php`: historial o nuevo préstamo.
- `POST api/devoluciones.php`: cierra préstamo con su fecha real.
- `GET api/resumen.php`: conteos del panel.
- `GET api/exportar.php`: CSV del inventario.

Las solicitudes que modifican datos requieren sesión y `X-CSRF-Token`. Las respuestas JSON siguen el formato `{ok, mensaje/datos}`. El código HTTP indica éxito, validación, conflicto, falta de autenticación o error del servidor.

## Reglas de negocio

- Código de equipo obligatorio, único y de 2 a 40 caracteres alfanuméricos, guion o guion bajo.
- Solo un préstamo abierto por equipo; el servidor bloquea la fila del equipo dentro de una transacción InnoDB antes de insertar.
- Solo se presta equipo cuyo estado almacenado es disponible y que no tenga préstamo abierto.
- La fecha límite no puede ser anterior a la fecha de préstamo.
- La devolución debe corresponder a un préstamo abierto y no puede ser anterior al préstamo ni futura.
- Desactivar un equipo con préstamo abierto devuelve conflicto.

## Instalación local

1. Colocar el proyecto en `C:\xampp\htdocs\inventario_colegio`.
2. Importar `database/schema.sql` en instalación nueva, o `database/migracion_usuarios.sql` en la base existente.
3. Ajustar `api/db.php` al host, base, usuario y contraseña locales.
4. Iniciar Apache y MySQL/MariaDB.
5. Abrir `http://localhost/inventario_colegio/` y crear la cuenta inicial.

## Organización

```text
api/                 Endpoints PHP, conexión y control de sesión
assets/css/           Estilos adaptables
assets/js/            Interfaz y consumo de la API
assets/wasm/          Archivos generados por Emscripten (inventario.js e inventario.wasm)
cpp/                  Fuente C++ y script de compilación
 database/            Esquema inicial y migración de usuarios
docs/                 Manuales, pruebas y presentación
index.html             Interfaz principal
```

## Configuración de producción pendiente

Usar alojamiento con PHP y MySQL/MariaDB, HTTPS, usuario de base con permisos mínimos, contraseña fuera del repositorio, copias de seguridad y publicación del binario WebAssembly compilado desde un entorno aprobado. No publicar el usuario `root` local de XAMPP ni la carpeta de herramientas Emscripten.
