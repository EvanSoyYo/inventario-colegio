# Manual de usuario

## Acceso a la aplicación

Con Apache y MySQL/MariaDB iniciados en XAMPP, abra `http://localhost/inventario_colegio/`.

En el primer acceso, cree la cuenta administrativa. Use una contraseña personal de al menos 10 caracteres y no la comparta en capturas. Luego de crear la cuenta, la pantalla mostrará el inicio de sesión.

**Captura real por agregar:** pantalla de creación de la cuenta inicial y pantalla de ingreso.

## Panel principal

El panel muestra el total de equipos, equipos disponibles, equipos prestados, equipos en mantenimiento y préstamos vencidos. Use **Actualizar datos** después de realizar cambios. **Exportar CSV** descarga el inventario para abrirlo en una hoja de cálculo.

**Captura real por agregar:** panel con datos de demostración.

## Registrar un equipo

1. Abra **Equipos**.
2. Complete código, nombre y categoría. Marca, modelo y observaciones son opcionales.
3. Use un código único de 2 a 40 letras, números, guion o guion bajo.
4. Elija el estado inicial y seleccione **Registrar equipo**.
5. Busque el equipo en la tabla y confirme su estado.

**Captura real por agregar:** formulario completo y equipo en la tabla.

## Buscar, editar y cambiar estado

1. Escriba el código, nombre o categoría en el buscador, o seleccione un estado.
2. Pulse **Buscar**.
3. Para modificar datos, pulse **Editar**, cambie los campos y guarde.
4. Use **Mantenimiento**, **Marcar disponible**, **Desactivar** o **Reactivar** según corresponda.
5. Los equipos con un préstamo abierto no se pueden desactivar.

**Captura real por agregar:** filtro aplicado y acciones de una fila.

## Registrar un préstamo

1. Vaya a **Nuevo préstamo**.
2. Seleccione un equipo disponible.
3. Escriba la persona responsable y las fechas del préstamo y de devolución esperada.
4. Agregue una observación si hace falta y pulse **Registrar préstamo**.
5. Si el equipo ya está prestado o en mantenimiento, el servidor rechazará la solicitud.

**Captura real por agregar:** formulario y mensaje de confirmación.

## Registrar una devolución

1. Abra **Historial**.
2. Localice el préstamo activo o vencido.
3. Pulse **Registrar devolución**, seleccione la fecha real y confirme.
4. El historial conserva el préstamo como devuelto; el equipo vuelve a su estado almacenado, normalmente disponible.

**Captura real por agregar:** diálogo de devolución y fila ya devuelta.

## Errores frecuentes

- **No se puede conectar:** compruebe que Apache y MySQL/MariaDB estén iniciados.
- **Usuario o contraseña incorrectos:** vuelva a ingresar; la contraseña no se puede recuperar desde la pantalla.
- **Código duplicado:** registre otro código.
- **Equipo no disponible:** revise su estado y el historial de préstamos.
- **La página no carga el estilo:** confirme que existen `assets/css/estilos.css` y `assets/js/app.js` bajo la carpeta del sitio.
- **Módulo C++/WebAssembly:** aporta validación adicional de códigos y cálculo de días de atraso cuando los archivos generados están desplegados. Si no carga, la interfaz mantiene las validaciones del servidor.

## Cierre de sesión

Pulse **Cerrar sesión** en la barra superior. El acceso a las operaciones administrativas queda protegido por la sesión.
