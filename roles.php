<?php
// roles.php - Gestión y edición de la columna "roles" de registros_ids
require_once 'config.php';

// Verificar autenticación
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Verificar que sea administrador
if (!esAdministrador($conn)) {
    registrarLog($conn, $_SESSION['usuario'], 
        "Intento no autorizado de acceder a roles.php", 
        "roles.php", 
        obtenerIP(),
        ['usuario' => $_SESSION['usuario']]
    );
    $_SESSION['mensaje'] = "No tienes permisos para acceder a esta sección";
    $_SESSION['tipo_mensaje'] = "error";
    header("Location: index.php");
    exit();
}

// ============================================
// PROCESAR ACCIONES
// ============================================
$accion = $_GET['accion'] ?? $_POST['accion'] ?? 'listar';
$mensaje = '';
$tipo_mensaje = '';

// ============================================
// EDITAR ROL DE UN REGISTRO
// ============================================
if ($accion === 'editar_rol' && isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_registro = intval($_GET['id']);
    $nuevo_rol = trim($_POST['rol'] ?? '');
    
    if (empty($nuevo_rol)) {
        $mensaje = "El rol no puede estar vacío";
        $tipo_mensaje = "error";
    } else {
        $stmt = $conn->prepare("UPDATE registros_ids SET roles = ?, updated_at = NOW() WHERE id_registro = ?");
        $stmt->bind_param("si", $nuevo_rol, $id_registro);
        
        if ($stmt->execute()) {
            $mensaje = "Rol actualizado correctamente";
            $tipo_mensaje = "success";
            
            registrarLog($conn, $_SESSION['usuario'], 
                "Editó rol del registro ID: $id_registro", 
                "roles.php", 
                obtenerIP(),
                ['id_registro' => $id_registro, 'nuevo_rol' => $nuevo_rol]
            );
        } else {
            $mensaje = "Error al actualizar rol: " . $conn->error;
            $tipo_mensaje = "error";
        }
        $stmt->close();
    }
}

// ============================================
// ELIMINAR ROL DE UN REGISTRO (dejar vacío)
// ============================================
if ($accion === 'eliminar_rol' && isset($_GET['id'])) {
    $id_registro = intval($_GET['id']);
    
    $stmt = $conn->prepare("UPDATE registros_ids SET roles = NULL, updated_at = NOW() WHERE id_registro = ?");
    $stmt->bind_param("i", $id_registro);
    
    if ($stmt->execute()) {
        $mensaje = "Rol eliminado correctamente";
        $tipo_mensaje = "success";
        
        registrarLog($conn, $_SESSION['usuario'], 
            "Eliminó rol del registro ID: $id_registro", 
            "roles.php", 
            obtenerIP(),
            ['id_registro' => $id_registro]
        );
    } else {
        $mensaje = "Error al eliminar rol: " . $conn->error;
        $tipo_mensaje = "error";
    }
    $stmt->close();
    
    header("Location: roles.php");
    exit();
}

// ============================================
// EDITAR MÚLTIPLES ROLES (actualización masiva)
// ============================================
if ($accion === 'editar_multiple' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = $_POST['ids'] ?? [];
    $nuevo_rol = trim($_POST['rol_multiple'] ?? '');
    
    if (empty($ids)) {
        $mensaje = "No se seleccionaron registros";
        $tipo_mensaje = "error";
    } elseif (empty($nuevo_rol)) {
        $mensaje = "El rol no puede estar vacío";
        $tipo_mensaje = "error";
    } else {
        $ids_array = explode(',', $ids[0]);
        $placeholders = implode(',', array_fill(0, count($ids_array), '?'));
        $stmt = $conn->prepare("UPDATE registros_ids SET roles = ?, updated_at = NOW() WHERE id_registro IN ($placeholders)");
        
        $types = "s" . str_repeat("i", count($ids_array));
        $params = array_merge([$nuevo_rol], $ids_array);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $mensaje = count($ids_array) . " registro(s) actualizado(s) correctamente";
            $tipo_mensaje = "success";
            
            registrarLog($conn, $_SESSION['usuario'], 
                "Editó múltiples roles", 
                "roles.php", 
                obtenerIP(),
                ['ids' => $ids_array, 'nuevo_rol' => $nuevo_rol]
            );
        } else {
            $mensaje = "Error al actualizar roles: " . $conn->error;
            $tipo_mensaje = "error";
        }
        $stmt->close();
    }
}

