# Inventario tecnológico escolar

Aplicación web para administrar el inventario, los préstamos y las devoluciones de recursos tecnológicos de un colegio técnico profesional.

## Estado de la entrega

- **Funcionamiento local:** página, estilos y JavaScript servidos por Apache en XAMPP.
- **Base de datos:** `inventario_colegio`, con tablas `equipos`, `prestamos` y `usuarios`; XAMPP aporta MariaDB con sintaxis compatible con MySQL.
- **API:** equipos, préstamos, devoluciones, resumen, exportación CSV e inicio de sesión.
- **Acceso:** el primer uso solicita crear una cuenta administrativa. No se incluye una contraseña predeterminada.
- **C++/WebAssembly:** módulo compilado, probado localmente y activo en el sitio público, según confirma el estudiante.
- **Documentación:** manual de usuario, manual técnico, identidad visual, plan de pruebas, guion de video, lista de cierre y presentación de ocho diapositivas incluidos en `docs/`.
- **Publicación en Internet:** el estudiante confirma que el sitio funciona en [chus-proyecto-2026.free.je](https://chus-proyecto-2026.free.je/). Para reproducir el despliegue se incluyen `database/schema_hosting.sql` y `docs/publicacion-hosting.md`.
- **Repositorio GitHub:** publicado en `https://github.com/EvanSoyYo/inventario-colegio`.
- **C++/WebAssembly:** compilado, comprobado y desplegado; el estudiante confirma que el sitio muestra el módulo activo.
- **Evidencia final:** guardar capturas de las pruebas funcionales. La grabación del video queda a cargo del estudiante.
- **Lista de cierre:** consulte `docs/cierre-entrega.md` para desplegar WebAssembly y reunir la evidencia final.

## Requisitos locales

- Windows con XAMPP.
- Apache y MySQL/MariaDB iniciados en el panel de XAMPP.
- Navegador moderno.

## Instalación local

1. Copie la carpeta `inventario_colegio` a `C:\xampp\htdocs\`.
2. En phpMyAdmin, importe `database/schema.sql` para una instalación nueva. Si la base ya existe, importe `database/migracion_usuarios.sql` para agregar la tabla de acceso.
3. Revise `api/db.php` y ajuste el usuario o contraseña de MariaDB si su XAMPP no usa `root` sin contraseña. Esos valores son solo para desarrollo local.
4. Inicie Apache y MySQL/MariaDB desde el panel XAMPP.
5. Abra `http://localhost/inventario_colegio/`.
6. En el primer acceso, cree un nombre de usuario y una contraseña personal de al menos 10 caracteres. La aplicación almacena el hash de la contraseña.

## Funciones

- Panel de conteos y préstamos vencidos.
- Alta, consulta, búsqueda, edición, cambio de estado y desactivación lógica de equipos.
- Registro de préstamos, prevención de doble préstamo y devolución.
- Historial con estados activo, vencido y devuelto.
- Exportación del inventario a CSV.
- Validación del lado del navegador y del servidor.

## API

| Ruta | Métodos | Función |
|---|---|---|
| `api/auth.php` | GET, POST | Estado de sesión, creación inicial de administrador, inicio y cierre de sesión |
| `api/equipos.php` | GET, POST, PUT, PATCH, DELETE | Consulta, búsqueda, alta, edición, cambio de estado y desactivación |
| `api/prestamos.php` | GET, POST | Historial y registro de préstamo |
| `api/devoluciones.php` | POST | Registro de devolución |
| `api/resumen.php` | GET | Conteos para el panel |
| `api/exportar.php` | GET | Descarga del inventario en CSV |

Las operaciones protegidas usan sesión de PHP y token CSRF. El servidor valida disponibilidad antes de crear préstamos.

## Compilar el módulo WebAssembly

El código está en `cpp/inventario.cpp` y el script en `cpp/compilar_wasm.bat`. Se requiere el SDK oficial de Emscripten. Defina `EMSDK_DIR` con la carpeta del SDK y ejecute el script desde la carpeta del proyecto. Los archivos generados son `assets/wasm/inventario.js` y `assets/wasm/inventario.wasm`. Se comprobó que `demostrar_arreglo_poo` devuelve 1, `prueba_archivos_virtuales` devuelve 2, la validación acepta `TEC-001` y rechaza `TEC 001`, y el cálculo devuelve 3 días para una diferencia de 3. El estudiante confirma que ambos archivos fueron desplegados y que el módulo está activo en el sitio público.

## Pruebas registradas

Los casos y el estado de cada verificación están en `docs/plan-pruebas.md`. La página y los archivos de interfaz responden correctamente en XAMPP. El flujo completo de préstamo y devolución se probó antes de activar autenticación; los casos autenticados deben repetirse después de crear la cuenta administrativa.
