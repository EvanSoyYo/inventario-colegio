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

- **Repositorio público:** crea el repositorio en tu cuenta de GitHub y sube el proyecto sin datos personales ni contraseñas. Este paquete no está publicado en GitHub.
- **Alojamiento público:** usa un servicio que ejecute PHP y MariaDB/MySQL. Netlify por sí solo no ejecuta esta API PHP. El sitio todavía no tiene URL pública.
- **WebAssembly:** el fuente y el script están en `cpp/`. La generación del `.wasm` quedó pendiente porque Control de aplicaciones de Windows bloqueó `wasm-ld.exe`; no cambies las protecciones del equipo para forzar la ejecución.
- **Video:** graba el recorrido de 90–120 segundos descrito en `docs/guion-video.md` después de ejecutar las pruebas reales.
- **Portada:** escribe tu nombre en el espacio reservado de la presentación.

## 4. Qué incluir en la entrega

- Código del sitio y API.
- Esquema y migración SQL.
- Manual de usuario y manual técnico.
- Plan de pruebas con resultados actualizados y capturas propias.
- Presentación y video.
- Enlace al repositorio y URL del sitio publicado, cuando estén disponibles.

