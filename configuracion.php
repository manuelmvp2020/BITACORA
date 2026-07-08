<?php
// configuracion.php - Página de configuración del sistema
require_once 'config.php';

// Procesar guardado de configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'] ?? '';
    
    if ($tipo === 'general') {
        // Guardar configuración general
        $nombre_sistema = $_POST['nombre_sistema'] ?? 'Bitácora de Registro';
        $items_por_pagina = (int)($_POST['items_por_pagina'] ?? 25);
        $formato_fecha = $_POST['formato_fecha'] ?? 'd/m/Y';
        $zona_horaria = $_POST['zona_horaria'] ?? 'America/Mexico_City';
        
        // Aquí guardarías en una tabla de configuración
        $_SESSION['mensaje'] = "Configuración general guardada correctamente";
        $_SESSION['tipo_mensaje'] = "success";
        
    } elseif ($tipo === 'estatus') {
        // Guardar/configurar estatus
        $nuevo_estatus = $_POST['nuevo_estatus'] ?? '';
        if ($nuevo_estatus) {
            $_SESSION['mensaje'] = "Estatus '{$nuevo_estatus}' agregado correctamente";
            $_SESSION['tipo_mensaje'] = "success";
        }
        
    } elseif ($tipo === 'roles') {
        // Guardar/configurar roles
        $nuevo_rol = $_POST['nuevo_rol'] ?? '';
        if ($nuevo_rol) {
            $_SESSION['mensaje'] = "Rol '{$nuevo_rol}' agregado correctamente";
            $_SESSION['tipo_mensaje'] = "success";
        }
        
    } elseif ($tipo === 'respaldo') {
        // Configuración de respaldos
        $respaldo_automatico = $_POST['respaldo_automatico'] ?? '0';
        $frecuencia_respaldo = $_POST['frecuencia_respaldo'] ?? 'diario';
        
        $_SESSION['mensaje'] = "Configuración de respaldos guardada correctamente";
        $_SESSION['tipo_mensaje'] = "success";
    }
    
    header("Location: configuracion.php");
    exit();
}

// Obtener estatus existentes para mostrar
$estatus_existentes = $conn->query("SELECT DISTINCT estatus FROM registros_ids WHERE estatus IS NOT NULL AND estatus != '' ORDER BY estatus");

// Obtener roles existentes
$roles_existentes = $conn->query("SELECT DISTINCT roles FROM registros_ids WHERE roles IS NOT NULL AND roles != '' ORDER BY roles");

