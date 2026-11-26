<?php
declare(strict_types=1);

/*
 * Conexión PDO para XAMPP y producción.
 * La configuración privada api/db.local.php no se sube al repositorio.
 */

function conectar(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = [
        'host' => getenv('INVENTARIO_DB_HOST') ?: 'localhost',
        'port' => getenv('INVENTARIO_DB_PORT') ?: '3306',
        'name' => getenv('INVENTARIO_DB_NAME') ?: 'inventario_colegio',
        'user' => getenv('INVENTARIO_DB_USER') ?: 'root',
        'pass' => getenv('INVENTARIO_DB_PASS') ?: '',
    ];

    $archivoLocal = __DIR__ . '/db.local.php';
    if (is_file($archivoLocal)) {
        $configPrivada = require $archivoLocal;
        if (!is_array($configPrivada)) {
            error_log('api/db.local.php debe devolver un arreglo de configuración.');
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'mensaje' => 'La configuración de la base de datos no es válida.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $config = array_replace($config, $configPrivada);
    }

    $dsn = 'mysql:host=' . $config['host'] . ';port=' . $config['port']
        . ';dbname=' . $config['name'] . ';charset=utf8mb4';
    $opciones = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, $config['user'], $config['pass'], $opciones);
    } catch (PDOException $e) {
        error_log('Error de conexión a la base de datos: ' . $e->getMessage());
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            ['ok' => false, 'mensaje' => 'No se pudo conectar con la base de datos.'],
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    return $pdo;
}
