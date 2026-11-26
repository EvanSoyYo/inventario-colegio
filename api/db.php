<?php
declare(strict_types=1);

/*
 * Conexión local a la base de datos del proyecto.
 * Uso desde un endpoint: require_once __DIR__ . '/db.php'; $pdo = conectar();
 */
const DB_HOST = 'localhost';
const DB_PORT = '3306';
const DB_NAME = 'inventario_colegio';
const DB_USER = 'root';
const DB_PASS = ''; // Cambiar si root tiene contraseña en esta instalación local.

function conectar(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT
        . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $opciones = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
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
