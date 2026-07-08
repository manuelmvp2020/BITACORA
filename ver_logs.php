<?php
// ver_logs.php - Visualización mejorada de logs
require_once 'config.php';

// Obtener logs con filtros
$where = "1=1";
$params = [];
$types = "";

if (isset($_GET['usuario']) && $_GET['usuario']) {
    $where .= " AND usuario LIKE ?";
    $params[] = "%" . $_GET['usuario'] . "%";
    $types .= "s";
}

if (isset($_GET['modulo']) && $_GET['modulo']) {
    $where .= " AND modulo = ?";
    $params[] = $_GET['modulo'];
    $types .= "s";
}

if (isset($_GET['fecha_desde']) && $_GET['fecha_desde']) {
    $where .= " AND fecha >= ?";
    $params[] = $_GET['fecha_desde'];
    $types .= "s";
}

if (isset($_GET['fecha_hasta']) && $_GET['fecha_hasta']) {
    $where .= " AND fecha <= ?";
    $params[] = $_GET['fecha_hasta'] . " 23:59:59";
    $types .= "s";
}

$sql = "SELECT * FROM logs WHERE $where ORDER BY fecha DESC LIMIT 1000";
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$resultado = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Actividades - Logs</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; margin: 0; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        .filtros { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
        .filtro-group { flex: 1; min-width: 150px; }
        .filtro-group label { display: block; font-size: 12px; color: #7f8c8d; margin-bottom: 5px; }
        .filtro-group input, .filtro-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { padding: 8px 15px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #2980b9; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #34495e; color: white; padding: 12px; text-align: left; font-size: 13px; }
        td { padding: 12px; border-bottom: 1px solid #ecf0f1; font-size: 13px; }
        tr:hover { background: #f5f9ff; }
        .badge-modulo { background: #e74c3c; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; display: inline-block; }
        .detalles { max-width: 300px; overflow-x: auto; font-family: monospace; font-size: 11px; background: #f8f9fa; padding: 5px; border-radius: 4px; }
        .estadisticas { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-history"></i> Registro de Actividades</h1>
        
        <div class="filtros">
            <div class="filtro-group">
                <label>Usuario</label>
                <input type="text" id="filtroUsuario" placeholder="Buscar por usuario...">
            </div>
            <div class="filtro-group">
                <label>Módulo</label>
                <select id="filtroModulo">
                    <option value="">Todos</option>
                    <option value="index.php">Dashboard</option>
                    <option value="crear.php">Nuevo Registro</option>
                    <option value="editar.php">Editar</option>
                    <option value="logs.php">Logs</option>
                </select>
            </div>
            <div class="filtro-group">
                <label>Fecha desde</label>
                <input type="date" id="fechaDesde">
            </div>
            <div class="filtro-group">
                <label>Fecha hasta</label>
                <input type="date" id="fechaHasta">
            </div>
            <div class="filtro-group">
                <button onclick="aplicarFiltros()"><i class="fas fa-search"></i> Buscar</button>
                <button onclick="limpiarFiltros()"><i class="fas fa-eraser"></i> Limpiar</button>
            </div>
        </div>
        
        <div class="estadisticas">
            <div class="stat-card">
                <div><i class="fas fa-chart-line"></i> Total Actividades</div>
                <div style="font-size: 28px; font-weight: bold;"><?php echo $resultado->num_rows; ?></div>
            </div>
        </div>
        
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha/Hora</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Módulo</th>
                        <th>IP</th>
                        <th>Detalles</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($log = $resultado->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $log['id']; ?></td>
                        <td><?php echo date('d/m/Y H:i:s', strtotime($log['fecha'])); ?></td>
                        <td><i class="fas fa-user"></i> <?php echo htmlspecialchars($log['usuario']); ?></td>
                        <td><?php echo htmlspecialchars($log['accion']); ?></td>
                        <td><span class="badge-modulo"><?php echo htmlspecialchars($log['modulo']); ?></span></td>
                        <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                        <td class="detalles">
                            <?php if ($log['detalles']): ?>
                                <details>
                                    <summary><i class="fas fa-info-circle"></i> Ver detalles</summary>
                                    <pre style="margin: 5px 0; font-size: 10px;"><?php 
                                        $detalles = json_decode($log['detalles'], true);
                                        if ($detalles) {
                                            print_r($detalles);
                                        } else {
                                            echo htmlspecialchars($log['detalles']);
                                        }
                                    ?></pre>
                                </details>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        function aplicarFiltros() {
            const params = new URLSearchParams();
            const usuario = document.getElementById('filtroUsuario').value;
            const modulo = document.getElementById('filtroModulo').value;
            const fechaDesde = document.getElementById('fechaDesde').value;
            const fechaHasta = document.getElementById('fechaHasta').value;
            
            if (usuario) params.append('usuario', usuario);
            if (modulo) params.append('modulo', modulo);
            if (fechaDesde) params.append('fecha_desde', fechaDesde);
            if (fechaHasta) params.append('fecha_hasta', fechaHasta);
            
            window.location.href = 'ver_logs.php?' + params.toString();
        }
        
        function limpiarFiltros() {
            window.location.href = 'ver_logs.php';
        }
        
        // Cargar valores de los filtros desde URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('usuario')) document.getElementById('filtroUsuario').value = urlParams.get('usuario');
        if (urlParams.get('modulo')) document.getElementById('filtroModulo').value = urlParams.get('modulo');
        if (urlParams.get('fecha_desde')) document.getElementById('fechaDesde').value = urlParams.get('fecha_desde');
        if (urlParams.get('fecha_hasta')) document.getElementById('fechaHasta').value = urlParams.get('fecha_hasta');
    </script>
</body>
</html>