<?php
declare(strict_types=1);

/*
 * api/equipos.php
 * API JSON para administrar equipos.
 *
 *   GET    equipos.php              -> lista (opcional: ?q=texto  &estado=disponible|prestado|mantenimiento|inactivo)
 *   GET    equipos.php?id=5         -> un equipo
 *   POST   equipos.php              -> registrar equipo (cuerpo JSON)
 *   PUT    equipos.php?id=5         -> modificar datos del equipo (cuerpo JSON)
 *   PATCH  equipos.php?id=5         -> cambiar estado: {"estado":"mantenimiento"}
 *   DELETE equipos.php?id=5         -> desactivación lógica (estado = 'inactivo')
 *
 * estado_actual (calculado): 'prestado' si existe en prestamos una fila con
 * el mismo equipo_id y fecha_devolucion IS NULL; si no, el valor de equipos.estado.
 * 'prestado' nunca se guarda en equipos.estado.
 *
 * PENDIENTE ANTES DE PUBLICAR: autenticación para POST, PUT, PATCH y DELETE.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_common.php';

header('Content-Type: application/json; charset=utf-8');

// Estados que se pueden GUARDAR en equipos.estado (POST y PATCH).
const ESTADOS_PERMITIDOS = ['disponible', 'mantenimiento', 'inactivo'];

// Estados por los que se puede FILTRAR en GET (incluye el calculado 'prestado').
const ESTADOS_FILTRO = ['disponible', 'prestado', 'mantenimiento', 'inactivo'];

// Consulta base: columnas de equipos + estado_actual calculado.
const SQL_BASE =
    'SELECT e.id, e.codigo, e.nombre, e.categoria, e.marca, e.modelo, e.estado, '
  . 'e.observaciones, e.creado_en, e.actualizado_en, '
  . "CASE WHEN EXISTS (SELECT 1 FROM prestamos p WHERE p.equipo_id = e.id AND p.fecha_devolucion IS NULL) "
  . "THEN 'prestado' ELSE e.estado END AS estado_actual "
  . 'FROM equipos e';

// Longitudes máximas según el esquema importado.
const LIMITES = [
    'codigo'        => 40,
    'nombre'        => 120,
    'categoria'     => 80,
    'marca'         => 80,
    'modelo'        => 80,
    'observaciones' => 500,
];

const CAMPOS_REQUERIDOS = ['codigo', 'nombre', 'categoria'];
const CAMPOS_OPCIONALES = ['marca', 'modelo', 'observaciones'];

/* ---------- Utilidades ---------- */

function responder(int $codigoHttp, array $datos)
{
    http_response_code($codigoHttp);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function responderError(int $codigoHttp, string $mensaje, array $detalles = [])
{
    $respuesta = ['ok' => false, 'mensaje' => $mensaje];
    if ($detalles) {
        $respuesta['errores'] = $detalles;
    }
    responder($codigoHttp, $respuesta);
}

function leerCuerpoJson(): array
{
    $crudo = file_get_contents('php://input');
    if ($crudo === false || trim($crudo) === '') {
        responderError(400, 'El cuerpo de la solicitud está vacío.');
    }
    $datos = json_decode($crudo, true);
    if (!is_array($datos)) {
        responderError(400, 'El cuerpo de la solicitud no es un JSON válido.');
    }
    return $datos;
}

function obtenerId(): int
{
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false || $id === null) {
        responderError(400, 'Falta el parámetro id o no es un número entero positivo.');
    }
    return $id;
}

function esTextoValido($valor): bool
{
    return is_string($valor) || is_int($valor);
}

/* ---------- Validación ---------- */

/** Valida y limpia los datos de un equipo. Devuelve [datosLimpios, errores]. */
function validarEquipo(array $entrada): array
{
    $errores = [];
    $limpio = [];

    foreach (CAMPOS_REQUERIDOS as $campo) {
        $valor = $entrada[$campo] ?? null;
        if (!esTextoValido($valor) || trim((string)$valor) === '') {
            $errores[$campo] = 'Campo obligatorio.';
            continue;
        }
        $limpio[$campo] = trim((string)$valor);
    }

    if (isset($limpio['codigo']) && !preg_match('/^[A-Za-z0-9_-]{2,40}$/D', $limpio['codigo'])) {
      $errores['codigo'] = 'Use 2 a 40 letras, números, guion o guion bajo.';
  }
  foreach (CAMPOS_OPCIONALES as $campo) {
        $valor = $entrada[$campo] ?? '';
        if ($valor === null) {
            $valor = '';
        }
        if (!esTextoValido($valor)) {
            $errores[$campo] = 'Valor no válido.';
            continue;
        }
        $limpio[$campo] = trim((string)$valor);
    }

    foreach (LIMITES as $campo => $maximo) {
        if (isset($limpio[$campo]) && mb_strlen($limpio[$campo], 'UTF-8') > $maximo) {
            $errores[$campo] = "Máximo $maximo caracteres.";
        }
    }

    return [$limpio, $errores];
}

