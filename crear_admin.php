<?php
// crear_admin.php - Script para crear usuario administrador (ejecutar una sola vez)
require_once 'config.php';

// Verificar si ya existe algún usuario
$check = $conn->query("SELECT COUNT(*) as total FROM usuarios");
$total = $check->fetch_assoc()['total'];

if ($total == 0) {
    $usuario = 'admin';
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $rol = 'admin';
    $nombre = 'Administrador del Sistema';
    
    $sql = "INSERT INTO usuarios (usuario, password, rol, nombre_completo, activo) VALUES (?, ?, ?, ?, 1)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $usuario, $password, $rol, $nombre);
    
    if ($stmt->execute()) {
        echo "✅ Usuario administrador creado exitosamente!\n";
        echo "Usuario: admin\n";
        echo "Contraseña: admin123\n";
        echo "⚠️ Por favor cambia la contraseña después del primer inicio de sesión.\n";
    } else {
        echo "❌ Error al crear usuario: " . $conn->error . "\n";
    }
    $stmt->close();
} else {
    echo "⚠️ Ya existen usuarios en el sistema. No se creará el administrador por defecto.\n";
    echo "Usa la opción de recuperación de contraseña si olvidaste tus credenciales.\n";
}
?>