<?php
// logs.php - Página de visualización de logs del sistema
require_once 'config.php';

// Verificar si el usuario tiene permisos para ver logs (solo administradores)
if (!isset($_SESSION['usuario']) || $_SESSION['usuario'] !== 'admin') {
    // Si no es admin, registrar intento no autorizado
    registrarLog($conn, $_SESSION['usuario'] ?? 'anonimo', 
        "Intento no autorizado de acceder a logs", 
        "logs.php", 
        obtenerIP(),
        json_encode(['nivel_acceso' => 'denegado'])
    );
    
    $_SESSION['mensaje'] = "No tienes permisos para acceder a esta sección";
    $_SESSION['tipo_mensaje'] = "error";
    header("Location: index.php");
    exit();
}

// Registrar acceso a la página de logs
registrarLog($conn, $_SESSION['usuario'], 
    "Accedió al panel de logs del sistema", 
    "logs.php", 
    obtenerIP(),
    json_encode(['fecha_acceso' => date('Y-m-d H:i:s')])
);

// Obtener página anterior para el botón volver
$pagina_anterior = $_SERVER['HTTP_REFERER'] ?? 'index.php';
if (strpos($pagina_anterior, 'logs.php') !== false || empty($pagina_anterior)) {
    $pagina_anterior = 'index.php';
}

// ============================================
// PROCESAR ELIMINACIÓN DE LOGS
// ============================================
if (isset($_POST['eliminar_log'])) {
    $id_log = intval($_POST['eliminar_log']);
    
    $sql = "DELETE FROM logs WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_log);
    
    if ($stmt->execute()) {
        $_SESSION['mensaje'] = "Log eliminado correctamente";
        $_SESSION['tipo_mensaje'] = "success";
        
        // Registrar la eliminación
        registrarLog($conn, $_SESSION['usuario'], 
            "Eliminó log ID: $id_log", 
            "logs.php", 
            obtenerIP(),
            json_encode(['id_eliminado' => $id_log])
        );
    } else {
        $_SESSION['mensaje'] = "Error al eliminar log: " . $conn->error;
        $_SESSION['tipo_mensaje'] = "error";
    }
    $stmt->close();
    header("Location: logs.php");
    exit();
}

// ============================================
// PROCESAR LIMPIEZA DE LOGS ANTIGUOS
// ============================================
if (isset($_POST['limpiar_antiguos'])) {
    $dias = intval($_POST['dias'] ?? 30);
    $fecha_limite = date('Y-m-d H:i:s', strtotime("-$dias days"));
    
    $sql = "DELETE FROM logs WHERE fecha < ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $fecha_limite);
    
    if ($stmt->execute()) {
        $eliminados = $stmt->affected_rows;
        $_SESSION['mensaje'] = "Se eliminaron $eliminados logs antiguos (más de $dias días)";
        $_SESSION['tipo_mensaje'] = "success";
        
        // Registrar la limpieza
        registrarLog($conn, $_SESSION['usuario'], 
            "Realizó limpieza de logs antiguos", 
            "logs.php", 
            obtenerIP(),
            json_encode([
                'dias' => $dias,
                'fecha_limite' => $fecha_limite,
                'eliminados' => $eliminados
            ])
        );
    } else {
        $_SESSION['mensaje'] = "Error al limpiar logs: " . $conn->error;
        $_SESSION['tipo_mensaje'] = "error";
    }
    $stmt->close();
    header("Location: logs.php");
    exit();
}

