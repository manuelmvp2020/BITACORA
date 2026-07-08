<?php
// crear_usuario_nuevo.php - Crear un nuevo usuario con contraseña simple
require_once 'config.php';

// Contraseña que quieres usar (cámbiala si quieres)
$usuario = 'admin2';
$password = '123456';
$rol = 'administrador';

$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>Creando nuevo usuario de prueba</h2>";

// Verificar si ya existe
$check_sql = "SELECT * FROM usuarios WHERE usuario = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("s", $usuario);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    // Actualizar contraseña
    $update_sql = "UPDATE usuarios SET password = ? WHERE usuario = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ss", $hash, $usuario);
    
    if ($update_stmt->execute()) {
        echo "<div style='color: green;'>✅ Usuario <strong>{$usuario}</strong> actualizado con contraseña: <strong>{$password}</strong></div>";
    }
    $update_stmt->close();
} else {
    // Crear nuevo usuario
    $insert_sql = "INSERT INTO usuarios (usuario, password, rol, activo) VALUES (?, ?, ?, 1)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("sss", $usuario, $hash, $rol);
    
    if ($insert_stmt->execute()) {
        echo "<div style='color: green;'>✅ Nuevo usuario creado:<br>";
        echo "Usuario: <strong>{$usuario}</strong><br>";
        echo "Contraseña: <strong>{$password}</strong><br>";
        echo "Rol: <strong>{$rol}</strong></div>";
    } else {
        echo "<div style='color: red;'>❌ Error: " . $conn->error . "</div>";
    }
    $insert_stmt->close();
}

$check_stmt->close();

echo "<br><a href='login.php' style='display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px;'>Ir al Login</a>";
?>