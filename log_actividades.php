<?php
// log_actividades.php - Visualización de logs del sistema
require_once 'config.php';

// Verificar autenticación
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$usuario = $_SESSION['usuario'];
$filtro_modulo = $_GET['modulo'] ?? '';
$filtro_usuario = $_GET['usuario'] ?? '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

// Construir consulta con filtros
$sql = "SELECT id, usuario, accion, modulo, fecha, ip_address FROM logs WHERE 1=1";
$params = [];
$types = "";

if ($filtro_modulo) {
    $sql .= " AND modulo = ?";
    $params[] = $filtro_modulo;
    $types .= "s";
}
if ($filtro_usuario) {
    $sql .= " AND usuario = ?";
    $params[] = $filtro_usuario;
    $types .= "s";
}

$sql .= " ORDER BY fecha DESC LIMIT ?";
$params[] = $limit;
$types .= "i";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener módulos únicos para el filtro
$modulos = $conn->query("SELECT DISTINCT modulo FROM logs ORDER BY modulo")->fetch_all(MYSQLI_ASSOC);
$usuarios = $conn->query("SELECT DISTINCT usuario FROM logs ORDER BY usuario")->fetch_all(MYSQLI_ASSOC);

include 'header.php';
?>

<div class="container mt-4">
    <h1>📋 Registro de Actividades</h1>
    
    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label>Módulo</label>
                    <select name="modulo" class="form-control">
                        <option value="">Todos</option>
                        <?php foreach ($modulos as $m): ?>
                        <option value="<?= htmlspecialchars($m['modulo']) ?>" <?= $filtro_modulo == $m['modulo'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['modulo']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label>Usuario</label>
                    <select name="usuario" class="form-control">
                        <option value="">Todos</option>
                        <?php foreach ($usuarios as $u): ?>
                        <option value="<?= htmlspecialchars($u['usuario']) ?>" <?= $filtro_usuario == $u['usuario'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['usuario']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label>Límite</label>
                    <select name="limit" class="form-control">
                        <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                        <option value="200" <?= $limit == 200 ? 'selected' : '' ?>>200</option>
                        <option value="500" <?= $limit == 500 ? 'selected' : '' ?>>500</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="log_actividades.php" class="btn btn-secondary w-100">Limpiar</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Tabla de logs -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Módulo</th>
                            <th>Acción</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($logs) == 0): ?>
                        <tr>
                            <td colspan="5" class="text-center">No hay registros</td>
                        </tr>
                        <?php else: foreach ($logs as $log): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i:s', strtotime($log['fecha'])) ?></td>
                            <td><?= htmlspecialchars($log['usuario']) ?></td>
                            <td><span class="badge bg-info"><?= htmlspecialchars($log['modulo']) ?></span></td>
                            <td><?= htmlspecialchars($log['accion']) ?></td>
                            <td><?= htmlspecialchars($log['ip_address']) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>