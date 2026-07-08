<?php
// admin_panel.php - Panel de administración principal
require_once 'config.php';

// Verificar autenticación
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Verificar que sea administrador
if (!esAdministrador($conn)) {
    registrarLog($conn, $_SESSION['usuario'], 
        "Intento no autorizado de acceder al panel de administración", 
        "admin_panel.php", 
        obtenerIP(),
        ['usuario' => $_SESSION['usuario']]
    );
    $_SESSION['mensaje'] = "No tienes permisos para acceder a esta sección";
    $_SESSION['tipo_mensaje'] = "error";
    header("Location: index.php");
    exit();
}

// Registrar acceso al panel
registrarLog($conn, $_SESSION['usuario'], 
    "Accedió al panel de administración", 
    "admin_panel.php", 
    obtenerIP(),
    ['fecha_acceso' => date('Y-m-d H:i:s')]
);

// Obtener página anterior para el botón volver
$pagina_anterior = $_SERVER['HTTP_REFERER'] ?? 'index.php';
if (strpos($pagina_anterior, 'admin_panel.php') !== false || empty($pagina_anterior)) {
    $pagina_anterior = 'index.php';
}

// ============================================
// OBTENER ESTADÍSTICAS DEL SISTEMA
// ============================================

// Estadísticas de registros
$stats = [
    'total_registros' => 0,
    'registros_activos' => 0,
    'registros_inactivos' => 0,
    'registros_ultimo_mes' => 0,
    'registros_hoy' => 0
];

