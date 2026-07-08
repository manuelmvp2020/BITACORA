<?php
// buscar.php - Maneja las búsquedas AJAX desde index.php
require_once 'config.php';

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['usuario'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No autorizado', 'html' => '', 'total' => 0]);
    exit();
}

// Obtener parámetros de búsqueda
$texto = $_GET['texto'] ?? '';
$estatus = $_GET['estatus'] ?? '';
$filtro_usuario = $_GET['filtro_usuario'] ?? '';

// Construir la consulta SQL base
$sql = "SELECT * FROM registros_ids WHERE 1=1";
$params = [];
$types = "";

// Filtro por texto en múltiples columnas
if (!empty($texto)) {
    $sql .= " AND (rango_militar LIKE ? OR usuario LIKE ? OR roles LIKE ? OR designacion LIKE ? OR configuracion LIKE ?)";
    $like = "%{$texto}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sssss";
}

// Filtro por estatus
if (!empty($estatus)) {
    $sql .= " AND estatus = ?";
    $params[] = $estatus;
    $types .= "s";
}

// Filtro por usuarios únicos (cada usuario aparece una sola vez)
if (!empty($filtro_usuario)) {
    if ($filtro_usuario === 'todos_unicos') {
        // Subconsulta para obtener un registro por usuario (el más reciente)
        $sql = "SELECT r.* FROM registros_ids r 
                INNER JOIN (
                    SELECT usuario, MAX(fecha_registro) as max_fecha 
                    FROM registros_ids 
                    WHERE usuario IS NOT NULL AND usuario != ''
                    GROUP BY usuario
                ) latest ON r.usuario = latest.usuario AND r.fecha_registro = latest.max_fecha
                WHERE 1=1";
        // Reaplicar otros filtros que ya estaban
        if (!empty($texto)) {
            $sql .= " AND (r.rango_militar LIKE ? OR r.usuario LIKE ? OR r.roles LIKE ? OR r.designacion LIKE ? OR r.configuracion LIKE ?)";
        }
        if (!empty($estatus)) {
            $sql .= " AND r.estatus = ?";
        }
    } elseif ($filtro_usuario === 'activos_unicos') {
        $sql = "SELECT r.* FROM registros_ids r 
                INNER JOIN (
                    SELECT usuario, MAX(fecha_registro) as max_fecha 
                    FROM registros_ids 
                    WHERE usuario IS NOT NULL AND usuario != '' AND estatus = 'Activo'
                    GROUP BY usuario
                ) latest ON r.usuario = latest.usuario AND r.fecha_registro = latest.max_fecha
                WHERE r.estatus = 'Activo'";
        if (!empty($texto)) {
            $sql_extra = " AND (r.rango_militar LIKE ? OR r.usuario LIKE ? OR r.roles LIKE ? OR r.designacion LIKE ? OR r.configuracion LIKE ?)";
            $sql .= $sql_extra;
        }
    } elseif ($filtro_usuario === 'inactivos_unicos') {
        $sql = "SELECT r.* FROM registros_ids r 
                INNER JOIN (
                    SELECT usuario, MAX(fecha_registro) as max_fecha 
                    FROM registros_ids 
                    WHERE usuario IS NOT NULL AND usuario != '' AND estatus = 'Inactivo'
                    GROUP BY usuario
                ) latest ON r.usuario = latest.usuario AND r.fecha_registro = latest.max_fecha
                WHERE r.estatus = 'Inactivo'";
        if (!empty($texto)) {
            $sql_extra = " AND (r.rango_militar LIKE ? OR r.usuario LIKE ? OR r.roles LIKE ? OR r.designacion LIKE ? OR r.configuracion LIKE ?)";
            $sql .= $sql_extra;
        }
    } elseif ($filtro_usuario === 'pendiente_unicos') {
        $sql = "SELECT r.* FROM registros_ids r 
                INNER JOIN (
                    SELECT usuario, MAX(fecha_registro) as max_fecha 
                    FROM registros_ids 
                    WHERE usuario IS NOT NULL AND usuario != '' AND estatus = 'Pendiente'
                    GROUP BY usuario
                ) latest ON r.usuario = latest.usuario AND r.fecha_registro = latest.max_fecha
                WHERE r.estatus = 'Pendiente'";
        if (!empty($texto)) {
            $sql_extra = " AND (r.rango_militar LIKE ? OR r.usuario LIKE ? OR r.roles LIKE ? OR r.designacion LIKE ? OR r.configuracion LIKE ?)";
            $sql .= $sql_extra;
        }
    } elseif ($filtro_usuario === 'bloqueados_unicos') {
        $sql = "SELECT r.* FROM registros_ids r 
                INNER JOIN (
                    SELECT usuario, MAX(fecha_registro) as max_fecha 
                    FROM registros_ids 
                    WHERE usuario IS NOT NULL AND usuario != '' AND estatus = 'Bloqueado'
                    GROUP BY usuario
                ) latest ON r.usuario = latest.usuario AND r.fecha_registro = latest.max_fecha
                WHERE r.estatus = 'Bloqueado'";
        if (!empty($texto)) {
            $sql_extra = " AND (r.rango_militar LIKE ? OR r.usuario LIKE ? OR r.roles LIKE ? OR r.designacion LIKE ? OR r.configuracion LIKE ?)";
            $sql .= $sql_extra;
        }
    }
}

