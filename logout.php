<?php
// logout.php - Módulo de cierre de sesión con logging completo
session_start();

// Incluir configuración
require_once 'config.php';

// Registrar el cierre de sesión ANTES de destruir la sesión
if (isset($_SESSION['usuario'])) {
    $usuario = $_SESSION['usuario'];
    $rol = $_SESSION['rol'] ?? 'No especificado';
    
    // Registrar el logout con todos los detalles
    registrarLog($conn, $usuario, 
        "Cerró sesión del sistema", 
        "logout.php", 
        obtenerIP(),
        [
            'usuario_cerro_sesion' => $usuario,
            'rol' => $rol,
            'tiempo_sesion' => isset($_SESSION['tiempo_inicio_sesion']) 
                ? time() - $_SESSION['tiempo_inicio_sesion'] 
                : 'No registrado',
            'fecha_cierre' => date('Y-m-d H:i:s'),
            'accion' => 'logout'
        ]
    );
    
    // Limpiar variables de sesión relacionadas con el usuario
    unset($_SESSION['usuario']);
    unset($_SESSION['rol']);
    unset($_SESSION['tiempo_inicio_sesion']);
    unset($_SESSION['ultima_actividad']);
    unset($_SESSION['ip_conexion']);
}

// Destruir completamente la sesión
session_unset();
session_destroy();

// Limpiar cookie de sesión si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirigir al login con mensaje de éxito
header("Location: login.php?mensaje=sesion_cerrada");
exit();
?>