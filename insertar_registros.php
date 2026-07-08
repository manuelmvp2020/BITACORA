<?php
// insertar_registros.php - Insertar registros de prueba en la bitácora
require_once 'config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Insertar Registros de Prueba</title>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden; }
        .header { background: linear-gradient(135deg, #2c3e50 0%, #1a2639 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { font-size: 28px; margin-bottom: 10px; }
        .content { padding: 30px; }
        .success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .info { background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .btn { display: inline-block; padding: 12px 24px; margin: 10px 5px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; transform: translateY(-2px); }
        .btn-success { background: #27ae60; color: white; }
        .btn-success:hover { background: #229954; transform: translateY(-2px); }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #34495e; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ecf0f1; }
        tr:hover { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1><i class='fas fa-database'></i> Insertar Registros de Prueba</h1>
            <p>Agregando datos de ejemplo a la bitácora</p>
        </div>
        <div class='content'>";

// Verificar si ya hay registros
$check = $conn->query("SELECT COUNT(*) as total FROM registros_ids");
$total_existente = $check->fetch_assoc()['total'];

if ($total_existente > 0) {
    echo "<div class='info'>
            <i class='fas fa-info-circle'></i> 
            Ya existen <strong>$total_existente</strong> registros en la base de datos.
            <br><br>
            <a href='index.php' class='btn btn-primary'><i class='fas fa-arrow-left'></i> Volver al Dashboard</a>
            <a href='?forzar=1' class='btn btn-success'><i class='fas fa-plus'></i> Agregar más registros</a>
          </div>";
    
    if (!isset($_GET['forzar'])) {
        echo "</div></div></body></html>";
        $conn->close();
        exit();
    }
}

// Datos de prueba
$registros_prueba = [
    [
        'rango_militar' => 'Capitán',
        'usuario' => 'jperez',
        'roles' => 'Operador',
        'estatus' => 'Activo',
        'designacion' => 'Comandante de Compañía Alpha - Responsable de operaciones tácticas',
        'configuracion' => 'Configuración Estándar'
    ],
    [
        'rango_militar' => 'Teniente',
        'usuario' => 'mrodriguez',
        'roles' => 'Supervisor',
        'estatus' => 'Activo',
        'designacion' => 'Oficial de Operaciones Especiales - Encargado de misiones críticas',
        'configuracion' => 'Configuración Avanzada'
    ],
    [
        'rango_militar' => 'Mayor',
        'usuario' => 'rgonzalez',
        'roles' => 'Administrador',
        'estatus' => 'Activo',
        'designacion' => 'Jefe de Estado Mayor - Coordinación de unidades militares',
        'configuracion' => 'Configuración Completa'
    ],
    [
        'rango_militar' => 'Sargento',
        'usuario' => 'lmartinez',
        'roles' => 'Operador',
        'estatus' => 'Reservado',
        'designacion' => 'Especialista en Comunicaciones - Redes y sistemas de comunicación',
        'configuracion' => 'Configuración Básica'
    ],
    [
        'rango_militar' => 'Coronel',
        'usuario' => 'cfernandez',
        'roles' => 'Supervisor',
        'estatus' => 'Inactivo',
        'designacion' => 'Director de Logística - Gestión de recursos y suministros',
        'configuracion' => 'Configuración Estándar'
    ],
    [
        'rango_militar' => 'Subteniente',
        'usuario' => 'atorres',
        'roles' => 'Consultor',
        'estatus' => 'Activo',
        'designacion' => 'Analista de Inteligencia - Procesamiento de información estratégica',
        'configuracion' => 'Configuración Especial'
    ],
    [
        'rango_militar' => 'Cabo',
        'usuario' => 'ehernandez',
        'roles' => 'Operador',
        'estatus' => 'Activo',
        'designacion' => 'Auxiliar Administrativo - Gestión de documentos y archivos',
        'configuracion' => 'Configuración Básica'
    ],
    [
        'rango_militar' => 'Teniente Coronel',
        'usuario' => 'ncastro',
        'roles' => 'Administrador',
        'estatus' => 'Bloqueado',
        'designacion' => 'Jefe de Seguridad - Control de acceso y seguridad interna',
        'configuracion' => 'Configuración Restringida'
    ],
    [
        'rango_militar' => 'Soldado',
        'usuario' => 'aalvarez',
        'roles' => 'Usuario',
        'estatus' => 'Inactivo',
        'designacion' => 'Personal de Apoyo - Asistencia administrativa general',
        'configuracion' => 'Configuración Básica'
    ],
    [
        'rango_militar' => 'Capitán',
        'usuario' => 'lramirez',
        'roles' => 'Supervisor',
        'estatus' => 'Activo',
        'designacion' => 'Instructor de Campo - Entrenamiento y capacitación de personal',
        'configuracion' => 'Configuración Estándar'
    ],
    [
        'rango_militar' => 'General',
        'usuario' => 'mfernandez',
        'roles' => 'Administrador',
        'estatus' => 'Activo',
        'designacion' => 'Comandante en Jefe - Dirección estratégica de operaciones',
        'configuracion' => 'Configuración Premium'
    ],
    [
        'rango_militar' => 'Primer Teniente',
        'usuario' => 'dsanchez',
        'roles' => 'Operador',
        'estatus' => 'Reservado',
        'designacion' => 'Oficial de Logística - Planificación de suministros',
        'configuracion' => 'Configuración Estándar'
    ]
];

echo "<h2><i class='fas fa-plus-circle'></i> Insertando registros...</h2>";

$insertados = 0;
$errores = 0;

$sql = "INSERT INTO registros_ids (rango_militar, usuario, roles, estatus, designacion, configuracion, fecha_registro) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())";

foreach ($registros_prueba as $registro) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", 
        $registro['rango_militar'],
        $registro['usuario'],
        $registro['roles'],
        $registro['estatus'],
        $registro['designacion'],
        $registro['configuracion']
    );
    
    if ($stmt->execute()) {
        $insertados++;
    } else {
        $errores++;
        echo "<div style='color: red;'>Error: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

echo "<div class='success'>";
echo "<i class='fas fa-check-circle'></i> <strong>Resultado:</strong><br>";
echo "✅ Registros insertados: <strong>$insertados</strong><br>";
if ($errores > 0) {
    echo "❌ Errores: <strong>$errores</strong><br>";
}
echo "</div>";

// Mostrar estadísticas actualizadas
$total = $conn->query("SELECT COUNT(*) as total FROM registros_ids")->fetch_assoc()['total'];
$activos = $conn->query("SELECT COUNT(*) as total FROM registros_ids WHERE estatus='Activo'")->fetch_assoc()['total'];
$usuarios_unicos = $conn->query("SELECT COUNT(DISTINCT usuario) as total FROM registros_ids WHERE usuario IS NOT NULL AND usuario != ''")->fetch_assoc()['total'];

echo "<h2><i class='fas fa-chart-bar'></i> Estadísticas actuales</h2>";
echo "<table>";
echo "<tr><th>Métrica</th><th>Valor</th></tr>";
echo "<tr><td>Total registros</td><td><strong>$total</strong></td></tr>";
echo "<tr><td>Registros Activos</td><td><strong>$activos</strong></td></tr>";
echo "<tr><td>Usuarios únicos</td><td><strong>$usuarios_unicos</strong></td></tr>";
echo "</table>";

// Mostrar últimos registros insertados
echo "<h2><i class='fas fa-list'></i> Últimos registros insertados</h2>";
$ultimos = $conn->query("SELECT id_registro, rango_militar, usuario, roles, estatus, fecha_registro FROM registros_ids ORDER BY id_registro DESC LIMIT 5");
if ($ultimos && $ultimos->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Rango</th><th>Usuario</th><th>Rol</th><th>Estatus</th><th>Fecha</th></tr>";
    while ($row = $ultimos->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id_registro']}</td>";
        echo "<td>{$row['rango_militar']}</td>";
        echo "<td>{$row['usuario']}</td>";
        echo "<td>{$row['roles']}</td>";
        echo "<td>{$row['estatus']}</td>";
        echo "<td>" . date('d/m/Y H:i', strtotime($row['fecha_registro'])) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<div style='text-align: center; margin-top: 30px;'>";
echo "<a href='index.php' class='btn btn-primary'><i class='fas fa-home'></i> Ir al Dashboard</a>";
echo "<a href='index.php' class='btn btn-success'><i class='fas fa-chart-line'></i> Ver Registros</a>";
echo "</div>";

echo "</div></div></body></html>";

$conn->close();
?>