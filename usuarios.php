<?php
// usuarios.php - Gestión de usuarios del sistema con estadísticas
session_start();
require_once 'config.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Verificar que sea administrador
if (!esAdministrador($conn)) {
    $_SESSION['mensaje'] = "No tienes permisos para acceder a esta sección";
    $_SESSION['tipo_mensaje'] = "error";
    header("Location: index.php");
    exit();
}

// Obtener página anterior para el botón volver
$pagina_anterior = $_SERVER['HTTP_REFERER'] ?? 'index.php';
if (strpos($pagina_anterior, 'usuarios.php') !== false || empty($pagina_anterior)) {
    $pagina_anterior = 'index.php';
}

// Procesar eliminación de usuario
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    
    // No permitir eliminar al propio usuario
    if ($id == $_SESSION['usuario_id']) {
        $_SESSION['mensaje'] = "No puedes eliminar tu propio usuario";
        $_SESSION['tipo_mensaje'] = "error";
    } else {
        // Obtener datos del usuario antes de eliminar
        $sql_select = "SELECT usuario, rol FROM usuarios WHERE id = ?";
        $stmt_select = $conn->prepare($sql_select);
        $stmt_select->bind_param("i", $id);
        $stmt_select->execute();
        $result_select = $stmt_select->get_result();
        $usuario_eliminar = $result_select->fetch_assoc();
        
        if ($usuario_eliminar) {
            $sql = "DELETE FROM usuarios WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $_SESSION['mensaje'] = "Usuario eliminado correctamente";
                $_SESSION['tipo_mensaje'] = "success";
                
                registrarLog($conn, $_SESSION['usuario'], 
                    "Eliminó usuario: " . $usuario_eliminar['usuario'], 
                    "usuarios.php", 
                    obtenerIP(),
                    [
                        'id_usuario' => $id,
                        'usuario_eliminado' => $usuario_eliminar['usuario'],
                        'rol' => $usuario_eliminar['rol']
                    ]
                );
            } else {
                $_SESSION['mensaje'] = "Error al eliminar usuario";
                $_SESSION['tipo_mensaje'] = "error";
            }
            $stmt->close();
        } else {
            $_SESSION['mensaje'] = "Usuario no encontrado";
            $_SESSION['tipo_mensaje'] = "warning";
        }
        $stmt_select->close();
    }
    
    header("Location: usuarios.php");
    exit();
}

// Procesar cambio de estado (activar/desactivar)
if (isset($_GET['cambiar_estado']) && is_numeric($_GET['cambiar_estado'])) {
    $id = intval($_GET['cambiar_estado']);
    
    // No permitir desactivar al propio usuario
    if ($id == $_SESSION['usuario_id']) {
        $_SESSION['mensaje'] = "No puedes cambiar tu propio estado";
        $_SESSION['tipo_mensaje'] = "error";
    } else {
        // Obtener estado actual
        $sql_select = "SELECT usuario, activo FROM usuarios WHERE id = ?";
        $stmt_select = $conn->prepare($sql_select);
        $stmt_select->bind_param("i", $id);
        $stmt_select->execute();
        $result_select = $stmt_select->get_result();
        $usuario = $result_select->fetch_assoc();
        
        if ($usuario) {
            $nuevo_estado = $usuario['activo'] ? 0 : 1;
            $estado_texto = $nuevo_estado ? "activado" : "desactivado";
            
            $sql = "UPDATE usuarios SET activo = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $nuevo_estado, $id);
            
            if ($stmt->execute()) {
                $_SESSION['mensaje'] = "Usuario " . $estado_texto . " correctamente";
                $_SESSION['tipo_mensaje'] = "success";
                
                registrarLog($conn, $_SESSION['usuario'], 
                    "Cambió estado del usuario: " . $usuario['usuario'] . " a " . $estado_texto, 
                    "usuarios.php", 
                    obtenerIP(),
                    [
                        'usuario_modificado' => $usuario['usuario'],
                        'nuevo_estado' => $nuevo_estado,
                        'estado_texto' => $estado_texto
                    ]
                );
            } else {
                $_SESSION['mensaje'] = "Error al cambiar estado";
                $_SESSION['tipo_mensaje'] = "error";
            }
            $stmt->close();
        }
        $stmt_select->close();
    }
    
    header("Location: usuarios.php");
    exit();
}

