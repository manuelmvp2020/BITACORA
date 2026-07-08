<?php
// procesar_login.php - Procesar autenticación de usuarios
session_start();
require_once 'config.php';

// Verificar que la conexión existe
if (!isset($conn) || $conn->connect_error) {
    die("Error de conexión a la base de datos. Contacte al administrador.");
}

// Habilitar reporte de errores para depuración (quitar en producción)
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

$usuario = trim($_POST['usuario'] ?? '');
$password = $_POST['password'] ?? '';

// Validar que los campos no estén vacíos
if (empty($usuario) || empty($password)) {
    if (function_exists('registrarLog')) {
        registrarLog($conn, 'sistema', 
            "Intento de login fallido - Campos vacíos", 
            "procesar_login.php", 
            obtenerIP(),
            ['usuario_intentado' => $usuario, 'razon' => 'campos_vacios']
        );
    }
    
    header("Location: login.php?error=campos_vacios");
    exit();
}

// Buscar usuario en la base de datos
$sql = "SELECT * FROM usuarios WHERE usuario = ? AND activo = 1";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Error en la consulta: " . $conn->error);
}

$stmt->bind_param("s", $usuario);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 1) {
    $user = $resultado->fetch_assoc();
    
    // Verificar contraseña
    if (password_verify($password, $user['password'])) {
        // Login exitoso
        $_SESSION['usuario'] = $user['usuario'];
        $_SESSION['rol'] = $user['rol'];
        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['tiempo_inicio_sesion'] = time();
        $_SESSION['ultima_actividad'] = time();
        $_SESSION['ip_conexion'] = obtenerIP();
        
        // Actualizar último login
        $update_sql = "UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $user['id']);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Registrar login exitoso
        if (function_exists('registrarLog')) {
            registrarLog($conn, $usuario, 
                "Inició sesión exitosamente", 
                "procesar_login.php", 
                obtenerIP(),
                [
                    'rol' => $user['rol'],
                    'id_usuario' => $user['id'],
                    'fecha_login' => date('Y-m-d H:i:s')
                ]
            );
        }
        
        // Redirigir al dashboard
        header("Location: index.php");
        exit();
    } else {
        // Contraseña incorrecta
        if (function_exists('registrarLog')) {
            registrarLog($conn, 'sistema', 
                "Intento de login fallido - Contraseña incorrecta para usuario: $usuario", 
                "procesar_login.php", 
                obtenerIP(),
                ['usuario_intentado' => $usuario, 'razon' => 'password_incorrecta']
            );
        }
        
        header("Location: login.php?error=password_incorrecta");
        exit();
    }
} else {
    // Usuario no encontrado o inactivo
    if (function_exists('registrarLog')) {
        registrarLog($conn, 'sistema', 
            "Intento de login fallido - Usuario no encontrado: $usuario", 
            "procesar_login.php", 
            obtenerIP(),
            ['usuario_intentado' => $usuario, 'razon' => 'usuario_no_existe']
        );
    }
    
    header("Location: login.php?error=usuario_no_existe");
    exit();
}

$stmt->close();
?>