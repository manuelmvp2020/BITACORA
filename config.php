<?php
// config.php - Configuración de la conexión a la base de datos y funciones comunes

// ==============================================
// MANEJO DE SESIÓN CORREGIDO
// ==============================================
// Iniciar sesión SOLO si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$usuario_db = 'root'; // Cambia esto por tu usuario de MySQL
$contrasena_db = 'mysql'; // Cambia esto por tu contraseña de MySQL
$nombre_db = 'bitacora_ids';


// Crear conexión
$conn = new mysqli($host, $usuario_db, $contrasena_db, $nombre_db);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Establecer charset
$conn->set_charset("utf8");

// ==============================================
// FUNCIÓN PARA REGISTRAR LOGS
// ==============================================

/**
 * Registra una actividad en la tabla de logs
 * @param mysqli $conn Conexión a la base de datos
 * @param string $usuario Nombre del usuario que realiza la acción
 * @param string $accion Descripción de la acción realizada
 * @param string $modulo Nombre del archivo/módulo (ej. 'crear', 'editar', 'index')
 * @param string|null $ip_address Dirección IP del usuario (opcional)
 * @param mixed $detalles Detalles adicionales en formato array o JSON (opcional)
 */
function registrarLog($conn, $usuario, $accion, $modulo, $ip_address = null, $detalles = null) {
    try {
        // Verificar si la tabla logs existe
        $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'logs'");
        if (mysqli_num_rows($check_table) == 0) {
            // Crear la tabla si no existe
            $create_sql = "
                CREATE TABLE IF NOT EXISTS logs (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    usuario VARCHAR(100) NOT NULL,
                    accion TEXT NOT NULL,
                    modulo VARCHAR(50) NOT NULL,
                    fecha DATETIME NOT NULL,
                    ip_address VARCHAR(45) DEFAULT NULL,
                    detalles TEXT DEFAULT NULL,
                    PRIMARY KEY (id),
                    KEY idx_usuario (usuario),
                    KEY idx_modulo (modulo),
                    KEY idx_fecha (fecha)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ";
            mysqli_query($conn, $create_sql);
        }
        
        // Preparar IP (si no se proporciona, obtenerla automáticamente)
        if ($ip_address === null) {
            $ip_address = obtenerIP();
        }
        
        // Convertir detalles a JSON si es un array
        if ($detalles !== null && is_array($detalles)) {
            $detalles = json_encode($detalles, JSON_UNESCAPED_UNICODE);
        }
        
        // Insertar log
        $stmt = $conn->prepare("INSERT INTO logs (usuario, accion, modulo, fecha, ip_address, detalles) VALUES (?, ?, ?, NOW(), ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssss", $usuario, $accion, $modulo, $ip_address, $detalles);
            $stmt->execute();
            $stmt->close();
            return true;
        }
        return false;
    } catch (Exception $e) {
        // Silenciar el error para no interrumpir la aplicación
        error_log("Error en registrarLog: " . $e->getMessage());
        return false;
    }
}

// ==============================================
// FUNCIÓN PARA OBTENER LA IP DEL USUARIO
// ==============================================

/**
 * Obtiene la dirección IP real del usuario
 * @return string Dirección IP
 */
function obtenerIP() {
    $ip = '0.0.0.0';
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    // Si es una IP compuesta (proxy), tomar la primera
    if (strpos($ip, ',') !== false) {
        $ips = explode(',', $ip);
        $ip = trim($ips[0]);
    }
    
    return $ip;
}

// ==============================================
// FUNCIÓN PARA FORMATEAR NÚMEROS
// ==============================================

/**
 * Formatea un número con separadores de miles y decimales
 * @param float $number Número a formatear
 * @param int $decimals Número de decimales
 * @return string Número formateado
 */
function formatearNumero($number, $decimals = 2) {
    if ($number === null || $number === '') {
        return number_format(0, $decimals);
    }
    return number_format(floatval($number), $decimals);
}

// ==============================================
// FUNCIÓN PARA ESCAPAR CADENAS
// ==============================================

