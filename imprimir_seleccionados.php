<?php
// imprimir_seleccionados.php - Imprime los registros seleccionados
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seleccionados'])) {
    $ids = $_POST['seleccionados'];
    
    // Limpiar y validar IDs
    $ids = array_filter(array_map('intval', $ids));
    
    if (empty($ids)) {
        $_SESSION['mensaje'] = "No se seleccionaron registros válidos para imprimir";
        $_SESSION['tipo_mensaje'] = "warning";
        header("Location: index.php");
        exit();
    }
    
    // Crear placeholders para la consulta IN
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $tipos = str_repeat('i', count($ids));
    
    $sql = "SELECT * FROM registros_ids WHERE id_registro IN ($placeholders) ORDER BY fecha_registro DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($tipos, ...$ids);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    // Obtener estadísticas de los seleccionados
    $total_seleccionados = $resultado->num_rows;
    $activos = 0; $inactivos = 0; $reservados = 0; $bloqueados = 0;
    $con_rol = 0; $sin_rol = 0;
    
    // Guardar resultados en array para poder contar y luego mostrar
    $registros = [];
    while ($row = $resultado->fetch_assoc()) {
        $registros[] = $row;
        
        switch($row['estatus']) {
            case 'Activo': $activos++; break;
            case 'Inactivo': $inactivos++; break;
            case 'Reservado': $reservados++; break;
            case 'Bloqueado': $bloqueados++; break;
        }
        
        if (!empty($row['roles'])) {
            $con_rol++;
        } else {
            $sin_rol++;
        }
    }
} else {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Seleccionados - Bitácora IDs</title>
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
        
        .info-seleccion {
            background-color: #f0f8ff;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 13px;
            border-radius: 5px;
        }
        
        .stats-seleccion {
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
        
        /* Estilos para roles */
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
        
        .rol.Administrador { 
            background-color: #c0392b; 
            color: white; 
            border: 1px solid #a93226;
        }
        
        .rol.Supervisor { 
            background-color: #e67e22; 
            color: white; 
            border: 1px solid #ca6f1e;
        }
        
        .rol.Operador { 
            background-color: #2980b9; 
            color: white; 
            border: 1px solid #2471a3;
        }
        
        .rol.Consultor { 
            background-color: #27ae60; 
            color: white; 
            border: 1px solid #229954;
        }
        
        .rol.Usuario { 
            background-color: #8e44ad; 
            color: white; 
            border: 1px solid #7d3c98;
        }
        
        .rol.Invitado { 
            background-color: #7f8c8d; 
            color: white; 
            border: 1px solid #707b7c;
        }
        
        .rol.No-asignado { 
            background-color: #f0f0f0; 
            color: #666; 
            border: 1px dashed #ccc;
            font-style: italic;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
        
        /* Anchos de columna */
        .col-id { width: 5%; }
        .col-rango { width: 15%; }
        .col-usuario { width: 12%; }
        .col-rol { width: 12%; }
        .col-estatus { width: 8%; }
        .col-designacion { width: 25%; }
        .col-fecha { width: 8%; }
        .col-config { width: 15%; }
        
        .icono {
            margin-right: 5px;
        }
        
        /* Botones */
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
        
        .btn-print:active {
            transform: translateY(0);
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
        
        /* IDs list */
        .ids-list {
            font-family: monospace;
            background-color: #f8f9fa;
            padding: 5px 10px;
            border-radius: 4px;
            display: inline-block;
        }
        
        @media print {
            body {
                padding: 0.5cm;
            }
            
            .no-print {
                display: none !important;
            }
            
            button, .btn-print, .btn-back {
                display: none;
            }
            
            th {
                background-color: #eee !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .estatus, .rol {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            table {
                page-break-inside: auto;
            }
            
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            
            thead {
                display: table-header-group;
            }
            
            .info-seleccion {
                border: 1px solid #ccc;
                background-color: #f9f9f9;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; gap: 10px; justify-content: center; margin-bottom: 20px;" class="no-print">
            <button onclick="window.print()" class="btn-print">
                <span class="icono">🖨️</span> Imprimir / Guardar PDF
            </button>
            <a href="index.php" class="btn-back">
                <span class="icono">↩️</span> Volver
            </a>
        </div>
        
        <h1>📋 BITÁCORA DE REGISTRO DE IDS</h1>
        <h2>REGISTROS SELECCIONADOS</h2>
        
        <div class="fecha-impresion">
            <span>
                <span class="icono">📅</span> Fecha de impresión: <?php echo date('d/m/Y H:i:s'); ?>
            </span>
            <span>
                <span class="icono">📊</span> Total seleccionados: <strong><?php echo $total_seleccionados; ?></strong>
            </span>
        </div>
        
        <!-- Información de la selección -->
        <div class="info-seleccion no-print">
            <strong>📋 Registros seleccionados:</strong> 
            <span class="ids-list"><?php echo implode(', ', $ids); ?></span>
            <div class="stats-seleccion">
                <span class="stat-badge">🟢 Activos: <?php echo $activos; ?></span>
                <span class="stat-badge">🔴 Inactivos: <?php echo $inactivos; ?></span>
                <span class="stat-badge">🟡 Reservados: <?php echo $reservados; ?></span>
                <span class="stat-badge">⚫ Bloqueados: <?php echo $bloqueados; ?></span>
                <span class="stat-badge">👥 Con rol: <?php echo $con_rol; ?></span>
                <span class="stat-badge">👤 Sin rol: <?php echo $sin_rol; ?></span>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th class="col-id">ID</th>
                    <th class="col-rango">RANGO / IP</th>
                    <th class="col-usuario">USUARIO</th>
                    <th class="col-rol">ROL / PERMISOS</th>
                    <th class="col-estatus">ESTATUS</th>
                    <th class="col-designacion">DESIGNACIÓN / CONTROL</th>
                    <th class="col-fecha">FECHA</th>
                    <th class="col-config">CONFIGURACIÓN</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($registros)): ?>
                    <?php foreach ($registros as $row): ?>
                        <tr>
                            <td><strong>#<?php echo $row['id_registro'] ?? ''; ?></strong></td>
                            <td><?php echo htmlspecialchars($row['rango_militar'] ?? $row['rango_ip'] ?? 'No especificado'); ?></td>
                            <td><?php echo htmlspecialchars($row['usuario'] ?? 'No especificado'); ?></td>
                            <td>
                                <?php 
                                if (isset($row['roles']) && !empty($row['roles'])) {
                                    $rol = $row['roles'];
                                    $clase_rol = '';
                                    $icono_rol = '';
                                    
                                    // Determinar clase según el rol
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
                                        // Para roles personalizados, usar hash para determinar color
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
                                    
                                    echo '<span class="rol ' . $clase_rol . '" title="' . htmlspecialchars($rol) . '">' . $icono_rol . ' ' . htmlspecialchars(mb_strimwidth($rol, 0, 20, "...")) . '</span>';
                                } else {
                                    echo '<span class="rol No-asignado">— No asignado —</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                $estatus = $row['estatus'] ?? 'Activo';
                                ?>
                                <span class="estatus <?php echo $estatus; ?>">
                                    <?php echo $estatus; ?>
                                </span>
                            </td>
                            <td><?php echo nl2br(htmlspecialchars($row['designacion'] ?? 'Sin designación')); ?></td>
                            <td><?php echo isset($row['fecha_registro']) ? date('d/m/Y', strtotime($row['fecha_registro'])) : 'N/A'; ?></td>
                            <td><?php echo nl2br(htmlspecialchars($row['configuracion'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 50px; font-size: 14px;">
                            <span style="font-size: 48px; display: block; margin-bottom: 20px;">📭</span>
                            <strong>No hay registros para mostrar</strong>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="footer">
            <p>📄 Documento generado por Sistema de Bitácora de IDs - FARD</p>
            <p>Total de registros seleccionados: <?php echo $total_seleccionados; ?></p>
            <p>IDs incluidos: <?php echo implode(', ', $ids); ?></p>
            <p>🔒 Documento confidencial - Uso exclusivo militar</p>
        </div>
    </div>
    
    <script>
        // Auto-imprimir al cargar (opcional - descomentar si se desea)
        // window.onload = function() {
        //     window.print();
        // }
    </script>
</body>
</html>