// Agregar ordenamiento
$sql .= " ORDER BY fecha_registro DESC";

// Ejecutar la consulta
try {
    $stmt = $conn->prepare($sql);
    if (!empty($params) && !empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    // Generar HTML de la tabla
    $html = '';
    $contador = 1;
    
    // Paleta de colores para usuarios
    $color_palette_usuarios = ['#9b59b6', '#e91e63', '#ba68c8', '#ff80ab', '#aa00ff', '#7b1fa2', '#c2185b', '#ab47bc', '#ec407a', '#8e24aa'];
    
    if ($resultado && $resultado->num_rows > 0) {
        while ($row = $resultado->fetch_assoc()) {
            $id_registro = htmlspecialchars($row['id_registro'] ?? '');
            $rango_militar = htmlspecialchars($row['rango_militar'] ?? 'No especificado');
            $usuario = $row['usuario'] ?? '';
            $roles = $row['roles'] ?? '';
            $estatus_val = htmlspecialchars($row['estatus'] ?? 'Activo');
            $designacion = htmlspecialchars(substr($row['designacion'] ?? '', 0, 50)) . (strlen($row['designacion'] ?? '') > 50 ? '...' : '');
            
            // ============================================
            // CORRECCIÓN: Mostrar fecha con hora solo si existe
            // ============================================
            $fecha_registro_raw = $row['fecha_registro'] ?? '';
            if (!empty($fecha_registro_raw) && $fecha_registro_raw != '0000-00-00') {
                $timestamp = strtotime($fecha_registro_raw);
                $formato = 'd/m/Y';
                // Si la hora no es 00:00:00, mostrar también la hora
                if (date('H:i:s', $timestamp) != '00:00:00') {
                    $formato = 'd/m/Y H:i';
                }
                $fecha_registro = date($formato, $timestamp);
            } else {
                $fecha_registro = '<span style="color: #999;">—</span>';
            }
            
            $configuracion = htmlspecialchars(substr($row['configuracion'] ?? '', 0, 30)) . (strlen($row['configuracion'] ?? '') > 30 ? '...' : '');
            
            // Color para el usuario
            $color_hash = abs(crc32($usuario)) % count($color_palette_usuarios);
            $user_color = $color_palette_usuarios[$color_hash];
            
            // Clase para el rol
            $rol_class = '';
            $rol_icon = '';
            if (stripos($roles, 'ADMIN') !== false) { 
                $rol_class = 'rol-administrador'; 
                $rol_icon = '👑'; 
            } elseif (stripos($roles, 'SUPER') !== false) { 
                $rol_class = 'rol-supervisor'; 
                $rol_icon = '👁️'; 
            } elseif (stripos($roles, 'OPER') !== false) { 
                $rol_class = 'rol-operador'; 
                $rol_icon = '⚙️'; 
            } elseif (stripos($roles, 'CONSUL') !== false) { 
                $rol_class = 'rol-consultor'; 
                $rol_icon = '📊'; 
            } else { 
                $rol_class = 'rol-usuario'; 
                $rol_icon = '👤'; 
            }
            
            $html .= '<tr>';
            $html .= '<td class="no-print" style="text-align: center;">';
            $html .= '<input type="checkbox" name="seleccionados[]" value="' . $id_registro . '" class="seleccionar-item">';
            $html .= '</td>';
            $html .= '<td style="text-align: center; font-weight: bold; background-color: #f8f9fa;">' . $contador++ . '</td>';
            $html .= '<td>' . $rango_militar . '</td>';
            $html .= '<td>';
            if (!empty($usuario)) {
                $html .= '<span style="background-color: ' . $user_color . '20; color: ' . $user_color . '; padding: 3px 8px; border-radius: 12px; font-weight: 500;">';
                $html .= htmlspecialchars($usuario);
                $html .= '</span>';
            } else {
                $html .= '<span style="color: #999;">—</span>';
            }
            $html .= '</td>';
            $html .= '<td>';
            if (!empty($roles)) {
                $html .= '<span class="rol-badge ' . $rol_class . '" title="' . htmlspecialchars($roles) . '">';
                $html .= $rol_icon . ' ' . htmlspecialchars(mb_strimwidth($roles, 0, 20, "..."));
                $html .= '</span>';
            } else {
                $html .= '<span style="color: #999;">—</span>';
            }
            $html .= '</td>';
            $html .= '<td><span class="estatus ' . $estatus_val . '">' . $estatus_val . '</span></td>';
            $html .= '<td>' . $designacion . '</td>';
            $html .= '<td style="white-space: nowrap;">' . $fecha_registro . '</td>';
            $html .= '<td>' . $configuracion . '</td>';
            $html .= '<td class="acciones no-print">';
            $html .= '<a href="editar.php?id=' . $id_registro . '" class="editar" title="Editar registro" onclick="registrarEdicion(' . $id_registro . ')">✏️ Editar</a>';
            $html .= '<a href="index.php?eliminar=' . $id_registro . '" class="eliminar" onclick="return confirmarEliminacion(' . $id_registro . ', \'' . addslashes($rango_militar) . '\')" title="Eliminar registro">🗑️ Eliminar</a>';
            $html .= '<a href="imprimir.php?id=' . $id_registro . '" class="imprimir" target="_blank" title="Imprimir registro" onclick="registrarImpresion(' . $id_registro . ')">🖨️ Imprimir</a>';
            $html .= '</td>';
            $html .= '</tr>';
        }
    } else {
        $html .= '<tr>';
        $html .= '<td colspan="10" style="text-align: center; padding: 40px; color: #7f8c8d;">';
        $html .= '<span style="font-size: 48px;">🔍</span><br>';
        $html .= '<strong>No se encontraron resultados</strong><br>';
        $html .= '<span style="font-size: 14px;">Intenta con otros términos de búsqueda o quita los filtros aplicados</span>';
        $html .= '</td>';
        $html .= '</tr>';
    }
    
    // Registrar la búsqueda en logs
    registrarLog($conn, $_SESSION['usuario'], 
        "Realizó búsqueda en panel principal", 
        "buscar.php", 
        obtenerIP(),
        [
            'termino' => $texto,
            'estatus' => $estatus,
            'filtro_usuario' => $filtro_usuario,
            'resultados' => $resultado ? $resultado->num_rows : 0,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    );
    
    // Devolver JSON
    header('Content-Type: application/json');
    echo json_encode([
        'html' => $html,
        'total' => $resultado ? $resultado->num_rows : 0,
        'success' => true
    ]);
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Error en buscar.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'error' => $e->getMessage(),
        'html' => '<tr><td colspan="10" style="text-align: center; padding: 40px; color: #e74c3c;">Error en la búsqueda: ' . htmlspecialchars($e->getMessage()) . '</td></tr>',
        'total' => 0
    ]);
}

$conn->close();
?>