# Plan de pruebas y resultados

Fecha de registro: 25 de noviembre de 2026. Las pruebas locales se ejecutaron contra XAMPP. Las operaciones autenticadas deben repetirse después de crear la cuenta inicial, porque la autenticación se agregó después de los primeros ensayos.

| ID | Caso | Resultado esperado | Estado actual | Evidencia |
|---|---|---|---|---|
| P01 | Revisar sintaxis de archivos PHP | Sin errores de sintaxis | Aprobado para `api/*.php` actuales | `php -l` en consola |
| P02 | Revisar sintaxis JavaScript | Sin errores | Aprobado | `node --check assets/js/app.js` |
| P03 | Revisar referencias HTML usadas por JavaScript | Todos los IDs existen | Aprobado | Comprobación local de IDs |
| P04 | Abrir página, CSS y JavaScript en Apache | HTTP 200 | Aprobado | Solicitudes a `localhost` |
| P05 | Consultar equipos antes de agregar autenticación | JSON con los cinco registros de ejemplo | Aprobado históricamente; requiere revalidación con sesión | Respuesta `ok=true`, `total=5` |
| P06 | Consultar préstamos antes de agregar autenticación | Historial JSON | Aprobado históricamente; requiere revalidación con sesión | Respuesta `ok=true`, `total=0` al inicio |
| P07 | Crear préstamo válido, repetir préstamo del mismo equipo y devolverlo | Alta 201; segundo préstamo 409; devolución actualiza disponibilidad | Aprobado históricamente antes de activar autenticación | Préstamo ficticio ID 1, luego devuelto |
| P08 | Confirmar estado inicial de autenticación | Cuenta inicial requerida, sin usuario autenticado | Aprobado | `configuracion_requerida=true` |
| P09 | Creación temporal de cuenta, consulta privada, cierre de sesión y validación CSRF | Acceso permitido; solicitud sin sesión rechazada; cuenta temporal retirada | Aprobado en XAMPP; la creación inicial del estudiante aún debe hacerse | Resumen y equipos 200; sesión cerrada; usuario temporal borrado |
| P10 | CRUD de equipo con cuenta iniciada | Alta, búsqueda, edición, estado y desactivación correctos | El estudiante confirma que completó las pruebas locales | Guardar las capturas reales del flujo |
| P11 | Préstamo y devolución con sesión | Reglas de fechas, disponibilidad e historial correctos | El estudiante confirma que completó las pruebas locales | Guardar las capturas reales del flujo |
| P12 | Exportar CSV con sesión | Archivo CSV válido, UTF-8 y estado calculado | Aprobado en XAMPP | HTTP 200, `text/csv; charset=utf-8` |
| P13 | C++ a WebAssembly | Compilación y funciones exportadas | Aprobado: compilación completada; funciones revisadas en Node.js | `poo=1`, `archivos=2`, `codigoValido=1`, `codigoInvalido=0`, `atraso=3` |
| P14 | Publicación pública | El sitio carga y la aplicación funciona en Internet | El estudiante confirma que el sitio funciona | https://chus-proyecto-2026.free.je/ |
| P15 | C++ a WebAssembly en el sitio público | Módulo compilado y carga confirmada | Pendiente: subir los dos artefactos al hosting y confirmar el indicador | `assets/wasm/inventario.js`, `assets/wasm/inventario.wasm` |
| P16 | Video | Recorrido de 90–120 segundos | Pendiente por decisión del estudiante; fuera de la ayuda solicitada | Guion en `docs/guion-video.md` |

## Registro funcional anterior

En el ensayo previo a agregar sesiones, se registró un préstamo ficticio del equipo `TEC-001`, el segundo préstamo del mismo equipo fue rechazado con HTTP 409, el estado calculado pasó a prestado y la devolución lo regresó a disponible. Se conservó ese préstamo en el historial con estado devuelto para la demostración. No contiene datos de una persona real. En la verificación final, las páginas, CSS y JavaScript respondieron HTTP 200; los endpoints protegidos sin sesión respondieron HTTP 401. Una cuenta de prueba se creó solo para verificar sesión, vistas privadas y exportación CSV, y fue eliminada al finalizar. La base sigue solicitando crear la cuenta inicial.

## Capturas requeridas para la entrega

Añadir capturas reales de: creación/inicio de sesión, panel, equipo registrado, préstamo activo, rechazo de préstamo duplicado, devolución registrada, historial, exportación CSV y sitio publicado. Las pruebas funcionales fueron confirmadas por el estudiante; las capturas deben guardarse como evidencia de la entrega. No se han inventado capturas.
