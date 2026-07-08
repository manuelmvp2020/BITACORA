<?php
// imprimir.php - Vista para impresión de todos los registros o uno específico
require_once 'config.php';

$id = $_GET['id'] ?? 0;

if ($id > 0) {
    // Imprimir un solo registro
    $sql = "SELECT * FROM registros_ids WHERE id_registro = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
} else {
    // Imprimir todos los registros
    $sql = "SELECT * FROM registros_ids ORDER BY fecha_registro DESC";
    $resultado = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Bitácora de IDs</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', 'Courier New', monospace;
            background-color: white;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        h1 {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 10px;
            border-bottom: 2px solid #000;
            font-size: 24px;
        }
        
        .fecha-impresion {
            text-align: right;
            margin-bottom: 20px;
            font-size: 12px;
            color: #666;
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dashed #ccc;
            padding-bottom: 8px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 11px;
            table-layout: fixed;
        }
        
        th {
            background-color: #2c3e50;
            color: white;
            border: 1px solid #1a252f;
            padding: 10px 6px;
            text-align: left;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        td {
            border: 1px solid #ddd;
            padding: 8px 6px;
            vertical-align: top;
            word-wrap: break-word;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .estatus {
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
            min-width: 80px;
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
        
        .estatus.Pendiente { 
            background-color: #fff3cd; 
            color: #856404; 
            border: 1px solid #ffeeba;
        }
        
        .estatus.Bloqueado { 
            background-color: #e2e3e5; 
            color: #383d41; 
            border: 1px solid #d6d8db;
        }
        
        /* Estilos para roles en impresión */
        .rol {
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
            min-width: 80px;
            text-align: center;
            font-size: 10px;
        }
        
        .rol.Administrador { 
            background-color: #c0392b; 
            color: white; 
        }
        
        .rol.Supervisor { 
            background-color: #e67e22; 
            color: white; 
        }
        
        .rol.Operador { 
            background-color: #2980b9; 
            color: white; 
        }
        
        .rol.Consultor { 
            background-color: #27ae60; 
            color: white; 
        }
        
        .rol.Usuario { 
            background-color: #8e44ad; 
            color: white; 
        }
        
        .rol.Invitado { 
            background-color: #7f8c8d; 
            color: white; 
        }
        
        .rol.No-asignado { 
            background-color: #ecf0f1; 
            color: #7f8c8d; 
            border: 1px dashed #bdc3c7;
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
        
        .stats-print {
            margin: 20px 0;
            padding: 12px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .stat-item {
            flex: 1;
            min-width: 130px;
        }
        
        .stat-label {
            font-weight: bold;
            display: inline-block;
            width: 85px;
        }
        
        .numero-correlativo {
            text-align: center;
            font-weight: bold;
            background-color: #f8f9fa;
            font-size: 12px;
        }
        
        .id-real {
            font-size: 10px;
            color: #7f8c8d;
        }
        
        @media print {
            body {
                padding: 0.5cm;
            }
            
            .no-print {
                display: none !important;
            }
            
            button {
                display: none;
            }
            
            th {
                background-color: #2c3e50 !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .estatus, .rol {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .numero-correlativo {
                background-color: #f0f0f0 !important;
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
            
            tfoot {
                display: table-footer-group;
            }
        }
        
        .btn-print {
            display: block;
            margin: 20px auto;
            padding: 12px 40px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            border-radius: 8px;
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
        
        /* Ajustes de columnas */
        .col-num { width: 4%; text-align: center; }
        .col-id { width: 6%; }
        .col-rango { width: 14%; }
        .col-usuario { width: 12%; }
        .col-rol { width: 10%; }
        .col-estatus { width: 8%; }
        .col-designacion { width: 26%; }
        .col-fecha { width: 8%; }
        .col-config { width: 12%; }
        
        .icono {
            margin-right: 5px;
        }
        
        /* Mejoras para visualización en pantalla */
        @media screen {
            table {
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            
            .fecha-impresion {
                background-color: #f8f9fa;
                padding: 10px 15px;
                border-radius: 8px;
            }
        }
        
        /* Estilo para badges de usuario */
        .usuario-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 11px;
        }
        
        .text-truncate {
            word-break: break-word;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <div class="container">
        <button onclick="window.print()" class="btn-print no-print">
            <span class="icono">🖨️</span> Imprimir / Guardar PDF
        </button>
        
        <h1>📋 BITÁCORA DE REGISTRO DE IDS</h1>
        
        <div class="fecha-impresion">
            <span>
                <span class="icono">📅</span> Fecha de impresión: <?php echo date('d/m/Y H:i:s'); ?>
            </span>
            <span>
                <?php if ($id > 0): ?>
                    <span class="icono">🔍</span> Registro específico ID: <strong>#<?php echo $id; ?></strong>
                <?php else: ?>
                    <span class="icono">📊</span> Reporte general - Todos los registros
                <?php endif; ?>
            </span>
        </div>
        
        <!-- Estadísticas rápidas -->
        <?php if ($id == 0 && $resultado && $resultado->num_rows > 0): ?>
        <div class="stats-print no-print">
            <?php
            // Calcular estadísticas para mostrar
            $total = $resultado->num_rows;
            $activos = 0; $inactivos = 0; $pendientes = 0; $bloqueados = 0;
            $con_rol = 0; $sin_rol = 0;
            $usuarios_unicos = [];
            
            // Volver al inicio para no afectar el while principal
            $resultado->data_seek(0);
            while ($row = $resultado->fetch_assoc()) {
                switch($row['estatus']) {
                    case 'Activo': $activos++; break;
                    case 'Inactivo': $inactivos++; break;
                    case 'Pendiente': $pendientes++; break;
                    case 'Bloqueado': $bloqueados++; break;
                }
                
                if (!empty($row['roles'])) {
                    $con_rol++;
                } else {
                    $sin_rol++;
                }
                
                if (!empty($row['usuario'])) {
                    $usuarios_unicos[$row['usuario']] = true;
                }
            }
            // Volver al inicio para el while principal
            $resultado->data_seek(0);
            $total_usuarios = count($usuarios_unicos);
            ?>
            <div class="stat-item"><span class="stat-label">📊 Total registros:</span> <?php echo $total; ?></div>
            <div class="stat-item"><span class="stat-label" style="color: #27ae60;">🟢 Activos:</span> <?php echo $activos; ?></div>
            <div class="stat-item"><span class="stat-label" style="color: #e74c3c;">🔴 Inactivos:</span> <?php echo $inactivos; ?></div>
            <div class="stat-item"><span class="stat-label" style="color: #f39c12;">🟡 Pendientes:</span> <?php echo $pendientes; ?></div>
            <div class="stat-item"><span class="stat-label" style="color: #7f8c8d;">⚫ Bloqueados:</span> <?php echo $bloqueados; ?></div>
            <div class="stat-item"><span class="stat-label">👥 Usuarios únicos:</span> <?php echo $total_usuarios; ?></div>
            <div class="stat-item"><span class="stat-label">🎭 Con rol asignado:</span> <?php echo $con_rol; ?></div>
        </div>
        <?php endif; ?>
        
        <table>
            <thead>
                <tr>
                    <th class="col-num">#</th>
                    <th class="col-id">ID REAL</th>
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
                <?php if ($resultado && $resultado->num_rows > 0): ?>
                    <?php $contador = 1; ?>
                    <?php while ($row = $resultado->fetch_assoc()): ?>
                        <tr>
                            <td class="numero-correlativo"><?php echo $contador++; ?></td>
                            <td><strong>#<?php echo htmlspecialchars($row['id_registro'] ?? 'N/A'); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['rango_militar'] ?? $row['rango_ip'] ?? 'No especificado'); ?></td>
                            <td>
                                <?php if (!empty($row['usuario'])): ?>
                                    <span class="usuario-badge" style="background-color: <?php 
                                        $colores = ['#9b59b6', '#e91e63', '#ba68c8', '#ff80ab', '#aa00ff'];
                                        $hash = abs(crc32($row['usuario'])) % count($colores);
                                        echo $colores[$hash] . '20'; 
                                    ?>; color: <?php 
                                        echo $colores[$hash]; 
                                    ?>;">
                                        <?php echo htmlspecialchars($row['usuario']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #999;">— No especificado —</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                if (isset($row['roles']) && !empty($row['roles'])) {
                                    $rol = $row['roles'];
                                    $clase_rol = '';
                                    $icono_rol = '';
                                    
                                    if (stripos($rol, 'ADMIN') !== false) {
                                        $clase_rol = 'Administrador';
                                        $icono_rol = '👑';
                                    } elseif (stripos($rol, 'SUPER') !== false) {
                                        $clase_rol = 'Supervisor';
                                        $icono_rol = '👁️';
                                    } elseif (stripos($rol, 'OPER') !== false) {
                                        $clase_rol = 'Operador';
                                        $icono_rol = '⚙️';
                                    } elseif (stripos($rol, 'CONSUL') !== false) {
                                        $clase_rol = 'Consultor';
                                        $icono_rol = '📊';
                                    } elseif (stripos($rol, 'USUARIO') !== false) {
                                        $clase_rol = 'Usuario';
                                        $icono_rol = '👤';
                                    } elseif (stripos($rol, 'INVIT') !== false) {
                                        $clase_rol = 'Invitado';
                                        $icono_rol = '🔑';
                                    } else {
                                        $clase_rol = 'No-asignado';
                                        $icono_rol = '📌';
                                    }
                                    echo '<span class="rol ' . $clase_rol . '">' . $icono_rol . ' ' . htmlspecialchars($rol) . '</span>';
                                } else {
                                    echo '<span class="rol No-asignado">— No asignado —</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                $estatus = $row['estatus'] ?? 'Activo';
                                ?>
                                <span class="estatus <?php echo htmlspecialchars($estatus); ?>">
                                    <?php echo htmlspecialchars($estatus); ?>
                                </span>
                            </td>
                            <td class="text-truncate"><?php echo nl2br(htmlspecialchars($row['designacion'] ?? 'Sin designación')); ?></td>
                            <td><?php echo isset($row['fecha_registro']) ? date('d/m/Y', strtotime($row['fecha_registro'])) : 'N/A'; ?></td>
                            <td class="text-truncate"><?php echo nl2br(htmlspecialchars($row['configuracion'] ?? '')); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 50px; font-size: 14px;">
                            <span style="font-size: 48px; display: block; margin-bottom: 20px;">📭</span>
                            <strong>No hay registros para mostrar</strong>
                            <br>
                            <span style="color: #666;">La bitácora está vacía</span>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="footer">
            <p>📄 Documento generado por Sistema de Bitácora de IDs - FARD</p>
            <p>Total de registros: <?php echo $resultado ? $resultado->num_rows : 0; ?></p>
            <?php if ($id > 0): ?>
                <p>ID del registro: <?php echo $id; ?></p>
            <?php endif; ?>
            <p>🔒 Documento confidencial - Uso exclusivo militar</p>
            <p>🔐 Sistema de Control de Acceso - <?php echo date('Y'); ?></p>
        </div>
    </div>
    
    <script>
        // Registrar acción de impresión en los logs
        <?php if ($id > 0): ?>
            registrarAccion('imprimió_registro_individual', { id_registro: <?php echo $id; ?> }, 'imprimir.php');
        <?php else: ?>
            registrarAccion('imprimió_todos_los_registros', { total: <?php echo $resultado ? $resultado->num_rows : 0; ?> }, 'imprimir.php');
        <?php endif; ?>
        
        function registrarAccion(accion, detalles, modulo) {
            fetch('log_accion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    accion: accion,
                    detalles: JSON.stringify(detalles),
                    modulo: modulo
                })
            }).catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>