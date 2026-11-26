# Lista de cierre de entrega

El proyecto local está copiado en XAMPP y sus rutas principales responden. Antes de entregar, completa estas acciones con tu propia cuenta y guarda la evidencia real solicitada por el profesor.

## 1. Iniciar la aplicación

1. Abre XAMPP Control Panel e inicia Apache y MySQL.
2. Abre `http://localhost/inventario_colegio/`.
3. Crea tu propia cuenta administrativa con una contraseña de al menos 10 caracteres. No envíes la contraseña por chat ni la incluyas en capturas.
4. Comprueba que aparece el panel y que la lista incluye los equipos de muestra.

## 2. Repetir la demostración con la sesión definitiva

Usa datos ficticios, no nombres ni información de personas reales.

1. Registra un equipo de demostración y búscalo por código.
2. Registra un préstamo para ese equipo.
3. Intenta registrar otro préstamo del mismo equipo. Debe rechazarse mientras el primero esté abierto.
4. Devuelve el equipo y confirma que el préstamo permanece en el historial.
5. Descarga el CSV.
6. Guarda capturas reales de la cuenta iniciada, panel, equipo, préstamo, rechazo, devolución, historial y CSV.

La prueba del préstamo y devolución que ya se registra en `docs/plan-pruebas.md` se hizo antes de activar autenticación; repítela con tu cuenta y anota el resultado.

## 3. Completar los entregables externos

- **Repositorio público:** el repositorio del proyecto ya está en [GitHub](https://github.com/EvanSoyYo/inventario-colegio). Antes de compartirlo, confirma que no tenga archivos privados ni credenciales.
- **Alojamiento público:** publicado en [chus-proyecto-2026.free.je](https://chus-proyecto-2026.free.je/); el estudiante confirma que funciona.
- **WebAssembly:** el fuente y el script están en `cpp/`. La generación del `.wasm` quedó pendiente porque Control de aplicaciones de Windows bloqueó `wasm-ld.exe`; no cambies las protecciones del equipo para forzar la ejecución. Pide aprobación al administrador o usa una computadora escolar donde Emscripten esté permitido. En ese entorno, configura `EMSDK_DIR` con la carpeta del SDK y ejecuta `cpp\compilar_wasm.bat`. Deben aparecer `assets/wasm/inventario.js` y `assets/wasm/inventario.wasm`; al actualizar la página, el estado debe indicar que el módulo está activo.
- **Video:** queda pendiente por decisión del estudiante y fuera del apoyo solicitado. El guion está en `docs/guion-video.md`.
- **Portada:** el proyecto se preparó sin nombre personal, según lo solicitado.

## 4. Qué incluir en la entrega

- Código del sitio y API.
- Esquema y migración SQL.
- Manual de usuario y manual técnico.
- Plan de pruebas con resultados y capturas propias de la cuenta, las funciones y el sitio público.
- Presentación y video.
- Enlace al [repositorio](https://github.com/EvanSoyYo/inventario-colegio) y al [sitio publicado](https://chus-proyecto-2026.free.je/).

