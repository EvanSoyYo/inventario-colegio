'use strict';

const API = {
  auth: 'api/auth.php',
  equipos: 'api/equipos.php',
  prestamos: 'api/prestamos.php',
  devoluciones: 'api/devoluciones.php',
  resumen: 'api/resumen.php',
};

const estado = {
  csrf: null,
  wasm: null,
  usuario: null,
  equipos: [],
  equiposDisponibles: [],
  prestamos: [],
};

const porId = (id) => document.getElementById(id);

async function solicitar(url, { method = 'GET', body = null, csrf = true } = {}) {
  const encabezados = { Accept: 'application/json' };
  if (body !== null) encabezados['Content-Type'] = 'application/json';
  if (csrf && method !== 'GET' && estado.csrf) encabezados['X-CSRF-Token'] = estado.csrf;
  let respuesta;
  try {
    respuesta = await fetch(url, {
      method,
      headers: encabezados,
      credentials: 'same-origin',
      body: body === null ? undefined : JSON.stringify(body),
    });
  } catch (error) {
    throw new Error('No se pudo conectar con el servidor local. Compruebe que Apache y MySQL estén iniciados.');
  }
  const datos = await respuesta.json().catch(() => ({}));
  if (!respuesta.ok) {
    const error = new Error(datos.mensaje || `La solicitud falló (${respuesta.status}).`);
    error.status = respuesta.status;
    error.detalles = datos.errores || {};
    throw error;
  }
  return datos;
}

function mostrarMensaje(texto, tipo = 'exito') {
  const zona = porId('mensajes-globales');
  zona.replaceChildren();
  const mensaje = document.createElement('div');
  mensaje.className = tipo === 'error' ? 'mensaje mensaje-error-global' : 'mensaje mensaje-exito';
  mensaje.setAttribute('role', tipo === 'error' ? 'alert' : 'status');
  mensaje.textContent = texto;
  zona.append(mensaje);
  window.setTimeout(() => { if (mensaje.isConnected) mensaje.remove(); }, 6000);
}

function mostrarMensajeAcceso(texto = '') {
  const nodo = porId('mensaje-acceso');
  nodo.textContent = texto;
  nodo.hidden = !texto;
}

function mostrarAcceso(tipo, mensaje = '') {
  document.body.classList.add('sin-sesion');
  porId('seccion-acceso').hidden = false;
  porId('contenido-principal').classList.add('oculto');
  porId('formulario-login').hidden = tipo !== 'login';
  porId('formulario-configuracion').hidden = tipo !== 'setup';
  porId('boton-cerrar-sesion').hidden = true;
  porId('usuario-actual').hidden = true;
  porId('ayuda-acceso').textContent = tipo === 'setup'
    ? 'Primero cree la cuenta administrativa para proteger las operaciones.'
    : 'Inicie sesión para administrar el inventario.';
  mostrarMensajeAcceso(mensaje);
}

function mostrarAplicacion(usuario) {
  document.body.classList.remove('sin-sesion');
  estado.usuario = usuario;
  porId('seccion-acceso').hidden = true;
  porId('contenido-principal').classList.remove('oculto');
  porId('usuario-actual').textContent = `Sesión: ${usuario.nombre_usuario}`;
  porId('usuario-actual').hidden = false;
  porId('boton-cerrar-sesion').hidden = false;
}

function fechaLocal() {
  const hoy = new Date();
  hoy.setMinutes(hoy.getMinutes() - hoy.getTimezoneOffset());
  return hoy.toISOString().slice(0, 10);
}

function configurarFechas() {
  const hoy = fechaLocal();
  const fechaPrestamo = porId('prestamo-fecha-prestamo');
  const fechaLimite = porId('prestamo-fecha-limite');
  const fechaDevolucion = porId('devolucion-fecha');
  if (!fechaPrestamo.value) fechaPrestamo.value = hoy;
  if (!fechaLimite.value) {
    const limite = new Date(`${hoy}T12:00:00`);
    limite.setDate(limite.getDate() + 7);
    fechaLimite.value = `${limite.getFullYear()}-${String(limite.getMonth() + 1).padStart(2, '0')}-${String(limite.getDate()).padStart(2, '0')}`;
  }
  fechaDevolucion.max = hoy;
  if (!fechaDevolucion.value) fechaDevolucion.value = hoy;
}