// ============================================
// PROCESAR EXPORTACIÓN DE LOGS
// ============================================
if (isset($_GET['exportar'])) {
    $formato = $_GET['exportar'] ?? 'csv';
    $filtros = $_SESSION['filtros_logs'] ?? [];
    
    // Construir consulta con filtros
    $where = "1=1";
    $params = [];
    $types = "";
    
    if (!empty($filtros['usuario'])) {
        $where .= " AND usuario LIKE ?";
        $params[] = "%" . $filtros['usuario'] . "%";
        $types .= "s";
    }
    if (!empty($filtros['modulo'])) {
        $where .= " AND modulo = ?";
        $params[] = $filtros['modulo'];
        $types .= "s";
    }
    if (!empty($filtros['accion'])) {
        $where .= " AND accion LIKE ?";
        $params[] = "%" . $filtros['accion'] . "%";
        $types .= "s";
    }
    if (!empty($filtros['fecha_desde'])) {
        $where .= " AND fecha >= ?";
        $params[] = $filtros['fecha_desde'];
        $types .= "s";
    }
    if (!empty($filtros['fecha_hasta'])) {
        $where .= " AND fecha <= ?";
        $params[] = $filtros['fecha_hasta'] . " 23:59:59";
        $types .= "s";
    }
    
    $sql = "SELECT * FROM logs WHERE $where ORDER BY fecha DESC";
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $resultado = $stmt->get_result();
    $logs = [];
    while ($log = $resultado->fetch_assoc()) {
        $logs[] = $log;
    }
    $stmt->close();
    
    // Registrar exportación
    registrarLog($conn, $_SESSION['usuario'], 
        "Exportó logs a $formato", 
        "logs.php", 
        obtenerIP(),
        json_encode([
            'formato' => $formato,
            'cantidad' => count($logs),
            'filtros' => $filtros
        ])
    );
    
    // Exportar según formato
    if ($formato == 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="logs_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Usuario', 'Acción', 'Módulo', 'Fecha', 'IP', 'Detalles']);
        
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['id'],
                $log['usuario'],
                $log['accion'],
                $log['modulo'],
                $log['fecha'],
                $log['ip_address'],
                $log['detalles']
            ]);
        }
        fclose($output);
        exit();
    } elseif ($formato == 'json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="logs_' . date('Y-m-d') . '.json"');
        echo json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// ============================================
// OBTENER FILTROS DE LA URL
// ============================================
$filtros = [
    'usuario' => $_GET['usuario'] ?? '',
    'modulo' => $_GET['modulo'] ?? '',
    'accion' => $_GET['accion'] ?? '',
    'fecha_desde' => $_GET['fecha_desde'] ?? '',
    'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
    'orden' => $_GET['orden'] ?? 'DESC',
    'campo_orden' => $_GET['campo_orden'] ?? 'fecha'
];

// Guardar filtros en sesión para exportación
$_SESSION['filtros_logs'] = $filtros;

// ============================================
// CONSTRUIR CONSULTA CON FILTROS
// ============================================
$where = "1=1";
$params = [];
$types = "";

if (!empty($filtros['usuario'])) {
    $where .= " AND usuario LIKE ?";
    $params[] = "%" . $filtros['usuario'] . "%";
    $types .= "s";
}
if (!empty($filtros['modulo'])) {
    $where .= " AND modulo = ?";
    $params[] = $filtros['modulo'];
    $types .= "s";
}
if (!empty($filtros['accion'])) {
    $where .= " AND accion LIKE ?";
    $params[] = "%" . $filtros['accion'] . "%";
    $types .= "s";
}
if (!empty($filtros['fecha_desde'])) {
    $where .= " AND fecha >= ?";
    $params[] = $filtros['fecha_desde'];
    $types .= "s";
}
if (!empty($filtros['fecha_hasta'])) {
    $where .= " AND fecha <= ?";
    $params[] = $filtros['fecha_hasta'] . " 23:59:59";
    $types .= "s";
}

// Ordenamiento permitido
$campos_validos = ['id', 'usuario', 'accion', 'modulo', 'fecha', 'ip_address'];
$campo_orden = in_array($filtros['campo_orden'], $campos_validos) ? $filtros['campo_orden'] : 'fecha';
$orden = $filtros['orden'] === 'ASC' ? 'ASC' : 'DESC';

// Paginación
$registros_por_pagina = 50;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Contar total de registros
$sql_count = "SELECT COUNT(*) as total FROM logs WHERE $where";
$stmt_count = $conn->prepare($sql_count);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_registros = $stmt_count->get_result()->fetch_assoc()['total'];
$stmt_count->close();

$total_paginas = ceil($total_registros / $registros_por_pagina);