// ============================================
// FILTROS
// ============================================
$filtro_rol = $_GET['filtro_rol'] ?? '';
$filtro_estatus = $_GET['filtro_estatus'] ?? '';
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$por_pagina = isset($_GET['por_pagina']) ? intval($_GET['por_pagina']) : 20;
$offset = ($pagina - 1) * $por_pagina;

// ============================================
// OBTENER LISTA DE ROLES ÚNICOS DESDE LA COLUMNA "roles" DE registros_ids
// ============================================
$roles_disponibles = [];
$roles_query = $conn->query("SELECT DISTINCT roles FROM registros_ids WHERE roles IS NOT NULL AND roles != '' AND TRIM(roles) != '' ORDER BY roles");
if ($roles_query) {
    while ($row = $roles_query->fetch_assoc()) {
        $roles_disponibles[] = trim($row['roles']);
    }
}

// ============================================
// CONSULTA PRINCIPAL
// ============================================

$sql_registros = "SELECT * FROM registros_ids WHERE 1=1";
$params = [];
$types = "";

if (!empty($filtro_rol)) {
    $sql_registros .= " AND roles = ?";
    $params[] = $filtro_rol;
    $types .= "s";
}

if (!empty($filtro_estatus)) {
    $sql_registros .= " AND estatus = ?";
    $params[] = $filtro_estatus;
    $types .= "s";
}

$sql_registros .= " ORDER BY id_registro DESC LIMIT ? OFFSET ?";
$params[] = $por_pagina;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql_registros);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $registros = [];
    while ($row = $result->fetch_assoc()) {
        $registros[] = $row;
    }
    $stmt->close();
}

// ============================================
// CONTAR TOTAL DE REGISTROS PARA PAGINACIÓN
// ============================================

$sql_count = "SELECT COUNT(*) as total FROM registros_ids WHERE 1=1";
$count_params = [];

if (!empty($filtro_rol)) {
    $sql_count .= " AND roles = ?";
    $count_params[] = $filtro_rol;
}
if (!empty($filtro_estatus)) {
    $sql_count .= " AND estatus = ?";
    $count_params[] = $filtro_estatus;
}

$stmt_count = $conn->prepare($sql_count);
if (!empty($count_params)) {
    $count_types = str_repeat("s", count($count_params));
    $stmt_count->bind_param($count_types, ...$count_params);
}
$stmt_count->execute();
$total_registros = $stmt_count->get_result()->fetch_assoc()['total'];
$stmt_count->close();

$total_paginas = $total_registros > 0 ? ceil($total_registros / $por_pagina) : 1;

// ============================================
// ESTADÍSTICAS
// ============================================

$stats_roles = [
    'total_registros_con_rol' => 0,
    'total_roles_distintos' => count($roles_disponibles),
    'registros_por_rol' => []
];

// Contar registros con rol no vacío
$count_rol_query = $conn->query("SELECT COUNT(*) as total FROM registros_ids WHERE roles IS NOT NULL AND roles != '' AND TRIM(roles) != ''");
$stats_roles['total_registros_con_rol'] = $count_rol_query->fetch_assoc()['total'];