// Obtener estadísticas de usuarios
$stats_usuarios = [
    'total' => 0,
    'activos' => 0,
    'inactivos' => 0,
    'administradores' => 0,
    'usuarios_normales' => 0,
    'administradores_activos' => 0,
    'usuarios_activos' => 0
];

// Total de usuarios
$stats_usuarios['total'] = $conn->query("SELECT COUNT(*) as total FROM usuarios")->fetch_assoc()['total'] ?? 0;

// Usuarios ACTIVOS (solo los que tienen activo = 1)
$stats_usuarios['activos'] = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE activo = 1")->fetch_assoc()['total'] ?? 0;

// Usuarios INACTIVOS
$stats_usuarios['inactivos'] = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE activo = 0")->fetch_assoc()['total'] ?? 0;

// Total de administradores
$stats_usuarios['administradores'] = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE rol = 'administrador'")->fetch_assoc()['total'] ?? 0;

// Total de usuarios normales
$stats_usuarios['usuarios_normales'] = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE rol = 'usuario'")->fetch_assoc()['total'] ?? 0;

// Administradores ACTIVOS
$stats_usuarios['administradores_activos'] = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE rol = 'administrador' AND activo = 1")->fetch_assoc()['total'] ?? 0;

// Usuarios normales ACTIVOS
$stats_usuarios['usuarios_activos'] = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE rol = 'usuario' AND activo = 1")->fetch_assoc()['total'] ?? 0;