async function cargarWasm() {
  const indicador = porId('estado-wasm');
  try {
    const urlModulo = new URL('assets/wasm/inventario.js', document.baseURI).href;
    const modulo = await import(urlModulo);
    const crearModulo = modulo.default || modulo.createInventarioModule;
    if (typeof crearModulo !== 'function') throw new Error('No se encontró el inicializador del módulo.');
    estado.wasm = await crearModulo({
      locateFile: (archivo) => new URL(`assets/wasm/${archivo}`, document.baseURI).href,
    });
    const ejemplo = estado.wasm.ccall('demostrar_arreglo_poo', 'number', [], []);
    const archivoVirtual = estado.wasm.ccall('prueba_archivos_virtuales', 'number', [], []);
    if (ejemplo !== 1 || archivoVirtual < 1) throw new Error('La comprobación del módulo no dio el resultado esperado.');
    indicador.textContent = 'Módulo C++/WebAssembly activo: validación de códigos y cálculo de atrasos.';
    indicador.classList.add('activo');
  } catch (error) {
    estado.wasm = null;
    indicador.textContent = 'Módulo C++/WebAssembly pendiente de compilación; la aplicación puede seguir usándose.';
    indicador.classList.add('pendiente');
    console.warn('No se pudo cargar el módulo C++/WebAssembly:', error.message);
  }
}

function diasDesdeEpoca(fecha) {
  const [anio, mes, dia] = fecha.split('-').map(Number);
  return Math.floor(Date.UTC(anio, mes - 1, dia) / 86400000);
}
async function iniciar() {
  try {
    const sesion = await solicitar(API.auth, { csrf: false });
    if (sesion.autenticado) {
      estado.csrf = sesion.csrf;
      mostrarAplicacion(sesion.usuario);
      configurarFechas();
      await cargarTodo();
    } else if (sesion.configuracion_requerida) {
      mostrarAcceso('setup');
    } else {
      mostrarAcceso('login');
    }
  } catch (error) {
    mostrarAcceso('login', error.message);
    porId('ayuda-acceso').textContent = 'No se pudo consultar el servidor. Inicie Apache y MySQL en XAMPP y actualice la página.';
  }
}

async function cerrarSesionPorExpiracion() {
  estado.csrf = null;
  estado.usuario = null;
  mostrarAcceso('login', 'La sesión terminó. Inicie sesión de nuevo.');
}

async function cargarTodo() {
  try {
    await Promise.all([cargarResumen(), cargarEquipos(), cargarDisponibles(), cargarPrestamos()]);
  } catch (error) {
    if (error.status === 401) {
      await cerrarSesionPorExpiracion();
    } else {
      mostrarMensaje(error.message, 'error');
    }
  }
}

async function cargarResumen() {
  const resultado = await solicitar(API.resumen);
  const datos = resultado.datos;
  porId('contador-total').textContent = datos.total;
  porId('contador-disponibles').textContent = datos.disponibles;
  porId('contador-prestados').textContent = datos.prestados;
  porId('contador-mantenimiento').textContent = datos.mantenimiento;
  porId('contador-vencidos').textContent = datos.prestamos_vencidos;
}

function parametrosBusqueda() {
  const params = new URLSearchParams();
  const q = porId('busqueda-q').value.trim();
  const estadoFiltro = porId('busqueda-estado').value;
  if (q) params.set('q', q);
  if (estadoFiltro) params.set('estado', estadoFiltro);
  return params.toString();
}

async function cargarEquipos() {
  const consulta = parametrosBusqueda();
  const resultado = await solicitar(`${API.equipos}${consulta ? `?${consulta}` : ''}`);
  estado.equipos = resultado.datos || [];
  renderEquipos();
}

async function cargarDisponibles() {
  const resultado = await solicitar(`${API.equipos}?estado=disponible`);
  estado.equiposDisponibles = resultado.datos || [];
  const selector = porId('prestamo-equipo');
  const anterior = selector.value;
  selector.replaceChildren(new Option('Seleccione un equipo…', ''));
  estado.equiposDisponibles.forEach((equipo) => {
    selector.add(new Option(`${equipo.codigo} · ${equipo.nombre}`, equipo.id));
  });
  if (estado.equiposDisponibles.some((equipo) => String(equipo.id) === anterior)) selector.value = anterior;
}

