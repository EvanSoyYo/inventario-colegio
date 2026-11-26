# Diseño técnico del inventario tecnológico escolar

## Propósito

Registrar los equipos tecnológicos de una institución, saber cuáles están disponibles y conservar el historial de préstamos y devoluciones. La API y la base de datos aplican las reglas principales para que las acciones no dependan solo de las validaciones del navegador.

## Arquitectura implementada

- **Interfaz:** `index.html`, CSS adaptable y JavaScript con solicitudes `fetch`.
- **API:** endpoints PHP que reciben y responden JSON.
- **Datos:** MariaDB de XAMPP con tablas InnoDB y consultas PDO preparadas.
- **Autenticación:** cuenta administrativa inicial, contraseña con hash, sesión de PHP y token CSRF para cambios.
- **C++/WebAssembly:** fuente C++ y script de Emscripten incluidos. La compilación del binario está pendiente porque Windows Application Control impidió ejecutar `wasm-ld.exe`.

## Modelo de datos

### Equipos

Cada equipo tiene código único, nombre, categoría, marca, modelo, observaciones, estado y fechas de registro y actualización. El estado almacenado puede ser `disponible`, `mantenimiento` o `inactivo`. La API calcula el estado `prestado` cuando existe un préstamo abierto.

### Préstamos

Cada registro enlaza un equipo con una persona responsable, fecha de préstamo, fecha límite, fecha de devolución y observaciones. `fecha_devolucion IS NULL` significa que el préstamo sigue abierto. El historial no se elimina al devolver el equipo.

### Usuarios

La tabla de usuarios almacena nombres únicos y hashes de contraseña. Si no existe ninguna cuenta, la página muestra un formulario de configuración inicial. La clave original no se guarda.

## Reglas de negocio

1. El código del equipo es obligatorio, único y admite letras, números, guion o guion bajo.
2. Solo se presta un equipo cuyo estado guardado sea disponible y que no tenga otro préstamo abierto.
3. La fecha límite no puede preceder a la fecha del préstamo.
4. La devolución debe pertenecer a un préstamo abierto y ser una fecha válida.
5. Un equipo con un préstamo abierto no se puede desactivar.
6. Todas las operaciones de escritura requieren una sesión autenticada y un token CSRF válido.

## Rutas de API

| Ruta | Métodos | Función |
|---|---|---|
| `api/auth.php` | GET, POST | Estado de sesión, creación inicial, ingreso y cierre de sesión |
| `api/equipos.php` | GET, POST, PUT, PATCH, DELETE | Consulta, búsqueda, alta, edición y estado de equipos |
| `api/prestamos.php` | GET, POST | Historial y nuevo préstamo |
| `api/devoluciones.php` | POST | Registrar devolución |
| `api/resumen.php` | GET | Totales para el panel |
| `api/exportar.php` | GET | Exportar inventario como CSV |

## Archivos principales

```text
api/                 Conexión, seguridad y rutas JSON
assets/css/           Estilos de la aplicación
assets/js/            Interfaz y consumo de la API
cpp/                  Fuente C++ y comando de compilación
database/             Esquema inicial y migración de usuarios
docs/                 Manuales, pruebas, guion y cierre de entrega
index.html             Página principal
```

## Instalación y entorno

1. Copiar la carpeta `proyecto-inventario` como `C:\xampp\htdocs\inventario_colegio`.
2. Importar `database/schema.sql` en una base vacía. Para conservar una base existente, usar `database/migracion_usuarios.sql`.
3. Ajustar las constantes de conexión en `api/db.php` según XAMPP.
4. Iniciar Apache y MySQL en el panel XAMPP.
5. Abrir `http://localhost/inventario_colegio/` y crear la cuenta administrativa.

El desarrollo se verificó localmente en XAMPP. La publicación pública, el video, las capturas finales y el binario WebAssembly no forman parte del estado actual.
