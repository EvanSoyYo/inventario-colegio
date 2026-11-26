<?php
declare(strict_types=1);

function seguridad_iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $segura = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('inventario_sesion');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $segura,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function seguridad_responder(int $codigo, array $datos): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function seguridad_error(int $codigo, string $mensaje): void
{
    seguridad_responder($codigo, ['ok' => false, 'mensaje' => $mensaje]);
}

function seguridad_leer_json(): array
{
    $contenido = file_get_contents('php://input');
    if ($contenido === false || trim($contenido) === '') {
        seguridad_error(400, 'El cuerpo JSON está vacío.');
    }
    $datos = json_decode($contenido, true);
    if (!is_array($datos)) {
        seguridad_error(400, 'El cuerpo no contiene JSON válido.');
    }
    return $datos;
}

function seguridad_generar_csrf(): string
{
    seguridad_iniciar_sesion();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function seguridad_exigir_sesion(bool $requiereCsrf = false): void
{
    seguridad_iniciar_sesion();
    if (empty($_SESSION['usuario_id'])) {
        seguridad_error(401, 'Inicie sesión para continuar.');
    }
    if ($requiereCsrf) {
        $recibido = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $esperado = $_SESSION['csrf_token'] ?? '';
        if ($esperado === '' || !is_string($recibido) || !hash_equals($esperado, $recibido)) {
            seguridad_error(403, 'La solicitud de seguridad expiró. Actualice la página e intente de nuevo.');
        }
    }
}

function seguridad_usuario_actual(): ?array
{
    seguridad_iniciar_sesion();
    if (empty($_SESSION['usuario_id'])) {
        return null;
    }
    return [
        'id' => (int)$_SESSION['usuario_id'],
        'nombre_usuario' => (string)($_SESSION['nombre_usuario'] ?? ''),
    ];
}

function seguridad_cerrar_sesion(): void
{
    seguridad_iniciar_sesion();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $parametros = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $parametros['path'],
            'domain' => $parametros['domain'],
            'secure' => $parametros['secure'],
            'httponly' => $parametros['httponly'],
            'samesite' => $parametros['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}