function celda(texto) {
  const td = document.createElement('td');
  td.textContent = texto === null || texto === undefined || texto === '' ? '—' : String(texto);
  return td;
}

function botonTabla(texto, accion, id, clase = 'boton-secundario') {
  const boton = document.createElement('button');
  boton.type = 'button';
  boton.className = `boton boton-pequeno ${clase}`;
  boton.dataset.accion = accion;
  boton.dataset.id = String(id);
  boton.textContent = texto;
  return boton;
}

function renderEquipos() {
  const cuerpo = porId('cuerpo-tabla-equipos');
  cuerpo.replaceChildren();
  porId('resumen-equipos').textContent = `${estado.equipos.length} equipo(s) encontrados.`;
  if (!estado.equipos.length) {
    const fila = document.createElement('tr');
    fila.id = 'fila-equipos-vacia';
    const vacia = celda('No hay equipos que coincidan con la búsqueda.');
    vacia.colSpan = 7;
    fila.append(vacia);
    cuerpo.append(fila);
    return;
  }

  estado.equipos.forEach((equipo) => {
    const fila = document.createElement('tr');
    [equipo.codigo, equipo.nombre, equipo.categoria, equipo.marca, equipo.modelo].forEach((valor) => fila.append(celda(valor)));
    const celdaEstado = document.createElement('td');
    const etiqueta = document.createElement('span');
    const estadoActual = equipo.estado_actual || equipo.estado;
    etiqueta.className = `estado estado-${estadoActual}`;
    etiqueta.textContent = estadoActual;
    celdaEstado.append(etiqueta);
    fila.append(celdaEstado);

    const acciones = document.createElement('td');
    const grupo = document.createElement('div');
    grupo.className = 'acciones-tabla';
    grupo.append(botonTabla('Editar', 'editar', equipo.id));
    if (estadoActual === 'disponible') {
      grupo.append(botonTabla('Mantenimiento', 'mantenimiento', equipo.id));
      grupo.append(botonTabla('Desactivar', 'desactivar', equipo.id, 'boton-peligro'));
    } else if (estadoActual === 'mantenimiento') {
      grupo.append(botonTabla('Marcar disponible', 'disponible', equipo.id));
      grupo.append(botonTabla('Desactivar', 'desactivar', equipo.id, 'boton-peligro'));
    } else if (estadoActual === 'inactivo') {
      grupo.append(botonTabla('Reactivar', 'disponible', equipo.id));
    }
    acciones.append(grupo);
    fila.append(acciones);
    cuerpo.append(fila);
  });
}

function editarEquipo(equipo) {
  porId('equipo-id').value = equipo.id;
  porId('equipo-codigo').value = equipo.codigo || '';
  porId('equipo-nombre').value = equipo.nombre || '';
  porId('equipo-categoria').value = equipo.categoria || '';
  porId('equipo-marca').value = equipo.marca || '';
  porId('equipo-modelo').value = equipo.modelo || '';
  porId('equipo-observaciones').value = equipo.observaciones || '';
  porId('equipo-estado').value = equipo.estado || 'disponible';
  porId('equipo-estado').disabled = true;
  porId('titulo-formulario-equipo').textContent = 'Editar equipo';
  porId('boton-guardar-equipo').textContent = 'Guardar cambios';
  porId('boton-cancelar-edicion').hidden = false;
  porId('seccion-equipos').scrollIntoView({ behavior: 'smooth', block: 'start' });
  porId('equipo-codigo').focus();
}

function limpiarFormularioEquipo() {
  porId('formulario-equipo').reset();
  porId('equipo-id').value = '';
  porId('equipo-estado').disabled = false;
  porId('titulo-formulario-equipo').textContent = 'Registrar equipo';
  porId('boton-guardar-equipo').textContent = 'Registrar equipo';
  porId('boton-cancelar-edicion').hidden = true;
  limpiarErrores('formulario-equipo');
}

