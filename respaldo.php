<?php
// respaldo.php - Respaldo de la base de datos
require_once 'config.php';

$accion = $_GET['accion'] ?? '';
$mensaje = '';
$tipo_mensaje = '';

// Obtener usuario actual (si existe sesión)
$usuario_actual = $_SESSION['usuario'] ?? $_SESSION['username'] ?? 'Sistema';

if ($accion === 'exportar') {
    // Registrar actividad: Exportar respaldo
    registrarLog($conn, $usuario_actual, 'Exportó respaldo completo de la base de datos', 'Respaldo');
    
    // Exportar estructura y datos
    $fecha = date('Y-m-d_H-i-s');
    $nombre_archivo = "backup_bitacora_ids_$fecha.sql";
    
    // Obtener nombre de la base de datos
    $db_result = $conn->query("SELECT DATABASE() as db");
    $db_row = $db_result->fetch_assoc();
    $nombre_db = $db_row['db'] ?? 'bitacora_ids';
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
    header('Content-Description: File Transfer');
    header('Cache-Control: private', false);
    
    // Obtener estructura de tablas
    $tablas = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $tablas[] = $row[0];
    }
    
    // Generar SQL con mejor formato
    $sql = "-- ===========================================\n";
    $sql .= "-- RESPALDO DE BITÁCORA DE IDs\n";
    $sql .= "-- ===========================================\n";
    $sql .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Base de datos: " . $nombre_db . "\n";
    $sql .= "-- Generado por: " . $usuario_actual . "\n";
    $sql .= "-- IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Desconocida') . "\n";
    $sql .= "-- ===========================================\n\n";
    
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $sql .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
    $sql .= "SET AUTOCOMMIT = 0;\n";
    $sql .= "START TRANSACTION;\n\n";
    
    foreach ($tablas as $tabla) {
        // Estructura de la tabla
        $result = $conn->query("SHOW CREATE TABLE $tabla");
        $row = $result->fetch_assoc();
        
        $sql .= "\n-- --------------------------------------------------------\n";
        $sql .= "-- Estructura de tabla `$tabla`\n";
        $sql .= "-- --------------------------------------------------------\n\n";
        $sql .= "DROP TABLE IF EXISTS `$tabla`;\n";
        $sql .= $row['Create Table'] . ";\n\n";
        
        // Datos de la tabla
        $result = $conn->query("SELECT * FROM $tabla");
        if ($result->num_rows > 0) {
            $sql .= "-- --------------------------------------------------------\n";
            $sql .= "-- Datos de tabla `$tabla` (" . $result->num_rows . " registros)\n";
            $sql .= "-- --------------------------------------------------------\n\n";
            
            while ($fila = $result->fetch_assoc()) {
                $columnas = array_keys($fila);
                $valores = array_values($fila);
                
                // Escapar valores
                foreach ($valores as &$valor) {
                    if ($valor === null) {
                        $valor = 'NULL';
                    } else {
                        $valor = "'" . $conn->real_escape_string($valor) . "'";
                    }
                }
                
                $sql .= "INSERT INTO `$tabla` (`" . implode('`, `', $columnas) . "`) VALUES (" . implode(", ", $valores) . ");\n";
            }
            $sql .= "\n";
        } else {
            $sql .= "-- La tabla `$tabla` está vacía\n\n";
        }
    }
    
    $sql .= "\nCOMMIT;\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    $sql .= "\n-- ===========================================\n";
    $sql .= "-- FIN DEL RESPALDO\n";
    $sql .= "-- ===========================================\n";
    
    echo $sql;
    exit();
    
} elseif ($accion === 'optimizar') {
    // Registrar actividad: Optimizar tablas
    registrarLog($conn, $usuario_actual, 'Inició optimización de tablas de la base de datos', 'Respaldo');
    
    // Optimizar tablas
    $tablas = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $tablas[] = $row[0];
    }
    
    $optimizados = 0;
    $tablas_optimizadas = [];
    
    foreach ($tablas as $tabla) {
        $conn->query("OPTIMIZE TABLE $tabla");
        $optimizados++;
        $tablas_optimizadas[] = $tabla;
    }
    
    // Registrar resultado de la optimización
    registrarLog($conn, $usuario_actual, "Optimizó $optimizados tablas", 'Respaldo');
    
    $_SESSION['mensaje'] = "Se optimizaron $optimizados tablas correctamente";
    $_SESSION['tipo_mensaje'] = "success";
    header("Location: respaldo.php");
    exit();
    
} elseif ($accion === 'verificar') {
    // Registrar actividad: Verificar estado
    registrarLog($conn, $usuario_actual, 'Verificó el estado de las tablas de la base de datos', 'Respaldo');
    
    // Verificar estado de las tablas
    $tablas = [];
    $result = $conn->query("SHOW TABLE STATUS");
    while ($row = $result->fetch_assoc()) {
        $tablas[] = $row;
    }
    
    $html = "<div class='status-container'>";
    $html .= "<h3>📊 Estado de las Tablas</h3>";
    $html .= "<table class='status-table'>";
    $html .= "<thead><tr><th>Tabla</th><th>Registros</th><th>Tamaño</th><th>Actualización</th><th>Motor</th></tr></thead><tbody>";
    
    $total_registros_verificados = 0;
    $total_tamano = 0;
    
    foreach ($tablas as $tabla) {
        $tamano = round(($tabla['Data_length'] + $tabla['Index_length']) / 1024, 2);
        $unidad = 'KB';
        if ($tamano > 1024) {
            $tamano = round($tamano / 1024, 2);
            $unidad = 'MB';
        }
        
        $total_registros_verificados += $tabla['Rows'];
        $total_tamano += ($tabla['Data_length'] + $tabla['Index_length']);
        
        $html .= "<tr>";
        $html .= "<td><strong>" . htmlspecialchars($tabla['Name']) . "</strong></td>";
        $html .= "<td>" . number_format($tabla['Rows']) . "</td>";
        $html .= "<td>" . $tamano . " " . $unidad . "</td>";
        $html .= "<td>" . ($tabla['Update_time'] ? date('Y-m-d H:i:s', strtotime($tabla['Update_time'])) : 'Nunca') . "</td>";
        $html .= "<td>" . htmlspecialchars($tabla['Engine']) . "</td>";
        $html .= "</tr>";
    }
    
    $total_tamano_mb = round($total_tamano / 1024 / 1024, 2);
    
    $html .= "</tbody></table>";
    $html .= "<div class='status-summary'>";
    $html .= "<p><strong>📈 Resumen:</strong> Total de registros: " . number_format($total_registros_verificados) . " | Tamaño total: " . $total_tamano_mb . " MB</p>";
    $html .= "</div></div>";
    
    $_SESSION['info_tablas'] = $html;
    $_SESSION['tipo_mensaje'] = "info";
    header("Location: respaldo.php");
    exit();
    
} elseif ($accion === 'historial') {
    // Ver historial de logs
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    
    $query = "SELECT id, usuario, accion, modulo, fecha, ip_address 
              FROM logs 
              WHERE modulo = 'Respaldo' 
              ORDER BY fecha DESC 
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    
    $html = "<div class='logs-container'>";
    $html .= "<h3>📋 Historial de Actividades - Módulo Respaldo</h3>";
    $html .= "<div style='margin-bottom: 15px;'>";
    $html .= "<a href='?accion=historial&limit=20' class='btn btn-sm' style='padding: 5px 10px; background: #3498db; color: white; text-decoration: none; border-radius: 3px; margin-right: 5px;'>Últimos 20</a> ";
    $html .= "<a href='?accion=historial&limit=50' class='btn btn-sm' style='padding: 5px 10px; background: #3498db; color: white; text-decoration: none; border-radius: 3px; margin-right: 5px;'>Últimos 50</a> ";
    $html .= "<a href='?accion=historial&limit=100' class='btn btn-sm' style='padding: 5px 10px; background: #3498db; color: white; text-decoration: none; border-radius: 3px;'>Últimos 100</a> ";
    $html .= "</div>";
    $html .= "<table class='logs-table' style='width: 100%; border-collapse: collapse;'>";
    $html .= "<thead><tr style='background: #2c3e50; color: white;'><th style='padding: 10px; text-align: left;'>Fecha</th><th style='padding: 10px; text-align: left;'>Usuario</th><th style='padding: 10px; text-align: left;'>Acción</th><th style='padding: 10px; text-align: left;'>IP</th></tr></thead><tbody>";
    
    if (count($logs) > 0) {
        foreach ($logs as $log) {
            $html .= "<tr>";
            $html .= "<td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . date('Y-m-d H:i:s', strtotime($log['fecha'])) . "</td>";
            $html .= "<td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($log['usuario']) . "</td>";
            $html .= "<td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($log['accion']) . "</td>";
            $html .= "<td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($log['ip_address']) . "</td>";
            $html .= "</tr>";
        }
    } else {
        $html .= "<tr><td colspan='4' style='padding: 20px; text-align: center;'>No hay actividades registradas en el módulo de respaldo</td></tr>";
    }
    
    $html .= "</tbody></table></div>";
    
    $_SESSION['info_tablas'] = $html;
    header("Location: respaldo.php");
    exit();
}

