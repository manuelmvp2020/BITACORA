<?php
// funciones_logs.php - Funciones adicionales para gestión de logs
// NOTA: La función registrarLog() ya está definida en config.php

// Verificar si las funciones auxiliares ya existen antes de declararlas
if (!function_exists('obtenerLogs')) {
    /**
     * Función para obtener logs con filtros
     */
    function obtenerLogs($conn, $modulo = null, $usuario = null, $fecha_inicio = null, $fecha_fin = null, $limit = 100) {
        $sql = "SELECT * FROM logs WHERE 1=1";
        $params = [];
        $types = "";
        
        if ($modulo) {
            $sql .= " AND modulo = ?";
            $params[] = $modulo;
            $types .= "s";
        }
        
        if ($usuario) {
            $sql .= " AND usuario = ?";
            $params[] = $usuario;
            $types .= "s";
        }
        
        if ($fecha_inicio) {
            $sql .= " AND fecha >= ?";
            $params[] = $fecha_inicio;
            $types .= "s";
        }
        
        if ($fecha_fin) {
            $sql .= " AND fecha <= ?";
            $params[] = $fecha_fin;
            $types .= "s";
        }
        
        $sql .= " ORDER BY fecha DESC LIMIT ?";
        $params[] = $limit;
        $types .= "i";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        $stmt->close();
        
        return $logs;
    }
}

if (!function_exists('exportarLogsCSV')) {
    /**
     * Función para exportar logs a CSV
     */
    function exportarLogsCSV($conn, $modulo = null, $fecha_inicio = null, $fecha_fin = null) {
        $logs = obtenerLogs($conn, $modulo, null, $fecha_inicio, $fecha_fin, 10000);
        
        $filename = "logs_" . date('Y-m-d') . ".csv";
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Usuario', 'Acción', 'Módulo', 'IP', 'Fecha']);
        
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['id'],
                $log['usuario'],
                $log['accion'],
                $log['modulo'],
                $log['ip_address'] ?? 'N/A',
                $log['fecha']
            ]);
        }
        
        fclose($output);
        exit();
    }
}

if (!function_exists('limpiarLogsAntiguos')) {
    /**
     * Limpia logs más antiguos que X días
     * @param mysqli $conn Conexión a la base de datos
     * @param int $dias Número de días a mantener
     * @return int Número de registros eliminados
     */
    function limpiarLogsAntiguos($conn, $dias = 180) {
        $sql = "DELETE FROM logs WHERE fecha < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $dias);
        $stmt->execute();
        $eliminados = $stmt->affected_rows;
        $stmt->close();
        return $eliminados;
    }
}

if (!function_exists('obtenerEstadisticasLogs')) {
    /**
     * Obtiene estadísticas de logs
     * @param mysqli $conn Conexión a la base de datos
     * @return array Estadísticas
     */
    function obtenerEstadisticasLogs($conn) {
        $stats = [];
        
        // Total de registros
        $result = $conn->query("SELECT COUNT(*) as total FROM logs");
        $stats['total'] = $result->fetch_assoc()['total'];
        
        // Registros por módulo
        $result = $conn->query("SELECT modulo, COUNT(*) as cantidad FROM logs GROUP BY modulo ORDER BY cantidad DESC");
        $stats['por_modulo'] = [];
        while ($row = $result->fetch_assoc()) {
            $stats['por_modulo'][] = $row;
        }
        
        // Registros por día (últimos 7 días)
        $result = $conn->query("
            SELECT DATE(fecha) as dia, COUNT(*) as cantidad 
            FROM logs 
            WHERE fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(fecha) 
            ORDER BY dia DESC
        ");
        $stats['ultimos_7_dias'] = [];
        while ($row = $result->fetch_assoc()) {
            $stats['ultimos_7_dias'][] = $row;
        }
        
        return $stats;
    }
}
?>