<?php
// actualizar_password_admin.php - Actualizar contraseña del admin a 'admin123'
require_once 'config.php';

echo "<h1>Actualizar Contraseña del Administrador</h1>";

// Verificar si existe la tabla usuarios
$check_table = $conn->query("SHOW TABLES LIKE 'usuarios'");
if ($check_table->num_rows == 0) {
    echo "<div style='background: #fee; padding: 15px; border-radius: 5px; color: #c0392b;'>";
    echo "❌ La tabla 'usuarios' no existe. Ejecuta primero <strong>corregir_tabla.php</strong>";
    echo "</div>";
} else {
    // Verificar si existe el usuario admin
    $check_admin = $conn->query("SELECT id FROM usuarios WHERE usuario = 'admin'");
    
    // Nueva contraseña: admin123
    $nueva_password = 'admin123';
    $hash = password_hash($nueva_password, PASSWORD_DEFAULT);
    
    if ($check_admin && $check_admin->num_rows > 0) {
        // Actualizar contraseña existente
        $sql = "UPDATE usuarios SET password = ? WHERE usuario = 'admin'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $hash);
        
        if ($stmt->execute()) {
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724; margin: 10px 0;'>";
            echo "✅ Contraseña actualizada exitosamente!<br>";
            echo "Usuario: <strong>admin</strong><br>";
            echo "Nueva contraseña: <strong>admin123</strong><br>";
            echo "</div>";
            
            // Verificar que funciona
            if (password_verify('admin123', $hash)) {
                echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; margin-top: 10px;'>";
                echo "✅ Verificación: La contraseña funciona correctamente.";
                echo "</div>";
            }
        } else {
            echo "<div style='background: #fee; padding: 15px; border-radius: 5px; color: #c0392b;'>";
            echo "❌ Error al actualizar: " . $stmt->error;
            echo "</div>";
        }
        $stmt->close();
        
    } else {
        // Crear usuario admin si no existe
        $sql = "INSERT INTO usuarios (usuario, password, rol, activo, nombre_completo, email) 
                VALUES (?, ?, 'admin', 1, 'Administrador del Sistema', 'admin@sistema.com')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $usuario, $hash);
        $usuario = 'admin';
        
        if ($stmt->execute()) {
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724; margin: 10px 0;'>";
            echo "✅ Usuario administrador creado exitosamente!<br>";
            echo "Usuario: <strong>admin</strong><br>";
            echo "Contraseña: <strong>admin123</strong><br>";
            echo "</div>";
        } else {
            echo "<div style='background: #fee; padding: 15px; border-radius: 5px; color: #c0392b;'>";
            echo "❌ Error al crear usuario: " . $stmt->error;
            echo "</div>";
        }
        $stmt->close();
    }
}

echo "<hr>";
echo "<p><a href='login.php' style='background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔐 Ir al Login</a></p>";
echo "<p><a href='verificar_admin.php' style='background: #9b59b6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔍 Verificar Usuario Admin</a></p>";

$conn->close();
?>