// Estadísticas de registros por columna "roles"
$rol_stats_query = $conn->query("
    SELECT roles, COUNT(*) as total 
    FROM registros_ids 
    WHERE roles IS NOT NULL 
        AND roles != '' 
        AND TRIM(roles) != ''
    GROUP BY roles 
    ORDER BY total DESC 
    LIMIT 10
");
while ($row = $rol_stats_query->fetch_assoc()) {
    $stats_roles['registros_por_rol'][$row['roles']] = $row['total'];
}

// Obtener lista de estatus para filtro
$estatus_disponibles = ['Activo', 'Inactivo', 'Pendiente', 'Bloqueado'];

// Obtener la página anterior para el botón volver (opcional)
$pagina_anterior = $_SERVER['HTTP_REFERER'] ?? 'index.php';
// Si la página anterior es la misma o está vacía, usar index.php
if (strpos($pagina_anterior, 'roles.php') !== false || empty($pagina_anterior)) {
    $pagina_anterior = 'index.php';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Roles - Bitácora</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; min-height: 100vh; }
        
        .main-content { padding: 20px; min-height: 100vh; }
        .top-bar { background: white; padding: 15px 25px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .user-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #3498db, #2980b9); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        .user-info { display: flex; align-items: center; gap: 15px; }
        
        /* ESTILOS DEL BOTÓN VOLVER */
        .btn-Volver {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #f8f9fa;
            color: #2c3e50;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid #e0e0e0;
        }
        
        .btn-Volver:hover {
            background: #e9ecef;
            border-color: #c0392b;
            color: #c0392b;
            transform: translateX(-3px);
        }
        
        .btn-Volver i {
            font-size: 12px;
        }
        
        /* Botón volver secundario (variante) */
        .btn-Volver-secondary {
            background: #2c3e50;
            color: white;
            border-color: #2c3e50;
        }
        
        .btn-Volver-secondary:hover {
            background: #1a252f;
            color: white;
            border-color: #1a252f;
        }
        
        .container { max-width: 1600px; margin: 0 auto; background: white; border-radius: 10px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        
        /* Header con botón volver */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-header h1 { 
            color: #2c3e50; 
            border-bottom: 3px solid #c0392b; 
            padding-bottom: 10px; 
            margin-bottom: 0;
            display: inline-block;
        }
        
        .subtitulo { color: #7f8c8d; margin-bottom: 20px; font-size: 13px; }
        
        .mensaje { padding: 12px 20px; margin-bottom: 20px; border-radius: 5px; }
        .mensaje.success { background: #d4edda; color: #155724; }
        .mensaje.error { background: #f8d7da; color: #721c24; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card .number { font-size: 32px; font-weight: bold; color: #3498db; }
        .stat-card .label { color: #7f8c8d; font-size: 13px; margin-top: 5px; }
        
        .filtros { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
        .filtros select, .filtros input { padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 13px; min-width: 180px; }
        .filtros button { padding: 8px 18px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 13px; }
        .filtros button:hover { background: #2980b9; }
        .filtros .btn-limpiar { background: #95a5a6; color: white; border-radius: 5px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; padding: 8px 18px; }
        .filtros .btn-limpiar:hover { background: #7f8c8d; }
        
        .btn { padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; font-size: 12px; }
        .btn-primary { background: #3498db; color: white; }
        .btn-secondary { background: #95a5a6; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        
        /* Tabla mejorada */
        .table-container { overflow-x: auto; margin-top: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        table { width: 100%; min-width: 1000px; background: white; border-collapse: collapse; font-size: 12px; }
        th { background: linear-gradient(135deg, #2c3e50, #34495e); color: white; padding: 12px 10px; text-align: left; font-weight: 600; white-space: nowrap; position: sticky; top: 0; }
        td { padding: 10px; border-bottom: 1px solid #ecf0f1; vertical-align: top; }
        tr:hover { background: #f5f9ff; }
        
        /* Estilos para celdas con texto largo */
        .rango-cell { min-width: 180px; max-width: 200px; }
        .usuario-cell { min-width: 130px; max-width: 150px; }
        .designacion-cell { min-width: 250px; max-width: 350px; }
        .truncate-text { 
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: help;
        }
        .truncate-text:hover {
            white-space: normal;
            word-wrap: break-word;
            background: #fff;
            position: absolute;
            z-index: 100;
            max-width: 300px;
            padding: 5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            border-radius: 4px;
            background-color: #fff;
        }
        
        .badge-rol { 
            display: inline-block; 
            padding: 4px 10px; 
            border-radius: 20px; 
            font-size: 11px; 
            font-weight: 600; 
            background: #3498db; 
            color: white; 
            cursor: pointer;
            white-space: nowrap;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .badge-rol:hover { background: #2980b9; transform: scale(1.02); }
        
        .badge-estatus { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; white-space: nowrap; }
        .estatus-Activo { background: #27ae60; color: white; }
        .estatus-Inactivo { background: #95a5a6; color: white; }
        .estatus-Pendiente { background: #f39c12; color: white; }
        .estatus-Bloqueado { background: #e74c3c; color: white; }
        
        .acciones { display: flex; gap: 6px; flex-wrap: wrap; white-space: nowrap; }
        .acciones button { 
            padding: 5px 10px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 11px; 
            font-weight: 500;
            transition: all 0.2s;
        }
        .acciones button:hover { transform: translateY(-1px); }
        .btn-editar { background: #f39c12; color: white; }
        .btn-eliminar-rol { background: #e74c3c; color: white; }
        
        .paginacion { display: flex; justify-content: center; gap: 8px; margin-top: 25px; flex-wrap: wrap; }
        .paginacion a, .paginacion span { padding: 8px 14px; background: white; border-radius: 5px; text-decoration: none; color: #3498db; border: 1px solid #ddd; font-size: 13px; }
        .paginacion a:hover { background: #3498db; color: white; border-color: #3498db; }
        .paginacion .active { background: #3498db; color: white; border-color: #3498db; }
        
        .top-roles { margin-top: 30px; background: #f8f9fa; padding: 20px; border-radius: 8px; }
        .top-roles h3 { margin-bottom: 15px; font-size: 16px; }
        .rol-tag { display: inline-flex; align-items: center; background: white; padding: 6px 12px; border-radius: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-decoration: none; margin: 4px; transition: all 0.2s; color: #2c3e50; font-size: 12px; }
        .rol-tag:hover { transform: scale(1.02); background: #3498db; color: white; }
        .rol-tag span { background: #3498db; color: white; border-radius: 50%; padding: 2px 7px; margin-left: 8px; font-size: 11px; }
        
        .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 10% auto; width: 90%; max-width: 500px; border-radius: 10px; }
        .modal-header { padding: 15px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px 10px 0 0; display: flex; justify-content: space-between; }
        .modal-body { padding: 20px; }
        .modal-footer { padding: 15px 20px; background: #f8f9fa; border-radius: 0 0 10px 10px; display: flex; justify-content: flex-end; gap: 10px; }
        .close { font-size: 28px; cursor: pointer; color: white; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 13px; }
        
        .selection-bar { background: #e8f4fd; padding: 10px 15px; border-radius: 8px; margin-bottom: 15px; display: none; align-items: center; gap: 15px; flex-wrap: wrap; font-size: 13px; }
        .selection-bar.active { display: flex; }
        
        .info-badge { display: inline-block; margin-bottom: 15px; padding: 6px 12px; background: #e8f4fd; border-radius: 5px; font-size: 12px; }
        
        /* Checkbox estilizado */
        input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; }
        
        /* ID column */
        .id-cell { width: 60px; text-align: center; font-weight: 600; color: #2c3e50; }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .container { padding: 15px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) { 
            .stats-grid { grid-template-columns: 1fr; }
            .filtros { flex-direction: column; align-items: stretch; }
            .filtros select, .filtros input { min-width: auto; }
            .table-container { font-size: 11px; }
            th, td { padding: 6px; }
            .acciones { flex-direction: column; }
            .rango-cell, .usuario-cell, .designacion-cell { min-width: auto; }
            .page-header { flex-direction: column; align-items: flex-start; }
        }
        
        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 15px;
        }
        .breadcrumb a {
            color: #3498db;
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        .breadcrumb i {
            font-size: 10px;
            color: #95a5a6;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="top-bar">
            <div class="user-info">
                <!-- BOTÓN VOLVER EN TOP BAR -->
                <a href="<?php echo htmlspecialchars($pagina_anterior); ?>" class="btn-Volver" aria-label="Volver">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
                <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario'] ?? 'Admin'); ?></span>
                <div class="user-avatar"><?php echo substr($_SESSION['usuario'] ?? 'A', 0, 1); ?></div>
            </div>
        </div>

        <div class="container">
            <!-- BREADCRUMB (miga de pan) -->
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i> Inicio</a>
                <i class="fas fa-chevron-right"></i>
                <span><i class="fas fa-user-tag"></i> Gestión de Roles</span>
            </div>

            <!-- HEADER CON BOTÓN VOLVER -->
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-user-tag"></i> Gestión de Roles</h1>
                </div>
                <div style="display: flex; gap: 10px;">
                    <!-- Botón volver secundario (alternativa) -->
                    <a href="index.php" class="btn-Volver btn-Volver-secondary">
                        <i class="fas fa-home"></i> Ir al Inicio
                    </a>
                    <!-- Botón volver con historial -->
                    <a href="javascript:history.back()" class="btn-Volver" title="Volver a la página anterior">
                        <i class="fas fa-undo-alt"></i> Atrás
                    </a>
                </div>
            </div>
            <div class="subtitulo">Edición de la columna "roles" en registros_ids</div>
            
            <?php if ($mensaje): ?>
                <div class="mensaje <?php echo $tipo_mensaje; ?>"><?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>
            
            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="number"><?php echo $stats_roles['total_registros_con_rol']; ?></div>
                    <div class="label">Registros con Rol</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $stats_roles['total_roles_distintos']; ?></div>
                    <div class="label">Roles Distintos</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $total_registros; ?></div>
                    <div class="label">Total Registros</div>
                </div>
            </div>
            
            <!-- Filtros -->
            <div class="filtros">
                <form method="GET" action="" style="display: contents;" id="filtroForm">
                    <select name="filtro_rol">
                        <option value="">👥 Todos los roles</option>
                        <?php foreach ($roles_disponibles as $rol_item): ?>
                            <option value="<?php echo htmlspecialchars($rol_item); ?>" <?php echo $filtro_rol == $rol_item ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(substr($rol_item, 0, 50)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="filtro_estatus">
                        <option value="">📊 Todos los estatus</option>
                        <?php foreach ($estatus_disponibles as $estatus): ?>
                            <option value="<?php echo $estatus; ?>" <?php echo $filtro_estatus == $estatus ? 'selected' : ''; ?>>
                                <?php echo $estatus; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="por_pagina">
                        <option value="10" <?php echo $por_pagina == 10 ? 'selected' : ''; ?>>10 por página</option>
                        <option value="20" <?php echo $por_pagina == 20 ? 'selected' : ''; ?>>20 por página</option>
                        <option value="50" <?php echo $por_pagina == 50 ? 'selected' : ''; ?>>50 por página</option>
                        <option value="100" <?php echo $por_pagina == 100 ? 'selected' : ''; ?>>100 por página</option>
                    </select>
                    <button type="submit"><i class="fas fa-filter"></i> Filtrar</button>
                    <a href="roles.php" class="btn-limpiar">
                        <i class="fas fa-eraser"></i> Limpiar
                    </a>
                </form>
            </div>
            
            <!-- Barra de selección múltiple -->
            <div id="selectionBar" class="selection-bar">
                <span><i class="fas fa-check-circle"></i> <span id="selectedCount">0</span> registro(s) seleccionado(s)</span>
                <select id="bulkRolSelect">
                    <option value="">Seleccionar rol...</option>
                    <?php foreach ($roles_disponibles as $rol_item): ?>
                        <option value="<?php echo htmlspecialchars($rol_item); ?>"><?php echo htmlspecialchars($rol_item); ?></option>
                    <?php endforeach; ?>
                </select>
                <button id="bulkEditBtn" class="btn-success"><i class="fas fa-save"></i> Aplicar</button>
                <button id="clearSelectionBtn" class="btn-secondary"><i class="fas fa-times"></i> Limpiar</button>
            </div>
            
            <!-- Resumen del filtro aplicado -->
            <?php if ($filtro_rol): ?>
                <div class="info-badge">
                    <i class="fas fa-info-circle"></i> 
                    Mostrando registros con rol: <strong><?php echo htmlspecialchars($filtro_rol); ?></strong>
                    <a href="roles.php" style="margin-left: 10px; color: #e74c3c;">✖</a>
                </div>
            <?php endif; ?>
            <?php if ($filtro_estatus): ?>
                <div class="info-badge">
                    <i class="fas fa-info-circle"></i> 
                    Mostrando registros con estatus: <strong><?php echo htmlspecialchars($filtro_estatus); ?></strong>
                    <a href="?<?php echo http_build_query(array_filter(['filtro_rol' => $filtro_rol, 'por_pagina' => $por_pagina])); ?>" style="margin-left: 10px; color: #e74c3c;">✖</a>
                </div>
            <?php endif; ?>
            
            <!-- Tabla de registros mejorada -->
            <div class="table-container">
                <form id="bulkForm" method="POST" action="?accion=editar_multiple">
                    <input type="hidden" name="accion" value="editar_multiple">
                    <input type="hidden" id="bulkRolValue" name="rol_multiple" value="">
                    <input type="hidden" id="bulkIds" name="ids" value="">
                </form>
                
                <table>
                    <thead>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="selectAll"></th>
                            <th>ID</th>
                            <th>Rango Militar</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Estatus</th>
                            <th>Designación</th>
                            <th style="width: 140px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($registros)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-user-tag" style="font-size: 48px; color: #ccc;"></i><br>
                                    No hay registros
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($registros as $registro): ?>
                                <tr id="row-<?php echo $registro['id_registro']; ?>">
                                    <td style="text-align: center;">
                                        <input type="checkbox" class="row-checkbox" data-id="<?php echo $registro['id_registro']; ?>">
                                    </td>
                                    <td class="id-cell"><?php echo $registro['id_registro']; ?></td>
                                    <td class="rango-cell">
                                        <span class="truncate-text" title="<?php echo htmlspecialchars($registro['rango_militar'] ?? ''); ?>">
                                            <?php echo htmlspecialchars(mb_strimwidth($registro['rango_militar'] ?? '', 0, 35, '...')); ?>
                                        </span>
                                    </td>
                                    <td class="usuario-cell">
                                        <span class="truncate-text" title="<?php echo htmlspecialchars($registro['usuario'] ?? ''); ?>">
                                            <?php echo htmlspecialchars(mb_strimwidth($registro['usuario'] ?? '', 0, 20, '...')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge-rol" onclick="editarRol(<?php echo $registro['id_registro']; ?>, '<?php echo htmlspecialchars(addslashes($registro['roles'] ?? '')); ?>')" title="<?php echo htmlspecialchars($registro['roles'] ?? 'Sin rol'); ?>">
                                            <?php echo htmlspecialchars(mb_strimwidth($registro['roles'] ?? 'Sin rol', 0, 25, '...')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge-estatus estatus-<?php echo $registro['estatus'] ?? 'Activo'; ?>">
                                            <?php echo $registro['estatus'] ?? 'Activo'; ?>
                                        </span>
                                    </td>
                                    <td class="designacion-cell">
                                        <span class="truncate-text" title="<?php echo htmlspecialchars($registro['designacion'] ?? ''); ?>">
                                            <?php echo htmlspecialchars(mb_strimwidth($registro['designacion'] ?? 'Sin designación', 0, 45, '...')); ?>
                                        </span>
                                    </td>
                                    <td class="acciones">
                                        <button class="btn-editar" onclick="editarRol(<?php echo $registro['id_registro']; ?>, '<?php echo htmlspecialchars(addslashes($registro['roles'] ?? '')); ?>')">
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                        <?php if (!empty($registro['roles'])): ?>
                                            <button class="btn-eliminar-rol" onclick="eliminarRol(<?php echo $registro['id_registro']; ?>)">
                                                <i class="fas fa-trash"></i> Quitar
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación -->
            <?php if ($total_paginas > 1): ?>
            <div class="paginacion">
                <?php if ($pagina > 1): ?>
                    <a href="?pagina=<?php echo $pagina - 1; ?>&filtro_rol=<?php echo urlencode($filtro_rol); ?>&filtro_estatus=<?php echo urlencode($filtro_estatus); ?>&por_pagina=<?php echo $por_pagina; ?>">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_paginas && $i <= 10; $i++): ?>
                    <?php if ($i == $pagina): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?pagina=<?php echo $i; ?>&filtro_rol=<?php echo urlencode($filtro_rol); ?>&filtro_estatus=<?php echo urlencode($filtro_estatus); ?>&por_pagina=<?php echo $por_pagina; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($pagina < $total_paginas): ?>
                    <a href="?pagina=<?php echo $pagina + 1; ?>&filtro_rol=<?php echo urlencode($filtro_rol); ?>&filtro_estatus=<?php echo urlencode($filtro_estatus); ?>&por_pagina=<?php echo $por_pagina; ?>">
                        Siguiente <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Top roles -->
            <?php if (!empty($stats_roles['registros_por_rol'])): ?>
            <div class="top-roles">
                <h3><i class="fas fa-chart-simple"></i> Top Roles en Registros</h3>
                <div>
                    <?php foreach ($stats_roles['registros_por_rol'] as $rol_item => $total): ?>
                        <a href="?filtro_rol=<?php echo urlencode($rol_item); ?>" class="rol-tag">
                            📋 <?php echo htmlspecialchars(substr($rol_item, 0, 30)); ?>
                            <span><?php echo $total; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal Editar Rol -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Editar Rol</h3>
                <span class="close" onclick="cerrarModal()">&times;</span>
            </div>
            <form id="editForm" method="POST">
                <input type="hidden" name="accion" value="editar_rol">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Registro ID: <strong id="modalRegistroId"></strong></label>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Rol</label>
                        <input type="text" id="rolInput" name="rol" required placeholder="Ej: CONSULTAS GENERAL, ADMINISTRADOR, etc.">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-list"></i> Sugerencias de roles existentes:</label>
                        <select id="rolSuggest" onchange="document.getElementById('rolInput').value = this.value">
                            <option value="">-- Seleccionar --</option>
                            <?php foreach ($roles_disponibles as $rol_item): ?>
                                <option value="<?php echo htmlspecialchars($rol_item); ?>"><?php echo htmlspecialchars($rol_item); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="cerrarModal()">Cancelar</button>
                    <button type="submit" class="btn-success"><i class="fas fa-save"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        const modal = document.getElementById('editModal');
        const editForm = document.getElementById('editForm');
        
        function editarRol(id, rolActual) {
            document.getElementById('modalRegistroId').textContent = id;
            document.getElementById('rolInput').value = rolActual || '';
            document.getElementById('rolSuggest').value = '';
            editForm.action = 'roles.php?accion=editar_rol&id=' + id;
            modal.style.display = 'block';
        }
        
        function eliminarRol(id) {
            if (confirm('¿Estás seguro de que deseas eliminar el rol de este registro?')) {
                window.location.href = 'roles.php?accion=eliminar_rol&id=' + id;
            }
        }
        
        function cerrarModal() {
            modal.style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target === modal) cerrarModal();
        }
        
        // Selección múltiple
        let selectedIds = new Set();
        const selectAllCheckbox = document.getElementById('selectAll');
        const selectionBar = document.getElementById('selectionBar');
        const selectedCountSpan = document.getElementById('selectedCount');
        const bulkRolSelect = document.getElementById('bulkRolSelect');
        const bulkEditBtn = document.getElementById('bulkEditBtn');
        const clearSelectionBtn = document.getElementById('clearSelectionBtn');
        
        function updateSelectionBar() {
            const count = selectedIds.size;
            selectedCountSpan.textContent = count;
            
            if (count > 0) {
                selectionBar.classList.add('active');
            } else {
                selectionBar.classList.remove('active');
                if (selectAllCheckbox) selectAllCheckbox.checked = false;
            }
            
            document.querySelectorAll('.row-checkbox').forEach(cb => {
                cb.checked = selectedIds.has(parseInt(cb.dataset.id));
            });
        }
        
        function toggleSelectAll() {
            if (selectAllCheckbox.checked) {
                document.querySelectorAll('.row-checkbox').forEach(cb => {
                    selectedIds.add(parseInt(cb.dataset.id));
                });
            } else {
                selectedIds.clear();
            }
            updateSelectionBar();
        }
        
        function toggleRowCheckbox(id) {
            if (selectedIds.has(id)) {
                selectedIds.delete(id);
            } else {
                selectedIds.add(id);
            }
            updateSelectionBar();
            
            if (selectAllCheckbox) {
                const totalCheckboxes = document.querySelectorAll('.row-checkbox').length;
                selectAllCheckbox.checked = (selectedIds.size === totalCheckboxes && totalCheckboxes > 0);
            }
        }
        
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', toggleSelectAll);
        }
        
        document.querySelectorAll('.row-checkbox').forEach(cb => {
            cb.addEventListener('change', function() {
                toggleRowCheckbox(parseInt(this.dataset.id));
            });
        });
        
        if (bulkEditBtn) {
            bulkEditBtn.addEventListener('click', function() {
                const selectedRol = bulkRolSelect.value;
                if (!selectedRol) {
                    alert('Por favor selecciona un rol para aplicar');
                    return;
                }
                if (selectedIds.size === 0) {
                    alert('No hay registros seleccionados');
                    return;
                }
                if (confirm(`¿Aplicar el rol "${selectedRol}" a ${selectedIds.size} registro(s)?`)) {
                    document.getElementById('bulkRolValue').value = selectedRol;
                    document.getElementById('bulkIds').value = Array.from(selectedIds).join(',');
                    document.getElementById('bulkForm').submit();
                }
            });
        }
        
        if (clearSelectionBtn) {
            clearSelectionBtn.addEventListener('click', function() {
                selectedIds.clear();
                updateSelectionBar();
            });
        }
        
        // Guardar filtros al volver (opcional)
        function guardarFiltros() {
            const filtroRol = document.querySelector('select[name="filtro_rol"]').value;
            const filtroEstatus = document.querySelector('select[name="filtro_estatus"]').value;
            if (filtroRol || filtroEstatus) {
                sessionStorage.setItem('roles_filtro_rol', filtroRol);
                sessionStorage.setItem('roles_filtro_estatus', filtroEstatus);
            }
        }
        
        // Función para volver con los filtros guardados
        function volverConFiltros() {
            const url = new URL(window.location.href);
            const filtroRol = sessionStorage.getItem('roles_filtro_rol');
            const filtroEstatus = sessionStorage.getItem('roles_filtro_estatus');
            if (filtroRol) url.searchParams.set('filtro_rol', filtroRol);
            if (filtroEstatus) url.searchParams.set('filtro_estatus', filtroEstatus);
            window.location.href = url.toString();
        }
    </script>
</body>
</html>