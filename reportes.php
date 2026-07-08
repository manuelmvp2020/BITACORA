<?php
// reportes.php - Página de reportes y estadísticas avanzadas
require_once 'config.php';

// ============================================
// VERIFICAR AUTENTICACIÓN
// ============================================
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// ============================================
// PALETA DE COLORES (definir antes de usar)
// ============================================
$color_palette_roles = [
    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
    '#FF9F40', '#FF6384', '#C9CBCF', '#7C4DFF', '#2ECC71',
    '#E74C3C', '#3498DB', '#F1C40F', '#9B59B6', '#1ABC9C',
    '#E67E22', '#27AE60', '#2980B9', '#8E44AD', '#16A085'
];

// Obtener página anterior para el botón volver
$pagina_anterior = $_SERVER['HTTP_REFERER'] ?? 'index.php';
if (strpos($pagina_anterior, 'reportes.php') !== false || empty($pagina_anterior)) {
    $pagina_anterior = 'index.php';
}

// ============================================
// ESTADÍSTICAS GENERALES
// ============================================
$stats = [];

// Totales por estatus
$stats['total'] = $conn->query("SELECT COUNT(*) as total FROM registros_ids")->fetch_assoc()['total'];
$stats['activos'] = $conn->query("SELECT COUNT(*) as total FROM registros_ids WHERE estatus='Activo'")->fetch_assoc()['total'];
$stats['inactivos'] = $conn->query("SELECT COUNT(*) as total FROM registros_ids WHERE estatus='Inactivo'")->fetch_assoc()['total'];
$stats['reservados'] = $conn->query("SELECT COUNT(*) as total FROM registros_ids WHERE estatus='Reservado'")->fetch_assoc()['total'];
$stats['bloqueados'] = $conn->query("SELECT COUNT(*) as total FROM registros_ids WHERE estatus='Bloqueado'")->fetch_assoc()['total'];

// ============================================
// ESTADÍSTICAS DE USUARIOS
// ============================================
$stats['usuarios'] = [];

// Usuarios únicos
$stats['usuarios']['total_unicos'] = $conn->query("SELECT COUNT(DISTINCT usuario) as total FROM registros_ids WHERE usuario IS NOT NULL AND usuario != ''")->fetch_assoc()['total'];

// Usuarios por estatus
$stats['usuarios']['activos_unicos'] = $conn->query("SELECT COUNT(DISTINCT usuario) as total FROM registros_ids WHERE usuario IS NOT NULL AND usuario != '' AND estatus='Activo'")->fetch_assoc()['total'];
$stats['usuarios']['inactivos_unicos'] = $conn->query("SELECT COUNT(DISTINCT usuario) as total FROM registros_ids WHERE usuario IS NOT NULL AND usuario != '' AND estatus='Inactivo'")->fetch_assoc()['total'];
$stats['usuarios']['reservados_unicos'] = $conn->query("SELECT COUNT(DISTINCT usuario) as total FROM registros_ids WHERE usuario IS NOT NULL AND usuario != '' AND estatus='Reservado'")->fetch_assoc()['total'];
$stats['usuarios']['bloqueados_unicos'] = $conn->query("SELECT COUNT(DISTINCT usuario) as total FROM registros_ids WHERE usuario IS NOT NULL AND usuario != '' AND estatus='Bloqueado'")->fetch_assoc()['total'];
$stats['usuarios']['sin_usuario'] = $conn->query("SELECT COUNT(*) as total FROM registros_ids WHERE usuario IS NULL OR usuario = ''")->fetch_assoc()['total'];

