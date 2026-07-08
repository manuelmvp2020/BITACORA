<?php
// log_actividad.php - Clase para manejar el registro de actividad
require_once 'config.php';

class LogActividad {
    private $conn;
    private $usuario_actual;
    private $ip;
    private $user_agent;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->usuario_actual = $this->getUsuarioActual();
        $this->ip = $this->getClientIP();
        $this->user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';
    }
    
    /**
     * Obtener el usuario actual (de sesión o sistema)
     */
    private function getUsuarioActual() {
        if (isset($_SESSION['usuario']) && !empty($_SESSION['usuario'])) {
            return $_SESSION['usuario'];
        }
        // Intentar obtener de otras fuentes
        if (isset($_SESSION['username'])) {
            return $_SESSION['username'];
        }
        if (isset($_SESSION['user'])) {
            return $_SESSION['user'];
        }
        return 'Sistema';
    }
    
    /**
     * Obtener IP real del cliente
     */
    private function getClientIP() {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'DESCONOCIDO';
        return $ipaddress;
    }
    
    /**
     * Registrar una acción en el log
     */
    public function registrar($accion, $descripcion = '', $tabla_afectada = null, $id_registro = null, $datos_anteriores = null, $datos_nuevos = null, $nivel = 'INFO') {
        try {
            // Preparar datos anteriores y nuevos como JSON si son arrays
            if (is_array($datos_anteriores)) {
                $datos_anteriores = json_encode($datos_anteriores, JSON_UNESCAPED_UNICODE);
            }
            if (is_array($datos_nuevos)) {
                $datos_nuevos = json_encode($datos_nuevos, JSON_UNESCAPED_UNICODE);
            }
            
            $sql = "INSERT INTO log_actividad (usuario, accion, descripcion, tabla_afectada, id_registro_afectado, datos_anteriores, datos_nuevos, ip_address, user_agent, nivel, fecha_hora) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param(
                "ssssisssss",
                $this->usuario_actual,
                $accion,
                $descripcion,
                $tabla_afectada,
                $id_registro,
                $datos_anteriores,
                $datos_nuevos,
                $this->ip,
                $this->user_agent,
                $nivel
            );
            
            if ($stmt->execute()) {
                return true;
            } else {
                error_log("Error al registrar log: " . $this->conn->error);
                return false;
            }
        } catch (Exception $e) {
            error_log("Excepción en log: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registrar creación de registro
     */
    public function registrarCreacion($tabla, $id_registro, $datos_nuevos, $descripcion = '') {
        $accion = 'CREAR';
        if (empty($descripcion)) {
            $descripcion = "Se creó un nuevo registro en {$tabla} con ID: {$id_registro}";
        }
        return $this->registrar($accion, $descripcion, $tabla, $id_registro, null, $datos_nuevos, 'INFO');
    }
    
    /**
     * Registrar edición de registro
     */
    public function registrarEdicion($tabla, $id_registro, $datos_anteriores, $datos_nuevos, $descripcion = '') {
        $accion = 'EDITAR';
        if (empty($descripcion)) {
            $descripcion = "Se editó el registro en {$tabla} con ID: {$id_registro}";
        }
        return $this->registrar($accion, $descripcion, $tabla, $id_registro, $datos_anteriores, $datos_nuevos, 'INFO');
    }
    
    /**
     * Registrar eliminación de registro
     */
    public function registrarEliminacion($tabla, $id_registro, $datos_anteriores, $descripcion = '') {
        $accion = 'ELIMINAR';
        if (empty($descripcion)) {
            $descripcion = "Se eliminó el registro en {$tabla} con ID: {$id_registro}";
        }
        return $this->registrar($accion, $descripcion, $tabla, $id_registro, $datos_anteriores, null, 'WARNING');
    }
    
    /**
     * Registrar importación de datos
     */
    public function registrarImportacion($tabla, $cantidad_registros, $archivo = '') {
        $accion = 'IMPORTAR';
        $descripcion = "Se importaron {$cantidad_registros} registros en {$tabla}" . ($archivo ? " desde archivo: {$archivo}" : "");
        return $this->registrar($accion, $descripcion, $tabla, null, null, ['cantidad' => $cantidad_registros, 'archivo' => $archivo], 'INFO');
    }
    
    /**
     * Registrar exportación de datos
     */
    public function registrarExportacion($tabla, $formato, $cantidad_registros) {
        $accion = 'EXPORTAR';
        $descripcion = "Se exportaron {$cantidad_registros} registros de {$tabla} a formato {$formato}";
        return $this->registrar($accion, $descripcion, $tabla, null, null, ['formato' => $formato, 'cantidad' => $cantidad_registros], 'INFO');
    }
    
    /**
     * Registrar inicio de sesión
     */
    public function registrarLogin($usuario, $exitoso = true, $motivo = '') {
        $accion = $exitoso ? 'LOGIN_EXITOSO' : 'LOGIN_FALLIDO';
        $nivel = $exitoso ? 'INFO' : 'WARNING';
        $descripcion = $exitoso ? "Inicio de sesión exitoso para usuario: {$usuario}" : "Intento fallido de inicio de sesión para usuario: {$usuario}" . ($motivo ? " - Motivo: {$motivo}" : "");
        
        // Guardar usuario temporal para este log
        $usuario_temp = $this->usuario_actual;
        $this->usuario_actual = $usuario;
        
        $resultado = $this->registrar($accion, $descripcion, 'usuarios', null, null, ['exitoso' => $exitoso], $nivel);
        
        // Restaurar usuario
        $this->usuario_actual = $usuario_temp;
        
        return $resultado;
    }
    
    /**
     * Registrar cierre de sesión
     */
    public function registrarLogout($usuario) {
        $accion = 'LOGOUT';
        $descripcion = "Cierre de sesión para usuario: {$usuario}";
        return $this->registrar($accion, $descripcion, 'usuarios', null, null, null, 'INFO');
    }
    
    /**
     * Registrar error del sistema
     */
    public function registrarError($error, $archivo, $linea, $descripcion_adicional = '') {
        $accion = 'ERROR_SISTEMA';
        $descripcion = "Error: {$error} en {$archivo}:{$linea}";
        if ($descripcion_adicional) {
            $descripcion .= " - {$descripcion_adicional}";
        }
        return $this->registrar($accion, $descripcion, null, null, null, ['archivo' => $archivo, 'linea' => $linea, 'error' => $error], 'ERROR');
    }
    
    /**
     * Registrar consulta/búsqueda
     */
    public function registrarBusqueda($termino, $filtros, $resultados_encontrados) {
        $accion = 'BUSCAR';
        $descripcion = "Búsqueda realizada: '{$termino}' con filtros: " . json_encode($filtros) . " - Resultados: {$resultados_encontrados}";
        return $this->registrar($accion, $descripcion, 'registros_ids', null, null, ['termino' => $termino, 'filtros' => $filtros, 'resultados' => $resultados_encontrados], 'INFO');
    }
    
    /**
     * Registrar cambio de configuración
     */
    public function registrarConfiguracion($configuracion, $valor_anterior, $valor_nuevo) {
        $accion = 'CONFIGURACION';
        $descripcion = "Cambio de configuración: {$configuracion} de '{$valor_anterior}' a '{$valor_nuevo}'";
        return $this->registrar($accion, $descripcion, 'configuracion', null, $valor_anterior, $valor_nuevo, 'INFO');
    }
    
    /**
     * Registrar respaldo de base de datos
     */
    public function registrarRespaldo($tipo, $archivo, $tamano, $exitoso = true) {
        $accion = $exitoso ? 'RESPALDO_EXITOSO' : 'RESPALDO_FALLIDO';
        $nivel = $exitoso ? 'INFO' : 'ERROR';
        $descripcion = $exitoso 
            ? "Respaldo {$tipo} creado exitosamente: {$archivo} (Tamaño: {$tamano})"
            : "Fallo al crear respaldo {$tipo}";
        return $this->registrar($accion, $descripcion, 'sistema', null, null, ['tipo' => $tipo, 'archivo' => $archivo, 'tamano' => $tamano], $nivel);
    }
    
    /**
     * Registrar actividad masiva (varios registros)
     */
    public function registrarActividadMasiva($accion, $tabla, $ids_registros, $descripcion_adicional = '') {
        $accion_log = $accion . '_MASIVO';
        $cantidad = count($ids_registros);
        $descripcion = "Acción masiva {$accion} sobre {$cantidad} registros en {$tabla}. IDs: " . implode(', ', $ids_registros);
        if ($descripcion_adicional) {
            $descripcion .= " - {$descripcion_adicional}";
        }
        return $this->registrar($accion_log, $descripcion, $tabla, null, null, ['ids' => $ids_registros, 'cantidad' => $cantidad], 'INFO');
    }
    
    /**
     * Obtener logs con filtros
     */
    public function obtenerLogs($filtros = [], $limite = 100, $offset = 0) {
        $sql = "SELECT * FROM log_actividad WHERE 1=1";
        $params = [];
        $types = "";
        
        if (!empty($filtros['usuario'])) {
            $sql .= " AND usuario LIKE ?";
            $params[] = "%{$filtros['usuario']}%";
            $types .= "s";
        }
        
        if (!empty($filtros['accion'])) {
            $sql .= " AND accion = ?";
            $params[] = $filtros['accion'];
            $types .= "s";
        }
        
        if (!empty($filtros['nivel'])) {
            $sql .= " AND nivel = ?";
            $params[] = $filtros['nivel'];
            $types .= "s";
        }
        
        if (!empty($filtros['tabla'])) {
            $sql .= " AND tabla_afectada = ?";
            $params[] = $filtros['tabla'];
            $types .= "s";
        }
        
        if (!empty($filtros['fecha_desde'])) {
            $sql .= " AND DATE(fecha_hora) >= ?";
            $params[] = $filtros['fecha_desde'];
            $types .= "s";
        }
        
        if (!empty($filtros['fecha_hasta'])) {
            $sql .= " AND DATE(fecha_hora) <= ?";
            $params[] = $filtros['fecha_hasta'];
            $types .= "s";
        }
        
        if (!empty($filtros['id_registro'])) {
            $sql .= " AND id_registro_afectado = ?";
            $params[] = $filtros['id_registro'];
            $types .= "i";
        }
        
        $sql .= " ORDER BY fecha_hora DESC LIMIT ? OFFSET ?";
        $params[] = $limite;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $resultado = $stmt->get_result();
        
        $logs = [];
        while ($row = $resultado->fetch_assoc()) {
            // Decodificar JSON si es necesario
            if ($row['datos_anteriores']) {
                $row['datos_anteriores_decoded'] = json_decode($row['datos_anteriores'], true);
            }
            if ($row['datos_nuevos']) {
                $row['datos_nuevos_decoded'] = json_decode($row['datos_nuevos'], true);
            }
            $logs[] = $row;
        }
        
        return $logs;
    }
    
    /**
     * Contar logs (para paginación)
     */
    public function contarLogs($filtros = []) {
        $sql = "SELECT COUNT(*) as total FROM log_actividad WHERE 1=1";
        $params = [];
        $types = "";
        
        if (!empty($filtros['usuario'])) {
            $sql .= " AND usuario LIKE ?";
            $params[] = "%{$filtros['usuario']}%";
            $types .= "s";
        }
        
        if (!empty($filtros['accion'])) {
            $sql .= " AND accion = ?";
            $params[] = $filtros['accion'];
            $types .= "s";
        }
        
        if (!empty($filtros['nivel'])) {
            $sql .= " AND nivel = ?";
            $params[] = $filtros['nivel'];
            $types .= "s";
        }
        
        if (!empty($filtros['fecha_desde'])) {
            $sql .= " AND DATE(fecha_hora) >= ?";
            $params[] = $filtros['fecha_desde'];
            $types .= "s";
        }
        
        if (!empty($filtros['fecha_hasta'])) {
            $sql .= " AND DATE(fecha_hora) <= ?";
            $params[] = $filtros['fecha_hasta'];
            $types .= "s";
        }
        
        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $resultado = $stmt->get_result();
        $row = $resultado->fetch_assoc();
        
        return $row['total'];
    }
    
    /**
     * Obtener estadísticas de actividad
     */
    public function obtenerEstadisticas($dias = 30) {
        $stats = [];
        
        // Total de acciones por día
        $sql = "SELECT DATE(fecha_hora) as fecha, COUNT(*) as total 
                FROM log_actividad 
                WHERE fecha_hora >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(fecha_hora)
                ORDER BY fecha DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $dias);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $stats['por_dia'] = [];
        while ($row = $resultado->fetch_assoc()) {
            $stats['por_dia'][$row['fecha']] = $row['total'];
        }
        
        // Acciones más comunes
        $sql = "SELECT accion, COUNT(*) as total 
                FROM log_actividad 
                WHERE fecha_hora >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY accion 
                ORDER BY total DESC 
                LIMIT 10";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $dias);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $stats['acciones'] = [];
        while ($row = $resultado->fetch_assoc()) {
            $stats['acciones'][$row['accion']] = $row['total'];
        }
        
        // Usuarios más activos
        $sql = "SELECT usuario, COUNT(*) as total 
                FROM log_actividad 
                WHERE fecha_hora >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY usuario 
                ORDER BY total DESC 
                LIMIT 10";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $dias);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $stats['usuarios_activos'] = [];
        while ($row = $resultado->fetch_assoc()) {
            $stats['usuarios_activos'][$row['usuario']] = $row['total'];
        }
        
        // Distribución por nivel
        $sql = "SELECT nivel, COUNT(*) as total 
                FROM log_actividad 
                WHERE fecha_hora >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY nivel";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $dias);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $stats['niveles'] = [];
        while ($row = $resultado->fetch_assoc()) {
            $stats['niveles'][$row['nivel']] = $row['total'];
        }
        
        return $stats;
    }
    
    /**
     * Limpiar logs antiguos
     */
    public function limpiarLogsAntiguos($dias = 90) {
        $sql = "DELETE FROM log_actividad WHERE fecha_hora < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $dias);
        $stmt->execute();
        return $stmt->affected_rows;
    }
}