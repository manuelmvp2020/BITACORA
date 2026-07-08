<?php
// imprimir_busqueda.php - Imprime los resultados de una búsqueda
require_once 'config.php';

// Obtener parámetros de búsqueda
$texto = isset($_GET['texto']) ? $_GET['texto'] : '';
$estatus = isset($_GET['estatus']) ? $_GET['estatus'] : '';

// Construir consulta con filtros en TODAS las columnas
$sql = "SELECT * FROM registros_ids WHERE 1=1";
$params = [];
$types = "";

if (!empty($texto)) {
    // Buscar en TODAS las columnas relevantes
    $sql .= " AND (";
    $sql .= "id_registro LIKE ? OR ";
    $sql .= "rango_militar LIKE ? OR ";
    $sql .= "usuario LIKE ? OR ";
    $sql .= "roles LIKE ? OR ";
    $sql .= "estatus LIKE ? OR ";
    $sql .= "designacion LIKE ? OR ";
    $sql .= "fecha_registro LIKE ? OR ";
    $sql .= "configuracion LIKE ? OR ";
    $sql .= "created_at LIKE ? OR ";
    $sql .= "updated_at LIKE ?";
    $sql .= ")";
    
    $param_texto = "%$texto%";
    for ($i = 0; $i < 10; $i++) {
        $params[] = $param_texto;
        $types .= "s";
    }
}

if (!empty($estatus)) {
    $sql .= " AND estatus = ?";
    $params[] = $estatus;
    $types .= "s";
}

$sql .= " ORDER BY fecha_registro DESC";

// Ejecutar consulta
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$resultado = $stmt->get_result();

// Obtener estadísticas de la búsqueda
$total_encontrados = $resultado->num_rows;

// Guardar resultados para mostrar
$registros = [];
while ($row = $resultado->fetch_assoc()) {
    $registros[] = $row;
}