// Obtener configuraciones existentes
$configuraciones_existentes = $conn->query("SELECT DISTINCT configuracion FROM registros_ids WHERE configuracion IS NOT NULL AND configuracion != '' ORDER BY configuracion LIMIT 20");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del Sistema</title>
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }
        
        /* ===== MAIN CONTENT ===== */
        .main-content {
            padding: 20px;
            min-height: 100vh;
        }
        
        .top-bar {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .page-title {
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .page-title i {
            color: #3498db;
            margin-right: 10px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        /* ===== CONFIGURACIÓN STYLES ===== */
        .config-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .mensaje {
            padding: 12px 20px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .mensaje.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .mensaje.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .config-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .config-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .config-card.full-width {
            grid-column: 1 / -1;
        }
        
        .config-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ecf0f1;
        }
        
        .config-header i {
            font-size: 28px;
            color: #3498db;
            background: #ebf5fb;
            padding: 12px;
            border-radius: 10px;
        }
        
        .config-header h3 {
            color: #2c3e50;
            font-size: 20px;
        }
        
        .config-header p {
            color: #7f8c8d;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group label i {
            margin-right: 8px;
            color: #3498db;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
        }
        
        .form-control[type="color"] {
            height: 50px;
            padding: 5px;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .form-check input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .form-check label {
            margin-bottom: 0;
            cursor: pointer;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        
        .btn-warning:hover {
            background: #e67e22;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .items-list {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .item-badge {
            display: inline-block;
            background: white;
            padding: 8px 15px;
            border-radius: 20px;
            margin: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            font-size: 13px;
        }
        
        .item-badge i {
            color: #3498db;
            margin-right: 5px;
            cursor: pointer;
        }
        
        .item-badge i:hover {
            color: #e74c3c;
        }
        
        .info-box {
            background: #ebf5fb;
            border-left: 4px solid #3498db;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .info-box i {
            color: #3498db;
            margin-right: 10px;
        }
        
        .info-box strong {
            color: #2c3e50;
        }
        
        .info-box p {
            color: #34495e;
            margin-top: 5px;
            font-size: 13px;
        }
        
        .tab-container {
            margin-top: 20px;
        }
        
        .tabs {
            display: flex;
            gap: 5px;
            border-bottom: 2px solid #ecf0f1;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .tab {
            padding: 12px 25px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 15px;
            color: #7f8c8d;
            position: relative;
            font-weight: 500;
        }
        
        .tab.active {
            color: #3498db;
        }
        
        .tab.active:after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 3px;
            background: #3498db;
            border-radius: 3px 3px 0 0;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .danger-zone {
            border: 2px solid #e74c3c;
            border-radius: 10px;
            padding: 20px;
            background: #fdf3f2;
        }
        
        .danger-zone h4 {
            color: #e74c3c;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 10px;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .config-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                text-align: center;
            }
        }
        
        @media print {
            .no-print, .top-bar, .tabs, .btn {
                display: none;
            }
            
            .main-content {
                padding: 0;
            }
            
            .config-card {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <!-- ===== MAIN CONTENT ===== -->
    <div class="main-content">
        <!-- Top bar -->
        <div class="top-bar">
            <div class="page-title">
                <i class="fas fa-cog"></i> Configuración del Sistema
            </div>
            <div class="user-info">
                <div class="user-avatar">A</div>
            </div>
        </div>

        <div class="config-container">
            <!-- Mensajes de sesión -->
            <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="mensaje <?php echo $_SESSION['tipo_mensaje']; ?>">
                    <?php 
                        echo $_SESSION['mensaje'];
                        unset($_SESSION['mensaje']);
                        unset($_SESSION['tipo_mensaje']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Tabs de configuración -->
            <div class="tab-container">
                <div class="tabs">
                    <button class="tab active" onclick="showTab('general', this)">
                        <i class="fas fa-sliders-h"></i> General
                    </button>
                    <button class="tab" onclick="showTab('estatus', this)">
                        <i class="fas fa-tags"></i> Estatus
                    </button>
                    <button class="tab" onclick="showTab('roles', this)">
                        <i class="fas fa-user-tag"></i> Roles
                    </button>
                    <button class="tab" onclick="showTab('apariencia', this)">
                        <i class="fas fa-paint-brush"></i> Apariencia
                    </button>
                    <button class="tab" onclick="showTab('respaldo', this)">
                        <i class="fas fa-database"></i> Respaldo
                    </button>
                    <button class="tab" onclick="showTab('avanzado', this)">
                        <i class="fas fa-exclamation-triangle"></i> Avanzado
                    </button>
                </div>

                <!-- Tab: Configuración General -->
                <div id="tab-general" class="tab-content active">
                    <div class="config-grid">
                        <div class="config-card">
                            <div class="config-header">
                                <i class="fas fa-sliders-h"></i>
                                <div>
                                    <h3>Configuración General</h3>
                                    <p>Ajustes básicos del sistema</p>
                                </div>
                            </div>
                            
                            <form method="POST">
                                <input type="hidden" name="tipo" value="general">
                                
                                <div class="form-group">
                                    <label><i class="fas fa-font"></i> Nombre del Sistema</label>
                                    <input type="text" class="form-control" name="nombre_sistema" value="Bitácora de Registro" placeholder="Ej: Sistema de Control Militar">
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label><i class="fas fa-list-ol"></i> Items por Página</label>
                                        <select class="form-control" name="items_por_pagina">
                                            <option value="10">10</option>
                                            <option value="25" selected>25</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                            <option value="250">250</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label><i class="far fa-calendar-alt"></i> Formato de Fecha</label>
                                        <select class="form-control" name="formato_fecha">
                                            <option value="d/m/Y">31/12/2024</option>
                                            <option value="Y-m-d">2024-12-31</option>
                                            <option value="m/d/Y">12/31/2024</option>
                                            <option value="d M Y">31 Dic 2024</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-globe"></i> Zona Horaria</label>
                                    <select class="form-control" name="zona_horaria">
                                        <option value="America/Mexico_City">Ciudad de México</option>
                                        <option value="America/Argentina/Buenos_Aires">Buenos Aires</option>
                                        <option value="America/Bogota">Bogotá</option>
                                        <option value="America/Lima">Lima</option>
                                        <option value="America/Santiago">Santiago</option>
                                        <option value="America/Caracas">Caracas</option>
                                    </select>
                                </div>
                                
                                <div class="form-check">
                                    <input type="checkbox" id="notificaciones" checked>
                                    <label for="notificaciones">Mostrar notificaciones del sistema</label>
                                </div>
                                
                                <div class="form-check">
                                    <input type="checkbox" id="confirmaciones" checked>
                                    <label for="confirmaciones">Solicitar confirmación antes de eliminar</label>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Guardar Cambios
                                </button>
                            </form>
                        </div>

                        <div class="config-card">
                            <div class="config-header">
                                <i class="fas fa-info-circle"></i>
                                <div>
                                    <h3>Información del Sistema</h3>
                                    <p>Detalles técnicos y versiones</p>
                                </div>
                            </div>
                            
                            <div class="info-box">
                                <i class="fas fa-code-branch"></i>
                                <strong>Versión actual:</strong> v1.0.0
                            </div>
                            
                            <div class="info-box">
                                <i class="fas fa-database"></i>
                                <strong>Base de datos:</strong> MySQL
                            </div>
                            
                            <div class="info-box">
                                <i class="fas fa-calendar-check"></i>
                                <strong>Última actualización:</strong> <?php echo date('d/m/Y'); ?>
                            </div>
                            
                            <div class="info-box">
                                <i class="fas fa-hdd"></i>
                                <strong>Espacio utilizado:</strong> 2.3 MB
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Configuración de Estatus -->
                <div id="tab-estatus" class="tab-content">
                    <div class="config-card">
                        <div class="config-header">
                            <i class="fas fa-tags"></i>
                            <div>
                                <h3>Gestión de Estatus</h3>
                                <p>Administra los estatus disponibles en el sistema</p>
                            </div>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="tipo" value="estatus">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-plus-circle"></i> Nuevo Estatus</label>
                                    <input type="text" class="form-control" name="nuevo_estatus" placeholder="Ej: Pendiente, Revisado, Aprobado">
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-palette"></i> Color</label>
                                    <input type="color" class="form-control" name="color_estatus" value="#3498db">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-plus"></i> Agregar Estatus
                            </button>
                        </form>
                        
                        <h4 style="margin: 25px 0 15px; color: #2c3e50;">Estatus Existentes</h4>
                        <div class="items-list">
                            <?php while ($estatus = $estatus_existentes->fetch_assoc()): ?>
                                <span class="item-badge">
                                    <i class="fas fa-tag"></i> 
                                    <?php echo htmlspecialchars($estatus['estatus']); ?>
                                </span>
                            <?php endwhile; ?>
                        </div>
                        
                        <div class="info-box" style="margin-top: 20px;">
                            <i class="fas fa-lightbulb"></i>
                            <strong>Sugerencia:</strong> Los estatus se utilizan para clasificar el estado de cada registro.
                        </div>
                    </div>
                </div>

                <!-- Tab: Configuración de Roles -->
                <div id="tab-roles" class="tab-content">
                    <div class="config-card">
                        <div class="config-header">
                            <i class="fas fa-user-tag"></i>
                            <div>
                                <h3>Gestión de Roles</h3>
                                <p>Administra los roles de usuario en el sistema</p>
                            </div>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="tipo" value="roles">
                            
                            <div class="form-group">
                                <label><i class="fas fa-plus-circle"></i> Nuevo Rol</label>
                                <input type="text" class="form-control" name="nuevo_rol" placeholder="Ej: Comandante, Oficial, Soldado">
                            </div>
                            
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-plus"></i> Agregar Rol
                            </button>
                        </form>
                        
                        <h4 style="margin: 25px 0 15px; color: #2c3e50;">Roles Existentes</h4>
                        <div class="items-list">
                            <?php while ($rol = $roles_existentes->fetch_assoc()): ?>
                                <span class="item-badge">
                                    <i class="fas fa-user"></i> 
                                    <?php echo htmlspecialchars($rol['roles']); ?>
                                </span>
                            <?php endwhile; ?>
                        </div>
                        
                        <div class="form-group" style="margin-top: 20px;">
                            <label><i class="fas fa-crown"></i> Rol por defecto para nuevos registros</label>
                            <select class="form-control">
                                <option>Seleccionar rol por defecto</option>
                                <?php 
                                $roles_existentes->data_seek(0);
                                while ($rol = $roles_existentes->fetch_assoc()): 
                                ?>
                                    <option><?php echo htmlspecialchars($rol['roles']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Tab: Apariencia -->
                <div id="tab-apariencia" class="tab-content">
                    <div class="config-grid">
                        <div class="config-card">
                            <div class="config-header">
                                <i class="fas fa-paint-brush"></i>
                                <div>
                                    <h3>Tema y Colores</h3>
                                    <p>Personaliza la apariencia del sistema</p>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-palette"></i> Color Primario</label>
                                <input type="color" class="form-control" value="#3498db">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-palette"></i> Color Secundario</label>
                                <input type="color" class="form-control" value="#2c3e50">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-moon"></i> Modo Oscuro</label>
                                <select class="form-control">
                                    <option>Automático</option>
                                    <option>Claro</option>
                                    <option>Oscuro</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-text-height"></i> Tamaño de Fuente</label>
                                <select class="form-control">
                                    <option>Pequeño</option>
                                    <option selected>Mediano</option>
                                    <option>Grande</option>
                                </select>
                            </div>
                            
                            <button class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Preferencias
                            </button>
                        </div>
                        
                        <div class="config-card">
                            <div class="config-header">
                                <i class="fas fa-columns"></i>
                                <div>
                                    <h3>Vista por Defecto</h3>
                                    <p>Configuración de la vista principal</p>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-table"></i> Vista de Tabla</label>
                                <select class="form-control">
                                    <option>Compacta</option>
                                    <option selected>Normal</option>
                                    <option>Amplia</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-columns"></i> Columnas visibles</label>
                                <div class="items-list">
                                    <div class="form-check">
                                        <input type="checkbox" checked> ID
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" checked> Rango Militar
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" checked> Usuario
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" checked> Rol
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" checked> Estatus
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" checked> Designación
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" checked> Fecha
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" checked> Configuración
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Respaldo -->
                <div id="tab-respaldo" class="tab-content">
                    <div class="config-card">
                        <div class="config-header">
                            <i class="fas fa-database"></i>
                            <div>
                                <h3>Configuración de Respaldos</h3>
                                <p>Programa y gestiona los respaldos automáticos</p>
                            </div>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="tipo" value="respaldo">
                            
                            <div class="form-check">
                                <input type="checkbox" id="respaldo_auto" name="respaldo_automatico" value="1">
                                <label for="respaldo_auto">Activar respaldos automáticos</label>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-clock"></i> Frecuencia de respaldo</label>
                                <select class="form-control" name="frecuencia_respaldo">
                                    <option value="diario">Diario</option>
                                    <option value="semanal">Semanal</option>
                                    <option value="mensual">Mensual</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-history"></i> Mantener respaldos de los últimos</label>
                                <select class="form-control">
                                    <option>7 días</option>
                                    <option>30 días</option>
                                    <option>90 días</option>
                                    <option>Siempre</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-folder"></i> Carpeta de destino</label>
                                <input type="text" class="form-control" value="/backups/">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Configuración
                            </button>
                        </form>
                        
                        <div style="margin-top: 30px;">
                            <h4 style="margin-bottom: 15px;">Últimos Respaldos</h4>
                            <div class="items-list">
                                <div class="stat-row" style="justify-content: space-between; border-bottom: 1px solid #ecf0f1;">
                                    <span><i class="fas fa-file-archive"></i> respaldo_20240315.sql</span>
                                    <span>15/03/2024</span>
                                    <span>2.3 MB</span>
                                </div>
                                <div class="stat-row" style="justify-content: space-between; border-bottom: 1px solid #ecf0f1;">
                                    <span><i class="fas fa-file-archive"></i> respaldo_20240314.sql</span>
                                    <span>14/03/2024</span>
                                    <span>2.1 MB</span>
                                </div>
                                <div class="stat-row" style="justify-content: space-between;">
                                    <span><i class="fas fa-file-archive"></i> respaldo_20240313.sql</span>
                                    <span>13/03/2024</span>
                                    <span>2.2 MB</span>
                                </div>
                            </div>
                            
                            <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                                <button class="btn btn-success">
                                    <i class="fas fa-download"></i> Descargar último respaldo
                                </button>
                                <button class="btn btn-primary">
                                    <i class="fas fa-sync-alt"></i> Crear respaldo ahora
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Avanzado -->
                <div id="tab-avanzado" class="tab-content">
                    <div class="config-card danger-zone">
                        <h4><i class="fas fa-exclamation-triangle"></i> Zona de Peligro</h4>
                        <p style="color: #7f8c8d; margin-bottom: 20px;">Estas acciones son irreversibles. Ten cuidado.</p>
                        
                        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                            <button class="btn btn-warning">
                                <i class="fas fa-eraser"></i> Limpiar caché
                            </button>
                            <button class="btn btn-danger" onclick="return confirm('¿Estás seguro de reiniciar el sistema?')">
                                <i class="fas fa-sync-alt"></i> Reiniciar sistema
                            </button>
                            <button class="btn btn-danger" onclick="return confirm('¿Estás ABSOLUTAMENTE seguro? Esta acción eliminará TODOS los registros.')">
                                <i class="fas fa-trash-alt"></i> Vaciar base de datos
                            </button>
                        </div>
                    </div>
                    
                    <div class="config-card" style="margin-top: 20px;">
                        <h4 style="margin-bottom: 15px;">Registro de Actividad</h4>
                        <div class="items-list" style="max-height: 300px;">
                            <div class="stat-row" style="justify-content: space-between; border-bottom: 1px solid #ecf0f1;">
                                <span><i class="fas fa-info-circle" style="color: #3498db;"></i> Configuración general actualizada</span>
                                <span style="color: #7f8c8d;">Hace 5 minutos</span>
                            </div>
                            <div class="stat-row" style="justify-content: space-between; border-bottom: 1px solid #ecf0f1;">
                                <span><i class="fas fa-plus-circle" style="color: #27ae60;"></i> Nuevo rol: Comandante</span>
                                <span style="color: #7f8c8d;">Hace 2 horas</span>
                            </div>
                            <div class="stat-row" style="justify-content: space-between;">
                                <span><i class="fas fa-database" style="color: #9b59b6;"></i> Respaldo automático completado</span>
                                <span style="color: #7f8c8d;">Ayer</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Función para cambiar de tab
        function showTab(tabName, element) {
            // Ocultar todos los contenidos de tab
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Desactivar todos los tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Mostrar el tab seleccionado
            document.getElementById(`tab-${tabName}`).classList.add('active');
            
            // Activar el botón del tab
            element.classList.add('active');
        }

        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                alert('Para guardar la configuración, use los botones Guardar en cada sección.');
            }
        });
    </script>
</body>
</html>