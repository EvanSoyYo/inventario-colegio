<?php
declare(strict_types=1);

/*
 * api/devoluciones.php
 * Registra la devolución de un préstamo.
 *
 *   POST devoluciones.php   cuerpo JSON: {"prestamo_id": 12, "fecha_devolucion": "2026-11-25"}
 *
 * Solo se actualiza prestamos.fecha_devolucion. No se borra historial y
 * equipos.estado no se modifica: el equipo deja de estar prestado porque
 * el préstamo ya no tiene fecha_devolucion NULL.
 *
 * Fechas: formato YYYY-MM-DD.
 *
 * PENDIENTE ANTES DE PUBLICAR: autenticación para registrar devoluciones.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_common.php';

header('Content-Type: application/json; charset=utf-8');

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

/** Devuelve el préstamo actualizado y su equipo asociado. */
function obtenerResultado(PDO $pdo, int $prestamoId): array
{
    $st = $pdo->prepare(
        'SELECT p.id, p.equipo_id, p.responsable, p.fecha_prestamo, p.fecha_limite,
                p.fecha_devolucion, p.observaciones, p.creado_en,
                CASE WHEN p.fecha_devolucion IS NOT NULL THEN \'devuelto\'
                     WHEN DATE(p.fecha_limite) < CURDATE() THEN \'vencido\'
                     ELSE \'activo\' END AS estado
           FROM prestamos p
          WHERE p.id = :id'
    );
    $st->execute([':id' => $prestamoId]);
    $prestamo = $st->fetch();

    $st = $pdo->prepare(
        'SELECT e.id, e.codigo, e.nombre, e.categoria, e.marca, e.modelo, e.estado,
                CASE WHEN EXISTS (SELECT 1 FROM prestamos x
                                   WHERE x.equipo_id = e.id AND x.fecha_devolucion IS NULL)
                     THEN \'prestado\' ELSE e.estado END AS estado_actual
           FROM equipos e
          WHERE e.id = :id'
    );
    $st->execute([':id' => $prestamo['equipo_id']]);
    $equipo = $st->fetch();

    return ['prestamo' => $prestamo, 'equipo' => $equipo];
}

/* ---------- POST ---------- */

function manejarPost(PDO $pdo)
{
    $entrada = leerCuerpoJson();
    $errores = [];

    $prestamoId = enteroPositivo($entrada['prestamo_id'] ?? null);
    if ($prestamoId === false) {
        $errores['prestamo_id'] = 'Obligatorio: número entero positivo.';
    }

    $fecha = $entrada['fecha_devolucion'] ?? null;
    if ($fecha === null || $fecha === '') {
        $errores['fecha_devolucion'] = 'Campo obligatorio.';
    } elseif (!fechaValida($fecha)) {
        $errores['fecha_devolucion'] = 'Fecha no válida. Use el formato YYYY-MM-DD.';
    }

    if ($errores) {
        responderError(400, 'Hay datos inválidos.', $errores);
    }

    $pdo->beginTransaction();

    // Bloqueo de la fila del préstamo: evita registrar dos devoluciones a la vez (requiere InnoDB).
    $st = $pdo->prepare(
        'SELECT id, DATE(fecha_prestamo) AS fecha_prestamo, fecha_devolucion, CURDATE() AS hoy
           FROM prestamos
          WHERE id = :id
            FOR UPDATE'
    );
    $st->execute([':id' => $prestamoId]);
    $prestamo = $st->fetch();

    if (!$prestamo) {
        abortar($pdo, 404, 'El préstamo no existe.');
    }

    if ($prestamo['fecha_devolucion'] !== null) {
        abortar($pdo, 409, 'Este préstamo ya fue devuelto.');
    }

    if ($fecha < $prestamo['fecha_prestamo']) {
        abortar($pdo, 400, 'Hay datos inválidos.', [
            'fecha_devolucion' => 'No puede ser anterior a la fecha del préstamo (' . $prestamo['fecha_prestamo'] . ').',
        ]);
    }

    if ($fecha > $prestamo['hoy']) {
        abortar($pdo, 400, 'Hay datos inválidos.', [
            'fecha_devolucion' => 'No puede ser una fecha futura.',
        ]);
    }

    $st = $pdo->prepare(
        'UPDATE prestamos SET fecha_devolucion = :fecha
          WHERE id = :id AND fecha_devolucion IS NULL'
    );
    $st->execute([':fecha' => $fecha, ':id' => $prestamoId]);

    $pdo->commit();

    responder(200, [
        'ok'      => true,
        'mensaje' => 'Devolución registrada.',
        'datos'   => obtenerResultado($pdo, $prestamoId),
    ]);
}

/* ---------- Punto de entrada ---------- */

$pdo = null;

try {
    $pdo = conectar();
    seguridad_exigir_sesion(($_SERVER['REQUEST_METHOD'] ?? '') === 'POST');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        manejarPost($pdo);
    } else {
        header('Allow: POST');
        responderError(405, 'Método no permitido.');
    }
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('devoluciones.php: ' . $e->getMessage());
    responderError(500, 'Error interno al procesar la solicitud.');
}