function limpiarErrores(formularioId) {
  const formulario = porId(formularioId);
  formulario.querySelectorAll('.mensaje-error').forEach((nodo) => {
    nodo.textContent = '';
    nodo.hidden = true;
  });
}

function mostrarErrores(formularioId, detalles) {
  limpiarErrores(formularioId);
  Object.entries(detalles || {}).forEach(([campo, mensaje]) => {
    const id = formularioId === 'formulario-equipo' ? `error-equipo-${campo}`
      : formularioId === 'formulario-prestamo' ? `error-prestamo-${campo.replaceAll('_', '-')}`
        : formularioId === 'formulario-devolucion' ? `error-devolucion-${campo.replaceAll('_', '-')}` : null;
    if (!id) return;
    const nodo = porId(id);
    if (nodo) { nodo.textContent = mensaje; nodo.hidden = false; }
  });
}

async function guardarEquipo(evento) {
  evento.preventDefault();
  limpiarErrores('formulario-equipo');
  const id = porId('equipo-id').value;
  const datos = {
    codigo: porId('equipo-codigo').value.trim(),
    nombre: porId('equipo-nombre').value.trim(),
    categoria: porId('equipo-categoria').value.trim(),
    marca: porId('equipo-marca').value.trim(),
    modelo: porId('equipo-modelo').value.trim(),
    observaciones: porId('equipo-observaciones').value.trim(),
  };
  const codigoValido = /^[A-Za-z0-9_-]{2,40}$/.test(datos.codigo)
    && (!estado.wasm || estado.wasm.ccall('validar_codigo_equipo', 'number', ['string'], [datos.codigo]) === 1);
  if (!codigoValido) {
    porId('error-equipo-codigo').textContent = 'Use 2 a 40 letras, números, guion o guion bajo.';
    porId('error-equipo-codigo').hidden = false;
    return;
  }
  const metodo = id ? 'PUT' : 'POST';
  if (!id) datos.estado = porId('equipo-estado').value;
  try {
    const url = id ? `${API.equipos}?id=${encodeURIComponent(id)}` : API.equipos;
    const resultado = await solicitar(url, { method: metodo, body: datos });
    mostrarMensaje(resultado.mensaje || 'Equipo guardado.');
    limpiarFormularioEquipo();
    await cargarTodo();
  } catch (error) {
    if (error.status === 401) return cerrarSesionPorExpiracion();
    mostrarErrores('formulario-equipo', error.detalles);
    mostrarMensaje(error.message, 'error');
  }
}

async function accionEquipo(evento) {
  const boton = evento.target.closest('button[data-accion]');
  if (!boton) return;
  const id = Number(boton.dataset.id);
  const equipo = estado.equipos.find((elemento) => elemento.id === id);
  const accion = boton.dataset.accion;
  if (accion === 'editar') {
    if (equipo) editarEquipo(equipo);
    return;
  }
  try {
    let resultado;
    if (accion === 'desactivar') {
      if (!window.confirm(`¿Desactivar el equipo ${equipo?.codigo || id}?`)) return;
      resultado = await solicitar(`${API.equipos}?id=${id}`, { method: 'DELETE' });
    } else {
      const estadoNuevo = accion === 'mantenimiento' ? 'mantenimiento' : 'disponible';
      resultado = await solicitar(`${API.equipos}?id=${id}`, { method: 'PATCH', body: { estado: estadoNuevo } });
    }
    mostrarMensaje(resultado.mensaje || 'Equipo actualizado.');
    await cargarTodo();
  } catch (error) {
    if (error.status === 401) return cerrarSesionPorExpiracion();
    mostrarMensaje(error.message, 'error');
  }
}

async function cargarPrestamos() {
  const estadoFiltro = porId('filtro-historial-estado').value;
  const consulta = estadoFiltro ? `?estado=${encodeURIComponent(estadoFiltro)}` : '';
  const resultado = await solicitar(`${API.prestamos}${consulta}`);
  estado.prestamos = resultado.datos || [];
  renderPrestamos();
}