// Top 10 usuarios con más registros
$top_usuarios = $conn->query("
    SELECT usuario, COUNT(*) as total,
           SUM(CASE WHEN estatus='Activo' THEN 1 ELSE 0 END) as activos,
           SUM(CASE WHEN estatus='Inactivo' THEN 1 ELSE 0 END) as inactivos,
           SUM(CASE WHEN estatus='Reservado' THEN 1 ELSE 0 END) as reservados,
           SUM(CASE WHEN estatus='Bloqueado' THEN 1 ELSE 0 END) as bloqueados
    FROM registros_ids 
    WHERE usuario IS NOT NULL AND usuario != '' 
    GROUP BY usuario 
    ORDER BY total DESC 
    LIMIT 10
");

// ============================================
// ESTADÍSTICAS DE ROLES
// ============================================
$roles_stats = $conn->query("
    SELECT roles, COUNT(*) as total,
           SUM(CASE WHEN estatus='Activo' THEN 1 ELSE 0 END) as activos,
           SUM(CASE WHEN estatus='Inactivo' THEN 1 ELSE 0 END) as inactivos
    FROM registros_ids 
    WHERE roles IS NOT NULL AND roles != '' 
    GROUP BY roles 
    ORDER BY total DESC
");

$total_con_rol = 0;
$roles_array = [];
while ($rol = $roles_stats->fetch_assoc()) {
    $total_con_rol += $rol['total'];
    $roles_array[] = $rol;
}
$roles_stats = $roles_array;

$stats['roles']['sin_rol'] = $conn->query("SELECT COUNT(*) as total FROM registros_ids WHERE roles IS NULL OR roles = ''")->fetch_assoc()['total'];

// ============================================
// ESTADÍSTICAS DE CONFIGURACIÓN
// ============================================
$config_stats = $conn->query("
    SELECT configuracion, COUNT(*) as total
    FROM registros_ids 
    WHERE configuracion IS NOT NULL AND configuracion != '' 
    GROUP BY configuracion 
    ORDER BY total DESC 
    LIMIT 15
");

$config_array = [];
while ($config = $config_stats->fetch_assoc()) {
    $config_array[] = $config;
}
$config_stats = $config_array;
$stats['config']['sin_config'] = $conn->query("SELECT COUNT(*) as total FROM registros_ids WHERE configuracion IS NULL OR configuracion = ''")->fetch_assoc()['total'];

// ============================================
// ESTADÍSTICAS TEMPORALES (CORREGIDAS)
// ============================================

// Registros por mes (últimos 12 meses) - CORREGIDO
$registros_por_mes = $conn->query("
    SELECT 
        DATE_FORMAT(fecha_registro, '%Y-%m') as mes,
        DATE_FORMAT(fecha_registro, '%M %Y') as mes_nombre,
        COUNT(*) as total
    FROM registros_ids 
    WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(fecha_registro, '%Y-%m'), DATE_FORMAT(fecha_registro, '%M %Y')
    ORDER BY mes DESC
");

$meses_array = [];
while ($mes = $registros_por_mes->fetch_assoc()) {
    $meses_array[] = $mes;
}
$registros_por_mes = $meses_array;

// Registros por día de la semana - CORREGIDO
$registros_por_dia = $conn->query("
    SELECT 
        DAYNAME(fecha_registro) as dia,
        DAYOFWEEK(fecha_registro) as num_dia,
        COUNT(*) as total
    FROM registros_ids 
    GROUP BY DAYNAME(fecha_registro), DAYOFWEEK(fecha_registro)
    ORDER BY num_dia
");

$dias_array = [];
while ($dia = $registros_por_dia->fetch_assoc()) {
    $dias_array[] = $dia;
}
$registros_por_dia = $dias_array;

// Registros por hora - CORREGIDO
$registros_por_hora = $conn->query("
    SELECT 
        HOUR(fecha_registro) as hora,
        COUNT(*) as total
    FROM registros_ids 
    GROUP BY HOUR(fecha_registro)
    ORDER BY hora
");

$horas_array = [];
while ($hora = $registros_por_hora->fetch_assoc()) {
    $horas_array[] = $hora;
}
$registros_por_hora = $horas_array;

// ============================================
// ESTADÍSTICAS DE RANGOS MILITARES
// ============================================
$rangos_stats = $conn->query("
    SELECT rango_militar, COUNT(*) as total
    FROM registros_ids 
    WHERE rango_militar IS NOT NULL AND rango_militar != '' 
    GROUP BY rango_militar 
    ORDER BY total DESC 
    LIMIT 10
");

$rangos_array = [];
while ($rango = $rangos_stats->fetch_assoc()) {
    $rangos_array[] = $rango;
}
$rangos_stats = $rangos_array;

// ============================================
// DATOS PARA GRÁFICOS
// ============================================

// Datos para gráfico de estatus (pastel)
$estatus_labels = ['Activos', 'Inactivos', 'Reservados', 'Bloqueados'];
$estatus_data = [$stats['activos'], $stats['inactivos'], $stats['reservados'], $stats['bloqueados']];
$estatus_colors = ['#27ae60', '#e74c3c', '#f39c12', '#95a5a6'];

// Datos para gráfico de usuarios por estatus
$usuarios_estatus_data = [
    $stats['usuarios']['activos_unicos'],
    $stats['usuarios']['inactivos_unicos'],
    $stats['usuarios']['reservados_unicos'],
    $stats['usuarios']['bloqueados_unicos']
];

// Datos para gráfico de líneas (tendencia mensual)
$meses_labels = [];
$meses_data = [];
foreach ($registros_por_mes as $mes) {
    $meses_labels[] = $mes['mes_nombre'];
    $meses_data[] = $mes['total'];
}

// Datos para gráfico de barras (días de la semana)
$dias_labels = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$dias_data = [0, 0, 0, 0, 0, 0, 0];
foreach ($registros_por_dia as $dia) {
    $index = $dia['num_dia'] - 1;
    $dias_data[$index] = $dia['total'];
}

// Datos para gráfico de distribución por hora
$horas_labels = [];
$horas_data = [];
for ($i = 0; $i < 24; $i++) {
    $horas_labels[] = $i . ':00';
    $horas_data[] = 0;
}
foreach ($registros_por_hora as $hora) {
    $horas_data[$hora['hora']] = $hora['total'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes y Estadísticas - Bitácora</title>
    <!-- Chart.js para gráficos -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }
        
        /* ===== MAIN CONTENT ===== */
        .main-content {
            padding: 20px;
            min-height: 100vh;
        }
        
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
            transform: translateX(-3px);
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
        
        /* ===== OTROS ESTILOS ===== */
        .page-title {
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .page-title i {
            color: #3498db;
            margin-right: 10px;
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
        
        .date-badge {
            background: #ecf0f1;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            color: #7f8c8d;
        }
        
        .date-badge i {
            margin-right: 5px;
            color: #3498db;
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
        
        /* ===== REPORTES STYLES ===== */
        .reports-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Tarjetas de resumen */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        
        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
        }
        
        .card-icon.total { background: linear-gradient(135deg, #3498db, #2980b9); }
        .card-icon.activos { background: linear-gradient(135deg, #27ae60, #229954); }
        .card-icon.usuarios { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        .card-icon.roles { background: linear-gradient(135deg, #e67e22, #d35400); }
        
        .card-info h3 {
            font-size: 28px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .card-info p {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .card-info small {
            color: #95a5a6;
            font-size: 12px;
        }
        
        /* Grid de gráficos */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .chart-card.full-width {
            grid-column: 1 / -1;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
        }
        
        .chart-header h3 {
            color: #2c3e50;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .chart-header h3 i {
            color: #3498db;
        }
        
        .chart-header .badge {
            background: #3498db;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .chart-wrapper {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        /* Tablas de estadísticas */
        .stats-tables {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stats-table {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .stats-table h4 {
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .table-content {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .stat-row:hover {
            background: #f8f9fa;
        }
        
        .stat-label {
            color: #7f8c8d;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .color-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .stat-value {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #ecf0f1;
            border-radius: 4px;
            margin-top: 5px;
            margin-bottom: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background: #3498db;
            border-radius: 4px;
            transition: width 0.3s;
        }
        
        /* Filtros y controles */
        .report-filters {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-group label {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .filter-select {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            color: #2c3e50;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
            transform: translateY(-2px);
        }
        
        .btn-export {
            background: #9b59b6;
            color: white;
            margin-left: auto;
        }
        
        .btn-export:hover {
            background: #8e44ad;
            transform: translateY(-2px);
        }
        
        /* Botón flotante de volver */
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
        
        /* Tooltips */
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
            font-size: 12px;
            border-radius: 3px;
            white-space: nowrap;
            display: none;
            z-index: 1000;
        }
        
        [data-tooltip]:hover:before {
            display: block;
        }
        
        /* Header con botones de navegación */
        .nav-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-tables {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .summary-cards {
                grid-template-columns: 1fr;
            }
            
            .report-filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn-export {
                margin-left: 0;
            }
            
            .top-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .nav-buttons {
                justify-content: space-between;
            }
            
            .floating-back {
                bottom: 20px;
                right: 20px;
                width: 45px;
                height: 45px;
            }
        }
        
        @media print {
            .top-bar, .report-filters, .btn, .breadcrumb, .floating-back, .nav-buttons {
                display: none;
            }
            
            .main-content {
                padding: 0;
            }
            
            .chart-card {
                break-inside: avoid;
            }
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

    <!-- ===== MAIN CONTENT ===== -->
    <div class="main-content">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="index.php"><i class="fas fa-home"></i> Inicio</a>
            <i class="fas fa-chevron-right"></i>
            <span><i class="fas fa-chart-bar"></i> Reportes y Estadísticas</span>
        </div>

        <!-- Top bar con botones de volver -->
        <div class="top-bar">
            <div class="page-title">
                <i class="fas fa-chart-bar"></i> Reportes y Estadísticas
            </div>
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
                <div class="date-badge">
                    <i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y'); ?>
                </div>
                <div class="user-avatar">
                    <?php echo substr($_SESSION['usuario'] ?? 'A', 0, 1); ?>
                </div>
            </div>
        </div>

        <div class="reports-container">
            <!-- Filtros -->
            <div class="report-filters">
                <div class="filter-group">
                    <label><i class="far fa-calendar"></i> Período:</label>
                    <select class="filter-select" id="periodo">
                        <option value="todo">Todo el historial</option>
                        <option value="12">Últimos 12 meses</option>
                        <option value="6">Últimos 6 meses</option>
                        <option value="3">Últimos 3 meses</option>
                        <option value="1">Último mes</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-filter"></i> Tipo:</label>
                    <select class="filter-select" id="tipoReporte">
                        <option value="todos">Todos los reportes</option>
                        <option value="usuarios">Usuarios</option>
                        <option value="roles">Roles</option>
                        <option value="tiempo">Tiempo</option>
                    </select>
                </div>
                <button class="btn btn-primary" onclick="actualizarReportes()">
                    <i class="fas fa-sync-alt"></i> Actualizar
                </button>
                <button class="btn btn-export" onclick="exportarReporte()">
                    <i class="fas fa-download"></i> Exportar Reporte
                </button>
            </div>

            <!-- Tarjetas de resumen -->
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="card-icon total">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="card-info">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Registros</p>
                        <small><?php echo $stats['usuarios']['total_unicos']; ?> usuarios únicos</small>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="card-icon activos">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="card-info">
                        <h3><?php echo $stats['activos']; ?></h3>
                        <p>Registros Activos</p>
                        <small><?php echo $stats['usuarios']['activos_unicos']; ?> usuarios activos</small>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="card-icon usuarios">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="card-info">
                        <h3><?php echo $stats['usuarios']['total_unicos']; ?></h3>
                        <p>Usuarios Únicos</p>
                        <small>Promedio: <?php echo $stats['total'] > 0 && $stats['usuarios']['total_unicos'] > 0 ? round($stats['total'] / $stats['usuarios']['total_unicos'], 1) : 0; ?> c/u</small>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="card-icon roles">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div class="card-info">
                        <h3><?php echo $total_con_rol; ?></h3>
                        <p>Con Rol Asignado</p>
                        <small><?php echo $stats['roles']['sin_rol']; ?> sin rol</small>
                    </div>
                </div>
            </div>

            <!-- Grid de gráficos -->
            <div class="charts-grid">
                <!-- Gráfico 1: Distribución por Estatus -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-pie"></i> Distribución por Estatus</h3>
                        <span class="badge">Total: <?php echo $stats['total']; ?></span>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="estatusChart"></canvas>
                    </div>
                </div>

                <!-- Gráfico 2: Usuarios por Estatus -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-user-tag"></i> Usuarios por Estatus</h3>
                        <span class="badge">Únicos: <?php echo $stats['usuarios']['total_unicos']; ?></span>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="usuariosEstatusChart"></canvas>
                    </div>
                </div>

                <!-- Gráfico 3: Tendencia Mensual (full width) -->
                <div class="chart-card full-width">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-line"></i> Tendencia de Registros (Últimos 12 meses)</h3>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="tendenciaChart"></canvas>
                    </div>
                </div>

                <!-- Gráfico 4: Distribución por Día -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="far fa-calendar-alt"></i> Registros por Día</h3>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="diasChart"></canvas>
                    </div>
                </div>

                <!-- Gráfico 5: Distribución por Hora -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="far fa-clock"></i> Registros por Hora</h3>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="horasChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tablas de estadísticas -->
            <div class="stats-tables">
                <!-- Top Usuarios -->
                <div class="stats-table">
                    <h4><i class="fas fa-trophy"></i> Top 10 Usuarios</h4>
                    <div class="table-content">
                        <?php 
                        $top_usuarios_array = [];
                        while ($u = $top_usuarios->fetch_assoc()) {
                            $top_usuarios_array[] = $u;
                        }
                        
                        $max_total = 0;
                        foreach ($top_usuarios_array as $usuario) {
                            if ($usuario['total'] > $max_total) $max_total = $usuario['total'];
                        }
                        
                        foreach ($top_usuarios_array as $usuario): 
                            $porcentaje = $max_total > 0 ? ($usuario['total'] / $max_total) * 100 : 0;
                        ?>
                            <div>
                                <div class="stat-row">
                                    <div class="stat-label">
                                        <span class="color-dot" style="background: #3498db;"></span>
                                        <?php echo htmlspecialchars($usuario['usuario']); ?>
                                    </div>
                                    <div class="stat-value"><?php echo $usuario['total']; ?></div>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $porcentaje; ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Distribución de Roles -->
                <div class="stats-table">
                    <h4><i class="fas fa-tags"></i> Roles</h4>
                    <div class="table-content">
                        <?php 
                        $contador_roles = 0;
                        foreach ($roles_stats as $rol): 
                            $porcentaje = $total_con_rol > 0 ? round(($rol['total'] / $total_con_rol) * 100, 1) : 0;
                        ?>
                            <div>
                                <div class="stat-row">
                                    <div class="stat-label">
                                        <span class="color-dot" style="background: <?php echo $color_palette_roles[$contador_roles % count($color_palette_roles)]; ?>;"></span>
                                        <?php echo htmlspecialchars($rol['roles'] ?: 'Sin especificar'); ?>
                                    </div>
                                    <div class="stat-value"><?php echo $rol['total']; ?> (<?php echo $porcentaje; ?>%)</div>
                                </div>
                                <div style="font-size: 11px; color: #7f8c8d; margin-top: -5px; margin-bottom: 5px; padding-left: 20px;">
                                    Activos: <?php echo $rol['activos']; ?> | Inactivos: <?php echo $rol['inactivos']; ?>
                                </div>
                            </div>
                        <?php 
                            $contador_roles++;
                        endforeach; 
                        ?>
                        <?php if ($stats['roles']['sin_rol'] > 0): ?>
                            <div class="stat-row" style="color: #95a5a6;">
                                <div class="stat-label">Sin rol asignado</div>
                                <div class="stat-value"><?php echo $stats['roles']['sin_rol']; ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Rangos Militares -->
                <div class="stats-table">
                    <h4><i class="fas fa-star"></i> Rangos Militares</h4>
                    <div class="table-content">
                        <?php foreach ($rangos_stats as $rango): ?>
                            <div class="stat-row">
                                <div class="stat-label">
                                    <span class="color-dot" style="background: #e67e22;"></span>
                                    <?php echo htmlspecialchars($rango['rango_militar']); ?>
                                </div>
                                <div class="stat-value"><?php echo $rango['total']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Información adicional -->
            <div style="background: white; border-radius: 10px; padding: 20px; margin-top: 20px;">
                <h4 style="color: #2c3e50; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-info-circle" style="color: #3498db;"></i> 
                    Resumen General
                </h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                    <div>
                        <strong style="color: #7f8c8d;">Usuarios sin asignar:</strong>
                        <span style="float: right; color: #2c3e50;"><?php echo $stats['usuarios']['sin_usuario']; ?></span>
                    </div>
                    <div>
                        <strong style="color: #7f8c8d;">Configuraciones distintas:</strong>
                        <span style="float: right; color: #2c3e50;"><?php echo count($config_stats); ?></span>
                    </div>
                    <div>
                        <strong style="color: #7f8c8d;">Sin configuración:</strong>
                        <span style="float: right; color: #2c3e50;"><?php echo $stats['config']['sin_config']; ?></span>
                    </div>
                    <div>
                        <strong style="color: #7f8c8d;">Promedio diario (30 días):</strong>
                        <span style="float: right; color: #2c3e50;"><?php echo round($stats['total'] / 30, 1); ?> registros/día</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Datos para gráficos
        const estatusLabels = <?php echo json_encode($estatus_labels); ?>;
        const estatusData = <?php echo json_encode($estatus_data); ?>;
        const estatusColors = <?php echo json_encode($estatus_colors); ?>;
        
        const usuariosEstatusData = <?php echo json_encode($usuarios_estatus_data); ?>;
        
        const mesesLabels = <?php echo json_encode(array_reverse($meses_labels)); ?>;
        const mesesData = <?php echo json_encode(array_reverse($meses_data)); ?>;
        
        const diasLabels = <?php echo json_encode($dias_labels); ?>;
        const diasData = <?php echo json_encode($dias_data); ?>;
        
        const horasLabels = <?php echo json_encode($horas_labels); ?>;
        const horasData = <?php echo json_encode($horas_data); ?>;

        // Inicializar gráficos
        document.addEventListener('DOMContentLoaded', function() {
            // Gráfico de estatus
            new Chart(document.getElementById('estatusChart'), {
                type: 'pie',
                data: {
                    labels: estatusLabels,
                    datasets: [{
                        data: estatusData,
                        backgroundColor: estatusColors,
                        borderColor: 'white',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });

            // Gráfico de usuarios por estatus
            new Chart(document.getElementById('usuariosEstatusChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Activos', 'Inactivos', 'Reservados', 'Bloqueados'],
                    datasets: [{
                        data: usuariosEstatusData,
                        backgroundColor: estatusColors,
                        borderColor: 'white',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} usuarios (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });

            // Gráfico de tendencia mensual
            new Chart(document.getElementById('tendenciaChart'), {
                type: 'line',
                data: {
                    labels: mesesLabels,
                    datasets: [{
                        label: 'Registros',
                        data: mesesData,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#ecf0f1' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });

            // Gráfico por día
            new Chart(document.getElementById('diasChart'), {
                type: 'bar',
                data: {
                    labels: diasLabels,
                    datasets: [{
                        label: 'Registros',
                        data: diasData,
                        backgroundColor: '#3498db',
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#ecf0f1' }
                        }
                    }
                }
            });

            // Gráfico por hora
            new Chart(document.getElementById('horasChart'), {
                type: 'line',
                data: {
                    labels: horasLabels,
                    datasets: [{
                        label: 'Registros',
                        data: horasData,
                        borderColor: '#e67e22',
                        backgroundColor: 'rgba(230, 126, 34, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#ecf0f1' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        });

        // Función para actualizar reportes
        function actualizarReportes() {
            const periodo = document.getElementById('periodo').value;
            const tipo = document.getElementById('tipoReporte').value;
            
            // Mostrar notificación
            mostrarNotificacion('Actualizando reportes con período: ' + periodo + ' y tipo: ' + tipo);
        }

        // Función para exportar reporte
        function exportarReporte() {
            mostrarNotificacion('Preparando exportación del reporte completo...');
            // window.location.href = 'exportar_reporte.php?tipo=completo&formato=pdf';
        }
        
        // Función para mostrar notificaciones
        function mostrarNotificacion(mensaje) {
            // Crear elemento de notificación
            const notif = document.createElement('div');
            notif.style.cssText = `
                position: fixed;
                bottom: 100px;
                right: 30px;
                background: #2c3e50;
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                font-size: 14px;
                z-index: 9999;
                animation: fadeInOut 3s ease;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            `;
            notif.innerHTML = '<i class="fas fa-info-circle"></i> ' + mensaje;
            document.body.appendChild(notif);
            
            // Agregar animación
            const style = document.createElement('style');
            style.textContent = `
                @keyframes fadeInOut {
                    0% { opacity: 0; transform: translateY(20px); }
                    15% { opacity: 1; transform: translateY(0); }
                    85% { opacity: 1; transform: translateY(0); }
                    100% { opacity: 0; transform: translateY(-20px); visibility: hidden; }
                }
            `;
            document.head.appendChild(style);
            
            // Eliminar después de 3 segundos
            setTimeout(() => {
                notif.remove();
            }, 3000);
        }

        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl + E = Exportar
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                exportarReporte();
            }
            // Ctrl + R = Actualizar
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                actualizarReportes();
            }
            // Ctrl + B = Volver (Back)
            if (e.ctrlKey && e.key === 'b') {
                e.preventDefault();
                window.history.back();
            }
            // Esc = Cerrar notificaciones
            if (e.key === 'Escape') {
                const notifs = document.querySelectorAll('[style*="fadeInOut"]');
                notifs.forEach(n => n.remove());
            }
        });
        
        // Guardar estado de los filtros
        function guardarFiltros() {
            const periodo = document.getElementById('periodo').value;
            const tipo = document.getElementById('tipoReporte').value;
            sessionStorage.setItem('reportes_periodo', periodo);
            sessionStorage.setItem('reportes_tipo', tipo);
        }
        
        // Cargar filtros guardados
        function cargarFiltros() {
            const periodo = sessionStorage.getItem('reportes_periodo');
            const tipo = sessionStorage.getItem('reportes_tipo');
            if (periodo) document.getElementById('periodo').value = periodo;
            if (tipo) document.getElementById('tipoReporte').value = tipo;
        }
        
        // Cargar filtros al iniciar
        document.addEventListener('DOMContentLoaded', cargarFiltros);
        
        // Guardar filtros al cambiar
        document.getElementById('periodo')?.addEventListener('change', guardarFiltros);
        document.getElementById('tipoReporte')?.addEventListener('change', guardarFiltros);
    </script>
</body>
</html>