// Obtener lista de usuarios
$sql = "SELECT id, usuario, rol, activo, fecha_creacion, ultimo_login FROM usuarios ORDER BY id DESC";
$resultado = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Bitácora Militar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; min-height: 100vh; }
        
        /* Main content */
        .main-content { padding: 20px; min-height: 100vh; }
        .top-bar { background: white; padding: 15px 25px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        
        /* ===== ESTILOS DEL BOTÓN VOLVER ===== */
        .btn-Volver {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #f8f9fa;
            color: #2c3e50;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid #e0e0e0;
        }
        
        .btn-Volver:hover {
            background: #e9ecef;
            border-color: #c0392b;
            color: #c0392b;
            transform: translateX(-3px);
        }
        
        .btn-Volver i {
            font-size: 12px;
        }
        
        .btn-Volver-secondary {
            background: #2c3e50;
            color: white;
            border-color: #2c3e50;
        }
        
        .btn-Volver-secondary:hover {
            background: #c0392b;
            border-color: #c0392b;
            color: white;
        }
        
        .btn-Volver-primary {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .btn-Volver-primary:hover {
            background: #2980b9;
            border-color: #2980b9;
            color: white;
        }
        
        /* Navegación */
        .nav-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 15px;
            background: white;
            padding: 10px 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .breadcrumb a {
            color: #3498db;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .breadcrumb i {
            font-size: 10px;
            color: #95a5a6;
        }
        
        /* Container */
        .container { max-width: 1400px; margin: 0 auto; background-color: white; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); padding: 25px; }
        h1 { color: #2c3e50; margin-bottom: 10px; border-bottom: 3px solid #c0392b; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .subtitulo { color: #7f8c8d; margin-bottom: 20px; font-size: 14px; }
        
        /* Header con botones */
        .header-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        /* Estadísticas de usuarios */
        .stats-usuarios { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card .numero { font-size: 2.5em; font-weight: bold; }
        .stat-card .etiqueta { font-size: 0.9em; opacity: 0.9; margin-top: 5px; }
        .stat-card.total { background: linear-gradient(135deg, #3498db, #2980b9); }
        .stat-card.activos { background: linear-gradient(135deg, #27ae60, #229954); }
        .stat-card.inactivos { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .stat-card.admin { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        .stat-card.usuarios-normales { background: linear-gradient(135deg, #f39c12, #e67e22); }
        
        /* Mensajes */
        .mensaje { padding: 12px 20px; margin-bottom: 20px; border-radius: 5px; font-weight: 500; animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { transform: translateY(-10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .mensaje.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .mensaje.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .mensaje.warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        
        /* Botones */
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; transition: all 0.3s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
        .btn-primary { background-color: #3498db; color: white; }
        .btn-success { background-color: #27ae60; color: white; }
        .btn-danger { background-color: #e74c3c; color: white; }
        .btn-warning { background-color: #f39c12; color: white; }
        .btn-info { background-color: #9b59b6; color: white; }
        
        /* Botón flotante */
        .floating-back {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: #c0392b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .floating-back a {
            color: white;
            text-decoration: none;
            font-size: 20px;
        }
        
        .floating-back:hover {
            background: #e74c3c;
            transform: scale(1.1);
        }
        
        /* Tabla */
        .table-container { overflow-x: auto; margin-top: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; background-color: white; }
        th { background: linear-gradient(135deg, #2c3e50, #34495e); color: white; padding: 12px; text-align: left; font-size: 14px; font-weight: 600; }
        td { padding: 12px; border-bottom: 1px solid #ecf0f1; vertical-align: middle; }
        tr:hover { background-color: #f5f9ff; }
        
        /* Badges */
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .badge-activo { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .badge-inactivo { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .badge-admin { background-color: #c0392b; color: white; }
        .badge-usuario { background-color: #8e44ad; color: white; }
        
        /* Acciones */
        .acciones { display: flex; gap: 5px; flex-wrap: wrap; }
        .acciones a { padding: 5px 10px; border-radius: 3px; text-decoration: none; font-size: 11px; color: white; transition: all 0.3s; display: inline-flex; align-items: center; gap: 3px; }
        .acciones a:hover { transform: translateY(-2px); box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .acciones .editar { background-color: #f39c12; }
        .acciones .eliminar { background-color: #e74c3c; }
        .acciones .cambiar-estado { background-color: #3498db; }
        
        /* Tooltip */
        [data-tooltip] {
            position: relative;
            cursor: help;
        }
        
        [data-tooltip]:before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 5px 10px;
            background-color: #2c3e50;
            color: white;
            font-size: 11px;
            border-radius: 3px;
            white-space: nowrap;
            display: none;
            z-index: 1000;
        }
        
        [data-tooltip]:hover:before {
            display: block;
        }
        
        /* Info panel */
        .info-panel {
            background: #e8f4fd;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 13px;
        }
        
        @media (max-width: 768px) {
            .main-content { padding: 15px; }
            h1 { flex-direction: column; align-items: stretch; }
            .header-buttons { justify-content: space-between; }
            .stats-usuarios { grid-template-columns: repeat(2, 1fr); }
            .floating-back { bottom: 20px; right: 20px; width: 45px; height: 45px; }
            .top-bar { flex-direction: column; align-items: stretch; }
            .nav-buttons { justify-content: space-between; }
        }
        
        @media print {
            .no-print, .top-bar, .acciones, .stats-usuarios, .floating-back, .breadcrumb, .nav-buttons, .info-panel { display: none; }
            .main-content { padding: 0; }
            .container { box-shadow: none; padding: 0; }
        }
    </style>
</head>
<body>
    <!-- ===== BOTÓN FLOTANTE VOLVER ===== -->
    <div class="floating-back" data-tooltip="Volver al inicio">
        <a href="<?php echo htmlspecialchars($pagina_anterior); ?>">
            <i class="fas fa-arrow-left"></i>
        </a>
    </div>

    <div class="main-content">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="index.php"><i class="fas fa-home"></i> Inicio</a>
            <i class="fas fa-chevron-right"></i>
            <a href="admin_panel.php"><i class="fas fa-shield-alt"></i> Panel Admin</a>
            <i class="fas fa-chevron-right"></i>
            <span><i class="fas fa-users"></i> Gestión de Usuarios</span>
        </div>

        <!-- Top bar con botones de volver -->
        <div class="top-bar">
            <div class="nav-buttons">
                <!-- Botón volver a la página anterior -->
                <a href="<?php echo htmlspecialchars($pagina_anterior); ?>" class="btn-Volver" data-tooltip="Volver a la página anterior">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
                <!-- Botón volver al inicio -->
                <a href="index.php" class="btn-Volver btn-Volver-primary" data-tooltip="Ir al panel principal">
                    <i class="fas fa-home"></i> Inicio
                </a>
                <!-- Botón volver con historial -->
                <a href="javascript:history.back()" class="btn-Volver btn-Volver-secondary" data-tooltip="Volver a la página visitada anteriormente">
                    <i class="fas fa-undo-alt"></i> Atrás
                </a>
            </div>
            <div class="user-info">
                <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario']); ?></span>
                <div class="user-avatar"><?php echo substr($_SESSION['usuario'], 0, 1); ?></div>
            </div>
        </div>

        <div class="container">
            <h1>
                <span><i class="fas fa-users"></i> Gestión de Usuarios del Sistema</span>
                <div class="header-buttons">
                    <a href="admin_panel.php" class="btn btn-info" data-tooltip="Volver al panel de administración">
                        <i class="fas fa-shield-alt"></i> Panel Admin
                    </a>
                    <a href="crear_usuario.php" class="btn btn-success" data-tooltip="Crear un nuevo usuario">
                        <i class="fas fa-plus"></i> Nuevo Usuario
                    </a>
                </div>
            </h1>
            <div class="subtitulo">Administración de usuarios que pueden acceder al sistema</div>
            
            <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="mensaje <?php echo $_SESSION['tipo_mensaje']; ?>">
                    <?php echo htmlspecialchars($_SESSION['mensaje']); 
                    unset($_SESSION['mensaje']); 
                    unset($_SESSION['tipo_mensaje']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Estadísticas de usuarios del SISTEMA (no de la bitácora) -->
            <div class="stats-usuarios">
                <div class="stat-card total">
                    <div class="numero"><?php echo $stats_usuarios['total']; ?></div>
                    <div class="etiqueta">Total Usuarios</div>
                </div>
                <div class="stat-card activos">
                    <div class="numero"><?php echo $stats_usuarios['activos']; ?></div>
                    <div class="etiqueta">
                        <i class="fas fa-check-circle"></i> Usuarios ACTIVOS
                        <div style="font-size: 11px; margin-top: 5px;">
                            (Solo los que pueden acceder)
                        </div>
                    </div>
                </div>
                <div class="stat-card inactivos">
                    <div class="numero"><?php echo $stats_usuarios['inactivos']; ?></div>
                    <div class="etiqueta">
                        <i class="fas fa-ban"></i> Usuarios Inactivos
                    </div>
                </div>
                <div class="stat-card admin">
                    <div class="numero"><?php echo $stats_usuarios['administradores_activos']; ?>/<?php echo $stats_usuarios['administradores']; ?></div>
                    <div class="etiqueta">
                        <i class="fas fa-crown"></i> Administradores
                        <div style="font-size: 11px;">
                            (<?php echo $stats_usuarios['administradores_activos']; ?> activos)
                        </div>
                    </div>
                </div>
                <div class="stat-card usuarios-normales">
                    <div class="numero"><?php echo $stats_usuarios['usuarios_activos']; ?>/<?php echo $stats_usuarios['usuarios_normales']; ?></div>
                    <div class="etiqueta">
                        <i class="fas fa-user"></i> Usuarios Normales
                        <div style="font-size: 11px;">
                            (<?php echo $stats_usuarios['usuarios_activos']; ?> activos)
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Fecha Creación</th>
                            <th>Último Login</th>
                            <th class="no-print">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultado && $resultado->num_rows > 0): ?>
                            <?php while ($row = $resultado->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['usuario']); ?></strong>
                                        <?php if ($row['id'] == $_SESSION['usuario_id']): ?>
                                            <span style="color: #27ae60; font-size: 10px;"> (Tú)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $row['rol'] == 'administrador' ? 'badge-admin' : 'badge-usuario'; ?>">
                                            <?php echo $row['rol'] == 'administrador' ? '👑 Administrador' : '👤 Usuario'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $row['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                            <?php echo $row['activo'] ? '🟢 Activo' : '🔴 Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($row['fecha_creacion'])); ?></td>
                                    <td><?php echo $row['ultimo_login'] ? date('d/m/Y H:i', strtotime($row['ultimo_login'])) : 'Nunca'; ?></td>
                                    <td class="acciones no-print">
                                        <a href="editar_usuario.php?id=<?php echo $row['id']; ?>" class="editar" title="Editar usuario">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                        <?php if ($row['id'] != $_SESSION['usuario_id']): ?>
                                            <a href="usuarios.php?cambiar_estado=<?php echo $row['id']; ?>" class="cambiar-estado" onclick="return confirmarCambioEstado('<?php echo $row['usuario']; ?>', <?php echo $row['activo']; ?>)" title="Cambiar estado">
                                                <i class="fas <?php echo $row['activo'] ? 'fa-ban' : 'fa-check-circle'; ?>"></i>
                                                <?php echo $row['activo'] ? 'Desactivar' : 'Activar'; ?>
                                            </a>
                                            <a href="usuarios.php?eliminar=<?php echo $row['id']; ?>" class="eliminar" onclick="return confirmarEliminacion('<?php echo $row['usuario']; ?>')" title="Eliminar usuario">
                                                <i class="fas fa-trash"></i> Eliminar
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px;">
                                    <span style="font-size: 48px;">👥</span><br>
                                    <strong>No hay usuarios registrados</strong>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Panel de información adicional -->
            <div class="info-panel">
                <div>
                    <i class="fas fa-info-circle" style="color: #3498db;"></i>
                    <strong>Nota:</strong> No puedes eliminar o desactivar tu propio usuario.
                </div>
                <div>
                    <i class="fas fa-keyboard"></i>
                    Atajos: <kbd>Ctrl</kbd> + <kbd>B</kbd> Volver | <kbd>Ctrl</kbd> + <kbd>N</kbd> Nuevo usuario
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmarEliminacion(usuario) {
            return confirm('¿Estás seguro de eliminar al usuario "' + usuario + '"?\nEsta acción no se puede deshacer.');
        }
        
        function confirmarCambioEstado(usuario, estadoActual) {
            const accion = estadoActual ? 'desactivar' : 'activar';
            return confirm('¿Estás seguro de ' + accion + ' al usuario "' + usuario + '"?\n' + 
                (estadoActual ? 'El usuario no podrá iniciar sesión.' : 'El usuario podrá acceder al sistema.'));
        }
        
        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl + B = Volver atrás
            if (e.ctrlKey && e.key === 'b') {
                e.preventDefault();
                window.history.back();
            }
            // Ctrl + N = Nuevo usuario
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'crear_usuario.php';
            }
            // Ctrl + H = Ir al inicio
            if (e.ctrlKey && e.key === 'h') {
                e.preventDefault();
                window.location.href = 'index.php';
            }
        });
        
        // Mostrar notificación de atajos (solo una vez por sesión)
        if (!sessionStorage.getItem('atajos_mostrados')) {
            setTimeout(function() {
                const notif = document.createElement('div');
                notif.style.cssText = `
                    position: fixed;
                    bottom: 100px;
                    right: 30px;
                    background: #2c3e50;
                    color: white;
                    padding: 12px 20px;
                    border-radius: 8px;
                    font-size: 13px;
                    z-index: 9999;
                    animation: fadeOut 5s ease;
                    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                `;
                notif.innerHTML = '<i class="fas fa-keyboard"></i> Atajos: Ctrl+B (Volver), Ctrl+N (Nuevo), Ctrl+H (Inicio)';
                document.body.appendChild(notif);
                
                const style = document.createElement('style');
                style.textContent = `
                    @keyframes fadeOut {
                        0% { opacity: 1; transform: translateY(0); }
                        70% { opacity: 1; transform: translateY(0); }
                        100% { opacity: 0; transform: translateY(-20px); visibility: hidden; }
                    }
                `;
                document.head.appendChild(style);
                
                setTimeout(() => notif.remove(), 5000);
                sessionStorage.setItem('atajos_mostrados', 'true');
            }, 1000);
        }
    </script>
</body>
</html>