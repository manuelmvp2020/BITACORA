<?php
// registrar_accion_ajax.php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $accion = $data['accion'] ?? 'accion_desconocida';
    $modulo = $data['modulo'] ?? 'ajax';
    $detalles = json_encode($data);
    
    $resultado = registrarLog(
        $conn,
        $_SESSION['usuario'] ?? 'sistema',
        $accion,
        $modulo,
        obtenerIP(),
        $detalles
    );
    
    echo json_encode(['success' => $resultado]);
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}
?>