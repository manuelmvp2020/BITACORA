<?php
// verificar_admin.php - Verificar la contraseña del usuario admin
require_once 'config.php';

echo "<h1>Verificación de Usuario Administrador</h1>";

// Verificar si existe el usuario admin
$sql = "SELECT id, usuario, password, rol, activo FROM usuarios WHERE usuario = 'admin'";
$resultado = $conn->query($sql);

if ($resultado && $resultado->num_rows > 0) {
    $user = $resultado->fetch_assoc();
    
    echo "<div style='background: #e8f5e9; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>✅ Usuario encontrado:</strong><br>";
    echo "ID: " . $user['id'] . "<br>";
    echo "Usuario: " . $user['usuario'] . "<br>";
    echo "Rol: " . $user['rol'] . "<br>";
    echo "Estado: " . ($user['activo'] ? 'Activo' : 'Inactivo') . "<br>";
    echo "Hash almacenado: " . $user['password'] . "<br>";
    echo "</div>";
    
    // Probar diferentes contraseñas
    $passwords_to_test = ['admin123', 'password', 'admin', '123456', 'admin12345'];
    
    echo "<h2>Probando contraseñas comunes:</h2>";
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
    echo "<tr style='background: #333; color: white;'><th>Contraseña probada</th><th>¿Coincide?</th></tr>";
    
    foreach ($passwords_to_test as $test_password) {
        $verify = password_verify($test_password, $user['password']);
        $status = $verify ? '✅ SÍ coincide' : '❌ NO coincide';
        $color = $verify ? 'green' : 'red';
        echo "<tr>";
        echo "<td><code>" . $test_password . "</code></td>";
        echo "<td style='color: $color; font-weight: bold;'>$status</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} else {
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "⚠️ No existe el usuario 'admin' en la base de datos.<br>";
    echo "</div>";
}

// Mostrar todos los usuarios
echo "<h2>Usuarios registrados:</h2>";
$todos = $conn->query("SELECT id, usuario, rol, activo FROM usuarios");
if ($todos && $todos->num_rows > 0) {
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
    echo "<tr style='background: #333; color: white;'><th>ID</th><th>Usuario</th><th>Rol</th><th>Estado</th></tr>";
    while ($row = $todos->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['usuario'] . "</td>";
        echo "<td>" . $row['rol'] . "</td>";
        echo "<td>" . ($row['activo'] ? 'Activo' : 'Inactivo') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No hay usuarios registrados.</p>";
}

echo "<hr>";
echo "<p><a href='login.php' style='background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>← Volver al Login</a></p>";
echo "<p><a href='corregir_tabla.php' style='background: #27ae60; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔧 Corregir Base de Datos</a></p>";

$conn->close();
?>