/**
 * Escapa una cadena para uso seguro en SQL
 * @param string $cadena Cadena a escapar
 * @return string Cadena escapada
 */
function escapar($cadena) {
    global $conn;
    return $conn->real_escape_string($cadena);
}

// ==============================================
// FUNCIÓN PARA VERIFICAR SI ES ADMINISTRADOR
// ==============================================

/**
 * Verifica si el usuario actual es administrador
 * @param mysqli $conn Conexión a la base de datos
 * @return bool True si es administrador, false en caso contrario
 */
function esAdministrador($conn) {
    if (!isset($_SESSION['usuario_id'])) {
        return false;
    }
    
    $sql = "SELECT rol FROM usuarios WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['usuario_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return in_array($row['rol'], ['administrador', 'admin']);
    }
    
    return false;
}

/**
 * Requiere que el usuario sea administrador, si no redirige
 * @param mysqli $conn Conexión a la base de datos
 */
function requerirAdmin($conn) {
    if (!esAdministrador($conn)) {
        registrarLog($conn, $_SESSION['usuario'] ?? 'anonimo', 
            "Intento no autorizado de acceder a área restringida", 
            basename($_SERVER['PHP_SELF']), 
            obtenerIP()
        );
        
        $_SESSION['mensaje'] = "No tienes permisos para acceder a esta sección";
        $_SESSION['tipo_mensaje'] = "error";
        header("Location: index.php");
        exit();
    }
}

// ==============================================
// FUNCIONES PARA USUARIOS ÚNICOS (AGREGADAS)
// ==============================================

/**
 * Obtiene los registros de un usuario específico
 * @param mysqli $conn Conexión a la base de datos
 * @param string $usuario Nombre del usuario
 * @param int|null $limit Límite de registros
 * @return array Registros del usuario
 */
function obtenerRegistrosPorUsuario($conn, $usuario, $limit = null) {
    $sql = "SELECT * FROM registros_ids WHERE usuario = ? ORDER BY fecha_registro DESC";
    if ($limit) {
        $sql .= " LIMIT " . intval($limit);
    }
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        $registros = [];
        while ($row = $result->fetch_assoc()) {
            $registros[] = $row;
        }
        $stmt->close();
        return $registros;
    }
    return [];
}

/**
 * Obtiene todos los usuarios únicos del sistema
 */
function obtenerUsuariosUnicos($conn, $filtro_estatus = null, $filtro_rol = null, $limit = null, $offset = 0) {
    $sql = "SELECT 
                usuario, 
                COUNT(*) as total_registros,
                MAX(fecha_registro) as ultimo_registro,
                MIN(fecha_registro) as primer_registro,
                GROUP_CONCAT(DISTINCT estatus ORDER BY estatus SEPARATOR ', ') as estatus_asignados,
                GROUP_CONCAT(DISTINCT roles ORDER BY roles SEPARATOR ', ') as roles_asignados,
                GROUP_CONCAT(DISTINCT rango_militar ORDER BY rango_militar SEPARATOR ', ') as rangos_asignados
            FROM registros_ids 
            WHERE usuario IS NOT NULL AND usuario != ''";
    
    if ($filtro_estatus && in_array($filtro_estatus, ['Activo', 'Inactivo', 'Pendiente', 'Bloqueado'])) {
        $sql .= " AND estatus = '" . $conn->real_escape_string($filtro_estatus) . "'";
    }
    
    if ($filtro_rol) {
        $sql .= " AND roles LIKE '%" . $conn->real_escape_string($filtro_rol) . "%'";
    }
    
    $sql .= " GROUP BY usuario ORDER BY total_registros DESC";
    
    if ($limit) {
        $sql .= " LIMIT " . intval($limit) . " OFFSET " . intval($offset);
    }
    
    $result = $conn->query($sql);
    $usuarios = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
        }
    }
    return $usuarios;
}

/**
 * Obtiene estadísticas resumidas de usuarios únicos
 */
