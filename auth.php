<?php
// Iniciar sesión solo si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();


}

require_once 'config.php';
require_once 'log_acceso.php';

// Verificar autenticación
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php?msg=sesion_expirada");
    exit;
}

// Registrar acceso solo una vez por sesión
if (!isset($_SESSION['acceso_registrado'])) {
    registrar_acceso($conn);
    $_SESSION['acceso_registrado'] = true;
}
?>