$stats['total_registros'] = $conn->query("SELECT COUNT(*) as total FROM registros_ids")->fetch_assoc()['total'] ?? 0;
$stats['registros_activos'] = $conn->query("SELECT COUNT(*) as total FROM registros_ids WHERE estatus = 'Activo'")->fetch_assoc()['total'] ?? 0;
$stats['registros_inactivos'] = $conn->query("SELECT COUNT(*) as total FROM registros_ids WHERE estatus = 'Inactivo'")->fetch_assoc()['total'] ?? 0;
$stats['registros_ultimo_mes'] = $conn->query("SELECT COUNT(*) as total FROM registros_ids WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['total'] ?? 0;
$stats['registros_hoy'] = $conn->query("SELECT COUNT(*) as total FROM registros_ids WHERE DATE(fecha_registro) = CURDATE()")->fetch_assoc()['total'] ?? 0;

// Estadísticas de usuarios del sistema
$stats['usuarios_sistema'] = $conn->query("SELECT COUNT(*) as total FROM usuarios")->fetch_assoc()['total'] ?? 0;
$stats['usuarios_activos_sistema'] = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE activo = 1")->fetch_assoc()['total'] ?? 0;
$stats['administradores'] = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE rol = 'administrador' AND activo = 1")->fetch_assoc()['total'] ?? 0;

// Estadísticas de usuarios únicos en bitácora
$stats['usuarios_unicos'] = $conn->query("SELECT COUNT(DISTINCT usuario) as total FROM registros_ids WHERE usuario IS NOT NULL AND usuario != ''")->fetch_assoc()['total'] ?? 0;
$stats['roles_distintos'] = $conn->query("SELECT COUNT(DISTINCT roles) as total FROM registros_ids WHERE roles IS NOT NULL AND roles != ''")->fetch_assoc()['total'] ?? 0;

// Últimas actividades (logs)
$ultimos_logs = $conn->query("
    SELECT * FROM logs 
    ORDER BY fecha DESC 
    LIMIT 10
");

// Actividad por hora (últimas 24h)
$actividad_hora = $conn->query("
    SELECT HOUR(fecha) as hora, COUNT(*) as total 
    FROM logs 
    WHERE fecha >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY HOUR(fecha) 
    ORDER BY hora
");

$horas_data = [];
for ($i = 0; $i < 24; $i++) {
    $horas_data[$i] = 0;
}
while ($hora = $actividad_hora->fetch_assoc()) {
    $horas_data[$hora['hora']] = $hora['total'];
}

// Versión del sistema
$version_sistema = '1.0.0';
$fecha_version = '2025-01-01';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Bitácora</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f0f2f5; 
            min-height: 100vh;
        }
        
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
        
        /* Navegación */
        .nav-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .main-content { padding: 20px; min-height: 100vh; }
        
        .top-bar { 
            background: white; 
            padding: 15px 25px; 
            border-radius: 10px; 
            margin-bottom: 20px; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
        }
        
        .user-info { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
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
            margin-bottom: 20px;
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
        
        .container { max-width: 1400px; margin: 0 auto; }
        
        /* Dashboard cards */
        .dashboard-header {
            margin-bottom: 25px;
        }
        
        .dashboard-header h1 {
            color: #2c3e50;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .dashboard-header p {
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        
        .stat-info h3 {
            font-size: 28px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .stat-info p {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .stat-info small {
            color: #95a5a6;
            font-size: 11px;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
        }
        
        .stat-icon.total { background: linear-gradient(135deg, #3498db, #2980b9); }
        .stat-icon.activos { background: linear-gradient(135deg, #27ae60, #229954); }
        .stat-icon.usuarios { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        .stat-icon.roles { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .stat-icon.logs { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        
        /* Sección de módulos */
        .modules-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .modules-section h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .module-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            transition: all 0.3s;
            text-decoration: none;
            color: #2c3e50;
            display: block;
            border: 1px solid #e0e0e0;
        }
        
        .module-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: #3498db;
        }
        
        .module-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin-bottom: 15px;
        }
        
        .module-card h3 {
            font-size: 18px;
            margin-bottom: 8px;
        }
        
        .module-card p {
            color: #7f8c8d;
            font-size: 13px;
            line-height: 1.4;
        }
        
        .module-badge {
            display: inline-block;
            background: #e8f4fd;
            color: #3498db;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            margin-top: 12px;
        }
        
        /* Gráfico de actividad */
        .activity-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .activity-section h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .chart-container {
            height: 300px;
            position: relative;
        }
        
        /* Últimos logs */
        .logs-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .logs-section h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logs-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .logs-table th {
            text-align: left;
            padding: 12px;
            background: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
            font-size: 13px;
        }
        
        .logs-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #ecf0f1;
            font-size: 12px;
        }
        
        .logs-table tr:hover {
            background: #f5f9ff;
        }
        
        .badge-modulo {
            background: #e74c3c;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
        }
        
        .ver-mas {
            margin-top: 15px;
            text-align: right;
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; }
            .modules-grid { grid-template-columns: 1fr; }
            .logs-table { font-size: 11px; }
            .logs-table th, .logs-table td { padding: 8px; }
            .floating-back { bottom: 20px; right: 20px; width: 45px; height: 45px; }
            .top-bar { flex-direction: column; align-items: stretch; }
            .nav-buttons { justify-content: space-between; }
            .dashboard-header h1 { font-size: 24px; }
        }
        
        @media print {
            .no-print, .top-bar, .floating-back, .nav-buttons, .breadcrumb, .info-panel { display: none; }
            .main-content { padding: 0; }
            .stat-card, .module-card, .activity-section, .logs-section { break-inside: avoid; }
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
            <span><i class="fas fa-shield-alt"></i> Panel de Administración</span>
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
                <!-- Botón ver dashboard público -->
                <a href="index.php" class="btn-Volver" data-tooltip="Ver dashboard principal">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </div>
            <div class="user-info">
                <span>Bienvenido, <strong><?php echo htmlspecialchars($_SESSION['usuario']); ?></strong></span>
                <span class="badge-modulo" style="background: #c0392b;">Administrador</span>
                <div class="user-avatar"><?php echo substr($_SESSION['usuario'], 0, 1); ?></div>
            </div>
        </div>

        <div class="container">
            <!-- Dashboard header -->
            <div class="dashboard-header">
                <h1>
                    <i class="fas fa-shield-alt" style="color: #c0392b;"></i> 
                    Panel de Administración
                </h1>
                <p>Gestión completa del sistema Bitácora Militar</p>
            </div>

            <!-- Tarjetas de estadísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total_registros']); ?></h3>
                        <p>Total Registros</p>
                        <small>En la bitácora</small>
                    </div>
                    <div class="stat-icon total">
                        <i class="fas fa-database"></i>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['registros_activos']); ?></h3>
                        <p>Registros Activos</p>
                        <small><?php echo round(($stats['registros_activos'] / max($stats['total_registros'], 1)) * 100, 1); ?>% del total</small>
                    </div>
                    <div class="stat-icon activos">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['usuarios_unicos']); ?></h3>
                        <p>Usuarios Únicos</p>
                        <small>En la bitácora</small>
                    </div>
                    <div class="stat-icon usuarios">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['roles_distintos']); ?></h3>
                        <p>Roles Distintos</p>
                        <small>Asignados en registros</small>
                    </div>
                    <div class="stat-icon roles">
                        <i class="fas fa-tags"></i>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo $stats['usuarios_activos_sistema']; ?>/<?php echo $stats['usuarios_sistema']; ?></h3>
                        <p>Usuarios Sistema</p>
                        <small><?php echo $stats['administradores']; ?> administradores</small>
                    </div>
                    <div class="stat-icon logs">
                        <i class="fas fa-user-shield"></i>
                    </div>
                </div>
            </div>

            <!-- Módulos de administración -->
            <div class="modules-section">
                <h2>
                    <i class="fas fa-cogs" style="color: #3498db;"></i>
                    Módulos de Administración
                </h2>
                <div class="modules-grid">
                    <a href="usuarios.php" class="module-card">
                        <div class="module-icon" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h3>Gestión de Usuarios</h3>
                        <p>Administrar usuarios del sistema, roles y permisos de acceso</p>
                        <span class="module-badge"><i class="fas fa-users"></i> <?php echo $stats['usuarios_sistema']; ?> usuarios</span>
                    </a>
                    
                    <a href="usuarios_unicos.php" class="module-card">
                        <div class="module-icon" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <h3>Usuarios Únicos</h3>
                        <p>Gestión de usuarios registrados en la bitácora (sin duplicados)</p>
                        <span class="module-badge"><i class="fas fa-users"></i> <?php echo $stats['usuarios_unicos']; ?> usuarios únicos</span>
                    </a>
                    
                    <a href="roles.php" class="module-card">
                        <div class="module-icon" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                            <i class="fas fa-tags"></i>
                        </div>
                        <h3>Gestión de Roles</h3>
                        <p>Editar y administrar roles asignados a los registros</p>
                        <span class="module-badge"><i class="fas fa-tag"></i> <?php echo $stats['roles_distintos']; ?> roles distintos</span>
                    </a>
                    
                    <a href="reportes.php" class="module-card">
                        <div class="module-icon" style="background: linear-gradient(135deg, #27ae60, #229954);">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h3>Reportes y Estadísticas</h3>
                        <p>Visualizar reportes avanzados y estadísticas del sistema</p>
                        <span class="module-badge"><i class="fas fa-chart-line"></i> Análisis completo</span>
                    </a>
                    
                    <a href="logs.php" class="module-card">
                        <div class="module-icon" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
                            <i class="fas fa-history"></i>
                        </div>
                        <h3>Logs del Sistema</h3>
                        <p>Ver historial de actividades y acciones de los usuarios</p>
                        <span class="module-badge"><i class="fas fa-clock"></i> Auditoría completa</span>
                    </a>
                    
                    <a href="backup.php" class="module-card">
                        <div class="module-icon" style="background: linear-gradient(135deg, #1abc9c, #16a085);">
                            <i class="fas fa-database"></i>
                        </div>
                        <h3>Respaldos</h3>
                        <p>Gestionar respaldos de la base de datos</p>
                        <span class="module-badge"><i class="fas fa-download"></i> Exportar datos</span>
                    </a>
                </div>
            </div>

            <!-- Gráfico de actividad -->
            <div class="activity-section">
                <h2>
                    <i class="fas fa-chart-line" style="color: #3498db;"></i>
                    Actividad del Sistema (Últimas 24 horas)
                </h2>
                <div class="chart-container">
                    <canvas id="actividadChart"></canvas>
                </div>
            </div>

            <!-- Últimos logs -->
            <div class="logs-section">
                <h2>
                    <i class="fas fa-history" style="color: #e74c3c;"></i>
                    Últimas Actividades del Sistema
                </h2>
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Fecha/Hora</th>
                            <th>Usuario</th>
                            <th>Acción</th>
                            <th>Módulo</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($ultimos_logs && $ultimos_logs->num_rows > 0): ?>
                            <?php while ($log = $ultimos_logs->fetch_assoc()): ?>
                                <tr>
                                    <td><small><?php echo date('d/m/Y H:i:s', strtotime($log['fecha'])); ?></small></td>
                                    <td><strong><?php echo htmlspecialchars($log['usuario']); ?></strong></td>
                                    <td><?php echo htmlspecialchars(mb_strimwidth($log['accion'], 0, 40, '...')); ?></td>
                                    <td><span class="badge-modulo"><?php echo htmlspecialchars($log['modulo']); ?></span></td>
                                    <td><code><?php echo htmlspecialchars($log['ip_address']); ?></code></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 30px;">
                                    <i class="fas fa-inbox" style="font-size: 24px; color: #ccc;"></i><br>
                                    No hay registros de actividad
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="ver-mas">
                    <a href="logs.php" class="btn-Volver" style="background: #3498db; color: white; border-color: #3498db;">
                        <i class="fas fa-eye"></i> Ver todos los logs
                    </a>
                </div>
            </div>

            <!-- Información del sistema -->
            <div class="info-panel">
                <div>
                    <i class="fas fa-info-circle" style="color: #3498db;"></i>
                    <strong>Sistema Bitácora Militar</strong> | Versión <?php echo $version_sistema; ?> | 
                    Última actualización: <?php echo date('d/m/Y', strtotime($fecha_version)); ?>
                </div>
                <div>
                    <i class="fas fa-chart-simple"></i>
                    Hoy: <?php echo $stats['registros_hoy']; ?> registros | 
                    Último mes: <?php echo number_format($stats['registros_ultimo_mes']); ?> registros
                </div>
                <div>
                    <i class="fas fa-keyboard"></i>
                    Atajos: <kbd>Ctrl</kbd> + <kbd>B</kbd> Volver | <kbd>Ctrl</kbd> + <kbd>R</kbd> Recargar
                </div>
            </div>
        </div>
    </div>

    <script>
        // Datos para el gráfico de actividad
        const horasLabels = [];
        const horasData = [];
        
        <?php for ($i = 0; $i < 24; $i++): ?>
            horasLabels.push('<?php echo $i; ?>:00');
            horasData.push(<?php echo $horas_data[$i]; ?>);
        <?php endfor; ?>
        
        // Inicializar gráfico
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('actividadChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: horasLabels,
                    datasets: [{
                        label: 'Actividades',
                        data: horasData,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Actividades: ${context.raw}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#ecf0f1' },
                            title: { display: true, text: 'Número de actividades' }
                        },
                        x: {
                            grid: { display: false },
                            title: { display: true, text: 'Hora del día' }
                        }
                    }
                }
            });
        });
        
        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl + B = Volver atrás
            if (e.ctrlKey && e.key === 'b') {
                e.preventDefault();
                window.history.back();
            }
            // Ctrl + R = Recargar página
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                window.location.reload();
            }
            // Ctrl + H = Ir al inicio
            if (e.ctrlKey && e.key === 'h') {
                e.preventDefault();
                window.location.href = 'index.php';
            }
        });
        
        // Mostrar notificación de bienvenida (solo una vez por sesión)
        if (!sessionStorage.getItem('admin_panel_bienvenida')) {
            setTimeout(function() {
                const notif = document.createElement('div');
                notif.style.cssText = `
                    position: fixed;
                    bottom: 100px;
                    right: 30px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 15px 25px;
                    border-radius: 10px;
                    font-size: 14px;
                    z-index: 9999;
                    animation: fadeOut 8s ease;
                    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                `;
                notif.innerHTML = `
                    <i class="fas fa-shield-alt"></i> 
                    Bienvenido al Panel de Administración<br>
                    <small style="opacity: 0.8;">Utilice los módulos para gestionar el sistema</small>
                `;
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
                
                setTimeout(() => notif.remove(), 8000);
                sessionStorage.setItem('admin_panel_bienvenida', 'true');
            }, 500);
        }
        
        // Refrescar estadísticas cada 30 segundos (opcional)
        let refreshInterval = setInterval(function() {
            // Solo refrescar si la pestaña está activa
            if (!document.hidden) {
                fetch('admin_panel_ajax.php?action=stats')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Actualizar números en las tarjetas
                            const statNumbers = document.querySelectorAll('.stat-info h3');
                            if (data.total_registros) statNumbers[0].textContent = data.total_registros.toLocaleString();
                            if (data.registros_activos) statNumbers[1].textContent = data.registros_activos.toLocaleString();
                            if (data.usuarios_unicos) statNumbers[2].textContent = data.usuarios_unicos.toLocaleString();
                            if (data.roles_distintos) statNumbers[3].textContent = data.roles_distintos.toLocaleString();
                        }
                    })
                    .catch(error => console.log('Error refreshing stats:', error));
            }
        }, 30000);
        
        // Limpiar intervalo al salir
        window.addEventListener('beforeunload', function() {
            clearInterval(refreshInterval);
        });
    </script>
</body>
</html>