// Contar estatus
$activos = 0; $inactivos = 0; $reservados = 0; $bloqueados = 0;
foreach ($registros as $row) {
    switch($row['estatus']) {
        case 'Activo': $activos++; break;
        case 'Inactivo': $inactivos++; break;
        case 'Reservado': $reservados++; break;
        case 'Bloqueado': $bloqueados++; break;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Resultados de Búsqueda - Bitácora Militar</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            background-color: white;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        h1 {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #000;
            font-size: 24px;
        }
        
        h2 {
            text-align: center;
            font-size: 18px;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        .fecha-impresion {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            font-size: 12px;
            color: #666;
            border-bottom: 1px dashed #ccc;
            padding-bottom: 8px;
        }
        
        .info-busqueda {
            background-color: #f0f8ff;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 13px;
            border-radius: 5px;
        }
        
        .stats-busqueda {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
        }
        
        .stat-badge {
            background-color: #f0f0f0;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 11px;
            table-layout: fixed;
        }
        
        th {
            background-color: #ddd;
            border: 1px solid #000;
            padding: 8px 4px;
            text-align: left;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11px;
        }
        
        td {
            border: 1px solid #000;
            padding: 6px 4px;
            vertical-align: top;
            word-wrap: break-word;
        }
        
        .estatus {
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 3px;
            display: inline-block;
            min-width: 70px;
            text-align: center;
            font-size: 10px;
        }
        
        .estatus.Activo { 
            background-color: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb;
        }
        
        .estatus.Inactivo { 
            background-color: #f8d7da; 
            color: #721c24; 
            border: 1px solid #f5c6cb;
        }
        
        .estatus.Reservado { 
            background-color: #fff3cd; 
            color: #856404; 
            border: 1px solid #ffeeba;
        }
        
        .estatus.Bloqueado { 
            background-color: #e2e3e5; 
            color: #383d41; 
            border: 1px solid #d6d8db;
        }
        
        .rol {
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 3px;
            display: inline-block;
            min-width: 70px;
            text-align: center;
            font-size: 10px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 120px;
        }
        
        .rol.Administrador { background-color: #c0392b; color: white; }
        .rol.Supervisor { background-color: #e67e22; color: white; }
        .rol.Operador { background-color: #2980b9; color: white; }
        .rol.Consultor { background-color: #27ae60; color: white; }
        .rol.Usuario { background-color: #8e44ad; color: white; }
        .rol.Invitado { background-color: #7f8c8d; color: white; }
        .rol.No-asignado { background-color: #f0f0f0; color: #666; border: 1px dashed #ccc; font-style: italic; }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
        
        .btn-print {
            display: block;
            margin: 20px auto;
            padding: 12px 40px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(52, 152, 219, 0.3);
        }
        
        .btn-back {
            display: inline-block;
            padding: 8px 20px;
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
            margin-right: 10px;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(149, 165, 166, 0.3);
        }
        
        @media print {
            body { padding: 0.5cm; }
            .no-print { display: none !important; }
            button, .btn-print, .btn-back { display: none; }
            th { background-color: #eee !important; }
            .estatus, .rol { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; gap: 10px; justify-content: center; margin-bottom: 20px;" class="no-print">
            <button onclick="window.print()" class="btn-print">
                <span>🖨️</span> Imprimir / Guardar PDF
            </button>
            <a href="index.php" class="btn-back">
                <span>↩️</span> Volver
            </a>
        </div>
        
        <h1>📋 BITÁCORA DE REGISTRO DE IDS</h1>
        <h2>RESULTADOS DE BÚSQUEDA</h2>
        
        <div class="fecha-impresion">
            <span>📅 Fecha de impresión: <?php echo date('d/m/Y H:i:s'); ?></span>
            <span>📊 Total encontrados: <strong><?php echo $total_encontrados; ?></strong></span>
        </div>
        
        <!-- Información de la búsqueda -->
        <div class="info-busqueda no-print">
            <strong>🔍 Criterios de búsqueda:</strong><br>
            <?php if (!empty($texto)): ?>
                Texto: "<strong><?php echo htmlspecialchars($texto); ?></strong>"<br>
            <?php endif; ?>
            <?php if (!empty($estatus)): ?>
                Estatus: <strong><?php echo $estatus; ?></strong><br>
            <?php endif; ?>
            
            <div class="stats-busqueda">
                <span class="stat-badge">🟢 Activos: <?php echo $activos; ?></span>
                <span class="stat-badge">🔴 Inactivos: <?php echo $inactivos; ?></span>
                <span class="stat-badge">🟡 Reservados: <?php echo $reservados; ?></span>
                <span class="stat-badge">⚫ Bloqueados: <?php echo $bloqueados; ?></span>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">ID</th>
                    <th style="width: 15%;">RANGO / IP</th>
                    <th style="width: 12%;">USUARIO</th>
                    <th style="width: 12%;">ROL / PERMISOS</th>
                    <th style="width: 8%;">ESTATUS</th>
                    <th style="width: 25%;">DESIGNACIÓN / CONTROL</th>
                    <th style="width: 8%;">FECHA</th>
                    <th style="width: 15%;">CONFIGURACIÓN</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($registros)): ?>
                    <?php foreach ($registros as $row): ?>
                        <tr>
                            <td><strong>#<?php echo $row['id_registro']; ?></strong></td>
                            <td><?php echo htmlspecialchars($row['rango_militar'] ?? 'No especificado'); ?></td>
                            <td><?php echo htmlspecialchars($row['usuario'] ?? 'No especificado'); ?></td>
                            <td>
                                <?php 
                                if (!empty($row['roles'])) {
                                    $rol = $row['roles'];
                                    $clase_rol = '';
                                    $icono_rol = '';
                                    
                                    if (strpos($rol, 'ADMIN') !== false || $rol == 'Administrador') {
                                        $clase_rol = 'Administrador';
                                        $icono_rol = '👑';
                                    } elseif (strpos($rol, 'SUPER') !== false || $rol == 'Supervisor') {
                                        $clase_rol = 'Supervisor';
                                        $icono_rol = '👁️';
                                    } elseif (strpos($rol, 'OPER') !== false || $rol == 'Operador') {
                                        $clase_rol = 'Operador';
                                        $icono_rol = '⚙️';
                                    } elseif (strpos($rol, 'CONSUL') !== false || $rol == 'Consultor') {
                                        $clase_rol = 'Consultor';
                                        $icono_rol = '📊';
                                    } elseif (strpos($rol, 'USER') !== false || $rol == 'Usuario') {
                                        $clase_rol = 'Usuario';
                                        $icono_rol = '👤';
                                    } elseif (strpos($rol, 'INVI') !== false || $rol == 'Invitado') {
                                        $clase_rol = 'Invitado';
                                        $icono_rol = '🔑';
                                    } else {
                                        $rol_hash = crc32($rol) % 6;
                                        switch($rol_hash) {
                                            case 0: $clase_rol = 'Administrador'; $icono_rol = '👑'; break;
                                            case 1: $clase_rol = 'Supervisor'; $icono_rol = '👁️'; break;
                                            case 2: $clase_rol = 'Operador'; $icono_rol = '⚙️'; break;
                                            case 3: $clase_rol = 'Consultor'; $icono_rol = '📊'; break;
                                            case 4: $clase_rol = 'Usuario'; $icono_rol = '👤'; break;
                                            case 5: $clase_rol = 'Invitado'; $icono_rol = '🔑'; break;
                                            default: $clase_rol = 'Invitado'; $icono_rol = '❓';
                                        }
                                    }
                                    
                                    echo '<span class="rol ' . $clase_rol . '">' . $icono_rol . ' ' . htmlspecialchars(mb_strimwidth($rol, 0, 20, "...")) . '</span>';
                                } else {
                                    echo '<span class="rol No-asignado">— No asignado —</span>';
                                }
                                ?>
                            </td>
                            <td><span class="estatus <?php echo $row['estatus']; ?>"><?php echo $row['estatus']; ?></span></td>
                            <td><?php echo nl2br(htmlspecialchars($row['designacion'] ?? '')); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($row['fecha_registro'])); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($row['configuracion'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 50px;">
                            <span style="font-size: 48px;">📭</span>
                            <br>
                            <strong>No se encontraron resultados</strong>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="footer">
            <p>📄 Documento generado por Sistema de Bitácora de IDs - FARD</p>
            <p>Total de registros encontrados: <?php echo $total_encontrados; ?></p>
            <p>🔒 Documento confidencial - Uso exclusivo militar</p>
        </div>
    </div>
</body>
</html>