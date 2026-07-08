<?php
/**
 * Sistema de Logs - Registro de Actividades
 * @version 2.0
 * @author Sistema de Oficios
 */

/**
 * Registrar una actividad en el sistema de logs
 * 
 * @param PDO $pdo Conexión a la base de datos
 * @param int $usuario_id ID del usuario
 * @param string $usuario_nombre Nombre de usuario
 * @param string $tipo Tipo de acción (ACCESO, CREACION, MODIFICACION, ELIMINACION, IMPORTACION, BACKUP)
 * @param string $accion Descripción de la acción realizada
 * @param string $modulo Módulo donde se realizó la acción
 * @param string $ip_address Dirección IP del usuario
 * @param array $detalles Detalles adicionales (opcional)
 * @return bool
 */
function registrarLog($pdo, $usuario_id, $usuario_nombre, $tipo, $accion, $modulo, $ip_address = null, $detalles = null) {
    try {
        // Si no se proporciona IP, obtenerla
        if ($ip_address === null) {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        
        // Preparar detalles en JSON si existen
        $detalles_json = $detalles ? json_encode($detalles, JSON_UNESCAPED_UNICODE) : null;
        
        $sql = "INSERT INTO logs (
                    usuario_id, 
                    usuario_nombre, 
                    tipo, 
                    accion, 
                    modulo, 
                    ip_address, 
                    detalles, 
                    fecha
                ) VALUES (
                    :usuario_id, 
                    :usuario_nombre, 
                    :tipo, 
                    :accion, 
                    :modulo, 
                    :ip_address, 
                    :detalles, 
                    NOW()
                )";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':usuario_id' => $usuario_id,
            ':usuario_nombre' => $usuario_nombre,
            ':tipo' => $tipo,
            ':accion' => $accion,
            ':modulo' => $modulo,
            ':ip_address' => $ip_address,
            ':detalles' => $detalles_json
        ]);
        
        if ($result) {
            return $pdo->lastInsertId();
        }
        
        return false;
        
    } catch (PDOException $e) {
        error_log("Error al registrar log: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtener logs con filtros
 * 
 * @param PDO $pdo Conexión a la base de datos
 * @param array $filtros Filtros (usuario, tipo, modulo, fecha_inicio, fecha_fin)
 * @param int $limite Límite de registros
 * @param int $offset Desplazamiento
 * @return array
 */
function obtenerLogs($pdo, $filtros = [], $limite = 100, $offset = 0) {
    try {
        $sql = "SELECT * FROM logs WHERE 1=1";
        $params = [];
        
        // Aplicar filtros
        if (!empty($filtros['usuario_id'])) {
            $sql .= " AND usuario_id = :usuario_id";
            $params[':usuario_id'] = $filtros['usuario_id'];
        }
        
        if (!empty($filtros['usuario_nombre'])) {
            $sql .= " AND usuario_nombre LIKE :usuario_nombre";
            $params[':usuario_nombre'] = '%' . $filtros['usuario_nombre'] . '%';
        }
        
        if (!empty($filtros['tipo'])) {
            $sql .= " AND tipo = :tipo";
            $params[':tipo'] = $filtros['tipo'];
        }
        
        if (!empty($filtros['modulo'])) {
            $sql .= " AND modulo = :modulo";
            $params[':modulo'] = $filtros['modulo'];
        }
        
        if (!empty($filtros['fecha_inicio'])) {
            $sql .= " AND fecha >= :fecha_inicio";
            $params[':fecha_inicio'] = $filtros['fecha_inicio'];
        }
        
        if (!empty($filtros['fecha_fin'])) {
            $sql .= " AND fecha <= :fecha_fin";
            $params[':fecha_fin'] = $filtros['fecha_fin'] . ' 23:59:59';
        }
        
        $sql .= " ORDER BY fecha DESC LIMIT :limite OFFSET :offset";
        $params[':limite'] = $limite;
        $params[':offset'] = $offset;
        
        $stmt = $pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            if ($key == ':limite' || $key == ':offset') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error al obtener logs: " . $e->getMessage());
        return [];
    }
}

/**
 * Contar total de logs con filtros
 * 
 * @param PDO $pdo Conexión a la base de datos
 * @param array $filtros Filtros aplicados
 * @return int
 */
function contarLogs($pdo, $filtros = []) {
    try {
        $sql = "SELECT COUNT(*) as total FROM logs WHERE 1=1";
        $params = [];
        
        if (!empty($filtros['usuario_id'])) {
            $sql .= " AND usuario_id = :usuario_id";
            $params[':usuario_id'] = $filtros['usuario_id'];
        }
        
        if (!empty($filtros['usuario_nombre'])) {
            $sql .= " AND usuario_nombre LIKE :usuario_nombre";
            $params[':usuario_nombre'] = '%' . $filtros['usuario_nombre'] . '%';
        }
        
        if (!empty($filtros['tipo'])) {
            $sql .= " AND tipo = :tipo";
            $params[':tipo'] = $filtros['tipo'];
        }
        
        if (!empty($filtros['modulo'])) {
            $sql .= " AND modulo = :modulo";
            $params[':modulo'] = $filtros['modulo'];
        }
        
        if (!empty($filtros['fecha_inicio'])) {
            $sql .= " AND fecha >= :fecha_inicio";
            $params[':fecha_inicio'] = $filtros['fecha_inicio'];
        }
        
        if (!empty($filtros['fecha_fin'])) {
            $sql .= " AND fecha <= :fecha_fin";
            $params[':fecha_fin'] = $filtros['fecha_fin'] . ' 23:59:59';
        }
        
        $stmt = $pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Error al contar logs: " . $e->getMessage());
        return 0;
    }
}

/**
 * Limpiar logs antiguos
 * 
 * @param PDO $pdo Conexión a la base de datos
 * @param int $dias Mantener logs de los últimos X días
 * @return int Número de registros eliminados
 */
function limpiarLogsAntiguos($pdo, $dias = 90) {
    try {
        $sql = "DELETE FROM logs WHERE fecha < DATE_SUB(NOW(), INTERVAL :dias DAY)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':dias', $dias, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
        
    } catch (PDOException $e) {
        error_log("Error al limpiar logs antiguos: " . $e->getMessage());
        return 0;
    }
}

/**
 * Obtener estadísticas de logs
 * 
 * @param PDO $pdo Conexión a la base de datos
 * @param int $dias Últimos X días
 * @return array
 */
function obtenerEstadisticasLogs($pdo, $dias = 30) {
    try {
        $stats = [];
        
        // Actividades por tipo
        $sql = "SELECT tipo, COUNT(*) as total 
                FROM logs 
                WHERE fecha >= DATE_SUB(NOW(), INTERVAL :dias DAY)
                GROUP BY tipo";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':dias', $dias, PDO::PARAM_INT);
        $stmt->execute();
        $stats['por_tipo'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Actividades por módulo
        $sql = "SELECT modulo, COUNT(*) as total 
                FROM logs 
                WHERE fecha >= DATE_SUB(NOW(), INTERVAL :dias DAY)
                GROUP BY modulo";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':dias', $dias, PDO::PARAM_INT);
        $stmt->execute();
        $stats['por_modulo'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Usuarios más activos
        $sql = "SELECT usuario_nombre, COUNT(*) as total 
                FROM logs 
                WHERE fecha >= DATE_SUB(NOW(), INTERVAL :dias DAY)
                GROUP BY usuario_nombre 
                ORDER BY total DESC 
                LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':dias', $dias, PDO::PARAM_INT);
        $stmt->execute();
        $stats['usuarios_activos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Actividad por hora
        $sql = "SELECT HOUR(fecha) as hora, COUNT(*) as total 
                FROM logs 
                WHERE fecha >= DATE_SUB(NOW(), INTERVAL :dias DAY)
                GROUP BY HOUR(fecha) 
                ORDER BY hora";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':dias', $dias, PDO::PARAM_INT);
        $stmt->execute();
        $stats['por_hora'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
        
    } catch (PDOException $e) {
        error_log("Error al obtener estadísticas de logs: " . $e->getMessage());
        return [];
    }
}

/**
 * Exportar logs a CSV
 * 
 * @param PDO $pdo Conexión a la base de datos
 * @param array $filtros Filtros aplicados
 * @return string CSV generado
 */
function exportarLogsCSV($pdo, $filtros = []) {
    try {
        $logs = obtenerLogs($pdo, $filtros, 10000, 0);
        
        if (empty($logs)) {
            return null;
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Cabeceras
        fputcsv($output, ['ID', 'Usuario', 'Tipo', 'Acción', 'Módulo', 'IP', 'Detalles', 'Fecha']);
        
        // Datos
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['id'],
                $log['usuario_nombre'],
                $log['tipo'],
                $log['accion'],
                $log['modulo'],
                $log['ip_address'],
                $log['detalles'],
                $log['fecha']
            ]);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
        
    } catch (Exception $e) {
        error_log("Error al exportar logs: " . $e->getMessage());
        return null;
    }
}
?>