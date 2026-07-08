<?php
// crear_usuario.php - Crear nuevo usuario
session_start();
require_once 'config.php';

// Verificar que el usuario esté logueado y sea administrador
if (!isset($_SESSION['usuario']) || !esAdministrador($conn)) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmar_password = $_POST['confirmar_password'] ?? '';
    $rol = $_POST['rol'] ?? 'usuario';
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    // Validaciones
    if (empty($usuario)) {
        $error = "El nombre de usuario es obligatorio";
    } elseif (strlen($usuario) < 3) {
        $error = "El usuario debe tener al menos 3 caracteres";
    } elseif (empty($password)) {
        $error = "La contraseña es obligatoria";
    } elseif (strlen($password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres";
    } elseif ($password !== $confirmar_password) {
        $error = "Las contraseñas no coinciden";
    } else {
        // Verificar si el usuario ya existe
        $check_sql = "SELECT id FROM usuarios WHERE usuario = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $usuario);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "El usuario '$usuario' ya existe";
        } else {
            // Crear usuario
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $insert_sql = "INSERT INTO usuarios (usuario, password, rol, activo) VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("sssi", $usuario, $password_hash, $rol, $activo);
            
            if ($insert_stmt->execute()) {
                $success = "Usuario '$usuario' creado correctamente";
                
                registrarLog($conn, $_SESSION['usuario'], 
                    "Creó nuevo usuario: $usuario", 
                    "crear_usuario.php", 
                    obtenerIP(),
                    [
                        'usuario_creado' => $usuario,
                        'rol' => $rol,
                        'activo' => $activo
                    ]
                );
                
                // Limpiar formulario
                $_POST = [];
            } else {
                $error = "Error al crear usuario: " . $conn->error;
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Usuario - Bitácora Militar</title>
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
        .menu-item i { width: 20px; }
        
        .main-content { flex: 1; margin-left: 280px; padding: 20px; min-height: 100vh; }
        .top-bar { background: white; padding: 15px 25px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #3498db, #2980b9); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); padding: 30px; }
        h1 { color: #2c3e50; margin-bottom: 10px; border-bottom: 3px solid #c0392b; padding-bottom: 10px; }
        .subtitulo { color: #7f8c8d; margin-bottom: 20px; font-size: 14px; }
        
        .mensaje { padding: 12px 20px; margin-bottom: 20px; border-radius: 5px; font-weight: 500; }
        .mensaje.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .mensaje.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #2c3e50; font-weight: 600; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 5px; font-size: 14px; transition: all 0.3s; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #3498db; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .checkbox-group input { width: auto; }
        
        .btn { padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .btn-primary { background: linear-gradient(135deg, #3498db, #2980b9); color: white; }
        .btn-secondary { background: #95a5a6; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
        
        .buttons { display: flex; gap: 10px; margin-top: 20px; }
        
        @media (max-width: 768px) {
            .sidebar { left: -280px; }
            .sidebar.active { left: 0; }
            .main-content { margin-left: 0; }
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
            <a href="permisos.php" class="menu-item"><i class="fas fa-lock"></i> Permisos</a>
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
            <h1><i class="fas fa-user-plus"></i> Crear Nuevo Usuario</h1>
            <div class="subtitulo">Complete el formulario para registrar un nuevo usuario</div>
            
            <?php if ($error): ?>
                <div class="mensaje error">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="mensaje success">✅ <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Nombre de Usuario *</label>
                    <input type="text" name="usuario" required value="<?php echo htmlspecialchars($_POST['usuario'] ?? ''); ?>">
                    <small style="color: #7f8c8d;">Mínimo 3 caracteres</small>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Contraseña *</label>
                    <input type="password" name="password" required>
                    <small style="color: #7f8c8d;">Mínimo 6 caracteres</small>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Confirmar Contraseña *</label>
                    <input type="password" name="confirmar_password" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Rol</label>
                    <select name="rol">
                        <option value="usuario">👤 Usuario</option>
                        <option value="administrador">👑 Administrador</option>
                    </select>
                </div>
                
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="activo" id="activo" checked>
                    <label for="activo">Usuario activo</label>
                </div>
                
                <div class="buttons">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Crear Usuario</button>
                    <a href="usuarios.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        if (menuToggle) {
            menuToggle.addEventListener('click', () => sidebar.classList.toggle('active'));
        }
    </script>
</body>
</html>