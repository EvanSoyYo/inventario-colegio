<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_common.php';

try {
    seguridad_exigir_sesion(false);
    $pdo = conectar();
    $sql = "SELECT e.codigo, e.nombre, e.categoria, e.marca, e.modelo,
        CASE WHEN EXISTS (SELECT 1 FROM prestamos p WHERE p.equipo_id = e.id AND p.fecha_devolucion IS NULL)
             THEN 'prestado' ELSE e.estado END AS estado,
        e.observaciones, e.creado_en
        FROM equipos e ORDER BY e.nombre ASC, e.id ASC";
    $filas = $pdo->query($sql);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventario_colegio.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    $salida = fopen('php://output', 'wb');
    if ($salida === false) {
        throw new RuntimeException('No se pudo abrir la salida CSV.');
    }
    fwrite($salida, "\xEF\xBB\xBF");
    fputcsv($salida, ['Código', 'Nombre', 'Categoría', 'Marca', 'Modelo', 'Estado', 'Observaciones', 'Creado'], ';', '"', '\\');
    while ($fila = $filas->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($salida, array_values($fila), ';', '"', '\\');
    }
    fclose($salida);
} catch (Throwable $e) {
    error_log('exportar.php: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'mensaje' => 'No se pudo generar el archivo.'], JSON_UNESCAPED_UNICODE);
}