function obtenerEstadisticasUsuariosUnicos($conn) {
    $stats = [
        'total' => 0,
        'activos' => 0,
        'inactivos' => 0,
        'pendiente' => 0,
        'bloqueados' => 0,
        'promedio_registros' => 0,
        'total_registros' => 0,
        'top_usuarios' => []
    ];
    
    try {
        // Total de usuarios únicos
        $result = $conn->query("SELECT COUNT(DISTINCT usuario) as total FROM registros_ids WHERE usuario IS NOT NULL AND usuario != ''");
        if ($result) {
            $stats['total'] = $result->fetch_assoc()['total'] ?? 0;
        }
        
        // Usuarios únicos por estatus
        $result = $conn->query("SELECT COUNT(DISTINCT usuario) as total FROM registros_ids WHERE usuario IS NOT NULL AND usuario != '' AND estatus = 'Activo'");
        if ($result) $stats['activos'] = $result->fetch_assoc()['total'] ?? 0;
        
        $result = $conn->query("SELECT COUNT(DISTINCT usuario) as total FROM registros_ids WHERE usuario IS NOT NULL AND usuario != '' AND estatus = 'Inactivo'");
        if ($result) $stats['inactivos'] = $result->fetch_assoc()['total'] ?? 0;
        
        $result = $conn->query("SELECT COUNT(DISTINCT usuario) as total FROM registros_ids WHERE usuario IS NOT NULL AND usuario != '' AND estatus = 'Pendiente'");
        if ($result) $stats['pendiente'] = $result->fetch_assoc()['total'] ?? 0;
        
        $result = $conn->query("SELECT COUNT(DISTINCT usuario) as total FROM registros_ids WHERE usuario IS NOT NULL AND usuario != '' AND estatus = 'Bloqueado'");
        if ($result) $stats['bloqueados'] = $result->fetch_assoc()['total'] ?? 0;
        
        // Total de registros
        $result = $conn->query("SELECT COUNT(*) as total FROM registros_ids WHERE usuario IS NOT NULL AND usuario != ''");
        if ($result) $stats['total_registros'] = $result->fetch_assoc()['total'] ?? 0;
        
        // Promedio
        $stats['promedio_registros'] = $stats['total'] > 0 ? round($stats['total_registros'] / $stats['total'], 2) : 0;
        
        // Top 10 usuarios
        $top_query = $conn->query("
            SELECT usuario, COUNT(*) as total, MAX(fecha_registro) as ultima_actividad
            FROM registros_ids 
            WHERE usuario IS NOT NULL AND usuario != ''
            GROUP BY usuario ORDER BY total DESC LIMIT 10
        ");
        if ($top_query) {
            while ($row = $top_query->fetch_assoc()) {
                $stats['top_usuarios'][] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Error en obtenerEstadisticasUsuariosUnicos: " . $e->getMessage());
    }
    
    return $stats;
}

/**
 * Cuenta el total de usuarios únicos
 */
function totalUsuariosUnicos($conn, $filtro_estatus = null) {
    $sql = "SELECT COUNT(DISTINCT usuario) as total FROM registros_ids WHERE usuario IS NOT NULL AND usuario != ''";
    if ($filtro_estatus && in_array($filtro_estatus, ['Activo', 'Inactivo', 'Pendiente', 'Bloqueado'])) {
        $sql .= " AND estatus = '" . $conn->real_escape_string($filtro_estatus) . "'";
    }
    $result = $conn->query($sql);
    return $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;
}

/**
 * Busca usuarios únicos por nombre
 */
function buscarUsuariosUnicos($conn, $termino, $limit = 20) {
    $termino = "%" . $conn->real_escape_string($termino) . "%";
    $sql = "SELECT 
                usuario, 
                COUNT(*) as total_registros,
                MAX(fecha_registro) as ultimo_registro,
                GROUP_CONCAT(DISTINCT estatus SEPARATOR ', ') as estatus_asignados
            FROM registros_ids 
            WHERE usuario IS NOT NULL AND usuario != '' AND usuario LIKE ?
            GROUP BY usuario ORDER BY total_registros DESC LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("si", $termino, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $usuarios = [];
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
        }
        $stmt->close();
        return $usuarios;
    }
    return [];
}

/**
 * Exporta lista de usuarios únicos a CSV
 */
function exportarUsuariosUnicosCSV($conn, $usuarios = null) {
    if ($usuarios === null) {
        $usuarios = obtenerUsuariosUnicos($conn, null, null, 1000, 0);
    }
    
    $output = fopen('php://temp', 'r+');
    fputcsv($output, ['Usuario', 'Total Registros', 'Primer Registro', 'Último Registro', 'Estatus Asignados', 'Roles Asignados', 'Rangos Militares']);
    
    foreach ($usuarios as $usuario) {
        fputcsv($output, [
            $usuario['usuario'],
            $usuario['total_registros'],
            $usuario['primer_registro'] ?? '',
            $usuario['ultimo_registro'] ?? '',
            $usuario['estatus_asignados'] ?? '',
            $usuario['roles_asignados'] ?? '',
            $usuario['rangos_asignados'] ?? ''
        ]);
    }
    
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    return $csv;
}

// ==============================================
// CREAR TABLA DE USUARIOS SI NO EXISTE
// ==============================================

// Verificar si la tabla usuarios existe, si no, crearla
$check_usuarios = $conn->query("SHOW TABLES LIKE 'usuarios'");
if ($check_usuarios && $check_usuarios->num_rows == 0) {
    $sql_create_usuarios = "
        CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            rol VARCHAR(50) DEFAULT 'usuario',
            activo TINYINT DEFAULT 1,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            ultimo_login DATETIME NULL,
            INDEX idx_usuario (usuario)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";
    $conn->query($sql_create_usuarios);
    
    // Insertar usuario admin por defecto (contraseña: admin123)
    $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $sql_insert_admin = "INSERT INTO usuarios (usuario, password, rol, activo) VALUES ('admin', '$password_hash', 'administrador', 1)";
    $conn->query($sql_insert_admin);
}

// ==============================================
// CREAR TABLA DE REGISTROS SI NO EXISTE
// ==============================================

$check_registros = $conn->query("SHOW TABLES LIKE 'registros_ids'");
if ($check_registros && $check_registros->num_rows == 0) {
    $sql_create_registros = "
        CREATE TABLE IF NOT EXISTS registros_ids (
            id_registro INT AUTO_INCREMENT PRIMARY KEY,
            rango_militar VARCHAR(100),
            usuario VARCHAR(100),
            roles VARCHAR(100),
            estatus VARCHAR(50) DEFAULT 'Activo',
            designacion TEXT,
            configuracion TEXT,
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_usuario (usuario),
            INDEX idx_estatus (estatus)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";
    $conn->query($sql_create_registros);
    
    // Insertar datos de ejemplo
    $conn->query("INSERT INTO registros_ids (rango_militar, usuario, roles, estatus, designacion, configuracion) VALUES 
        ('General', 'admin', 'ADMINISTRADOR', 'Activo', 'Comandante General', 'Configuración principal'),
        ('Coronel', 'juan.perez', 'SUPERVISOR', 'Activo', 'Jefe de Operaciones', 'Configuración operativa'),
        ('Mayor', 'maria.gomez', 'OPERADOR', 'Activo', 'Coordinadora de Personal', 'Configuración personal')
    ");
}

// ==============================================
// VERIFICAR QUE LA SESIÓN DEL USUARIO SEA VÁLIDA
// ==============================================

// Si hay sesión de usuario, verificar que existe en la base de datos
if (isset($_SESSION['usuario'])) {
    $check_user = $conn->prepare("SELECT id, usuario, rol, activo FROM usuarios WHERE usuario = ? AND activo = 1");
    if ($check_user) {
        $check_user->bind_param("s", $_SESSION['usuario']);
        $check_user->execute();
        $result_user = $check_user->get_result();
        
        if ($result_user->num_rows == 0) {
            // Usuario no existe o está inactivo, cerrar sesión
            session_unset();
            session_destroy();
            header("Location: login.php?error=sesion_invalida");
            exit();
        } else {
            $user_data = $result_user->fetch_assoc();
            $_SESSION['usuario_id'] = $user_data['id'];
            $_SESSION['rol'] = $user_data['rol'];
        }
        $check_user->close();
    }
}
?>