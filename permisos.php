<?php
// permisos.php - Gestión de permisos del sistema
session_start();
require_once 'config.php';

// Verificar que el usuario esté logueado y sea administrador
if (!isset($_SESSION['usuario']) || !esAdministrador($conn)) {
    header("Location: login.php");
    exit();
}

// Definir los módulos y permisos disponibles
$modulos = [
    'dashboard' => ['nombre' => 'Dashboard', 'icono' => 'fa-home', 'descripcion' => 'Ver panel principal'],
    'crear_registro' => ['nombre' => 'Crear Registro', 'icono' => 'fa-plus-circle', 'descripcion' => 'Crear nuevos registros'],
    'editar_registro' => ['nombre' => 'Editar Registro', 'icono' => 'fa-edit', 'descripcion' => 'Editar registros existentes'],
    'eliminar_registro' => ['nombre' => 'Eliminar Registro', 'icono' => 'fa-trash', 'descripcion' => 'Eliminar registros'],
    'ver_logs' => ['nombre' => 'Ver Logs', 'icono' => 'fa-history', 'descripcion' => 'Visualizar logs del sistema'],
    'importar' => ['nombre' => 'Importar', 'icono' => 'fa-file-import', 'descripcion' => 'Importar datos desde archivos'],
    'exportar' => ['nombre' => 'Exportar', 'icono' => 'fa-file-export', 'descripcion' => 'Exportar datos a archivos'],
    'gestion_usuarios' => ['nombre' => 'Gestionar Usuarios', 'icono' => 'fa-users', 'descripcion' => 'Crear, editar y eliminar usuarios'],
    'gestion_permisos' => ['nombre' => 'Gestionar Permisos', 'icono' => 'fa-lock', 'descripcion' => 'Configurar permisos de usuarios'],
    'respaldo' => ['nombre' => 'Respaldo', 'icono' => 'fa-database', 'descripcion' => 'Realizar respaldos de la base de datos']
];

// Crear tabla de permisos si no existe
$create_permisos = "
CREATE TABLE IF NOT EXISTS permisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    modulo VARCHAR(50) NOT NULL,
    permitido TINYINT DEFAULT 1,
    fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_permiso (usuario_id, modulo),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($create_permisos);

// Procesar actualización de permisos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_permisos'])) {
    $usuario_id = intval($_POST['usuario_id']);
    $permisos_asignados = $_POST['permisos'] ?? [];
    
    // Eliminar permisos existentes del usuario
    $delete_sql = "DELETE FROM permisos WHERE usuario_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $usuario_id);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    // Insertar nuevos permisos
    $insert_sql = "INSERT INTO permisos (usuario_id, modulo) VALUES (?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    
    $asignados = 0;
    foreach ($modulos as $modulo_key => $modulo) {
        if (in_array($modulo_key, $permisos_asignados)) {
            $insert_stmt->bind_param("is", $usuario_id, $modulo_key);
            $insert_stmt->execute();
            $asignados++;
        }
    }
    $insert_stmt->close();
    
    // Obtener nombre del usuario
    $user_sql = "SELECT usuario FROM usuarios WHERE id = ?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param("i", $usuario_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $usuario_nombre = $user_result->fetch_assoc()['usuario'];
    $user_stmt->close();
    
    registrarLog($conn, $_SESSION['usuario'], 
        "Actualizó permisos del usuario: $usuario_nombre", 
        "permisos.php", 
        obtenerIP(),
        [
            'usuario_modificado' => $usuario_nombre,
            'permisos_asignados' => $asignados,
            'total_permisos' => count($modulos)
        ]
    );
    
    $_SESSION['mensaje'] = "Permisos actualizados correctamente para el usuario $usuario_nombre";
    $_SESSION['tipo_mensaje'] = "success";
    header("Location: permisos.php");
    exit();
}

