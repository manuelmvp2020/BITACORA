<?php
// registrar_log_respaldo.php - Endpoint específico para registrar logs de respaldo
// Este archivo puede ser llamado mediante AJAX para registrar eventos

require_once 'config.php';
require_once 'funciones_logs.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit();
}

// Verificar que la solicitud sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);
$accion = $input['accion'] ?? '';
$detalles = $input['detalles'] ?? '';
$exito = $input['exito'] ?? true;

$usuario = $_SESSION['usuario_nombre'] ?? 'Usuario desconocido';
$modulo = 'respaldo';

// Registrar el log
registrarLog($conn, $usuario, $accion . ($detalles ? ': ' . $detalles : ''), $modulo, $exito);

// Responder
echo json_encode(['success' => true, 'message' => 'Log registrado correctamente']);
?>