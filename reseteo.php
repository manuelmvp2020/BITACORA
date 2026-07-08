<?php
// crear_admin_fresh.php - Crear usuario admin fresco con contraseña admin123
require_once 'config.php';

echo "<h1>Crear Usuario Administrador (Fresco)</h1>";

// Primero, eliminar cualquier usuario admin existente
$conn->query("DELETE FROM usuarios WHERE usuario = 'admin'");

// Crear nuevo usuario admin
$usuario = 'admin';
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);
$rol = 'admin';
$nombre = 'Administrador del Sistema';
$email = 'admin@sistema.com';

$sql = "INSERT INTO usuarios (usuario, password, rol, activo, nombre_completo, email) VALUES (?, ?, ?, 1, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssss", $usuario, $hash, $rol, $nombre, $email);

if ($stmt->execute()) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724; margin: 10px 0;'>";
    echo "✅ Usuario administrador creado exitosamente!<br><br>";
    echo "<strong>📝 Credenciales:</strong><br>";
    echo "🔹 Usuario: <code style='background: #fff; padding: 2px 5px; border-radius: 3px;'>admin</code><br>";
    echo "🔹 Contraseña: <code style='background: #fff; padding: 2px 5px; border-radius: 3px;'>admin123</code><br>";
    echo "</div>";
    
    // Verificar que funciona
    if (password_verify('admin123', $hash)) {
        echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; margin-top: 10px;'>";
        echo "✅ Verificación exitosa: La contraseña funciona correctamente.";
        echo "</div>";
    }
} else {
    echo "<div style='background: #fee; padding: 15px; border-radius: 5px; color: #c0392b;'>";
    echo "❌ Error: " . $stmt->error;
    echo "</div>";
}

$stmt->close();

// Mostrar usuario creado
$result = $conn->query("SELECT id, usuario, rol, activo FROM usuarios WHERE usuario = 'admin'");
if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "<div style='background: #e8f5e9; padding: 15px; border-radius: 5px; margin-top: 15px;'>";
    echo "<strong>✅ Usuario en base de datos:</strong><br>";
    echo "ID: " . $user['id'] . "<br>";
    echo "Usuario: " . $user['usuario'] . "<br>";
    echo "Rol: " . $user['rol'] . "<br>";
    echo "Estado: " . ($user['activo'] ? 'Activo' : 'Inactivo') . "<br>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='login.php' style='background: #27ae60; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>🔐 Iniciar Sesión</a></p>";
echo "<p><a href='verificar_admin.php' style='background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔍 Verificar Contraseña</a></p>";

$conn->close();
?>