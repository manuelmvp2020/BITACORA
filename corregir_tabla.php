<?php
// corregir_tabla.php - Script para corregir la estructura de la tabla usuarios
require_once 'config.php';

// Estilos para la página
echo '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corregir Tabla - Bitácora</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden; animation: fadeInUp 0.5s ease; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .header { background: linear-gradient(135deg, #2c3e50 0%, #1a2639 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { font-size: 28px; margin-bottom: 10px; }
        .header p { opacity: 0.8; }
        .content { padding: 30px; }
        .success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .info { background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #34495e; color: white; padding: 12px; text-align: left; font-weight: 600; }
        td { padding: 10px; border-bottom: 1px solid #ecf0f1; }
        tr:hover { background: #f8f9fa; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .badge-success { background: #28a745; color: white; }
        .badge-error { background: #dc3545; color: white; }
        .badge-warning { background: #ffc107; color: #333; }
        .btn { display: inline-block; padding: 12px 24px; margin: 10px 5px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; transform: translateY(-2px); }
        .btn-success { background: #27ae60; color: white; }
        .btn-success:hover { background: #229954; transform: translateY(-2px); }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-danger:hover { background: #c0392b; transform: translateY(-2px); }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #ecf0f1; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 4px; font-family: monospace; }
        .progress-step { display: inline-block; width: 30px; height: 30px; line-height: 30px; text-align: center; border-radius: 50%; background: #ecf0f1; color: #7f8c8d; margin-right: 10px; font-weight: bold; }
        .progress-step.completed { background: #27ae60; color: white; }
        .progress-step.active { background: #3498db; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-tools"></i> Reparador de Base de Datos</h1>
            <p>Corrección automática de la estructura de la tabla de usuarios</p>
        </div>
        <div class="content">';

echo "<h2><i class='fas fa-database'></i> Proceso de Corrección</h2>";

// ============================================
// PASO 1: Verificar conexión
// ============================================
echo "<p><span class='progress-step completed'>1</span> Verificando conexión a la base de datos...</p>";
if ($conn && !$conn->connect_error) {
    echo "<div class='success'><i class='fas fa-check-circle'></i> Conexión exitosa a la base de datos</div>";
} else {
    echo "<div class='error'><i class='fas fa-exclamation-circle'></i> Error de conexión: " . ($conn ? $conn->connect_error : "Conexión nula") . "</div>";
    echo "</div></div></body></html>";
    exit();
}

// ============================================
// PASO 2: Verificar si la tabla existe
// ============================================
echo "<p><span class='progress-step completed'>2</span> Verificando existencia de la tabla 'usuarios'...</p>";
$check_table = $conn->query("SHOW TABLES LIKE 'usuarios'");
$tabla_existe = ($check_table && $check_table->num_rows > 0);

if (!$tabla_existe) {
    echo "<div class='warning'><i class='fas fa-exclamation-triangle'></i> La tabla 'usuarios' no existe. Creándola...</div>";
    
    $sql = "CREATE TABLE `usuarios` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `usuario` varchar(50) NOT NULL,
        `password` varchar(255) NOT NULL,
        `rol` enum('admin','usuario','supervisor') NOT NULL DEFAULT 'usuario',
        `activo` tinyint(1) NOT NULL DEFAULT 1,
        `email` varchar(100) DEFAULT NULL,
        `nombre_completo` varchar(100) DEFAULT NULL,
        `fecha_creacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `ultimo_login` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `usuario` (`usuario`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if ($conn->query($sql)) {
        echo "<div class='success'><i class='fas fa-check-circle'></i> Tabla 'usuarios' creada correctamente</div>";
        $tabla_existe = true;
    } else {
        echo "<div class='error'><i class='fas fa-exclamation-circle'></i> Error al crear tabla: " . $conn->error . "</div>";
    }
} else {
    echo "<div class='success'><i class='fas fa-check-circle'></i> La tabla 'usuarios' ya existe</div>";
}

// ============================================
// PASO 3: Agregar columnas faltantes
// ============================================
if ($tabla_existe) {
    echo "<p><span class='progress-step'>3</span> Verificando y agregando columnas faltantes...</p>";
    
    $columnas_requeridas = [
        'password' => "ALTER TABLE `usuarios` ADD COLUMN `password` VARCHAR(255) NOT NULL AFTER `usuario`",
        'rol' => "ALTER TABLE `usuarios` ADD COLUMN `rol` ENUM('admin','usuario','supervisor') NOT NULL DEFAULT 'usuario' AFTER `password`",
        'activo' => "ALTER TABLE `usuarios` ADD COLUMN `activo` TINYINT(1) NOT NULL DEFAULT 1 AFTER `rol`",
        'email' => "ALTER TABLE `usuarios` ADD COLUMN `email` VARCHAR(100) DEFAULT NULL AFTER `activo`",
        'nombre_completo' => "ALTER TABLE `usuarios` ADD COLUMN `nombre_completo` VARCHAR(100) DEFAULT NULL AFTER `email`",
        'fecha_creacion' => "ALTER TABLE `usuarios` ADD COLUMN `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `nombre_completo`",
        'ultimo_login' => "ALTER TABLE `usuarios` ADD COLUMN `ultimo_login` DATETIME DEFAULT NULL AFTER `fecha_creacion`"
    ];
    
    $columnas_existentes = [];
    $result = $conn->query("DESCRIBE usuarios");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columnas_existentes[] = $row['Field'];
        }
    }
    
    foreach ($columnas_requeridas as $columna => $sql_alter) {
        if (!in_array($columna, $columnas_existentes)) {
            echo "<div class='info'><i class='fas fa-plus-circle'></i> Agregando columna '<strong>$columna</strong>'...</div>";
            if ($conn->query($sql_alter)) {
                echo "<div class='success' style='margin-top: -10px;'><i class='fas fa-check'></i> Columna '$columna' agregada correctamente</div>";
            } else {
                echo "<div class='error' style='margin-top: -10px;'><i class='fas fa-times'></i> Error al agregar '$columna': " . $conn->error . "</div>";
            }
        } else {
            echo "<div class='success' style='margin-top: 5px;'><i class='fas fa-check'></i> Columna '$columna' ya existe</div>";
        }
    }
}

// ============================================
// PASO 4: Verificar y crear usuario administrador
// ============================================
echo "<p><span class='progress-step'>4</span> Verificando usuario administrador...</p>";

$check_admin = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE usuario = 'admin'");
$admin_existe = ($check_admin && $check_admin->fetch_assoc()['total'] > 0);

if (!$admin_existe) {
    echo "<div class='warning'><i class='fas fa-exclamation-triangle'></i> No hay usuario administrador. Creándolo...</div>";
    
    $usuario = 'admin';
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $rol = 'admin';
    $nombre = 'Administrador del Sistema';
    $email = 'admin@sistema.com';
    
    $sql = "INSERT INTO usuarios (usuario, password, rol, nombre_completo, email, activo) VALUES (?, ?, ?, ?, ?, 1)";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("sssss", $usuario, $password, $rol, $nombre, $email);
        if ($stmt->execute()) {
            echo "<div class='success'><i class='fas fa-check-circle'></i> Usuario administrador creado exitosamente!</div>";
            echo "<div style='background: #e8f5e9; padding: 15px; border-radius: 8px; margin: 15px 0;'>";
            echo "<strong><i class='fas fa-key'></i> Credenciales de acceso:</strong><br>";
            echo "📝 Usuario: <code>admin</code><br>";
            echo "🔒 Contraseña: <code>admin123</code><br>";
            echo "<span style='color: #e67e22;'><i class='fas fa-exclamation-triangle'></i> ⚠️ Cambia la contraseña después del primer inicio de sesión.</span>";
            echo "</div>";
        } else {
            echo "<div class='error'><i class='fas fa-exclamation-circle'></i> Error al crear usuario: " . $stmt->error . "</div>";
        }
        $stmt->close();
    } else {
        echo "<div class='error'><i class='fas fa-exclamation-circle'></i> Error al preparar consulta: " . $conn->error . "</div>";
    }
} else {
    echo "<div class='success'><i class='fas fa-check-circle'></i> Usuario administrador ya existe</div>";
}

// ============================================
// PASO 5: Verificar tabla logs
// ============================================
echo "<p><span class='progress-step'>5</span> Verificando tabla 'logs'...</p>";

$check_logs = $conn->query("SHOW TABLES LIKE 'logs'");
$logs_existe = ($check_logs && $check_logs->num_rows > 0);

if (!$logs_existe) {
    echo "<div class='warning'><i class='fas fa-exclamation-triangle'></i> La tabla 'logs' no existe. Creándola...</div>";
    
    $sql_logs = "CREATE TABLE IF NOT EXISTS `logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `usuario` varchar(100) NOT NULL,
        `accion` text NOT NULL,
        `modulo` varchar(50) NOT NULL,
        `fecha` datetime NOT NULL,
        `ip_address` varchar(45) DEFAULT NULL,
        `detalles` text DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_usuario` (`usuario`),
        KEY `idx_fecha` (`fecha`),
        KEY `idx_modulo` (`modulo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if ($conn->query($sql_logs)) {
        echo "<div class='success'><i class='fas fa-check-circle'></i> Tabla 'logs' creada correctamente</div>";
    } else {
        echo "<div class='error'><i class='fas fa-exclamation-circle'></i> Error al crear tabla logs: " . $conn->error . "</div>";
    }
} else {
    // Verificar columna detalles en logs
    $check_detalles = $conn->query("SHOW COLUMNS FROM logs LIKE 'detalles'");
    if ($check_detalles && $check_detalles->num_rows == 0) {
        echo "<div class='info'><i class='fas fa-plus-circle'></i> Agregando columna 'detalles' a la tabla logs...</div>";
        $conn->query("ALTER TABLE `logs` ADD COLUMN `detalles` TEXT DEFAULT NULL AFTER `ip_address`");
        echo "<div class='success' style='margin-top: -10px;'>✅ Columna 'detalles' agregada</div>";
    }
    echo "<div class='success'><i class='fas fa-check-circle'></i> Tabla 'logs' existe y está correcta</div>";
}

// ============================================
// PASO 6: Verificar tabla registros_ids
// ============================================
echo "<p><span class='progress-step'>6</span> Verificando tabla 'registros_ids'...</p>";

$check_registros = $conn->query("SHOW TABLES LIKE 'registros_ids'");
$registros_existe = ($check_registros && $check_registros->num_rows > 0);

if (!$registros_existe) {
    echo "<div class='warning'><i class='fas fa-exclamation-triangle'></i> La tabla 'registros_ids' no existe. Creándola...</div>";
    
    $sql_registros = "CREATE TABLE IF NOT EXISTS `registros_ids` (
        `id_registro` int(11) NOT NULL AUTO_INCREMENT,
        `rango_militar` varchar(100) DEFAULT NULL,
        `usuario` varchar(100) DEFAULT NULL,
        `roles` varchar(100) DEFAULT NULL,
        `estatus` enum('Activo','Inactivo','Reservado','Bloqueado') DEFAULT 'Activo',
        `designacion` text,
        `configuracion` text,
        `fecha_registro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id_registro`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if ($conn->query($sql_registros)) {
        echo "<div class='success'><i class='fas fa-check-circle'></i> Tabla 'registros_ids' creada correctamente</div>";
    } else {
        echo "<div class='error'><i class='fas fa-exclamation-circle'></i> Error al crear tabla registros_ids: " . $conn->error . "</div>";
    }
} else {
    echo "<div class='success'><i class='fas fa-check-circle'></i> Tabla 'registros_ids' existe</div>";
}

// ============================================
// MOSTRAR ESTRUCTURA FINAL
// ============================================
echo "<h2><i class='fas fa-table'></i> Estructura final de la tabla 'usuarios'</h2>";

$result = $conn->query("DESCRIBE usuarios");
if ($result && $result->num_rows > 0) {
    echo "<table>";
    echo "<thead><tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Clave</th><th>Default</th><th>Extra</th></tr></thead>";
    echo "<tbody>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($row['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
} else {
    echo "<div class='error'>No se pudo obtener la estructura de la tabla</div>";
}

// ============================================
// MOSTRAR USUARIOS EXISTENTES
// ============================================
echo "<h2><i class='fas fa-users'></i> Usuarios registrados</h2>";

$users = $conn->query("SELECT id, usuario, rol, activo, email, nombre_completo, fecha_creacion FROM usuarios ORDER BY id");
if ($users && $users->num_rows > 0) {
    echo "<table>";
    echo "<thead><tr><th>ID</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Email</th><th>Nombre</th><th>Fecha Creación</th></tr></thead>";
    echo "<tbody>";
    while ($user = $users->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td><strong>" . htmlspecialchars($user['usuario']) . "</strong></td>";
        echo "<td><span class='badge badge-" . ($user['rol'] == 'admin' ? 'success' : 'warning') . "'>" . htmlspecialchars($user['rol']) . "</span></td>";
        echo "<td>" . ($user['activo'] ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-error">Inactivo</span>') . "</td>";
        echo "<td>" . htmlspecialchars($user['email'] ?? '—') . "</td>";
        echo "<td>" . htmlspecialchars($user['nombre_completo'] ?? '—') . "</td>";
        echo "<td>" . date('d/m/Y H:i', strtotime($user['fecha_creacion'])) . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
} else {
    echo "<div class='warning'>No hay usuarios registrados en el sistema</div>";
}

// ============================================
// ESTADÍSTICAS
// ============================================
echo "<h2><i class='fas fa-chart-bar'></i> Estadísticas</h2>";

$stats = [];
$stats['total_usuarios'] = $conn->query("SELECT COUNT(*) as total FROM usuarios")->fetch_assoc()['total'];
$stats['usuarios_activos'] = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE activo = 1")->fetch_assoc()['total'];
$stats['total_logs'] = $conn->query("SELECT COUNT(*) as total FROM logs")->fetch_assoc()['total'];
$stats['total_registros'] = $conn->query("SELECT COUNT(*) as total FROM registros_ids")->fetch_assoc()['total'];

echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;'>";
echo "<div style='background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 20px; border-radius: 10px; text-align: center;'><div style='font-size: 32px; font-weight: bold;'>" . $stats['total_usuarios'] . "</div><div>Total Usuarios</div></div>";
echo "<div style='background: linear-gradient(135deg, #27ae60, #229954); color: white; padding: 20px; border-radius: 10px; text-align: center;'><div style='font-size: 32px; font-weight: bold;'>" . $stats['usuarios_activos'] . "</div><div>Usuarios Activos</div></div>";
echo "<div style='background: linear-gradient(135deg, #9b59b6, #8e44ad); color: white; padding: 20px; border-radius: 10px; text-align: center;'><div style='font-size: 32px; font-weight: bold;'>" . number_format($stats['total_logs']) . "</div><div>Registros de Logs</div></div>";
echo "<div style='background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; padding: 20px; border-radius: 10px; text-align: center;'><div style='font-size: 32px; font-weight: bold;'>" . $stats['total_registros'] . "</div><div>Registros en Bitácora</div></div>";
echo "</div>";

// ============================================
// BOTONES DE ACCIÓN
// ============================================
echo "<div style='text-align: center; margin-top: 30px;'>";
echo "<a href='login.php' class='btn btn-primary'><i class='fas fa-sign-in-alt'></i> Ir al Login</a>";
echo "<a href='index.php' class='btn btn-success'><i class='fas fa-chart-line'></i> Ir al Dashboard</a>";
echo "<a href='logs.php' class='btn btn-primary'><i class='fas fa-history'></i> Ver Logs</a>";
echo "</div>";

$conn->close();
?>

        </div>
        <div class="footer">
            <p><i class="fas fa-check-circle"></i> Proceso de corrección completado</p>
            <p style="font-size: 12px; color: #7f8c8d; margin-top: 10px;">© 2024 - Sistema Bitácora de Registro</p>
        </div>
    </div>
</body>
</html>