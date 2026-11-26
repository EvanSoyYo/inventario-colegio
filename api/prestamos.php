<?php
declare(strict_types=1);

/*
 * api/prestamos.php
 * API JSON para el historial y el registro de préstamos.
 *
 *   GET   prestamos.php            -> historial (opcional: ?estado=activo|vencido|devuelto
 *                                                          &equipo_id=5  &id=12)
 *   POST  prestamos.php            -> registrar un préstamo (cuerpo JSON)
 *
 * Estado calculado de cada préstamo (campo "estado"):
 *   devuelto : fecha_devolucion tiene valor
 *   vencido  : sin devolución y la fecha límite es anterior a hoy (se compara por día)
 *   activo   : sin devolución y la fecha límite es hoy o posterior
 *
 * Un equipo está prestado mientras exista un préstamo con fecha_devolucion IS NULL.
 * 'prestado' nunca se guarda en equipos.estado.
 *
 * Fechas: formato YYYY-MM-DD.
 *
 * PENDIENTE ANTES DE PUBLICAR: autenticación para POST (y para devoluciones).
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_common.php';

header('Content-Type: application/json; charset=utf-8');

const ESTADOS_PRESTAMO = ['activo', 'vencido', 'devuelto'];

// Longitudes máximas (SUPUESTOS: ajustar a tu schema.sql si son distintas).
const LIMITE_RESPONSABLE   = 150;
const LIMITE_OBSERVACIONES = 500;

// Consulta base: préstamo + datos del equipo + estado calculado.
const SQL_BASE =
    'SELECT p.id, p.equipo_id, e.codigo AS equipo_codigo, e.nombre AS equipo_nombre, '
  . 'p.responsable, p.fecha_prestamo, p.fecha_limite, p.fecha_devolucion, '
  . 'p.observaciones, p.creado_en, '
  . "CASE WHEN p.fecha_devolucion IS NOT NULL THEN 'devuelto' "
  . "WHEN DATE(p.fecha_limite) < CURDATE() THEN 'vencido' "
  . "ELSE 'activo' END AS estado "
  . 'FROM prestamos p INNER JOIN equipos e ON e.id = p.equipo_id';

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

/** Deshace la transacción abierta (si hay) y responde con error. */
function abortar(PDO $pdo, int $codigoHttp, string $mensaje, array $detalles = [])
{
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    responderError($codigoHttp, $mensaje, $detalles);
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

function esTextoValido($valor): bool
{
    return is_string($valor) || is_int($valor);
}

/** Devuelve true si $texto es una fecha real con formato YYYY-MM-DD. */
function fechaValida($texto): bool
{
    if (!is_string($texto)) {
        return false;
    }
    $fecha = DateTime::createFromFormat('!Y-m-d', $texto);
    return $fecha !== false && $fecha->format('Y-m-d') === $texto;
}

function enteroPositivo($valor)
{
    if (!is_int($valor) && !is_string($valor)) {
        return false;
    }
    return filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}

function obtenerPrestamo(PDO $pdo, int $id)
{
    $st = $pdo->prepare(SQL_BASE . ' WHERE p.id = :id');
    $st->execute([':id' => $id]);
    return $st->fetch();   // false si no existe
}

/* ---------- GET ---------- */

function manejarGet(PDO $pdo)
{
    $condiciones = [];
    $parametros = [];

    if (isset($_GET['id']) && $_GET['id'] !== '') {
        $id = enteroPositivo($_GET['id']);
        if ($id === false) {
            responderError(400, 'El parámetro id no es un número entero positivo.');
        }
        $condiciones[] = 't.id = :id';
        $parametros[':id'] = $id;
    }

    if (isset($_GET['equipo_id']) && $_GET['equipo_id'] !== '') {
        $equipoId = enteroPositivo($_GET['equipo_id']);
        if ($equipoId === false) {
            responderError(400, 'El parámetro equipo_id no es un número entero positivo.');
        }
        $condiciones[] = 't.equipo_id = :equipo_id';
        $parametros[':equipo_id'] = $equipoId;
    }

    if (isset($_GET['estado']) && $_GET['estado'] !== '') {
        $estado = (string)$_GET['estado'];
        if (!in_array($estado, ESTADOS_PRESTAMO, true)) {
            responderError(400, 'Estado no permitido.', ['estado' => 'Valores válidos: ' . implode(', ', ESTADOS_PRESTAMO)]);
        }
        $condiciones[] = 't.estado = :estado';
        $parametros[':estado'] = $estado;
    }

    $sql = 'SELECT t.* FROM (' . SQL_BASE . ') AS t';
    if ($condiciones) {
        $sql .= ' WHERE ' . implode(' AND ', $condiciones);
    }
    $sql .= ' ORDER BY t.fecha_prestamo DESC, t.id DESC';

    $st = $pdo->prepare($sql);
    $st->execute($parametros);
    $filas = $st->fetchAll();

    responder(200, ['ok' => true, 'total' => count($filas), 'datos' => $filas]);
}

/* ---------- POST ---------- */

function validarPrestamo(array $entrada): array
{
    $errores = [];
    $datos = [];

    // equipo_id
    $equipoId = enteroPositivo($entrada['equipo_id'] ?? null);
    if ($equipoId === false) {
        $errores['equipo_id'] = 'Obligatorio: número entero positivo.';
    } else {
        $datos['equipo_id'] = $equipoId;
    }

    // responsable
    $responsable = $entrada['responsable'] ?? null;
    if (!esTextoValido($responsable) || trim((string)$responsable) === '') {
        $errores['responsable'] = 'Campo obligatorio.';
    } else {
        $responsable = trim((string)$responsable);
        if (mb_strlen($responsable, 'UTF-8') > LIMITE_RESPONSABLE) {
            $errores['responsable'] = 'Máximo ' . LIMITE_RESPONSABLE . ' caracteres.';
        } else {
            $datos['responsable'] = $responsable;
        }
    }

    // fechas
    $fechaPrestamo = $entrada['fecha_prestamo'] ?? null;
    $fechaLimite   = $entrada['fecha_limite'] ?? null;

    if ($fechaPrestamo === null || $fechaPrestamo === '') {
        $errores['fecha_prestamo'] = 'Campo obligatorio.';
    } elseif (!fechaValida($fechaPrestamo)) {
        $errores['fecha_prestamo'] = 'Fecha no válida. Use el formato YYYY-MM-DD.';
    } else {
        $datos['fecha_prestamo'] = $fechaPrestamo;
    }

    if ($fechaLimite === null || $fechaLimite === '') {
        $errores['fecha_limite'] = 'Campo obligatorio.';
    } elseif (!fechaValida($fechaLimite)) {
        $errores['fecha_limite'] = 'Fecha no válida. Use el formato YYYY-MM-DD.';
    } else {
        $datos['fecha_limite'] = $fechaLimite;
    }

    if (isset($datos['fecha_prestamo'], $datos['fecha_limite'])
        && $datos['fecha_limite'] < $datos['fecha_prestamo']) {
        $errores['fecha_limite'] = 'La fecha límite no puede ser anterior a la fecha del préstamo.';
    }

    // observaciones (opcional)
    $observaciones = $entrada['observaciones'] ?? '';
    if ($observaciones === null) {
        $observaciones = '';
    }
    if (!esTextoValido($observaciones)) {
        $errores['observaciones'] = 'Valor no válido.';
    } else {
        $observaciones = trim((string)$observaciones);
        if (mb_strlen($observaciones, 'UTF-8') > LIMITE_OBSERVACIONES) {
            $errores['observaciones'] = 'Máximo ' . LIMITE_OBSERVACIONES . ' caracteres.';
        } else {
            $datos['observaciones'] = $observaciones;
        }
    }

    return [$datos, $errores];
}

function manejarPost(PDO $pdo)
{
    [$datos, $errores] = validarPrestamo(leerCuerpoJson());
    if ($errores) {
        responderError(400, 'Hay datos inválidos.', $errores);
    }

    $pdo->beginTransaction();

    // Bloqueo de la fila del equipo: otra solicitud para el mismo equipo
    // espera aquí hasta que esta transacción termine (requiere InnoDB).
    $st = $pdo->prepare('SELECT id, estado FROM equipos WHERE id = :id FOR UPDATE');
    $st->execute([':id' => $datos['equipo_id']]);
    $equipo = $st->fetch();

    if (!$equipo) {
        abortar($pdo, 404, 'El equipo no existe.');
    }

    if ($equipo['estado'] !== 'disponible') {
        abortar($pdo, 409, 'El equipo no está disponible para préstamo (estado: ' . $equipo['estado'] . ').');
    }

    $st = $pdo->prepare('SELECT 1 FROM prestamos WHERE equipo_id = :id AND fecha_devolucion IS NULL LIMIT 1');
    $st->execute([':id' => $datos['equipo_id']]);
    if ($st->fetchColumn()) {
        abortar($pdo, 409, 'El equipo ya tiene un préstamo sin devolución.');
    }

    $st = $pdo->prepare(
        'INSERT INTO prestamos (equipo_id, responsable, fecha_prestamo, fecha_limite, observaciones)
         VALUES (:equipo_id, :responsable, :fecha_prestamo, :fecha_limite, :observaciones)'
    );
    $st->execute([
        ':equipo_id'      => $datos['equipo_id'],
        ':responsable'    => $datos['responsable'],
        ':fecha_prestamo' => $datos['fecha_prestamo'],
        ':fecha_limite'   => $datos['fecha_limite'],
        ':observaciones'  => $datos['observaciones'],
    ]);
    $id = (int)$pdo->lastInsertId();

    $pdo->commit();

    responder(201, ['ok' => true, 'mensaje' => 'Préstamo registrado.', 'datos' => obtenerPrestamo($pdo, $id)]);
}

/* ---------- Punto de entrada ---------- */

$pdo = null;

try {
    $pdo = conectar();
    seguridad_exigir_sesion(($_SERVER['REQUEST_METHOD'] ?? '') === 'POST');

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':  manejarGet($pdo);  break;
        case 'POST': manejarPost($pdo); break;
        default:
            header('Allow: GET, POST');
            responderError(405, 'Método no permitido.');
    }
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('prestamos.php: ' . $e->getMessage());
    responderError(500, 'Error interno al procesar la solicitud.');
}