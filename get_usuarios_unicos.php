<?php
// get_usuarios_unicos.php - Obtener IDs de usuarios únicos por estatus
require_once 'config.php';

if (!isset($_SESSION['usuario'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

header('Content-Type: application/json');

$tipo = $_GET['tipo'] ?? '';

if (empty($tipo)) {
    echo json_encode(['error' => 'Tipo de filtro no especificado']);
    exit();
}

try {
    $usuarios = [];
    
    switch ($tipo) {
        case 'activos_unicos':
            $sql = "SELECT DISTINCT id_registro FROM registros_ids WHERE usuario IS NOT NULL AND usuario != '' AND estatus = 'Activo'";
            break;
        case 'inactivos_unicos':
            $sql = "SELECT DISTINCT id_registro FROM registros_ids WHERE usuario IS NOT NULL AND usuario != '' AND estatus = 'Inactivo'";
            break;
        case 'pendiente_unicos':
            $sql = "SELECT DISTINCT id_registro FROM registros_ids WHERE usuario IS NOT NULL AND usuario != '' AND estatus = 'Pendiente'";
            break;
        case 'bloqueados_unicos':
            $sql = "SELECT DISTINCT id_registro FROM registros_ids WHERE usuario IS NOT NULL AND usuario != '' AND estatus = 'Bloqueado'";
            break;
        default:
            echo json_encode(['error' => 'Tipo de filtro inválido']);
            exit();
    }
    
    $resultado = $conn->query($sql);
    
    if ($resultado) {
        while ($row = $resultado->fetch_assoc()) {
            $usuarios[] = $row['id_registro'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'tipo' => $tipo,
        'ids' => $usuarios,
        'total' => count($usuarios)
    ]);
    
} catch (Exception $e) {
    error_log("Error en get_usuarios_unicos.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener los usuarios'
    ]);
}

$conn->close();
?>