function renderPrestamos() {
  const cuerpo = porId('cuerpo-tabla-prestamos');
  cuerpo.replaceChildren();
  porId('resumen-prestamos').textContent = `${estado.prestamos.length} préstamo(s) encontrados.`;
  if (!estado.prestamos.length) {
    const fila = document.createElement('tr');
    fila.id = 'fila-prestamos-vacia';
    const td = celda('Todavía no hay préstamos para mostrar.');
    td.colSpan = 7;
    fila.append(td);
    cuerpo.append(fila);
    return;
  }
  estado.prestamos.forEach((prestamo) => {
    const fila = document.createElement('tr');
    fila.append(celda(`${prestamo.equipo_codigo} · ${prestamo.equipo_nombre}`));
    fila.append(celda(prestamo.responsable));
    fila.append(celda(prestamo.fecha_prestamo));
    fila.append(celda(prestamo.fecha_limite));
    fila.append(celda(prestamo.fecha_devolucion));
    const estadoTd = document.createElement('td');
    const etiqueta = document.createElement('span');
    etiqueta.className = `estado estado-${prestamo.estado}`;
    if (prestamo.estado === 'vencido' && estado.wasm) {
      const dias = estado.wasm.ccall('calcular_dias_atraso', 'number', ['number', 'number'], [diasDesdeEpoca(prestamo.fecha_limite), diasDesdeEpoca(fechaLocal())]);
      etiqueta.textContent = `vencido · ${dias} día(s)`;
    } else {
      etiqueta.textContent = prestamo.estado;
    }
    estadoTd.append(etiqueta);
    fila.append(estadoTd);
    const acciones = document.createElement('td');
    if (prestamo.estado === 'activo' || prestamo.estado === 'vencido') {
      acciones.append(botonTabla('Registrar devolución', 'devolver', prestamo.id));
    } else {
      acciones.textContent = '—';
    }
    fila.append(acciones);
    cuerpo.append(fila);
  });
}

async function registrarPrestamo(evento) {
  evento.preventDefault();
  limpiarErrores('formulario-prestamo');
  const datos = {
    equipo_id: Number(porId('prestamo-equipo').value),
    responsable: porId('prestamo-responsable').value.trim(),
    fecha_prestamo: porId('prestamo-fecha-prestamo').value,
    fecha_limite: porId('prestamo-fecha-limite').value,
    observaciones: porId('prestamo-observaciones').value.trim(),
  };
  try {
    const resultado = await solicitar(API.prestamos, { method: 'POST', body: datos });
    mostrarMensaje(resultado.mensaje || 'Préstamo registrado.');
    porId('formulario-prestamo').reset();
    configurarFechas();
    await cargarTodo();
  } catch (error) {
    if (error.status === 401) return cerrarSesionPorExpiracion();
    mostrarErrores('formulario-prestamo', error.detalles);
    mostrarMensaje(error.message, 'error');
  }
}

function abrirDialogoDevolucion(evento) {
  const boton = evento.target.closest('button[data-accion="devolver"]');
  if (!boton) return;
  const prestamo = estado.prestamos.find((elemento) => Number(elemento.id) === Number(boton.dataset.id));
  if (!prestamo) return;
  porId('devolucion-prestamo-id').value = prestamo.id;
  porId('devolucion-resumen').textContent = `${prestamo.equipo_codigo} · ${prestamo.equipo_nombre} · Responsable: ${prestamo.responsable}`;
  porId('devolucion-fecha').value = fechaLocal();
  porId('dialogo-devolucion').showModal();
}

async function confirmarDevolucion(evento) {
  evento.preventDefault();
  limpiarErrores('formulario-devolucion');
  const datos = {
    prestamo_id: Number(porId('devolucion-prestamo-id').value),
    fecha_devolucion: porId('devolucion-fecha').value,
  };
  try {
    const resultado = await solicitar(API.devoluciones, { method: 'POST', body: datos });
    porId('dialogo-devolucion').close();
    mostrarMensaje(resultado.mensaje || 'Devolución registrada.');
    await cargarTodo();
  } catch (error) {
    if (error.status === 401) return cerrarSesionPorExpiracion();
    mostrarErrores('formulario-devolucion', error.detalles);
    mostrarMensaje(error.message, 'error');
  }
}

