<?php
// usuarios_unicos.php - Gestión completa de usuarios únicos
require_once 'config.php';



// Verificar autenticación
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Obtener página anterior para el botón volver
$pagina_anterior = $_SERVER['HTTP_REFERER'] ?? 'index.php';
if (strpos($pagina_anterior, 'usuarios_unicos.php') !== false || empty($pagina_anterior)) {
    $pagina_anterior = 'index.php';
}

// ============================================
// PROCESAR ACCIONES
// ============================================
$accion = $_GET['accion'] ?? $_POST['accion'] ?? 'listar';
$mensaje = '';
$tipo_mensaje = '';

// Eliminar usuario? (mover registros o marcar)
if ($accion === 'eliminar' && isset($_GET['usuario'])) {
    $usuario = $_GET['usuario'];
    
    // Registrar acción
    registrarLog($conn, $_SESSION['usuario'], 
        "Eliminó usuario único: $usuario", 
        "usuarios_unicos.php", 
        obtenerIP(),
        ['usuario' => $usuario]
    );
    
    // Opción: actualizar registros a 'Anónimo' en lugar de eliminar
    $update_sql = "UPDATE registros_ids SET usuario = 'Anónimo' WHERE usuario = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("s", $usuario);
    
    if ($stmt->execute()) {
        $mensaje = "Usuario '$usuario' eliminado correctamente (sus registros se marcaron como Anónimo)";
        $tipo_mensaje = "success";
    } else {
        $mensaje = "Error al eliminar usuario: " . $conn->error;
        $tipo_mensaje = "error";
    }
    $stmt->close();
    
    header("Location: usuarios_unicos.php");
    exit();
}

// Exportar CSV
if ($accion === 'exportar_csv') {
    $usuarios = obtenerUsuariosUnicos($conn, $filtro_estatus ?? null, $filtro_rol ?? null, 1000, 0);
    $csv = exportarUsuariosUnicosCSV($conn, $usuarios);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="usuarios_unicos_' . date('Y-m-d') . '.csv"');
    echo $csv;
    exit();
}