// Obtener lista de usuarios
$usuarios_sql = "SELECT id, usuario, rol FROM usuarios ORDER BY id";
$usuarios_result = $conn->query($usuarios_sql);

// Obtener permisos actuales si se selecciona un usuario
$usuario_seleccionado = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : 0;
$permisos_actuales = [];

if ($usuario_seleccionado > 0) {
    $permisos_sql = "SELECT modulo FROM permisos WHERE usuario_id = ?";
    $permisos_stmt = $conn->prepare($permisos_sql);
    $permisos_stmt->bind_param("i", $usuario_seleccionado);
    $permisos_stmt->execute();
    $permisos_result = $permisos_stmt->get_result();
    
    while ($row = $permisos_result->fetch_assoc()) {
        $permisos_actuales[] = $row['modulo'];
    }
    $permisos_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Permisos - Bitácora Militar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; min-height: 100vh; display: flex; }
        
        .sidebar { width: 280px; background: linear-gradient(180deg, #1a2639 0%, #2c3e50 100%); color: white; height: 100vh; position: fixed; left: 0; top: 0; overflow-y: auto; transition: all 0.3s; z-index: 1000; }
        .sidebar-header { padding: 25px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h3 { font-size: 22px; color: #fff; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item { padding: 12px 25px; display: flex; align-items: center; gap: 15px; color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s; border-left: 4px solid transparent; }
        .menu-item:hover { background: rgba(255,255,255,0.1); color: white; border-left-color: #3498db; }
        .menu-item.active { background: rgba(52,152,219,0.2); color: white; border-left-color: #3498db; }
        .menu-item i { width: 20px; }
        
        .main-content { flex: 1; margin-left: 280px; padding: 20px; min-height: 100vh; }
        .top-bar { background: white; padding: 15px 25px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #3498db, #2980b9); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        
        .container { max-width: 1000px; margin: 0 auto; background: white; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); padding: 30px; }
        h1 { color: #2c3e50; margin-bottom: 10px; border-bottom: 3px solid #c0392b; padding-bottom: 10px; }
        .subtitulo { color: #7f8c8d; margin-bottom: 20px; font-size: 14px; }
        
        .mensaje { padding: 12px 20px; margin-bottom: 20px; border-radius: 5px; font-weight: 500; }
        .mensaje.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        
        .selector-usuario { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 25px; }
        .selector-usuario label { font-weight: 600; margin-right: 10px; }
        .selector-usuario select { padding: 10px; border: 2px solid #e0e0e0; border-radius: 5px; min-width: 200px; }
        .selector-usuario button { padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px; }
        
        .permisos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; margin: 20px 0; }
        .permiso-item { background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 15px; transition: all 0.3s; }
        .permiso-item:hover { background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .permiso-item label { display: flex; align-items: center; gap: 12px; cursor: pointer; }
        .permiso-item input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; }
        .permiso-icono { width: 40px; height: 40px; background: linear-gradient(135deg, #3498db, #2980b9); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; }
        .permiso-info { flex: 1; }
        .permiso-info h4 { margin: 0 0 5px 0; color: #2c3e50; }
        .permiso-info p { margin: 0; font-size: 12px; color: #7f8c8d; }
        
        .btn { padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .btn-primary { background: linear-gradient(135deg, #3498db, #2980b9); color: white; }
        .btn-secondary { background: #95a5a6; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
        
        .buttons { display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end; }
        
        @media (max-width: 768px) {
            .sidebar { left: -280px; }
            .sidebar.active { left: 0; }
            .main-content { margin-left: 0; }
            .permisos-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>📋 BITÁCORA</h3>
        </div>
        <div class="sidebar-menu">
            <a href="index.php" class="menu-item"><i class="fas fa-home"></i> Dashboard</a>
            <a href="usuarios.php" class="menu-item"><i class="fas fa-users"></i> Usuarios</a>
            <a href="permisos.php" class="menu-item active"><i class="fas fa-lock"></i> Permisos</a>
            <a href="logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <button class="menu-toggle" id="menuToggle" style="background: none; border: none; font-size: 24px; cursor: pointer;">☰</button>
            <div class="user-info">
                <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario']); ?></span>
                <div class="user-avatar"><?php echo substr($_SESSION['usuario'], 0, 1); ?></div>
            </div>
        </div>

        <div class="container">
            <h1><i class="fas fa-lock"></i> Gestión de Permisos</h1>
            <div class="subtitulo">Configura los permisos de acceso para cada usuario del sistema</div>
            
            <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="mensaje <?php echo $_SESSION['tipo_mensaje']; ?>">
                    <?php echo htmlspecialchars($_SESSION['mensaje']); 
                    unset($_SESSION['mensaje']); 
                    unset($_SESSION['tipo_mensaje']); ?>
                </div>
            <?php endif; ?>
            
            <form method="GET" action="permisos.php" class="selector-usuario">
                <label><i class="fas fa-user"></i> Seleccionar Usuario:</label>
                <select name="usuario_id" onchange="this.form.submit()">
                    <option value="">-- Seleccione un usuario --</option>
                    <?php while ($user = $usuarios_result->fetch_assoc()): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $usuario_seleccionado == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['usuario']); ?> 
                            (<?php echo $user['rol'] == 'administrador' ? 'Administrador' : 'Usuario'; ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </form>
            
            <?php if ($usuario_seleccionado > 0): 
                $nombre_usuario_sql = "SELECT usuario FROM usuarios WHERE id = ?";
                $nombre_stmt = $conn->prepare($nombre_usuario_sql);
                $nombre_stmt->bind_param("i", $usuario_seleccionado);
                $nombre_stmt->execute();
                $nombre_result = $nombre_stmt->get_result();
                $nombre_usuario = $nombre_result->fetch_assoc()['usuario'];
                $nombre_stmt->close();
            ?>
                <form method="POST" action="permisos.php">
                    <input type="hidden" name="usuario_id" value="<?php echo $usuario_seleccionado; ?>">
                    
                    <div class="permisos-grid">
                        <?php foreach ($modulos as $modulo_key => $modulo): ?>
                            <div class="permiso-item">
                                <label>
                                    <input type="checkbox" name="permisos[]" value="<?php echo $modulo_key; ?>" 
                                        <?php echo in_array($modulo_key, $permisos_actuales) ? 'checked' : ''; ?>
                                        <?php echo $nombre_usuario == $_SESSION['usuario'] && $modulo_key == 'gestion_permisos' ? 'disabled' : ''; ?>>
                                    <div class="permiso-icono">
                                        <i class="fas <?php echo $modulo['icono']; ?>"></i>
                                    </div>
                                    <div class="permiso-info">
                                        <h4><?php echo $modulo['nombre']; ?></h4>
                                        <p><?php echo $modulo['descripcion']; ?></p>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="buttons">
                        <button type="submit" name="guardar_permisos" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar Permisos
                        </button>
                        <a href="permisos.php" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Limpiar
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <div style="text-align: center; padding: 50px; color: #7f8c8d;">
                    <i class="fas fa-user-lock" style="font-size: 64px; margin-bottom: 20px;"></i>
                    <h3>Seleccione un usuario para gestionar sus permisos</h3>
                    <p>Desde aquí podrá asignar o quitar permisos de acceso a los diferentes módulos del sistema</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        if (menuToggle) {
            menuToggle.addEventListener('click', () => sidebar.classList.toggle('active'));
        }
        
        // Deshabilitar seleccionar todos si es el propio usuario editando permisos
        <?php if (isset($nombre_usuario) && $nombre_usuario == $_SESSION['usuario']): ?>
        document.querySelectorAll('input[value="gestion_permisos"]').forEach(cb => {
            cb.disabled = true;
            cb.checked = true;
        });
        <?php endif; ?>
    </script>
</body>
</html>