/* ---------- Consultas auxiliares ---------- */

function obtenerEquipo(PDO $pdo, int $id)
{
    $st = $pdo->prepare(SQL_BASE . ' WHERE e.id = :id');
    $st->execute([':id' => $id]);
    return $st->fetch();   // false si no existe
}

function codigoExiste(PDO $pdo, string $codigo, int $excluirId = 0): bool
{
    $st = $pdo->prepare('SELECT 1 FROM equipos WHERE codigo = :codigo AND id <> :id LIMIT 1');
    $st->execute([':codigo' => $codigo, ':id' => $excluirId]);
    return (bool)$st->fetchColumn();
}

function tienePrestamoAbierto(PDO $pdo, int $equipoId): bool
{
    $st = $pdo->prepare('SELECT 1 FROM prestamos WHERE equipo_id = :id AND fecha_devolucion IS NULL LIMIT 1');
    $st->execute([':id' => $equipoId]);
    return (bool)$st->fetchColumn();
}

function exigirEquipoExistente(PDO $pdo, int $id): array
{
    $equipo = obtenerEquipo($pdo, $id);
    if (!$equipo) {
        responderError(404, 'El equipo no existe.');
    }
    return $equipo;
}

function exigirSinPrestamoAbierto(PDO $pdo, int $id)
{
    if (tienePrestamoAbierto($pdo, $id)) {
        responderError(409, 'No se puede desactivar: el equipo tiene un préstamo sin devolución.');
    }
}

/* ---------- Manejadores por método ---------- */

function manejarGet(PDO $pdo)
{
    if (isset($_GET['id'])) {
        $equipo = exigirEquipoExistente($pdo, obtenerId());
        responder(200, ['ok' => true, 'datos' => $equipo]);
    }

    $condiciones = [];
    $parametros = [];

    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    if ($q !== '') {
        // Se escapan % y _ para que se busquen como texto literal.
        $patron = '%' . addcslashes($q, '%_\\') . '%';
        $condiciones[] = '(t.codigo LIKE :q1 OR t.nombre LIKE :q2 OR t.categoria LIKE :q3)';
        $parametros[':q1'] = $patron;
        $parametros[':q2'] = $patron;
        $parametros[':q3'] = $patron;
    }

    if (isset($_GET['estado']) && $_GET['estado'] !== '') {
        $estado = (string)$_GET['estado'];
        if (!in_array($estado, ESTADOS_FILTRO, true)) {
            responderError(400, 'Estado no permitido.', ['estado' => 'Valores válidos: ' . implode(', ', ESTADOS_FILTRO)]);
        }
        // Se filtra por el estado calculado.
        $condiciones[] = 't.estado_actual = :estado';
        $parametros[':estado'] = $estado;
    }

    $sql = 'SELECT t.* FROM (' . SQL_BASE . ') AS t';
    if ($condiciones) {
        $sql .= ' WHERE ' . implode(' AND ', $condiciones);
    }
    $sql .= ' ORDER BY t.nombre ASC, t.id ASC';

    $st = $pdo->prepare($sql);
    $st->execute($parametros);
    $filas = $st->fetchAll();

    responder(200, ['ok' => true, 'total' => count($filas), 'datos' => $filas]);
}

function manejarPost(PDO $pdo)
{
    $entrada = leerCuerpoJson();
    [$datos, $errores] = validarEquipo($entrada);

    $estado = $entrada['estado'] ?? 'disponible';
    if (!is_string($estado) || !in_array($estado, ESTADOS_PERMITIDOS, true)) {
        $errores['estado'] = 'Valores válidos: ' . implode(', ', ESTADOS_PERMITIDOS);
    }

    if ($errores) {
        responderError(400, 'Hay datos inválidos.', $errores);
    }

    if (codigoExiste($pdo, $datos['codigo'])) {
        responderError(409, 'Ya existe un equipo con ese código.', ['codigo' => 'Código duplicado.']);
    }

    $st = $pdo->prepare(
        'INSERT INTO equipos (codigo, nombre, categoria, marca, modelo, estado, observaciones)
         VALUES (:codigo, :nombre, :categoria, :marca, :modelo, :estado, :observaciones)'
    );
    $st->execute([
        ':codigo'        => $datos['codigo'],
        ':nombre'        => $datos['nombre'],
        ':categoria'     => $datos['categoria'],
        ':marca'         => $datos['marca'],
        ':modelo'        => $datos['modelo'],
        ':estado'        => $estado,
        ':observaciones' => $datos['observaciones'],
    ]);

    $id = (int)$pdo->lastInsertId();
    responder(201, ['ok' => true, 'mensaje' => 'Equipo registrado.', 'datos' => obtenerEquipo($pdo, $id)]);
}

