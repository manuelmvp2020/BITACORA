<?php
require 'config.php';
session_start();

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Verificar permisos (opcional: solo para administradores)
// if ($_SESSION['rol'] !== 'administrador') {
//     header("Location: index.php");
//     exit();
// }

// Obtener filtros
$filtro_usuario = $_GET['usuario'] ?? '';
$filtro_fecha = $_GET['fecha'] ?? '';
$limit = 50; // Límite de registros por página

// Construir consulta con filtros
$sql = "
    SELECT 
        la.id,
        la.fecha_acceso,
        la.ip,
        la.user_agent,
        u.usuario,
        u.nombre as nombre_usuario,
        u.rol
    FROM logs_acceso la
    INNER JOIN usuarios u ON la.usuario_id = u.id
    WHERE 1=1
";

$params = [];
$types = "";

if (!empty($filtro_usuario)) {
    $sql .= " AND (u.usuario LIKE ? OR u.nombre LIKE ?)";
    $params[] = "%$filtro_usuario%";
    $params[] = "%$filtro_usuario%";
    $types .= "ss";
}

if (!empty($filtro_fecha)) {
    $sql .= " AND DATE(la.fecha_acceso) = ?";
    $params[] = $filtro_fecha;
    $types .= "s";
}

$sql .= " ORDER BY la.fecha_acceso DESC LIMIT ?";
$params[] = $limit;
$types .= "i";

// Preparar y ejecutar consulta
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$resultado = $stmt->get_result();
$logs = $resultado->fetch_all(MYSQLI_ASSOC);

// Obtener estadísticas básicas
$sql_stats = "
    SELECT 
        COUNT(*) as total_accesos,
        COUNT(DISTINCT usuario_id) as usuarios_unicos,
        DATE(MAX(fecha_acceso)) as ultimo_acceso
    FROM logs_acceso
";
$stats_result = $conn->query($sql_stats);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>📊 Logs de Acceso - Sistema de Agua</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-card {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .filter-card {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
        }
        .user-agent {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: help;
        }
        .ip-address {
            font-family: monospace;
            background: #f8f9fa;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 0.9em;
        }
        .badge-role {
            font-size: 0.8em;
            padding: 4px 8px;
        }
        .table th {
            background-color: #007bff;
            color: white;
            border: none;
        }
        .table td {
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-droplet me-2"></i>Sistema de Agua
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-house me-1"></i>Inicio
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="logs_acceso.php">
                            <i class="bi bi-clock-history me-1"></i>Logs de Acceso
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="bi bi-box-arrow-right me-1"></i>Salir
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-door-open"></i>
                    </div>
                    <h4><?= number_format($stats['total_accesos']) ?></h4>
                    <p class="mb-0">Total de Accesos</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card" style="background: linear-gradient(135deg, #28a745, #1e7e34);">
                    <div class="stat-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <h4><?= $stats['usuarios_unicos'] ?></h4>
                    <p class="mb-0">Usuarios Únicos</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card" style="background: linear-gradient(135deg, #6c757d, #495057);">
                    <div class="stat-icon">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <h4><?= $stats['ultimo_acceso'] ?: 'N/A' ?></h4>
                    <p class="mb-0">Último Acceso</p>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card filter-card mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3">
                    <i class="bi bi-funnel me-2"></i>Filtrar Logs
                </h5>
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Usuario o Nombre</label>
                        <input type="text" name="usuario" class="form-control" 
                               value="<?= htmlspecialchars($filtro_usuario) ?>" 
                               placeholder="Buscar por usuario o nombre...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="fecha" class="form-control" 
                               value="<?= htmlspecialchars($filtro_fecha) ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="d-grid gap-2 d-md-flex">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-1"></i>Filtrar
                            </button>
                            <a href="logs_acceso.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-clockwise me-1"></i>Limpiar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de Logs -->
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4>
                    <i class="bi bi-list-check me-2"></i>Historial de Accesos
                </h4>
                <span class="badge bg-info">Mostrando últimos <?= $limit ?> registros</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover" id="logsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha y Hora</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>IP</th>
                            <th>Dispositivo/Navegador</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <i class="bi bi-database-exclamation display-4 text-muted mb-3"></i>
                                    <p class="text-muted">No se encontraron registros de acceso</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="fw-bold">#<?= $log['id'] ?></td>
                                    <td>
                                        <i class="bi bi-clock me-1"></i>
                                        <?= date('d/m/Y H:i:s', strtotime($log['fecha_acceso'])) ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($log['usuario']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($log['nombre_usuario']) ?></small>
                                    </td>
                                    <td>
                                        <?php 
                                        $role_class = [
                            'administrador' => 'bg-danger',
                            'supervisor' => 'bg-warning',
                            'usuario' => 'bg-primary',
                            'lector' => 'bg-info'
                        ];
                                        $role_class = $role_class[$log['rol']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?= $role_class ?> badge-role">
                                            <?= $log['rol'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="ip-address"><?= $log['ip'] ?></span>
                                    </td>
                                    <td>
                                        <span class="user-agent" title="<?= htmlspecialchars($log['user_agent']) ?>">
                                            <?= htmlspecialchars(substr($log['user_agent'], 0, 50)) ?>...
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-3 text-muted small">
                <i class="bi bi-info-circle me-1"></i>
                Mostrando <?= count($logs) ?> registros de acceso al sistema
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Inicializar DataTable
            $('#logsTable').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                order: [[0, 'desc']],
                pageLength: 25,
                responsive: true
            });

            // Tooltip para user-agent
            $('.user-agent').tooltip({
                placement: 'top',
                trigger: 'hover'
            });
        });
    </script>
</body>
</html>