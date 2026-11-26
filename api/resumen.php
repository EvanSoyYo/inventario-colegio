<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_common.php';
header('Content-Type: application/json; charset=utf-8');

try {
    seguridad_exigir_sesion(false);
    $pdo = conectar();
    $sql = "SELECT COUNT(*) AS total,
        SUM(CASE WHEN e.estado = 'disponible' AND NOT EXISTS
            (SELECT 1 FROM prestamos p WHERE p.equipo_id = e.id AND p.fecha_devolucion IS NULL) THEN 1 ELSE 0 END) AS disponibles,
        SUM(CASE WHEN EXISTS
            (SELECT 1 FROM prestamos p WHERE p.equipo_id = e.id AND p.fecha_devolucion IS NULL) THEN 1 ELSE 0 END) AS prestados,
        SUM(CASE WHEN e.estado = 'mantenimiento' AND NOT EXISTS
            (SELECT 1 FROM prestamos p WHERE p.equipo_id = e.id AND p.fecha_devolucion IS NULL) THEN 1 ELSE 0 END) AS mantenimiento,
        SUM(CASE WHEN e.estado = 'inactivo' THEN 1 ELSE 0 END) AS inactivos
        FROM equipos e";
    $resumen = $pdo->query($sql)->fetch() ?: [];
    $vencidos = (int)$pdo->query("SELECT COUNT(*) FROM prestamos WHERE fecha_devolucion IS NULL AND fecha_limite < CURDATE()")->fetchColumn();
    seguridad_responder(200, [
        'ok' => true,
        'datos' => [
            'total' => (int)($resumen['total'] ?? 0),
            'disponibles' => (int)($resumen['disponibles'] ?? 0),
            'prestados' => (int)($resumen['prestados'] ?? 0),
            'mantenimiento' => (int)($resumen['mantenimiento'] ?? 0),
            'inactivos' => (int)($resumen['inactivos'] ?? 0),
            'prestamos_vencidos' => $vencidos,
        ],
    ]);
} catch (Throwable $e) {
    error_log('resumen.php: ' . $e->getMessage());
    seguridad_error(500, 'No se pudo cargar el resumen.');
}
