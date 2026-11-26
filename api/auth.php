<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_common.php';

header('Content-Type: application/json; charset=utf-8');
$pdo = conectar();
seguridad_iniciar_sesion();

function auth_responder(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function auth_validar_credenciales(string $usuario, string $clave): array
{
    $errores = [];
    $usuario = trim($usuario);
    if (mb_strlen($usuario, 'UTF-8') < 3 || mb_strlen($usuario, 'UTF-8') > 50) {
        $errores['nombre_usuario'] = 'Use entre 3 y 50 caracteres.';
    }
    if (strlen($clave) < 10 || strlen($clave) > 200) {
        $errores['contrasena'] = 'Use una contraseña de entre 10 y 200 caracteres.';
    }
    return [$usuario, $errores];
}

try {
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($metodo === 'GET') {
        $cantidad = (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
        $usuario = seguridad_usuario_actual();
        auth_responder(200, [
            'ok' => true,
            'configuracion_requerida' => $cantidad === 0,
            'autenticado' => $usuario !== null,
            'usuario' => $usuario,
            'csrf' => $usuario !== null ? seguridad_generar_csrf() : null,
        ]);
    }

    if ($metodo !== 'POST') {
        header('Allow: GET, POST');
        auth_responder(405, ['ok' => false, 'mensaje' => 'Método no permitido.']);
    }

    $entrada = seguridad_leer_json();
    $accion = $entrada['accion'] ?? '';

    if ($accion === 'logout') {
        seguridad_exigir_sesion(true);
        seguridad_cerrar_sesion();
        auth_responder(200, ['ok' => true, 'mensaje' => 'Sesión cerrada.']);
    }

    if ($accion === 'setup') {
        $nombre = is_string($entrada['nombre_usuario'] ?? null) ? $entrada['nombre_usuario'] : '';
        $clave = is_string($entrada['contrasena'] ?? null) ? $entrada['contrasena'] : '';
        $confirmacion = is_string($entrada['confirmacion'] ?? null) ? $entrada['confirmacion'] : '';
        [$nombre, $errores] = auth_validar_credenciales($nombre, $clave);
        if (!preg_match('/^[\pL\pN._-]+$/u', $nombre)) {
            $errores['nombre_usuario'] = 'Use letras, números, punto, guion o guion bajo.';
        }
        if ($clave !== $confirmacion) {
            $errores['confirmacion'] = 'Las contraseñas no coinciden.';
        }
        if ($errores) {
            auth_responder(400, ['ok' => false, 'mensaje' => 'Revise los datos.', 'errores' => $errores]);
        }
        $pdo->beginTransaction();
        $cantidad = (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
        if ($cantidad > 0) {
            $pdo->rollBack();
            auth_responder(409, ['ok' => false, 'mensaje' => 'La cuenta inicial ya fue creada.']);
        }
        $st = $pdo->prepare('INSERT INTO usuarios (nombre_usuario, contrasena_hash) VALUES (:nombre, :hash)');
        $st->execute([':nombre' => $nombre, ':hash' => password_hash($clave, PASSWORD_DEFAULT)]);
        $id = (int)$pdo->lastInsertId();
        $pdo->commit();
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $id;
        $_SESSION['nombre_usuario'] = $nombre;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        auth_responder(201, ['ok' => true, 'mensaje' => 'Cuenta administrativa creada.', 'usuario' => ['id' => $id, 'nombre_usuario' => $nombre], 'csrf' => $_SESSION['csrf_token']]);
    }

    if ($accion === 'login') {
        $nombre = is_string($entrada['nombre_usuario'] ?? null) ? trim($entrada['nombre_usuario']) : '';
        $clave = is_string($entrada['contrasena'] ?? null) ? $entrada['contrasena'] : '';
        $st = $pdo->prepare('SELECT id, nombre_usuario, contrasena_hash FROM usuarios WHERE nombre_usuario = :nombre LIMIT 1');
        $st->execute([':nombre' => $nombre]);
        $usuario = $st->fetch();
        if (!$usuario || !password_verify($clave, $usuario['contrasena_hash'])) {
            auth_responder(401, ['ok' => false, 'mensaje' => 'Usuario o contraseña incorrectos.']);
        }
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int)$usuario['id'];
        $_SESSION['nombre_usuario'] = $usuario['nombre_usuario'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        auth_responder(200, ['ok' => true, 'mensaje' => 'Sesión iniciada.', 'usuario' => ['id' => (int)$usuario['id'], 'nombre_usuario' => $usuario['nombre_usuario']], 'csrf' => $_SESSION['csrf_token']]);
    }

    auth_responder(400, ['ok' => false, 'mensaje' => 'Acción no reconocida.']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('auth.php: ' . $e->getMessage());
    auth_responder(500, ['ok' => false, 'mensaje' => 'Error interno al procesar la autenticación.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('auth.php: ' . $e->getMessage());
    auth_responder(500, ['ok' => false, 'mensaje' => 'Error interno al procesar la autenticación.']);
}
