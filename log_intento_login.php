<?php
// log_intento_login.php - Registrar intentos de login desde JavaScript
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'] ?? 'desconocido';
    $accion = $_POST['accion'] ?? 'intento';
    
    registrarLog($conn, 'sistema', 
        "Intento de login desde formulario", 
        "login.php", 
        obtenerIP(),
        ['usuario_intentado' => $usuario, 'tipo' => $accion]
    );
}

echo json_encode(['success' => true]);
?>