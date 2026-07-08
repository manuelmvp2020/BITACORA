<?php
// ver_usuario.php - Ver detalle completo de un usuario específico
require_once 'config.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$usuario_nombre = $_GET['usuario'] ?? '';
if (!$usuario_nombre) {
    header("Location: usuarios_unicos.php");
    exit();
}

// Obtener registros del usuario
$registros = obtenerRegistrosPorUsuario($conn, $usuario_nombre, 100);
$total_registros = count($registros);

// Estadísticas del usuario
$stats_usuario = [
    'total' => $total_registros,
    'activos' => 0,
    'inactivos' => 0,
    'pendiente' => 0,
    'roles' => [],
    'rangos' => []
];

foreach ($registros as $reg) {
    if ($reg['estatus'] == 'Activo') $stats_usuario['activos']++;
    elseif ($reg['estatus'] == 'Inactivo') $stats_usuario['inactivos']++;
    else $stats_usuario['pendiente']++;
    
    if ($reg['roles']) $stats_usuario['roles'][$reg['roles']] = ($stats_usuario['roles'][$reg['roles']] ?? 0) + 1;
    if ($reg['rango_militar']) $stats_usuario['rangos'][$reg['rango_militar']] = ($stats_usuario['rangos'][$reg['rango_militar']] ?? 0) + 1;
}

// Registrar vista
registrarLog($conn, $_SESSION['usuario'], 
    "Vio detalles del usuario: $usuario_nombre", 
    "ver_usuario.php", 
    obtenerIP(),
    ['usuario_visto' => $usuario_nombre, 'total_registros' => $total_registros]
);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Usuario - <?php echo htmlspecialchars($usuario_nombre); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
        .stat { background: white; padding: 15px; border-radius: 8px; text-align: center; }
        .stat .number { font-size: 28px; font-weight: bold; }
        table { width: 100%; background: white; border-radius: 8px; overflow: hidden; }
        th { background: #2c3e50; color: white; padding: 12px; text-align: left; }
        td { padding: 12px; border-bottom: 1px solid #ecf0f1; }
        .btn-back { padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; display: inline-block; margin-bottom: 15px; }
        @media (max-width: 768px) { .stats { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>
    <div class="container">
        <a href="usuarios_unicos.php" class="btn-back"><i class="fas fa-arrow-left"></i> Volver a Usuarios</a>
        
        <div class="header">
            <h1><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($usuario_nombre); ?></h1>
            <p>Total de registros: <?php echo $total_registros; ?></p>
        </div>
        
        <div class="stats">
            <div class="stat">
                <div class="number" style="color: #27ae60;"><?php echo $stats_usuario['activos']; ?></div>
                <div>Registros Activos</div>
            </div>
            <div class="stat">
                <div class="number" style="color: #e74c3c;"><?php echo $stats_usuario['inactivos']; ?></div>
                <div>Registros Inactivos</div>
            </div>
            <div class="stat">
                <div class="number" style="color: #f39c12;"><?php echo $stats_usuario['pendiente']; ?></div>
                <div>Registros Pendientes</div>
            </div>
            <div class="stat">
                <div class="number" style="color: #9b59b6;"><?php echo count($stats_usuario['roles']); ?></div>
                <div>Roles Distintos</div>
            </div>
        </div>
        
        <h3>📋 Registros del usuario</h3>
        <table>
            <thead>
                <tr><th>#</th><th>Rango Militar</th><th>Rol</th><th>Estatus</th><th>Designación</th><th>Fecha</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                <?php $contador = 1; ?>
                <?php foreach ($registros as $reg): ?>
                <tr>
                    <td><?php echo $contador++; ?></td>
                    <td><?php echo htmlspecialchars($reg['rango_militar'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($reg['roles'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($reg['estatus'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars(substr($reg['designacion'] ?? '', 0, 50)); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($reg['fecha_registro'])); ?></td>
                    <td>
                        <a href="editar.php?id=<?php echo $reg['id_registro']; ?>">✏️</a>
                        <a href="imprimir.php?id=<?php echo $reg['id_registro']; ?>" target="_blank">🖨️</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>