function manejarPut(PDO $pdo)
{
    $id = obtenerId();
    exigirEquipoExistente($pdo, $id);

    // PUT no cambia el estado; para eso se usa PATCH.
    [$datos, $errores] = validarEquipo(leerCuerpoJson());
    if ($errores) {
        responderError(400, 'Hay datos inválidos.', $errores);
    }

    if (codigoExiste($pdo, $datos['codigo'], $id)) {
        responderError(409, 'Ya existe otro equipo con ese código.', ['codigo' => 'Código duplicado.']);
    }

    $st = $pdo->prepare(
        'UPDATE equipos
            SET codigo = :codigo, nombre = :nombre, categoria = :categoria,
                marca = :marca, modelo = :modelo, observaciones = :observaciones
          WHERE id = :id'
    );
    $st->execute([
        ':codigo'        => $datos['codigo'],
        ':nombre'        => $datos['nombre'],
        ':categoria'     => $datos['categoria'],
        ':marca'         => $datos['marca'],
        ':modelo'        => $datos['modelo'],
        ':observaciones' => $datos['observaciones'],
        ':id'            => $id,
    ]);

    responder(200, ['ok' => true, 'mensaje' => 'Equipo modificado.', 'datos' => obtenerEquipo($pdo, $id)]);
}

function manejarPatch(PDO $pdo)
{
    $id = obtenerId();
    exigirEquipoExistente($pdo, $id);

    $entrada = leerCuerpoJson();
    $estado = $entrada['estado'] ?? null;
    if (!is_string($estado) || !in_array($estado, ESTADOS_PERMITIDOS, true)) {
        responderError(400, 'Estado no permitido.', ['estado' => 'Valores válidos: ' . implode(', ', ESTADOS_PERMITIDOS)]);
    }

    if ($estado === 'inactivo') {
        exigirSinPrestamoAbierto($pdo, $id);
    }

    $st = $pdo->prepare('UPDATE equipos SET estado = :estado WHERE id = :id');
    $st->execute([':estado' => $estado, ':id' => $id]);

    responder(200, ['ok' => true, 'mensaje' => 'Estado actualizado.', 'datos' => obtenerEquipo($pdo, $id)]);
}

function manejarDelete(PDO $pdo)
{
    $id = obtenerId();
    $equipo = exigirEquipoExistente($pdo, $id);

    if ($equipo['estado'] === 'inactivo') {
        responder(200, ['ok' => true, 'mensaje' => 'El equipo ya estaba inactivo.', 'datos' => $equipo]);
    }

    exigirSinPrestamoAbierto($pdo, $id);

    // Desactivación lógica: no se borra la fila.
    $st = $pdo->prepare("UPDATE equipos SET estado = 'inactivo' WHERE id = :id");
    $st->execute([':id' => $id]);

    responder(200, ['ok' => true, 'mensaje' => 'Equipo desactivado.', 'datos' => obtenerEquipo($pdo, $id)]);
}

/* ---------- Punto de entrada ---------- */

try {
    $pdo = conectar();
    seguridad_exigir_sesion(in_array($_SERVER['REQUEST_METHOD'] ?? '', ['POST', 'PUT', 'PATCH', 'DELETE'], true));

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':    manejarGet($pdo);    break;
        case 'POST':   manejarPost($pdo);   break;
        case 'PUT':    manejarPut($pdo);    break;
        case 'PATCH':  manejarPatch($pdo);  break;
        case 'DELETE': manejarDelete($pdo); break;
        default:
            header('Allow: GET, POST, PUT, PATCH, DELETE');
            responderError(405, 'Método no permitido.');
    }
} catch (PDOException $e) {
    error_log('equipos.php (PDO): ' . $e->getMessage());
    if ($e->getCode() === '23000') {
        responderError(409, 'Conflicto con datos existentes (por ejemplo, un código duplicado).');
    }
    responderError(500, 'Error interno al procesar la solicitud.');
} catch (Throwable $e) {
    error_log('equipos.php: ' . $e->getMessage());
    responderError(500, 'Error interno al procesar la solicitud.');
}