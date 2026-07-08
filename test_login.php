<?php
// test_login.php - Archivo temporal para probar credenciales
require_once 'config.php';

$usuario = 'admin';
$password = 'admin123';

echo "<h2>Prueba de Autenticación</h2>";

// Verificar si la tabla existe
$result = $conn->query("SHOW TABLES LIKE 'usuarios'");
if ($result->num_rows > 0) {
    echo "✓ Tabla 'usuarios' existe<br>";
    
    // Buscar usuario
    $sql = "SELECT * FROM usuarios WHERE usuario = '$usuario'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo "✓ Usuario encontrado: " . $user['usuario'] . "<br>";
        echo "✓ Rol: " . $user['rol'] . "<br>";
        echo "✓ Activo: " . ($user['activo'] ? 'Sí' : 'No') . "<br>";
        
        // Verificar contraseña
        if (password_verify($password, $user['password'])) {
            echo "✓ Contraseña correcta!<br>";
            echo "<strong style='color: green;'>Puedes iniciar sesión con:</strong><br>";
            echo "Usuario: admin<br>";
            echo "Contraseña: admin123<br>";
        } else {
            echo "✗ Contraseña incorrecta<br>";
            echo "Hash almacenado: " . $user['password'] . "<br>";
        }
    } else {
        echo "✗ Usuario 'admin' no encontrado<br>";
        echo "Creando usuario de prueba...<br>";
        
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $sql_insert = "INSERT INTO usuarios (usuario, password, rol, activo) 
                       VALUES ('admin', '$password_hash', 'administrador', 1)";
        
        if ($conn->query($sql_insert)) {
            echo "✓ Usuario admin creado correctamente!<br>";
            echo "Usuario: admin<br>";
            echo "Contraseña: admin123<br>";
        } else {
            echo "✗ Error al crear usuario: " . $conn->error . "<br>";
        }
    }
} else {
    echo "✗ Tabla 'usuarios' no existe<br>";
    echo "Creando tabla...<br>";
    
    $sql_create = "CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        rol VARCHAR(50) DEFAULT 'usuario',
        activo TINYINT DEFAULT 1,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        ultimo_login DATETIME NULL
    )";
    
    if ($conn->query($sql_create)) {
        echo "✓ Tabla creada correctamente<br>";
        
        // Insertar usuario admin
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $sql_insert = "INSERT INTO usuarios (usuario, password, rol, activo) 
                       VALUES ('admin', '$password_hash', 'administrador', 1)";
        
        if ($conn->query($sql_insert)) {
            echo "✓ Usuario admin creado correctamente!<br>";
            echo "<strong>Credenciales:</strong><br>";
            echo "Usuario: admin<br>";
            echo "Contraseña: admin123<br>";
        }
    } else {
        echo "✗ Error al crear tabla: " . $conn->error . "<br>";
    }
}

echo "<br><a href='login.php'>Ir al login</a>";
?>