// Obtener logs
$sql = "SELECT * FROM logs WHERE $where ORDER BY $campo_orden $orden LIMIT ? OFFSET ?";
$params[] = $registros_por_pagina;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$resultado = $stmt->get_result();

// ============================================
// ESTADÍSTICAS DE LOGS
// ============================================
$stats = [];

// Total de logs
$stats['total'] = $total_registros;

// Logs por módulo
$modulos_stats = $conn->query("
    SELECT modulo, COUNT(*) as total 
    FROM logs 
    GROUP BY modulo 
    ORDER BY total DESC 
    LIMIT 10
");
$stats['modulos'] = [];
while ($modulo = $modulos_stats->fetch_assoc()) {
    $stats['modulos'][$modulo['modulo']] = $modulo['total'];
}

// Logs por usuario (top 10)
$usuarios_stats = $conn->query("
    SELECT usuario, COUNT(*) as total 
    FROM logs 
    GROUP BY usuario 
    ORDER BY total DESC 
    LIMIT 10
");
$stats['usuarios'] = [];
while ($usuario = $usuarios_stats->fetch_assoc()) {
    $stats['usuarios'][$usuario['usuario']] = $usuario['total'];
}

// Actividad por hora
$horas_stats = $conn->query("
    SELECT HOUR(fecha) as hora, COUNT(*) as total 
    FROM logs 
    GROUP BY HOUR(fecha) 
    ORDER BY hora
");
$stats['horas'] = [];
while ($hora = $horas_stats->fetch_assoc()) {
    $stats['horas'][$hora['hora']] = $hora['total'];
}

// Actividad por día (últimos 7 días)
$dias_stats = $conn->query("
    SELECT DATE(fecha) as dia, COUNT(*) as total 
    FROM logs 
    WHERE fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(fecha) 
    ORDER BY dia DESC
");
$stats['dias'] = [];
while ($dia = $dias_stats->fetch_assoc()) {
    $stats['dias'][$dia['dia']] = $dia['total'];
}

// Acciones más comunes
$acciones_stats = $conn->query("
    SELECT accion, COUNT(*) as total 
    FROM logs 
    GROUP BY accion 
    ORDER BY total DESC 
    LIMIT 10
");
$stats['acciones'] = [];
while ($accion = $acciones_stats->fetch_assoc()) {
    $stats['acciones'][$accion['accion']] = $accion['total'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs del Sistema - Bitácora</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; min-height: 100vh; }
        
        .main-content { padding: 20px; min-height: 100vh; }
        
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
        .user-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #3498db, #2980b9); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        
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
        
        .container { max-width: 1400px; margin: 0 auto; background-color: white; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); padding: 25px; }
        
        h1 { color: #2c3e50; margin-bottom: 10px; border-bottom: 3px solid #c0392b; padding-bottom: 10px; font-size: 28px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .subtitulo { color: #7f8c8d; margin-bottom: 20px; font-size: 14px; }
        
        .mensaje { padding: 12px 20px; margin-bottom: 20px; border-radius: 5px; font-weight: 500; animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { transform: translateY(-10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .mensaje.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .mensaje.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .mensaje.warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; text-align: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card .numero { font-size: 2.5em; font-weight: bold; }
        .stat-card .etiqueta { font-size: 0.9em; opacity: 0.9; margin-top: 5px; }
        .stat-card.total { background: linear-gradient(135deg, #3498db, #2980b9); }
        .stat-card.modulos { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        .stat-card.usuarios { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        
        .charts-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px; }
        .chart-container { background: #f8f9fa; border-radius: 10px; padding: 15px; border: 1px solid #e0e0e0; }
        .chart-container h3 { font-size: 14px; color: #2c3e50; margin-bottom: 15px; text-align: center; }
        .chart-wrapper { height: 200px; }
        
        .filtros { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .filtros-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px; }
        .filtro-group label { display: block; font-size: 12px; color: #7f8c8d; margin-bottom: 5px; font-weight: 600; }
        .filtro-group input, .filtro-group select { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .filtro-group input:focus, .filtro-group select:focus { outline: none; border-color: #3498db; }
        .btn-filtro { background: #3498db; color: white; border: none; padding: 8px 20px; border-radius: 5px; cursor: pointer; font-weight: 600; margin-top: 23px; }
        .btn-filtro:hover { background: #2980b9; }
        .btn-limpiar { background: #95a5a6; color: white; border: none; padding: 8px 20px; border-radius: 5px; cursor: pointer; font-weight: 600; margin-top: 23px; }
        .btn-limpiar:hover { background: #7f8c8d; }
        
        .acciones-bulk { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
        .btn-danger { background-color: #e74c3c; color: white; }
        .btn-warning { background-color: #f39c12; color: white; }
        .btn-info { background-color: #3498db; color: white; }
        .btn-success { background-color: #27ae60; color: white; }
        .btn-secondary { background-color: #95a5a6; color: white; }
        
        .table-container { overflow-x: auto; margin-top: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; background-color: white; font-size: 13px; }
        th { background: linear-gradient(135deg, #2c3e50, #34495e); color: white; padding: 12px; text-align: left; font-weight: 600; white-space: nowrap; cursor: pointer; user-select: none; }
        th:hover { background: #34495e; }
        th i { margin-left: 5px; font-size: 11px; }
        td { padding: 12px; border-bottom: 1px solid #ecf0f1; vertical-align: top; }
        tr:hover { background-color: #f5f9ff; }
        .badge-modulo { background: #e74c3c; color: white; padding: 3px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .badge-accion { background: #3498db; color: white; padding: 3px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .detalles-cell { max-width: 300px; }
        .detalles-preview { font-family: monospace; font-size: 11px; background: #f8f9fa; padding: 5px; border-radius: 4px; cursor: pointer; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .detalles-preview:hover { white-space: normal; background: #e9ecef; }
        
        .paginacion { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 5px; flex-wrap: wrap; gap: 15px; }
        .paginacion-links { display: flex; gap: 5px; flex-wrap: wrap; }
        .paginacion-links a, .paginacion-links span { padding: 5px 10px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #3498db; }
        .paginacion-links a:hover { background: #3498db; color: white; }
        .paginacion-links .active { background: #3498db; color: white; border-color: #3498db; }
        .info-registros { color: #7f8c8d; font-size: 14px; }
        
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
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center; }
        .modal-content { background: white; border-radius: 10px; padding: 25px; max-width: 500px; width: 90%; }
        .modal-content h3 { margin-bottom: 15px; color: #2c3e50; }
        .modal-content input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; }
        .modal-buttons { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .close-modal { float: right; cursor: pointer; font-size: 24px; color: #e74c3c; }
        
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
        
        @media (max-width: 1200px) { 
            .charts-row { grid-template-columns: 1fr 1fr; } 
        }
        @media (max-width: 768px) { 
            .charts-row { grid-template-columns: 1fr; } 
            .filtros-row { grid-template-columns: 1fr; } 
            .btn-filtro, .btn-limpiar { margin-top: 0; }
            .floating-back { bottom: 20px; right: 20px; width: 45px; height: 45px; }
            .top-bar { flex-direction: column; align-items: stretch; }
            .nav-buttons { justify-content: space-between; }
            h1 { flex-direction: column; align-items: flex-start; }
        }
        @media print { 
            .no-print, .top-bar, .filtros, .acciones-bulk, .stats-grid, .charts-row, .paginacion, .floating-back, .breadcrumb, .nav-buttons, .info-panel { display: none; } 
            body { background: white; padding: 0; } 
            .container { box-shadow: none; padding: 10px; } 
            th { background-color: #ddd !important; color: black !important; } 
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
            <span><i class="fas fa-history"></i> Logs del Sistema</span>
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
                <span>Bienvenido, <?php echo $_SESSION['usuario'] ?? 'Admin'; ?></span>
                <div class="user-avatar"><?php echo substr($_SESSION['usuario'] ?? 'A', 0, 1); ?></div>
            </div>
        </div>

        <div class="container">
            <h1>
                <span><i class="fas fa-history"></i> Logs del Sistema</span>
                <a href="admin_panel.php" class="btn btn-info no-print" data-tooltip="Volver al panel de administración">
                    <i class="fas fa-shield-alt"></i> Panel Admin
                </a>
            </h1>
            <div class="subtitulo">Registro detallado de todas las actividades realizadas en el sistema</div>
            
            <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="mensaje <?php echo $_SESSION['tipo_mensaje']; ?>">
                    <?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje']); unset($_SESSION['tipo_mensaje']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Estadísticas rápidas -->
            <div class="stats-grid no-print">
                <div class="stat-card total"><div class="numero"><?php echo number_format($stats['total']); ?></div><div class="etiqueta">Total de Actividades</div></div>
                <div class="stat-card modulos"><div class="numero"><?php echo count($stats['modulos']); ?></div><div class="etiqueta">Módulos Activos</div></div>
                <div class="stat-card usuarios"><div class="numero"><?php echo count($stats['usuarios']); ?></div><div class="etiqueta">Usuarios con Actividad</div></div>
            </div>
            
            <!-- Gráficos de actividad -->
            <div class="charts-row no-print">
                <div class="chart-container">
                    <h3>📊 Top Usuarios</h3>
                    <div class="chart-wrapper"><canvas id="usuariosChart"></canvas></div>
                </div>
                <div class="chart-container">
                    <h3>📁 Top Módulos</h3>
                    <div class="chart-wrapper"><canvas id="modulosChart"></canvas></div>
                </div>
                <div class="chart-container">
                    <h3>⏰ Actividad por Hora</h3>
                    <div class="chart-wrapper"><canvas id="horasChart"></canvas></div>
                </div>
                <div class="chart-container">
                    <h3>📅 Actividad Últimos 7 Días</h3>
                    <div class="chart-wrapper"><canvas id="diasChart"></canvas></div>
                </div>
            </div>
            
            <!-- Filtros de búsqueda -->
            <div class="filtros no-print">
                <form method="GET" action="logs.php" id="filtrosForm">
                    <div class="filtros-row">
                        <div class="filtro-group">
                            <label><i class="fas fa-user"></i> Usuario</label>
                            <input type="text" name="usuario" value="<?php echo htmlspecialchars($filtros['usuario']); ?>" placeholder="Buscar por usuario...">
                        </div>
                        <div class="filtro-group">
                            <label><i class="fas fa-folder"></i> Módulo</label>
                            <select name="modulo">
                                <option value="">Todos</option>
                                <option value="index.php" <?php echo $filtros['modulo'] == 'index.php' ? 'selected' : ''; ?>>Dashboard (index.php)</option>
                                <option value="crear.php" <?php echo $filtros['modulo'] == 'crear.php' ? 'selected' : ''; ?>>Nuevo Registro (crear.php)</option>
                                <option value="editar.php" <?php echo $filtros['modulo'] == 'editar.php' ? 'selected' : ''; ?>>Editar (editar.php)</option>
                                <option value="logs.php" <?php echo $filtros['modulo'] == 'logs.php' ? 'selected' : ''; ?>>Logs (logs.php)</option>
                                <option value="exportar.php" <?php echo $filtros['modulo'] == 'exportar.php' ? 'selected' : ''; ?>>Exportar (exportar.php)</option>
                                <option value="importar.php" <?php echo $filtros['modulo'] == 'importar.php' ? 'selected' : ''; ?>>Importar (importar.php)</option>
                                <option value="roles.php" <?php echo $filtros['modulo'] == 'roles.php' ? 'selected' : ''; ?>>Roles (roles.php)</option>
                                <option value="reportes.php" <?php echo $filtros['modulo'] == 'reportes.php' ? 'selected' : ''; ?>>Reportes (reportes.php)</option>
                                <option value="usuarios.php" <?php echo $filtros['modulo'] == 'usuarios.php' ? 'selected' : ''; ?>>Usuarios (usuarios.php)</option>
                            </select>
                        </div>
                        <div class="filtro-group">
                            <label><i class="fas fa-tag"></i> Acción</label>
                            <input type="text" name="accion" value="<?php echo htmlspecialchars($filtros['accion']); ?>" placeholder="Buscar por acción...">
                        </div>
                        <div class="filtro-group">
                            <label><i class="fas fa-calendar"></i> Fecha Desde</label>
                            <input type="date" name="fecha_desde" value="<?php echo $filtros['fecha_desde']; ?>">
                        </div>
                        <div class="filtro-group">
                            <label><i class="fas fa-calendar"></i> Fecha Hasta</label>
                            <input type="date" name="fecha_hasta" value="<?php echo $filtros['fecha_hasta']; ?>">
                        </div>
                        <div class="filtro-group">
                            <button type="submit" class="btn-filtro"><i class="fas fa-search"></i> Buscar</button>
                            <button type="button" class="btn-limpiar" onclick="limpiarFiltros()"><i class="fas fa-eraser"></i> Limpiar</button>
                        </div>
                    </div>
                    <input type="hidden" name="orden" id="orden" value="<?php echo $filtros['orden']; ?>">
                    <input type="hidden" name="campo_orden" id="campo_orden" value="<?php echo $filtros['campo_orden']; ?>">
                    <input type="hidden" name="pagina" value="1">
                </form>
            </div>
            
            <!-- Acciones masivas -->
            <div class="acciones-bulk no-print">
                <button class="btn btn-info" onclick="exportarLogs('csv')"><i class="fas fa-file-csv"></i> Exportar a CSV</button>
                <button class="btn btn-info" onclick="exportarLogs('json')"><i class="fas fa-file-code"></i> Exportar a JSON</button>
                <button class="btn btn-warning" onclick="mostrarModalLimpieza()"><i class="fas fa-broom"></i> Limpiar Logs Antiguos</button>
            </div>
            
            <!-- Tabla de logs -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th onclick="ordenarPor('id')">ID <?php echo $filtros['campo_orden'] == 'id' ? ($filtros['orden'] == 'DESC' ? '▼' : '▲') : ''; ?></th>
                            <th onclick="ordenarPor('fecha')">Fecha/Hora <?php echo $filtros['campo_orden'] == 'fecha' ? ($filtros['orden'] == 'DESC' ? '▼' : '▲') : ''; ?></th>
                            <th onclick="ordenarPor('usuario')">Usuario <?php echo $filtros['campo_orden'] == 'usuario' ? ($filtros['orden'] == 'DESC' ? '▼' : '▲') : ''; ?></th>
                            <th>Acción</th>
                            <th onclick="ordenarPor('modulo')">Módulo <?php echo $filtros['campo_orden'] == 'modulo' ? ($filtros['orden'] == 'DESC' ? '▼' : '▲') : ''; ?></th>
                            <th onclick="ordenarPor('ip_address')">IP <?php echo $filtros['campo_orden'] == 'ip_address' ? ($filtros['orden'] == 'DESC' ? '▼' : '▲') : ''; ?></th>
                            <th>Detalles</th>
                            <th class="no-print">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultado && $resultado->num_rows > 0): ?>
                            <?php while ($log = $resultado->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $log['id']; ?></td>
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($log['fecha'])); ?></td>
                                    <td><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($log['usuario']); ?></td>
                                    <td><span class="badge-accion"><?php echo htmlspecialchars(mb_strimwidth($log['accion'], 0, 40, '...')); ?></span></td>
                                    <td><span class="badge-modulo"><?php echo htmlspecialchars($log['modulo']); ?></span></td>
                                    <td><code><?php echo htmlspecialchars($log['ip_address']); ?></code></td>
                                    <td class="detalles-cell">
                                        <?php if ($log['detalles']): ?>
                                            <div class="detalles-preview" onclick="verDetalles(<?php echo htmlspecialchars(json_encode($log['detalles'])); ?>)">
                                                <i class="fas fa-info-circle"></i> Ver detalles
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #999;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="no-print">
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar este registro de log?');">
                                            <input type="hidden" name="eliminar_log" value="<?php echo $log['id']; ?>">
                                            <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 11px;"><i class="fas fa-trash"></i> Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-database" style="font-size: 48px; color: #bdc3c7;"></i><br>
                                    <strong>No hay registros de actividad</strong><br>
                                    <span style="font-size: 12px; color: #7f8c8d;">No se encontraron logs con los filtros aplicados</span>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación -->
            <?php if ($total_paginas > 1): ?>
            <div class="paginacion no-print">
                <div class="info-registros">
                    <strong>Total:</strong> <?php echo number_format($total_registros); ?> registros | 
                    <strong>Página:</strong> <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>
                </div>
                <div class="paginacion-links">
                    <?php if ($pagina_actual > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($filtros, ['pagina' => 1, 'orden' => $filtros['orden'], 'campo_orden' => $filtros['campo_orden']])); ?>">« Primera</a>
                        <a href="?<?php echo http_build_query(array_merge($filtros, ['pagina' => $pagina_actual - 1, 'orden' => $filtros['orden'], 'campo_orden' => $filtros['campo_orden']])); ?>">‹ Anterior</a>
                    <?php endif; ?>
                    
                    <?php
                    $inicio = max(1, $pagina_actual - 2);
                    $fin = min($total_paginas, $pagina_actual + 2);
                    for ($i = $inicio; $i <= $fin; $i++):
                    ?>
                        <?php if ($i == $pagina_actual): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo http_build_query(array_merge($filtros, ['pagina' => $i, 'orden' => $filtros['orden'], 'campo_orden' => $filtros['campo_orden']])); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($pagina_actual < $total_paginas): ?>
                        <a href="?<?php echo http_build_query(array_merge($filtros, ['pagina' => $pagina_actual + 1, 'orden' => $filtros['orden'], 'campo_orden' => $filtros['campo_orden']])); ?>">Siguiente ›</a>
                        <a href="?<?php echo http_build_query(array_merge($filtros, ['pagina' => $total_paginas, 'orden' => $filtros['orden'], 'campo_orden' => $filtros['campo_orden']])); ?>">Última »</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Panel de información adicional -->
            <div class="info-panel no-print">
                <div>
                    <i class="fas fa-info-circle" style="color: #3498db;"></i>
                    <strong>Nota:</strong> Solo los administradores pueden ver y gestionar los logs del sistema.
                </div>
                <div>
                    <i class="fas fa-keyboard"></i>
                    Atajos: <kbd>Ctrl</kbd> + <kbd>B</kbd> Volver | <kbd>Ctrl</kbd> + <kbd>F</kbd> Enfocar filtros
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para ver detalles -->
    <div id="detallesModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="cerrarModal()">&times;</span>
            <h3><i class="fas fa-info-circle"></i> Detalles del Log</h3>
            <pre id="detallesContenido" style="background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; margin-top: 15px;"></pre>
        </div>
    </div>
    
    <!-- Modal para limpiar logs antiguos -->
    <div id="limpiezaModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-broom"></i> Limpiar Logs Antiguos</h3>
            <p>Eliminar logs con más de:</p>
            <form method="POST" action="logs.php">
                <input type="number" name="dias" value="30" min="1" max="365" required>
                <label>días</label>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModalLimpieza()">Cancelar</button>
                    <button type="submit" name="limpiar_antiguos" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Datos para gráficos
        const usuariosData = <?php 
            $usuarios_labels = array_keys(array_slice($stats['usuarios'], 0, 8));
            $usuarios_values = array_values(array_slice($stats['usuarios'], 0, 8));
            echo json_encode(['labels' => $usuarios_labels, 'values' => $usuarios_values]);
        ?>;
        
        const modulosData = <?php 
            $modulos_labels = array_keys(array_slice($stats['modulos'], 0, 8));
            $modulos_values = array_values(array_slice($stats['modulos'], 0, 8));
            echo json_encode(['labels' => $modulos_labels, 'values' => $modulos_values]);
        ?>;
        
        const horasData = <?php 
            $horas = [];
            for ($i = 0; $i < 24; $i++) {
                $horas[$i] = $stats['horas'][$i] ?? 0;
            }
            echo json_encode(['labels' => array_keys($horas), 'values' => array_values($horas)]);
        ?>;
        
        const diasData = <?php 
            $dias_labels = array_keys($stats['dias']);
            $dias_values = array_values($stats['dias']);
            echo json_encode(['labels' => $dias_labels, 'values' => $dias_values]);
        ?>;
        
        // Inicializar gráficos
        document.addEventListener('DOMContentLoaded', function() {
            // Gráfico de usuarios
            const ctxUsuarios = document.getElementById('usuariosChart').getContext('2d');
            new Chart(ctxUsuarios, {
                type: 'bar',
                data: {
                    labels: usuariosData.labels,
                    datasets: [{ label: 'Actividades', data: usuariosData.values, backgroundColor: '#9b59b6' }]
                },
                options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
            });
            
            // Gráfico de módulos
            const ctxModulos = document.getElementById('modulosChart').getContext('2d');
            new Chart(ctxModulos, {
                type: 'pie',
                data: {
                    labels: modulosData.labels,
                    datasets: [{ data: modulosData.values, backgroundColor: ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#e67e22', '#34495e'] }]
                },
                options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
            });
            
            // Gráfico de horas
            const ctxHoras = document.getElementById('horasChart').getContext('2d');
            new Chart(ctxHoras, {
                type: 'line',
                data: {
                    labels: horasData.labels,
                    datasets: [{ label: 'Actividades', data: horasData.values, borderColor: '#3498db', backgroundColor: 'rgba(52,152,219,0.1)', fill: true }]
                },
                options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
            });
            
            // Gráfico de días
            const ctxDias = document.getElementById('diasChart').getContext('2d');
            new Chart(ctxDias, {
                type: 'bar',
                data: {
                    labels: diasData.labels,
                    datasets: [{ label: 'Actividades', data: diasData.values, backgroundColor: '#27ae60' }]
                },
                options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
            });
        });
        
        function ordenarPor(campo) {
            const ordenActual = document.getElementById('orden').value;
            const campoActual = document.getElementById('campo_orden').value;
            let nuevoOrden = 'DESC';
            
            if (campoActual === campo && ordenActual === 'DESC') {
                nuevoOrden = 'ASC';
            }
            
            document.getElementById('orden').value = nuevoOrden;
            document.getElementById('campo_orden').value = campo;
            document.getElementById('filtrosForm').submit();
        }
        
        function limpiarFiltros() {
            window.location.href = 'logs.php';
        }
        
        function exportarLogs(formato) {
            window.location.href = 'logs.php?exportar=' + formato;
        }
        
        function verDetalles(detalles) {
            const modal = document.getElementById('detallesModal');
            const contenido = document.getElementById('detallesContenido');
            
            try {
                const parsed = JSON.parse(detalles);
                contenido.textContent = JSON.stringify(parsed, null, 2);
            } catch(e) {
                contenido.textContent = detalles;
            }
            
            modal.style.display = 'flex';
        }
        
        function cerrarModal() {
            document.getElementById('detallesModal').style.display = 'none';
        }
        
        function mostrarModalLimpieza() {
            document.getElementById('limpiezaModal').style.display = 'flex';
        }
        
        function cerrarModalLimpieza() {
            document.getElementById('limpiezaModal').style.display = 'none';
        }
        
        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('detallesModal');
            const modalLimpieza = document.getElementById('limpiezaModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
            if (event.target === modalLimpieza) {
                modalLimpieza.style.display = 'none';
            }
        }
        
        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl + B = Volver atrás
            if (e.ctrlKey && e.key === 'b') {
                e.preventDefault();
                window.history.back();
            }
            // Ctrl + F = Enfocar filtro de usuario
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.querySelector('input[name="usuario"]')?.focus();
            }
            // Escape = Cerrar modales
            if (e.key === 'Escape') {
                cerrarModal();
                cerrarModalLimpieza();
            }
        });
        
        // Mostrar notificación de atajos (solo una vez por sesión)
        if (!sessionStorage.getItem('logs_atajos_mostrados')) {
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
                notif.innerHTML = '<i class="fas fa-keyboard"></i> Atajos: Ctrl+B (Volver), Ctrl+F (Filtros), Esc (Cerrar)';
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
                sessionStorage.setItem('logs_atajos_mostrados', 'true');
            }, 1000);
        }
    </script>
</body>
</html>