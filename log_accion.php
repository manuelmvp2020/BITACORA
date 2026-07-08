<?php
// log_accion.php
require_once 'config.php';
session_start();

function obtenerIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    }
    return $ip;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'acción_desconocida';
    $detalles = $_POST['detalles'] ?? null;
    $modulo = $_POST['modulo'] ?? 'index.php';
    $usuario = $_SESSION['usuario'] ?? 'admin';
    $ip = obtenerIP();
    
    try {
        $sql = "INSERT INTO logs (usuario, accion, modulo, ip_address, detalles, fecha) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $detalles_json = $detalles ? (is_string($detalles) ? $detalles : json_encode($detalles, JSON_UNESCAPED_UNICODE)) : null;
        $stmt->execute([$usuario, $accion, $modulo, $ip, $detalles_json]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        error_log("Error en log_accion: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
}
?>