// Exportar PDF
if ($accion === 'exportar_pdf') {
    // Obtener usuarios con los filtros actuales
    $filtro_estatus_pdf = $_GET['estatus'] ?? null;
    $filtro_rol_pdf = $_GET['rol'] ?? null;
    $busqueda_pdf = $_GET['busqueda'] ?? '';
    
    if ($busqueda_pdf) {
        $usuarios_pdf = buscarUsuariosUnicos($conn, $busqueda_pdf, 1000);
    } else {
        $usuarios_pdf = obtenerUsuariosUnicos($conn, $filtro_estatus_pdf, $filtro_rol_pdf, 1000, 0);
    }
    
    $stats_pdf = obtenerEstadisticasUsuariosUnicos($conn);
    
    // Registrar acción
    registrarLog($conn, $_SESSION['usuario'], 
        "Exportó usuarios únicos a PDF", 
        "usuarios_unicos.php", 
        obtenerIP(),
        ['filtros' => ['estatus' => $filtro_estatus_pdf, 'rol' => $filtro_rol_pdf, 'busqueda' => $busqueda_pdf]]
    );
    
    // Función para generar PDF usando HTML2PDF (sin librerías externas)
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Usuarios Únicos - Reporte</title>
        <style>
            @media print {
                body { margin: 0; padding: 0; }
                .no-print { display: none; }
                button { display: none; }
            }
            body { 
                font-family: Arial, Helvetica, sans-serif; 
                margin: 20px;
                font-size: 12px;
            }
            h1 { 
                color: #2c3e50; 
                text-align: center; 
                font-size: 18px;
                margin-bottom: 5px;
            }
            .fecha {
                text-align: center;
                color: #7f8c8d;
                font-size: 11px;
                margin-bottom: 20px;
            }
            .stats {
                background: #f8f9fa;
                padding: 10px;
                margin-bottom: 20px;
                border-radius: 5px;
                font-size: 11px;
            }
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(5, 1fr);
                gap: 10px;
                margin-bottom: 15px;
            }
            .stat-item {
                background: white;
                padding: 8px;
                border: 1px solid #dee2e6;
                border-radius: 5px;
                text-align: center;
            }
            .stat-number {
                font-size: 18px;
                font-weight: bold;
                color: #3498db;
            }
            .stat-label {
                font-size: 10px;
                color: #6c757d;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }
            th {
                background: #2c3e50;
                color: white;
                padding: 8px;
                text-align: left;
                font-size: 11px;
            }
            td {
                padding: 6px 8px;
                border-bottom: 1px solid #dee2e6;
                font-size: 10px;
            }
            .badge {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 4px;
                font-size: 9px;
                font-weight: bold;
                margin: 1px;
            }
            .badge-activo { background: #d4edda; color: #155724; }
            .badge-inactivo { background: #f8d7da; color: #721c24; }
            .badge-pendiente { background: #fff3cd; color: #856404; }
            .footer {
                margin-top: 20px;
                text-align: center;
                font-size: 9px;
                color: #6c757d;
                border-top: 1px solid #dee2e6;
                padding-top: 10px;
            }
            .btn-print {
                background: #3498db;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                margin: 5px;
                font-size: 14px;
            }
            .btn-print:hover {
                background: #2980b9;
            }
            @media (max-width: 768px) {
                .stats-grid { grid-template-columns: repeat(2, 1fr); }
                body { font-size: 10px; margin: 10px; }
                th, td { padding: 4px; }
            }
        </style>
    </head>
    <body>
        <div style="text-align: center; margin-bottom: 20px;" class="no-print">
            <button onclick="window.print();" class="btn-print">
                <i class="fas fa-print"></i> Imprimir / Guardar como PDF
            </button>
            <button onclick="window.location.href='usuarios_unicos.php';" class="btn-print" style="background: #95a5a6;">
                <i class="fas fa-arrow-left"></i> Volver
            </button>
            <button onclick="window.history.back();" class="btn-print" style="background: #2c3e50;">
                <i class="fas fa-undo-alt"></i> Atrás
            </button>
        </div>
        
        <h1>📋 REPORTE DE USUARIOS ÚNICOS</h1>
        <div class="fecha">
            Fecha de generación: <?php echo date('d/m/Y H:i:s'); ?><br>
            Generado por: <?php echo htmlspecialchars($_SESSION['usuario'] ?? 'Sistema'); ?>
        </div>
        
        <!-- Filtros aplicados -->
        <?php if ($filtro_estatus_pdf || $filtro_rol_pdf || $busqueda_pdf): ?>
        <div class="stats" style="background: #e8f4fd;">
            <strong>📌 Filtros aplicados:</strong><br>
            <?php if ($filtro_estatus_pdf): ?>• Estatus: <?php echo $filtro_estatus_pdf; ?><br><?php endif; ?>
            <?php if ($filtro_rol_pdf): ?>• Rol: <?php echo htmlspecialchars($filtro_rol_pdf); ?><br><?php endif; ?>
            <?php if ($busqueda_pdf): ?>• Búsqueda: <?php echo htmlspecialchars($busqueda_pdf); ?><br><?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number"><?php echo $stats_pdf['total']; ?></div>
                <div class="stat-label">Total Usuarios</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" style="color: #27ae60;"><?php echo $stats_pdf['activos']; ?></div>
                <div class="stat-label">Activos</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" style="color: #e74c3c;"><?php echo $stats_pdf['inactivos']; ?></div>
                <div class="stat-label">Inactivos</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" style="color: #f39c12;"><?php echo $stats_pdf['pendiente']; ?></div>
                <div class="stat-label">Pendientes</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" style="color: #9b59b6;"><?php echo $stats_pdf['promedio_registros']; ?></div>
                <div class="stat-label">Promedio Registros</div>
            </div>
        </div>
        
        <!-- Tabla de usuarios -->
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Usuario</th>
                    <th>Registros</th>
                    <th>Estatus</th>
                    <th>Roles</th>
                    <th>Rango</th>
                    <th>Último Registro</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usuarios_pdf)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 30px;">
                            No se encontraron usuarios con los filtros aplicados
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $contador_pdf = 1; ?>
                    <?php foreach ($usuarios_pdf as $usuario): ?>
                        <tr>
                            <td><?php echo $contador_pdf++; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($usuario['usuario']); ?></strong>
                            </td>
                            <td><?php echo $usuario['total_registros']; ?></td>
                            <td>
                                <?php 
                                $estatus_array = explode(', ', $usuario['estatus_asignados'] ?? '');
                                $estatus_unicos = array_unique($estatus_array);
                                foreach ($estatus_unicos as $est): 
                                    $badge_class = '';
                                    if ($est == 'Activo') $badge_class = 'badge-activo';
                                    elseif ($est == 'Inactivo') $badge_class = 'badge-inactivo';
                                    else $badge_class = 'badge-pendiente';
                                ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo $est; ?></span>
                                <?php endforeach; ?>
                              </td>
                            <td>
                                <?php 
                                $roles_array = explode(', ', $usuario['roles_asignados'] ?? '');
                                echo htmlspecialchars(substr($roles_array[0] ?? '—', 0, 20));
                                if (count($roles_array) > 1) echo " +" . (count($roles_array) - 1);
                                ?>
                              </td>
                            <td>
                                <?php 
                                $rangos_array = explode(', ', $usuario['rangos_asignados'] ?? '');
                                echo htmlspecialchars(substr($rangos_array[0] ?? '—', 0, 20));
                                if (count($rangos_array) > 1) echo " +" . (count($rangos_array) - 1);
                                ?>
                              </td>
                            <td>
                                <?php echo date('d/m/Y', strtotime($usuario['ultimo_registro'] ?? 'now')); ?>
                              </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Top usuarios -->
        <?php if (!empty($stats_pdf['top_usuarios']) && !$filtro_estatus_pdf && !$filtro_rol_pdf && !$busqueda_pdf): ?>
        <div style="margin-top: 30px;">
            <h3 style="font-size: 14px;">🏆 Top 10 Usuarios con más registros</h3>
            <table style="margin-top: 10px;">
                <thead>
                    <tr><th>#</th><th>Usuario</th><th>Total Registros</th><th>Última Actividad</th></tr>
                </thead>
                <tbody>
                    <?php $rank = 1; ?>
                    <?php foreach ($stats_pdf['top_usuarios'] as $top): ?>
                        <tr>
                            <td><?php echo $rank++; ?></td>
                            <td><strong><?php echo htmlspecialchars($top['usuario']); ?></strong></td>
                            <td><?php echo $top['total']; ?> registros</td>
                            <td><?php echo date('d/m/Y', strtotime($top['ultima_actividad'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            Reporte generado automáticamente por el Sistema de Bitácora<br>
            Total de usuarios únicos: <?php echo count($usuarios_pdf); ?> | Total de registros en el sistema: <?php echo $stats_pdf['total_registros']; ?>
        </div>
        
        <script>
            // Atajos de teclado en el reporte
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'p') {
                    e.preventDefault();
                    window.print();
                }
                if (e.key === 'Escape') {
                    window.location.href = 'usuarios_unicos.php';
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit();
}

// ============================================
// OBTENER PARÁMETROS
// ============================================
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$por_pagina = isset($_GET['por_pagina']) ? intval($_GET['por_pagina']) : 20;
$filtro_estatus = $_GET['estatus'] ?? null;
$filtro_rol = $_GET['rol'] ?? null;
$busqueda = $_GET['busqueda'] ?? '';

$offset = ($pagina - 1) * $por_pagina;

// ============================================
// OBTENER DATOS
// ============================================
$stats = obtenerEstadisticasUsuariosUnicos($conn);

if ($busqueda) {
    $usuarios = buscarUsuariosUnicos($conn, $busqueda, $por_pagina);
    $total_usuarios = count($usuarios);
} else {
    $usuarios = obtenerUsuariosUnicos($conn, $filtro_estatus, $filtro_rol, $por_pagina, $offset);
    $total_usuarios = totalUsuariosUnicos($conn, $filtro_estatus);
}

$total_paginas = ceil($total_usuarios / $por_pagina);

// Obtener lista de roles disponibles para filtros
$roles_disponibles = [];
$roles_query = $conn->query("SELECT DISTINCT roles FROM registros_ids WHERE roles IS NOT NULL AND roles != '' ORDER BY roles");
while ($row = $roles_query->fetch_assoc()) {
    $roles_disponibles[] = $row['roles'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios Únicos - Bitácora</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; }
        
        /* ===== ESTILOS DEL BOTÓN VOLVER ===== */
        .btn-Volver {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .btn-Volver:hover {
            background: rgba(255,255,255,0.3);
            transform: translateX(-3px);
            color: white;
        }
        
        .btn-Volver i {
            font-size: 12px;
        }
        
        /* Navegación en header */
        .nav-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 10px;
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
        
        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: rgba(255,255,255,0.8);
            margin-bottom: 10px;
        }
        
        .breadcrumb a {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .breadcrumb i {
            font-size: 10px;
        }
        
        .container { max-width: 1400px; margin: 20px auto; padding: 0 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .header h1 { font-size: 28px; margin-bottom: 5px; }
        .header p { opacity: 0.9; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: transform 0.3s; cursor: pointer; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card .number { font-size: 32px; font-weight: bold; }
        .stat-card .label { color: #7f8c8d; font-size: 14px; margin-top: 5px; }
        .stat-card.total .number { color: #3498db; }
        .stat-card.activos .number { color: #27ae60; }
        .stat-card.inactivos .number { color: #e74c3c; }
        .stat-card.pendiente .number { color: #f39c12; }
        .stat-card.promedio .number { color: #9b59b6; }
        
        .filtros { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 15px; flex-wrap: wrap; align-items: center; }
        .filtros select, .filtros input { padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .filtros button { padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .filtros button:hover { background: #2980b9; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-secondary { background: #95a5a6; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        
        table { width: 100%; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        th { background: #2c3e50; color: white; padding: 12px; text-align: left; }
        td { padding: 12px; border-bottom: 1px solid #ecf0f1; }
        tr:hover { background: #f5f9ff; }
        
        .badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; margin: 2px; }
        .badge-activo { background: #d4edda; color: #155724; }
        .badge-inactivo { background: #f8d7da; color: #721c24; }
        .badge-pendiente { background: #fff3cd; color: #856404; }
        
        .paginacion { display: flex; justify-content: center; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
        .paginacion a, .paginacion span { padding: 8px 15px; background: white; border-radius: 5px; text-decoration: none; color: #3498db; }
        .paginacion .active { background: #3498db; color: white; }
        
        .mensaje { padding: 12px 20px; margin-bottom: 20px; border-radius: 5px; }
        .mensaje.success { background: #d4edda; color: #155724; }
        .mensaje.error { background: #f8d7da; color: #721c24; }
        
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
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filtros { flex-direction: column; }
            table { font-size: 12px; }
            th, td { padding: 8px; }
            .floating-back { bottom: 20px; right: 20px; width: 45px; height: 45px; }
            .nav-buttons { justify-content: center; }
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

    <div class="container">
        <div class="header">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i> Inicio</a>
                <i class="fas fa-chevron-right"></i>
                <a href="admin_panel.php"><i class="fas fa-shield-alt"></i> Panel Admin</a>
                <i class="fas fa-chevron-right"></i>
                <span><i class="fas fa-users"></i> Usuarios Únicos</span>
            </div>
            
            <h1><i class="fas fa-users"></i> Usuarios Únicos</h1>
            <p>Gestión de usuarios del sistema - Sin duplicados</p>
            
            <!-- Botones de navegación -->
            <div class="nav-buttons">
                <!-- Botón volver a la página anterior -->
                <a href="<?php echo htmlspecialchars($pagina_anterior); ?>" class="btn-Volver" data-tooltip="Volver a la página anterior">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
                <!-- Botón volver al inicio -->
                <a href="index.php" class="btn-Volver" data-tooltip="Ir al panel principal">
                    <i class="fas fa-home"></i> Inicio
                </a>
                <!-- Botón volver con historial -->
                <a href="javascript:history.back()" class="btn-Volver" data-tooltip="Volver a la página visitada anteriormente">
                    <i class="fas fa-undo-alt"></i> Atrás
                </a>
                <!-- Botón Panel Admin -->
                <a href="admin_panel.php" class="btn-Volver" data-tooltip="Volver al panel de administración">
                    <i class="fas fa-shield-alt"></i> Panel Admin
                </a>
            </div>
            
            <!-- Acciones de exportación -->
            <div style="margin-top: 10px;">
                <a href="?accion=exportar_csv<?php echo $filtro_estatus ? '&estatus=' . urlencode($filtro_estatus) : ''; ?><?php echo $filtro_rol ? '&rol=' . urlencode($filtro_rol) : ''; ?><?php echo $busqueda ? '&busqueda=' . urlencode($busqueda) : ''; ?>" class="btn btn-success" style="background: rgba(255,255,255,0.2);" data-tooltip="Exportar a CSV">
                    <i class="fas fa-file-csv"></i> Exportar CSV
                </a>
                <a href="?accion=exportar_pdf<?php echo $filtro_estatus ? '&estatus=' . urlencode($filtro_estatus) : ''; ?><?php echo $filtro_rol ? '&rol=' . urlencode($filtro_rol) : ''; ?><?php echo $busqueda ? '&busqueda=' . urlencode($busqueda) : ''; ?>" class="btn btn-danger" style="background: rgba(255,255,255,0.2);" data-tooltip="Exportar a PDF">
                    <i class="fas fa-file-pdf"></i> Exportar PDF
                </a>
            </div>
        </div>
        
        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>
        
        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card total" onclick="location.href='?estatus='" data-tooltip="Ver todos los usuarios">
                <div class="number"><?php echo $stats['total']; ?></div>
                <div class="label">Total Usuarios Únicos</div>
            </div>
            <div class="stat-card activos" onclick="location.href='?estatus=Activo'" data-tooltip="Ver solo usuarios activos">
                <div class="number"><?php echo $stats['activos']; ?></div>
                <div class="label">Usuarios Activos</div>
                <small><?php echo round(($stats['activos'] / max($stats['total'], 1)) * 100, 1); ?>% del total</small>
            </div>
            <div class="stat-card inactivos" onclick="location.href='?estatus=Inactivo'" data-tooltip="Ver solo usuarios inactivos">
                <div class="number"><?php echo $stats['inactivos']; ?></div>
                <div class="label">Usuarios Inactivos</div>
                <small><?php echo round(($stats['inactivos'] / max($stats['total'], 1)) * 100, 1); ?>% del total</small>
            </div>
            <div class="stat-card pendiente" onclick="location.href='?estatus=Pendiente'" data-tooltip="Ver solo usuarios pendientes">
                <div class="number"><?php echo $stats['pendiente']; ?></div>
                <div class="label">Usuarios Pendientes</div>
                <small><?php echo round(($stats['pendiente'] / max($stats['total'], 1)) * 100, 1); ?>% del total</small>
            </div>
            <div class="stat-card promedio" data-tooltip="Promedio de registros por usuario">
                <div class="number"><?php echo $stats['promedio_registros']; ?></div>
                <div class="label">Promedio Registros/Usuario</div>
                <small>Total: <?php echo $stats['total_registros']; ?> registros</small>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="filtros">
            <form method="GET" action="" style="display: contents;" id="filtrosForm">
                <input type="text" name="busqueda" placeholder="🔍 Buscar usuario..." value="<?php echo htmlspecialchars($busqueda); ?>">
                <select name="estatus">
                    <option value="">Todos los estatus</option>
                    <option value="Activo" <?php echo $filtro_estatus == 'Activo' ? 'selected' : ''; ?>>Activos</option>
                    <option value="Inactivo" <?php echo $filtro_estatus == 'Inactivo' ? 'selected' : ''; ?>>Inactivos</option>
                    <option value="Pendiente" <?php echo $filtro_estatus == 'Pendiente' ? 'selected' : ''; ?>>Pendientes</option>
                    <option value="Bloqueado" <?php echo $filtro_estatus == 'Bloqueado' ? 'selected' : ''; ?>>Bloqueados</option>
                </select>
                <select name="rol">
                    <option value="">Todos los roles</option>
                    <?php foreach ($roles_disponibles as $rol): ?>
                        <option value="<?php echo htmlspecialchars($rol); ?>" <?php echo $filtro_rol == $rol ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($rol); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="por_pagina">
                    <option value="10" <?php echo $por_pagina == 10 ? 'selected' : ''; ?>>10 por página</option>
                    <option value="20" <?php echo $por_pagina == 20 ? 'selected' : ''; ?>>20 por página</option>
                    <option value="50" <?php echo $por_pagina == 50 ? 'selected' : ''; ?>>50 por página</option>
                    <option value="100" <?php echo $por_pagina == 100 ? 'selected' : ''; ?>>100 por página</option>
                </select>
                <button type="submit"><i class="fas fa-filter"></i> Filtrar</button>
                <a href="usuarios_unicos.php" class="btn btn-secondary" style="background: #95a5a6;" data-tooltip="Limpiar todos los filtros">
                    <i class="fas fa-eraser"></i> Limpiar
                </a>
            </form>
        </div>
        
        <!-- Tabla de usuarios -->
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Usuario</th>
                    <th>Total Registros</th>
                    <th>Estatus Asignados</th>
                    <th>Roles</th>
                    <th>Rangos Militares</th>
                    <th>Último Registro</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usuarios)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            <i class="fas fa-user-slash" style="font-size: 48px; color: #ccc;"></i><br>
                            No se encontraron usuarios
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $contador = $offset + 1; ?>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td><?php echo $contador++; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($usuario['usuario']); ?></strong>
                            </td>
                            <td>
                                <span class="badge" style="background: #3498db20; color: #3498db;">
                                    <?php echo $usuario['total_registros']; ?> registros
                                </span>
                            </td>
                            <td>
                                <?php 
                                $estatus_array = explode(', ', $usuario['estatus_asignados'] ?? '');
                                $estatus_unicos = array_unique($estatus_array);
                                foreach ($estatus_unicos as $est): 
                                    $badge_class = '';
                                    if ($est == 'Activo') $badge_class = 'badge-activo';
                                    elseif ($est == 'Inactivo') $badge_class = 'badge-inactivo';
                                    else $badge_class = 'badge-pendiente';
                                ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo $est; ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <?php 
                                $roles_array = explode(', ', $usuario['roles_asignados'] ?? '');
                                foreach (array_slice($roles_array, 0, 2) as $rol): 
                                ?>
                                    <span class="badge" style="background: #9b59b620; color: #8e44ad;"><?php echo htmlspecialchars(substr($rol, 0, 15)); ?></span>
                                <?php endforeach; 
                                if (count($roles_array) > 2) echo "<span class='badge'>+".(count($roles_array)-2)."</span>";
                                ?>
                            </td>
                            <td>
                                <?php 
                                $rangos_array = explode(', ', $usuario['rangos_asignados'] ?? '');
                                echo htmlspecialchars($rangos_array[0] ?? '—');
                                if (count($rangos_array) > 1) echo " +" . (count($rangos_array) - 1);
                                ?>
                            </td>
                            <td>
                                <small><?php echo date('d/m/Y', strtotime($usuario['ultimo_registro'] ?? 'now')); ?></small>
                            </td>
                            <td>
                                <a href="ver_usuario.php?usuario=<?php echo urlencode($usuario['usuario']); ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;" data-tooltip="Ver detalles del usuario">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                                <a href="?accion=eliminar&usuario=<?php echo urlencode($usuario['usuario']); ?>" 
                                   class="btn btn-danger" 
                                   style="padding: 5px 10px; font-size: 12px;"
                                   onclick="return confirm('¿Eliminar usuario <?php echo addslashes($usuario['usuario']); ?>? Sus registros se marcarán como Anónimo')"
                                   data-tooltip="Eliminar usuario">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Paginación -->
        <?php if ($total_paginas > 1 && !$busqueda): ?>
        <div class="paginacion">
            <?php if ($pagina > 1): ?>
                <a href="?pagina=<?php echo $pagina - 1; ?>&estatus=<?php echo urlencode($filtro_estatus ?? ''); ?>&rol=<?php echo urlencode($filtro_rol ?? ''); ?>&por_pagina=<?php echo $por_pagina; ?>">
                    <i class="fas fa-chevron-left"></i> Anterior
                </a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                <?php if ($i == $pagina): ?>
                    <span class="active"><?php echo $i; ?></span>
                <?php elseif ($i <= 5 || $i > $total_paginas - 2 || ($i >= $pagina - 2 && $i <= $pagina + 2)): ?>
                    <a href="?pagina=<?php echo $i; ?>&estatus=<?php echo urlencode($filtro_estatus ?? ''); ?>&rol=<?php echo urlencode($filtro_rol ?? ''); ?>&por_pagina=<?php echo $por_pagina; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php elseif ($i == 6 && $pagina > 5): ?>
                    <span>...</span>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($pagina < $total_paginas): ?>
                <a href="?pagina=<?php echo $pagina + 1; ?>&estatus=<?php echo urlencode($filtro_estatus ?? ''); ?>&rol=<?php echo urlencode($filtro_rol ?? ''); ?>&por_pagina=<?php echo $por_pagina; ?>">
                    Siguiente <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Top usuarios -->
        <?php if (!empty($stats['top_usuarios']) && !$busqueda && !$filtro_estatus && !$filtro_rol): ?>
        <div style="margin-top: 30px; background: white; padding: 20px; border-radius: 8px;">
            <h3><i class="fas fa-trophy"></i> Top 10 Usuarios con más registros</h3>
            <table style="margin-top: 15px;">
                <thead>
                    <tr><th>#</th><th>Usuario</th><th>Total Registros</th><th>Última Actividad</th></tr>
                </thead>
                <tbody>
                    <?php $rank = 1; ?>
                    <?php foreach ($stats['top_usuarios'] as $top): ?>
                        <tr>
                            <td><?php echo $rank++; ?></td>
                            <td><strong><?php echo htmlspecialchars($top['usuario']); ?></strong></td>
                            <td><?php echo $top['total']; ?> registros</td>
                            <td><?php echo date('d/m/Y', strtotime($top['ultima_actividad'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Panel de información adicional -->
        <div class="info-panel">
            <div>
                <i class="fas fa-info-circle" style="color: #3498db;"></i>
                <strong>Nota:</strong> Los usuarios únicos son aquellos que aparecen en al menos un registro de la bitácora.
            </div>
            <div>
                <i class="fas fa-keyboard"></i>
                Atajos: <kbd>Ctrl</kbd> + <kbd>B</kbd> Volver | <kbd>Ctrl</kbd> + <kbd>F</kbd> Enfocar búsqueda
            </div>
        </div>
    </div>

    <script>
        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl + B = Volver atrás
            if (e.ctrlKey && e.key === 'b') {
                e.preventDefault();
                window.history.back();
            }
            // Ctrl + F = Enfocar búsqueda
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.querySelector('input[name="busqueda"]')?.focus();
            }
            // Ctrl + R = Limpiar filtros
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                window.location.href = 'usuarios_unicos.php';
            }
        });
        
        // Mostrar notificación de atajos (solo una vez por sesión)
        if (!sessionStorage.getItem('usuarios_unicos_atajos_mostrados')) {
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
                notif.innerHTML = '<i class="fas fa-keyboard"></i> Atajos: Ctrl+B (Volver), Ctrl+F (Buscar), Ctrl+R (Limpiar)';
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
                sessionStorage.setItem('usuarios_unicos_atajos_mostrados', 'true');
            }, 1000);
        }
        
        // Guardar filtros en sesión
        function guardarFiltros() {
            const busqueda = document.querySelector('input[name="busqueda"]')?.value || '';
            const estatus = document.querySelector('select[name="estatus"]')?.value || '';
            const rol = document.querySelector('select[name="rol"]')?.value || '';
            sessionStorage.setItem('usuarios_unicos_busqueda', busqueda);
            sessionStorage.setItem('usuarios_unicos_estatus', estatus);
            sessionStorage.setItem('usuarios_unicos_rol', rol);
        }
        
        // Cargar filtros guardados
        function cargarFiltros() {
            const busqueda = sessionStorage.getItem('usuarios_unicos_busqueda');
            const estatus = sessionStorage.getItem('usuarios_unicos_estatus');
            const rol = sessionStorage.getItem('usuarios_unicos_rol');
            if (busqueda && !document.querySelector('input[name="busqueda"]')?.value) {
                document.querySelector('input[name="busqueda"]').value = busqueda;
            }
            if (estatus && !document.querySelector('select[name="estatus"]')?.value) {
                document.querySelector('select[name="estatus"]').value = estatus;
            }
            if (rol && !document.querySelector('select[name="rol"]')?.value) {
                document.querySelector('select[name="rol"]').value = rol;
            }
        }
        
        // Guardar filtros al cambiar
        document.querySelectorAll('#filtrosForm input, #filtrosForm select').forEach(el => {
            el.addEventListener('change', guardarFiltros);
        });
    </script>
</body>
</html>