// Obtener información de la base de datos
$db_info = [];
$db_info['nombre'] = $conn->query("SELECT DATABASE() as db")->fetch_assoc()['db'] ?? 'bitacora_ids';
$db_info['version'] = $conn->query("SELECT VERSION() as version")->fetch_assoc()['version'] ?? 'Desconocida';
$db_info['tamano'] = 0;

$tablas_info = $conn->query("SHOW TABLE STATUS");
$total_registros = 0;
while ($row = $tablas_info->fetch_assoc()) {
    $db_info['tamano'] += $row['Data_length'] + $row['Index_length'];
    $total_registros += $row['Rows'];
}
$db_info['tamano_mb'] = round($db_info['tamano'] / 1024 / 1024, 2);
$db_info['num_tablas'] = $conn->query("SHOW TABLES")->num_rows;

// Obtener último respaldo registrado
$ultimo_respaldo = null;
$log_result = $conn->query("SELECT fecha FROM logs WHERE modulo = 'Respaldo' AND accion LIKE '%Exportó%' ORDER BY fecha DESC LIMIT 1");
if ($log_result && $log_result->num_rows > 0) {
    $ultimo_respaldo = $log_result->fetch_assoc()['fecha'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Respaldo de Base de Datos - Bitácora Militar</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #2c3e50 0%, #4a5568 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 30px;
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
            border-bottom: 3px solid #27ae60;
            padding-bottom: 10px;
            font-size: 28px;
        }
        .mensaje {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .mensaje.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .mensaje.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .mensaje.warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .mensaje.info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .info-item { text-align: center; }
        .info-label { font-size: 14px; opacity: 0.9; margin-bottom: 5px; }
        .info-value { font-size: 24px; font-weight: bold; }
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .action-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
            border: 1px solid #e0e0e0;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .action-icon { font-size: 48px; margin-bottom: 15px; }
        .action-title { font-size: 18px; font-weight: 600; color: #2c3e50; margin-bottom: 10px; }
        .action-description { color: #7f8c8d; font-size: 13px; margin-bottom: 20px; }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-primary { background-color: #3498db; color: white; }
        .btn-primary:hover { background-color: #2980b9; transform: translateY(-2px); }
        .btn-success { background-color: #27ae60; color: white; }
        .btn-success:hover { background-color: #219a52; transform: translateY(-2px); }
        .btn-warning { background-color: #f39c12; color: white; }
        .btn-warning:hover { background-color: #e67e22; transform: translateY(-2px); }
        .btn-info { background-color: #17a2b8; color: white; }
        .btn-info:hover { background-color: #138496; transform: translateY(-2px); }
        .btn-secondary { background-color: #95a5a6; color: white; }
        .btn-secondary:hover { background-color: #7f8c8d; transform: translateY(-2px); }
        .status-table, .logs-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 14px; }
        .status-table th, .logs-table th { background-color: #2c3e50; color: white; padding: 12px; text-align: left; }
        .status-table td, .logs-table td { padding: 10px; border-bottom: 1px solid #ecf0f1; }
        .status-table tr:hover, .logs-table tr:hover { background-color: #f5f9ff; }
        .status-container, .logs-container { background-color: white; padding: 20px; border-radius: 8px; margin-top: 10px; }
        .status-container h3, .logs-container h3 { color: #2c3e50; margin-bottom: 15px; border-bottom: 2px solid #27ae60; padding-bottom: 8px; }
        .status-summary { margin-top: 15px; padding: 10px; background-color: #e8f4f8; border-radius: 5px; font-size: 14px; }
        .warning-box { background-color: #fff3cd; border-left: 4px solid #f39c12; padding: 15px; margin-top: 20px; border-radius: 5px; }
        .warning-box ul { margin-top: 10px; margin-left: 20px; }
        .back-link { display: inline-block; margin-top: 20px; padding: 10px 20px; background-color: #95a5a6; color: white; text-decoration: none; border-radius: 5px; transition: all 0.3s; }
        .back-link:hover { background-color: #7f8c8d; transform: translateY(-2px); }
        .ultimo-respaldo { background-color: #e8f4f8; padding: 10px; border-radius: 5px; margin-top: 10px; font-size: 13px; text-align: center; }
        @media (max-width: 768px) {
            .actions-grid { grid-template-columns: 1fr; }
            .status-table, .logs-table { font-size: 12px; }
            .status-table th, .status-table td, .logs-table th, .logs-table td { padding: 8px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>💾 Respaldo de Base de Datos</h1>
        
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="mensaje <?php echo $_SESSION['tipo_mensaje']; ?>">
                <?php 
                    echo $_SESSION['mensaje'];
                    unset($_SESSION['mensaje']);
                    unset($_SESSION['tipo_mensaje']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['info_tablas'])): ?>
            <div class="mensaje info" style="padding: 0; background: transparent; border: none;">
                <?php 
                    echo $_SESSION['info_tablas'];
                    unset($_SESSION['info_tablas']);
                ?>
                <div style="text-align: right; margin-top: 15px;">
                    <a href="respaldo.php" class="btn btn-secondary">Cerrar</a>
                </div>
            </div>
        <?php else: ?>
        
        <div class="info-card">
            <div class="info-item">
                <div class="info-label">Base de Datos</div>
                <div class="info-value"><?php echo htmlspecialchars($db_info['nombre']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Tablas</div>
                <div class="info-value"><?php echo $db_info['num_tablas']; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Registros Totales</div>
                <div class="info-value"><?php echo number_format($total_registros); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Tamaño</div>
                <div class="info-value"><?php echo $db_info['tamano_mb']; ?> MB</div>
            </div>
        </div>
        
        <?php if ($ultimo_respaldo): ?>
        <div class="ultimo-respaldo">
            📅 Último respaldo realizado: <?php echo date('d/m/Y H:i:s', strtotime($ultimo_respaldo)); ?>
        </div>
        <?php endif; ?>
        
        <div class="actions-grid">
            <div class="action-card">
                <div class="action-icon">📥</div>
                <div class="action-title">Realizar Respaldo</div>
                <div class="action-description">Descarga un archivo SQL con toda la estructura y datos de la base de datos.</div>
                <a href="backup_automatico.php?accion=exportar" class="btn btn-primary">📥 Descargar SQL</a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">🔧</div>
                <div class="action-title">Optimizar Tablas</div>
                <div class="action-description">Optimiza las tablas para mejorar el rendimiento y recuperar espacio.</div>
                <a href="respaldo.php?accion=optimizar" class="btn btn-success" onclick="return confirm('¿Estás seguro de optimizar todas las tablas?')">⚡ Optimizar</a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">📊</div>
                <div class="action-title">Verificar Estado</div>
                <div class="action-description">Muestra información detallada del estado de todas las tablas.</div>
                <a href="respaldo.php?accion=verificar" class="btn btn-warning">🔍 Verificar</a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">📋</div>
                <div class="action-title">Historial de Actividades</div>
                <div class="action-description">Consulta el historial de actividades realizadas en el módulo de respaldo.</div>
                <a href="respaldo.php?accion=historial" class="btn btn-info">📜 Ver Historial</a>
            </div>
        </div>
        
        <div class="warning-box">
            <strong>⚠️ Recomendaciones importantes:</strong>
            <ul>
                <li>Realiza respaldos periódicos de la base de datos</li>
                <li>El archivo SQL contiene toda la información de la bitácora</li>
                <li>Para restaurar, importa este archivo usando phpMyAdmin o la línea de comandos</li>
                <li>Mantén los respaldos en un lugar seguro fuera del servidor</li>
                <li>Todas las acciones quedan registradas en el historial de logs</li>
            </ul>
        </div>
        
        <div style="margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 5px; font-size: 13px;">
            <p><strong>📌 Detalles del sistema:</strong></p>
            <p>• Versión de MySQL: <?php echo htmlspecialchars($db_info['version']); ?></p>
            <p>• Fecha del servidor: <?php echo date('Y-m-d H:i:s'); ?></p>
            <p>• Zona horaria: <?php echo date('T'); ?></p>
            <p>• Usuario actual: <?php echo htmlspecialchars($usuario_actual); ?></p>
            <p>• IP: <?php echo $_SERVER['REMOTE_ADDR'] ?? 'Desconocida'; ?></p>
        </div>
        
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" class="back-link">
                <span>↩️</span> Volver a la Bitácora
            </a>
        </div>
    </div>
</body>
</html>