function mostrarErrorDeAcceso(error) {
  mostrarMensajeAcceso(error.message);
  if (error.detalles) {
    const primeros = Object.values(error.detalles).filter(Boolean);
    if (primeros.length) mostrarMensajeAcceso(`${error.message} ${primeros.join(' ')}`);
  }
}

async function crearCuentaInicial(evento) {
  evento.preventDefault();
  mostrarMensajeAcceso('Creando cuenta…');
  try {
    const resultado = await solicitar(API.auth, {
      method: 'POST', csrf: false,
      body: {
        accion: 'setup',
        nombre_usuario: porId('configuracion-usuario').value.trim(),
        contrasena: porId('configuracion-contrasena').value,
        confirmacion: porId('configuracion-confirmacion').value,
      },
    });
    estado.csrf = resultado.csrf;
    mostrarAplicacion(resultado.usuario);
    configurarFechas();
    await cargarTodo();
  } catch (error) {
    mostrarErrorDeAcceso(error);
  }
}

async function iniciarSesion(evento) {
  evento.preventDefault();
  mostrarMensajeAcceso('Verificando acceso…');
  try {
    const resultado = await solicitar(API.auth, {
      method: 'POST', csrf: false,
      body: {
        accion: 'login',
        nombre_usuario: porId('login-usuario').value.trim(),
        contrasena: porId('login-contrasena').value,
      },
    });
    estado.csrf = resultado.csrf;
    mostrarAplicacion(resultado.usuario);
    configurarFechas();
    await cargarTodo();
  } catch (error) {
    mostrarErrorDeAcceso(error);
  }
}

async function cerrarSesion() {
  try {
    await solicitar(API.auth, { method: 'POST', body: { accion: 'logout' } });
  } catch (error) {
    if (error.status !== 401) mostrarMensaje(error.message, 'error');
  }
  estado.csrf = null;
  estado.usuario = null;
  mostrarAcceso('login', 'La sesión se cerró.');
}

porId('formulario-login').addEventListener('submit', iniciarSesion);
porId('formulario-configuracion').addEventListener('submit', crearCuentaInicial);
porId('boton-cerrar-sesion').addEventListener('click', cerrarSesion);
porId('formulario-equipo').addEventListener('submit', guardarEquipo);
porId('formulario-equipo').addEventListener('reset', () => window.setTimeout(() => {
  if (!porId('equipo-id').value) {
    porId('equipo-estado').disabled = false;
    porId('titulo-formulario-equipo').textContent = 'Registrar equipo';
    porId('boton-guardar-equipo').textContent = 'Registrar equipo';
    porId('boton-cancelar-edicion').hidden = true;
    limpiarErrores('formulario-equipo');
  }
}, 0));
porId('boton-cancelar-edicion').addEventListener('click', limpiarFormularioEquipo);
porId('cuerpo-tabla-equipos').addEventListener('click', accionEquipo);
porId('formulario-busqueda-equipos').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  try { await cargarEquipos(); } catch (error) { mostrarMensaje(error.message, 'error'); }
});
porId('boton-limpiar-busqueda').addEventListener('click', async () => {
  porId('busqueda-q').value = '';
  porId('busqueda-estado').value = '';
  try { await cargarEquipos(); } catch (error) { mostrarMensaje(error.message, 'error'); }
});
porId('boton-actualizar-panel').addEventListener('click', cargarTodo);
porId('formulario-prestamo').addEventListener('submit', registrarPrestamo);
porId('formulario-filtro-historial').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  try { await cargarPrestamos(); } catch (error) { mostrarMensaje(error.message, 'error'); }
});
porId('boton-limpiar-filtro-historial').addEventListener('click', async () => {
  porId('filtro-historial-estado').value = '';
  try { await cargarPrestamos(); } catch (error) { mostrarMensaje(error.message, 'error'); }
});
porId('cuerpo-tabla-prestamos').addEventListener('click', abrirDialogoDevolucion);
porId('formulario-devolucion').addEventListener('submit', confirmarDevolucion);
porId('boton-cancelar-devolucion').addEventListener('click', () => porId('dialogo-devolucion').close());
porId('boton-exportar-csv').addEventListener('click', (evento) => {
  if (!estado.usuario) evento.preventDefault();
});

cargarWasm().finally(iniciar);
