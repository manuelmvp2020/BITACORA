<?php
// index.php - Página principal con listado de registros y registro completo de logs
require_once 'config.php';

// ============================================
// NOTA: La función registrarLog() ya está definida en config.php
// NO la vuelvas a definir aquí
// ============================================

// ============================================
// REGISTRO DE ACTIVIDADES DEL SISTEMA
// ============================================

// Inicializar contador de vistas
if (!isset($_SESSION['vista_index_count'])) {
    $_SESSION['vista_index_count'] = 0;
}

$_SESSION['vista_index_count']++;

// Registrar cada 5 visitas para no saturar
if ($_SESSION['vista_index_count'] >= 5) {
    registrarLog($conn, $_SESSION['usuario'] ?? 'admin', 
        "Visualizó el panel principal (múltiples veces)", 
        "index.php", 
        obtenerIP(),
        [
            'tipo' => 'carga_completa',
            'visitas' => $_SESSION['vista_index_count'],
            'timestamp' => date('Y-m-d H:i:s')
        ]
    );
    $_SESSION['vista_index_count'] = 0;
}

// Registro de acceso al módulo (una vez por hora)
$hora_actual = date('Y-m-d H');
if (!isset($_SESSION['log_index_registrado']) || $_SESSION['log_index_registrado'] != $hora_actual) {
    registrarLog($conn, $_SESSION['usuario'] ?? 'admin', 
        "Accedió al panel principal", 
        "index.php", 
        obtenerIP(),
        [
            'acceso' => 'dashboard_completo',
            'estadisticas' => true,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    );
    $_SESSION['log_index_registrado'] = $hora_actual;
}

// ============================================
// LIMPIAR FILTROS DE FECHA
// ============================================
if (isset($_GET['limpiar_fechas']) && $_GET['limpiar_fechas'] == 1) {
    unset($_SESSION['filtro_fecha_desde']);
    unset($_SESSION['filtro_fecha_hasta']);
    header("Location: index.php");
    exit();
}

// ============================================
// MANEJO DE ELIMINACIÓN CON LOGS MEJORADO
// ============================================
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    
    // Registrar intento de eliminación
    registrarLog($conn, $_SESSION['usuario'] ?? 'admin', 
        "Intentó eliminar registro ID: $id", 
        "index.php", 
        obtenerIP(),
        ['accion' => 'inicio_eliminacion', 'id' => $id]
    );
    
    try {
        // Obtener datos del registro antes de eliminar
        $sql_select = "SELECT rango_militar, usuario, roles, estatus, designacion FROM registros_ids WHERE id_registro = ?";
        $stmt_select = $conn->prepare($sql_select);
        $stmt_select->bind_param("i", $id);
        $stmt_select->execute();
        $resultado_select = $stmt_select->get_result();
        $registro = $resultado_select->fetch_assoc();
        
        if ($registro) {
            $sql = "DELETE FROM registros_ids WHERE id_registro = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $_SESSION['mensaje'] = "Registro eliminado correctamente";
                $_SESSION['tipo_mensaje'] = "success";
                
                // REGISTRAR LOG DE ELIMINACIÓN EXITOSA
                registrarLog($conn, $_SESSION['usuario'] ?? 'admin', 
                    "Eliminó registro ID: $id - " . ($registro['rango_militar'] ?? 'N/A'), 
                    "index.php", 
                    obtenerIP(), 
                    [
                        'id_registro' => $id,
                        'rango_militar' => $registro['rango_militar'] ?? 'No especificado',
                        'usuario_asignado' => $registro['usuario'] ?? 'Sin usuario',
                        'roles' => $registro['roles'] ?? 'Sin rol',
                        'estatus' => $registro['estatus'] ?? 'Sin estatus',
                        'designacion' => $registro['designacion'] ?? 'Sin designación',
                        'fecha_eliminacion' => date('Y-m-d H:i:s'),
                        'eliminado_por' => $_SESSION['usuario'] ?? 'admin'
                    ]
                );
            } else {
                $_SESSION['mensaje'] = "Error al eliminar: " . $conn->error;
                $_SESSION['tipo_mensaje'] = "error";
                
                registrarLog($conn, $_SESSION['usuario'] ?? 'admin', 
                    "Error al eliminar registro ID: $id", 
                    "index.php", 
                    obtenerIP(),
                    [
                        'error' => $conn->error,
                        'id_registro' => $id,
                        'timestamp' => date('Y-m-d H:i:s')
                    ]
                );
            }
            $stmt->close();
        } else {
            $_SESSION['mensaje'] = "Registro no encontrado";
            $_SESSION['tipo_mensaje'] = "warning";
            
            registrarLog($conn, $_SESSION['usuario'] ?? 'admin', 
                "Intentó eliminar registro inexistente ID: $id", 
                "index.php", 
                obtenerIP(),
                ['id_intentado' => $id, 'resultado' => 'registro_no_encontrado']
            );
        }
        $stmt_select->close();
    } catch (Exception $e) {
        $_SESSION['mensaje'] = "Error al eliminar: " . $e->getMessage();
        $_SESSION['tipo_mensaje'] = "error";
        registrarLog($conn, $_SESSION['usuario'] ?? 'admin', 
            "Excepción al eliminar registro ID: $id", 
            "index.php", 
            obtenerIP(),
            ['error' => $e->getMessage(), 'id_registro' => $id]
        );
    }
    
    header("Location: index.php");
    exit();
}

// ============================================
// REGISTRAR EXPORTACIÓN SI SE SOLICITA
// ============================================
if (isset($_GET['exportar'])) {
    $formato = $_GET['exportar'] ?? 'csv';
    $tipo = $_GET['tipo'] ?? 'todos';
    
    registrarLog($conn, $_SESSION['usuario'] ?? 'admin',
        "Exportó datos a $formato",
        "index.php",
        obtenerIP(),
        [
            'formato' => $formato,
            'tipo' => $tipo,
            'filtros_aplicados' => $_GET,
            'fecha_exportacion' => date('Y-m-d H:i:s')
        ]
    );
}

// ============================================
// RECUPERAR FILTROS DE FECHA DESDE GET O SESSION
// ============================================
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : ($_SESSION['filtro_fecha_desde'] ?? '');
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : ($_SESSION['filtro_fecha_hasta'] ?? '');

// Guardar filtros en sesión para persistencia
if (isset($_GET['fecha_desde']) || isset($_GET['fecha_hasta'])) {
    if (!empty($fecha_desde)) {
        $_SESSION['filtro_fecha_desde'] = $fecha_desde;
    } else {
        unset($_SESSION['filtro_fecha_desde']);
    }
    if (!empty($fecha_hasta)) {
        $_SESSION['filtro_fecha_hasta'] = $fecha_hasta;
    } else {
        unset($_SESSION['filtro_fecha_hasta']);
    }
}

// Validar y limpiar fechas
if ($fecha_desde && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)) {
    $fecha_desde = '';
}
if ($fecha_hasta && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) {
    $fecha_hasta = '';
}

// ============================================
// OBTENER TODOS LOS REGISTROS CON FILTROS DE FECHA
// ============================================
try {
    $sql = "SELECT * FROM registros_ids WHERE 1=1";
    $params = [];
    $types = "";
    
    // Aplicar filtro de fecha desde
    if (!empty($fecha_desde)) {
        $sql .= " AND DATE(fecha_registro) >= ?";
        $params[] = $fecha_desde;
        $types .= "s";
    }
    
    // Aplicar filtro de fecha hasta
    if (!empty($fecha_hasta)) {
        $sql .= " AND DATE(fecha_registro) <= ?";
        $params[] = $fecha_hasta;
        $types .= "s";
    }
    
    $sql .= " ORDER BY fecha_registro DESC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if (!$resultado) {
        throw new Exception("Error en consulta: " . $conn->error);
    }
} catch (Exception $e) {
    error_log("Error en consulta principal: " . $e->getMessage());
    $_SESSION['error_db'] = "Error al cargar los datos. Contacte al administrador.";
    $resultado = null;
}

// ============================================
// FUNCIONES AUXILIARES PARA ESTADÍSTICAS
// ============================================
function ejecutarConsultaCount($conn, $sql, $params, $types) {
    try {
        if (empty($params)) {
            $result = $conn->query($sql);
            if ($result) {
                $row = $result->fetch_assoc();
                return $row ? (int)$row['total'] : 0;
            }
            return 0;
        } else {
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $stmt->close();
                return $row ? (int)$row['total'] : 0;
            }
            return 0;
        }
    } catch (Exception $e) {
        error_log("Error en ejecutarConsultaCount: " . $e->getMessage());
        return 0;
    }
}

function ejecutarConsultaTop($conn, $sql, $params, $types) {
    $resultados = [];
    try {
        if (empty($params)) {
            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $resultados[] = $row;
                }
            }
        } else {
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $resultados[] = $row;
                }
                $stmt->close();
            }
        }
    } catch (Exception $e) {
        error_log("Error en ejecutarConsultaTop: " . $e->getMessage());
    }
    return $resultados;
}

// ============================================
// ESTADÍSTICAS COMPLETAS CON VALIDACIÓN Y FILTROS
// ============================================
$stats = [
    'total' => 0,
    'activos' => 0,
    'pendiente' => 0,
    'inactivos' => 0,
    'bloqueados' => 0,
    'usuarios' => [
        'total_unicos' => 0,
        'activos_unicos' => 0,
        'inactivos_unicos' => 0,
        'pendiente_unicos' => 0,
        'bloqueados_unicos' => 0,
        'promedio_registros_por_usuario' => 0,
        'porcentaje_activos' => 0,
        'porcentaje_inactivos' => 0,
        'porcentaje_pendiente' => 0,
        'porcentaje_bloqueados' => 0,
        'top' => [],
        'top_activos' => [],
        'top_inactivos' => [],
        'top_pendiente' => [],
        'top_bloqueados' => []
    ],
    'roles' => []
];

try {
    // Construir cláusula WHERE para estadísticas
    $where_clause = "WHERE 1=1";
    $stats_params = [];
    $stats_types = "";
    
    if (!empty($fecha_desde)) {
        $where_clause .= " AND DATE(fecha_registro) >= ?";
        $stats_params[] = $fecha_desde;
        $stats_types .= "s";
    }
    
    if (!empty($fecha_hasta)) {
        $where_clause .= " AND DATE(fecha_registro) <= ?";
        $stats_params[] = $fecha_hasta;
        $stats_types .= "s";
    }
    
    // ============================================
    // 1. TOTALES POR ESTATUS DE REGISTROS (CON REPETICIÓN)
    // ============================================
    $stats['total'] = ejecutarConsultaCount($conn, "SELECT COUNT(*) as total FROM registros_ids $where_clause", $stats_params, $stats_types);
    $stats['activos'] = ejecutarConsultaCount($conn, "SELECT COUNT(*) as total FROM registros_ids $where_clause AND estatus='Activo'", $stats_params, $stats_types);
    $stats['pendiente'] = ejecutarConsultaCount($conn, "SELECT COUNT(*) as total FROM registros_ids $where_clause AND estatus='Pendiente'", $stats_params, $stats_types);
    $stats['inactivos'] = ejecutarConsultaCount($conn, "SELECT COUNT(*) as total FROM registros_ids $where_clause AND estatus='Inactivo'", $stats_params, $stats_types);
    $stats['bloqueados'] = ejecutarConsultaCount($conn, "SELECT COUNT(*) as total FROM registros_ids $where_clause AND estatus='Bloqueado'", $stats_params, $stats_types);
    
    // ============================================
    // 2. USUARIOS ÚNICOS POR ESTATUS (CORREGIDO - INCLUYE VACÍOS COMO UN SOLO USUARIO)
    // ============================================
    
    // Total de usuarios únicos (incluyendo vacíos como un solo usuario)
    $stats['usuarios']['total_unicos'] = ejecutarConsultaCount($conn, "
        SELECT COUNT(DISTINCT 
            CASE 
                WHEN usuario IS NULL OR TRIM(usuario) = '' THEN '___SIN_USUARIO___'
                ELSE TRIM(usuario)
            END
        ) as total 
        FROM registros_ids $where_clause", $stats_params, $stats_types);
    
    // Usuarios únicos ACTIVOS
    $stats['usuarios']['activos_unicos'] = ejecutarConsultaCount($conn, "
        SELECT COUNT(DISTINCT 
            CASE 
                WHEN usuario IS NULL OR TRIM(usuario) = '' THEN '___SIN_USUARIO___'
                ELSE TRIM(usuario)
            END
        ) as total 
        FROM registros_ids $where_clause AND estatus = 'Activo'", $stats_params, $stats_types);
    
    // Usuarios únicos INACTIVOS - CORREGIDO
    $stats['usuarios']['inactivos_unicos'] = ejecutarConsultaCount($conn, "
        SELECT COUNT(DISTINCT 
            CASE 
                WHEN usuario IS NULL OR TRIM(usuario) = '' THEN '___SIN_USUARIO___'
                ELSE TRIM(usuario)
            END
        ) as total 
        FROM registros_ids $where_clause AND estatus = 'Inactivo'", $stats_params, $stats_types);
    
    // Usuarios únicos PENDIENTES
    $stats['usuarios']['pendiente_unicos'] = ejecutarConsultaCount($conn, "
        SELECT COUNT(DISTINCT 
            CASE 
                WHEN usuario IS NULL OR TRIM(usuario) = '' THEN '___SIN_USUARIO___'
                ELSE TRIM(usuario)
            END
        ) as total 
        FROM registros_ids $where_clause AND estatus = 'Pendiente'", $stats_params, $stats_types);
    
    // Usuarios únicos BLOQUEADOS
    $stats['usuarios']['bloqueados_unicos'] = ejecutarConsultaCount($conn, "
        SELECT COUNT(DISTINCT 
            CASE 
                WHEN usuario IS NULL OR TRIM(usuario) = '' THEN '___SIN_USUARIO___'
                ELSE TRIM(usuario)
            END
        ) as total 
        FROM registros_ids $where_clause AND estatus = 'Bloqueado'", $stats_params, $stats_types);
    
    // ============================================
    // 3. CÁLCULO DE PORCENTAJES (basado en usuarios únicos)
    // ============================================
    $stats['usuarios']['porcentaje_activos'] = $stats['usuarios']['total_unicos'] > 0 
        ? round(($stats['usuarios']['activos_unicos'] / $stats['usuarios']['total_unicos']) * 100, 1) : 0;
    
    $stats['usuarios']['porcentaje_inactivos'] = $stats['usuarios']['total_unicos'] > 0 
        ? round(($stats['usuarios']['inactivos_unicos'] / $stats['usuarios']['total_unicos']) * 100, 1) : 0;
    
    $stats['usuarios']['porcentaje_pendiente'] = $stats['usuarios']['total_unicos'] > 0 
        ? round(($stats['usuarios']['pendiente_unicos'] / $stats['usuarios']['total_unicos']) * 100, 1) : 0;
    
    $stats['usuarios']['porcentaje_bloqueados'] = $stats['usuarios']['total_unicos'] > 0 
        ? round(($stats['usuarios']['bloqueados_unicos'] / $stats['usuarios']['total_unicos']) * 100, 1) : 0;
    
    // ============================================
    // 4. PROMEDIO DE REGISTROS POR USUARIO ÚNICO
    // ============================================
    $total_registros_con_usuario = ejecutarConsultaCount($conn, "
        SELECT COUNT(*) as total 
        FROM registros_ids $where_clause", $stats_params, $stats_types);
    
    $stats['usuarios']['promedio_registros_por_usuario'] = $stats['usuarios']['total_unicos'] > 0 
        ? round($total_registros_con_usuario / $stats['usuarios']['total_unicos'], 1) : 0;
    
    // ============================================
    // 5. TOP USUARIOS POR CATEGORÍA
    // ============================================
    
    // Top 10 usuarios generales (excluyendo el marcador de sin usuario para no mostrarlo)
    $top_query = ejecutarConsultaTop($conn, "
        SELECT usuario, COUNT(*) as total 
        FROM registros_ids $where_clause 
        AND usuario IS NOT NULL AND TRIM(usuario) != '' 
        GROUP BY usuario 
        ORDER BY total DESC 
        LIMIT 10", $stats_params, $stats_types);
    
    foreach ($top_query as $row) {
        $stats['usuarios']['top'][$row['usuario']] = $row['total'];
    }
    
    // Top 5 usuarios ACTIVOS
    $top_activos = ejecutarConsultaTop($conn, "
        SELECT usuario, COUNT(*) as total 
        FROM registros_ids $where_clause 
        AND usuario IS NOT NULL AND TRIM(usuario) != '' AND estatus = 'Activo' 
        GROUP BY usuario 
        ORDER BY total DESC 
        LIMIT 5", $stats_params, $stats_types);
    
    foreach ($top_activos as $row) {
        $stats['usuarios']['top_activos'][$row['usuario']] = $row['total'];
    }
    
    // Top 5 usuarios INACTIVOS
    $top_inactivos = ejecutarConsultaTop($conn, "
        SELECT usuario, COUNT(*) as total 
        FROM registros_ids $where_clause 
        AND usuario IS NOT NULL AND TRIM(usuario) != '' AND estatus = 'Inactivo' 
        GROUP BY usuario 
        ORDER BY total DESC 
        LIMIT 5", $stats_params, $stats_types);
    
    foreach ($top_inactivos as $row) {
        $stats['usuarios']['top_inactivos'][$row['usuario']] = $row['total'];
    }
    
    // Top 5 usuarios PENDIENTES
    $top_pendiente = ejecutarConsultaTop($conn, "
        SELECT usuario, COUNT(*) as total 
        FROM registros_ids $where_clause 
        AND usuario IS NOT NULL AND TRIM(usuario) != '' AND estatus = 'Pendiente' 
        GROUP BY usuario 
        ORDER BY total DESC 
        LIMIT 5", $stats_params, $stats_types);
    
    foreach ($top_pendiente as $row) {
        $stats['usuarios']['top_pendiente'][$row['usuario']] = $row['total'];
    }
    
    // Top 5 usuarios BLOQUEADOS
    $top_bloqueados = ejecutarConsultaTop($conn, "
        SELECT usuario, COUNT(*) as total 
        FROM registros_ids $where_clause 
        AND usuario IS NOT NULL AND TRIM(usuario) != '' AND estatus = 'Bloqueado' 
        GROUP BY usuario 
        ORDER BY total DESC 
        LIMIT 5", $stats_params, $stats_types);
    
    foreach ($top_bloqueados as $row) {
        $stats['usuarios']['top_bloqueados'][$row['usuario']] = $row['total'];
    }
    
    // ============================================
    // 6. ESTADÍSTICAS DE ROLES
    // ============================================
    $roles_query = ejecutarConsultaTop($conn, "
        SELECT roles, COUNT(*) as total 
        FROM registros_ids $where_clause 
        AND roles IS NOT NULL AND roles != '' 
        GROUP BY roles 
        ORDER BY total DESC 
        LIMIT 15", $stats_params, $stats_types);
    
    foreach ($roles_query as $row) {
        $stats['roles'][$row['roles']] = $row['total'];
    }
    
    // ============================================
    // 7. CORRECCIÓN: VERIFICAR Y ELIMINAR REGISTROS INACTIVOS DUPLICADOS
    // ============================================
    // Esta sección identifica y opcionalmente elimina registros inactivos duplicados
    // para mantener la consistencia de los datos
    
    $sql_duplicados_inactivos = "
        SELECT usuario, COUNT(*) as total, GROUP_CONCAT(id_registro ORDER BY fecha_registro DESC) as ids
        FROM registros_ids 
        $where_clause AND estatus = 'Inactivo' 
        AND usuario IS NOT NULL AND TRIM(usuario) != ''
        GROUP BY usuario 
        HAVING COUNT(*) > 1
    ";
    
    $result_duplicados = $conn->query($sql_duplicados_inactivos);
    $duplicados_encontrados = [];
    
    if ($result_duplicados && $result_duplicados->num_rows > 0) {
        while ($row_dup = $result_duplicados->fetch_assoc()) {
            $ids_array = explode(',', $row_dup['ids']);
            // Conservar el primero (más reciente), eliminar los demás
            $ids_a_eliminar = array_slice($ids_array, 1);
            
            foreach ($ids_a_eliminar as $id_eliminar) {
                $duplicados_encontrados[] = [
                    'usuario' => $row_dup['usuario'],
                    'id' => $id_eliminar,
                    'total_duplicados' => $row_dup['total']
                ];
            }
        }
        
        // Si hay duplicados, los eliminamos automáticamente para mantener la consistencia
        if (!empty($duplicados_encontrados) && !isset($_SESSION['duplicados_eliminados'])) {
            $eliminados_count = 0;
            foreach ($duplicados_encontrados as $dup) {
                $sql_delete_dup = "DELETE FROM registros_ids WHERE id_registro = ? AND estatus = 'Inactivo'";
                $stmt_delete_dup = $conn->prepare($sql_delete_dup);
                $stmt_delete_dup->bind_param("i", $dup['id']);
                if ($stmt_delete_dup->execute()) {
                    $eliminados_count++;
                    registrarLog($conn, 'sistema', 
                        "Eliminación automática de registro inactivo duplicado", 
                        "index.php", 
                        $_SERVER['REMOTE_ADDR'],
                        [
                            'id_registro' => $dup['id'],
                            'usuario' => $dup['usuario'],
                            'razon' => 'duplicado_inactivo',
                            'total_duplicados' => $dup['total_duplicados']
                        ]
                    );
                }
                $stmt_delete_dup->close();
            }
            
            if ($eliminados_count > 0) {
                $_SESSION['duplicados_eliminados'] = true;
                $_SESSION['mensaje'] = "Se eliminaron $eliminados_count registro(s) inactivo(s) duplicado(s) automáticamente para mantener la consistencia de los datos.";
                $_SESSION['tipo_mensaje'] = "info";
                // Recargar la página para actualizar las estadísticas
                header("Location: index.php");
                exit();
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Error en estadísticas: " . $e->getMessage());
}

// ============================================
// PREPARAR DATOS PARA GRÁFICOS
// ============================================

// Datos para gráfico de roles
$roles_labels = [];
$roles_data = [];
$roles_colors = [];
$color_palette_roles = [
    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
    '#FF9F40', '#7C4DFF', '#2ECC71', '#E74C3C', '#3498DB'
];

$color_index = 0;
foreach ($stats['roles'] as $rol => $cantidad) {
    $roles_labels[] = $rol . ' (' . $cantidad . ')';
    $roles_data[] = $cantidad;
    $roles_colors[] = $color_palette_roles[$color_index % count($color_palette_roles)];
    $color_index++;
}

// Datos para gráfico de usuarios por estatus (USUARIOS ÚNICOS)
$usuarios_estatus_labels = [
    'Activos (' . $stats['usuarios']['activos_unicos'] . ')',
    'Inactivos (' . $stats['usuarios']['inactivos_unicos'] . ')',
    'Pendiente (' . $stats['usuarios']['pendiente_unicos'] . ')',
    'Bloqueados (' . $stats['usuarios']['bloqueados_unicos'] . ')'
];
$usuarios_estatus_data = [
    $stats['usuarios']['activos_unicos'],
    $stats['usuarios']['inactivos_unicos'],
    $stats['usuarios']['pendiente_unicos'],
    $stats['usuarios']['bloqueados_unicos']
];
$usuarios_estatus_colors = ['#27ae60', '#e74c3c', '#f39c12', '#95a5a6'];

// Datos para top usuarios
$usuarios_labels = [];
$usuarios_data = [];
$usuarios_colors = [];
$color_palette_usuarios = ['#9b59b6', '#e91e63', '#ba68c8', '#ff80ab', '#aa00ff', '#7b1fa2', '#c2185b', '#ab47bc', '#ec407a', '#8e24aa'];

$color_index = 0;
foreach ($stats['usuarios']['top'] as $usuario => $cantidad) {
    $usuarios_labels[] = $usuario . ' (' . $cantidad . ' registros)';
    $usuarios_data[] = $cantidad;
    $usuarios_colors[] = $color_palette_usuarios[$color_index % count($color_palette_usuarios)];
    $color_index++;
}

// DEPURACIÓN OPCIONAL - Mostrar si hay registros sin usuario
$sql_debug = "SELECT COUNT(*) as total FROM registros_ids $where_clause AND (usuario IS NULL OR TRIM(usuario) = '')";
$total_sin_usuario = ejecutarConsultaCount($conn, $sql_debug, $stats_params, $stats_types);
if ($total_sin_usuario > 0 && !isset($_SESSION['debug_mostrado'])) {
    $_SESSION['debug_mostrado'] = true;
    $mensaje_debug = "ℹ️ Información: Se encontraron $total_sin_usuario registro(s) sin nombre de usuario. " .
                     "Estos se agrupan como un solo 'Usuario Anónimo' en las estadísticas de usuarios únicos.";
    $_SESSION['mensaje_debug'] = $mensaje_debug;
}

// Mostrar mensaje de depuración si existe
if (isset($_SESSION['mensaje_debug'])) {
    $debug_message = $_SESSION['mensaje_debug'];
    unset($_SESSION['mensaje_debug']);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bitácora de Registro</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; min-height: 100vh; display: flex; }
        
        /* Sidebar styles */
        .sidebar { width: 280px; background: linear-gradient(180deg, #1a2639 0%, #2c3e50 100%); color: white; height: 100vh; position: fixed; left: 0; top: 0; overflow-y: auto; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 1000; box-shadow: 4px 0 15px rgba(0,0,0,0.1); }
        .sidebar-header { padding: 25px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h3 { font-size: 22px; margin-bottom: 5px; color: #fff; }
        .sidebar-header p { font-size: 12px; opacity: 0.7; color: #ecf0f1; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item { padding: 12px 25px; display: flex; align-items: center; gap: 15px; color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s; border-left: 4px solid transparent; margin: 5px 0; }
        .menu-item:hover { background: rgba(255,255,255,0.1); color: white; border-left-color: #3498db; }
        .menu-item.active { background: rgba(52,152,219,0.2); color: white; border-left-color: #3498db; }
        .menu-item i { width: 20px; font-size: 18px; }
        .menu-section { padding: 15px 25px 5px; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.5); font-weight: 600; }
        .sidebar-stats { padding: 20px; margin: 15px; background: rgba(255,255,255,0.1); border-radius: 8px; }
        .stat-mini { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 13px; }
        .stat-mini span:last-child { font-weight: bold; color: #3498db; }
        .sidebar-footer { padding: 20px; text-align: center; border-top: 1px solid rgba(255,255,255,0.1); font-size: 12px; color: rgba(255,255,255,0.5); }
        
        /* Overlay para móviles */
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; transition: all 0.3s ease; }
        .sidebar-overlay.active { display: block; }
        
        /* Main content */
        .main-content { flex: 1; margin-left: 280px; padding: 20px; transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1); min-height: 100vh; }
        .top-bar { background: white; padding: 15px 25px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .menu-toggle { display: none; background: none; border: none; font-size: 24px; cursor: pointer; color: #2c3e50; padding: 8px 12px; border-radius: 8px; transition: all 0.3s; }
        .menu-toggle:hover { background: rgba(0,0,0,0.05); transform: scale(1.05); }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #3498db, #2980b9); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        
        /* Container */
        .container { max-width: 1400px; margin: 0 auto; background-color: white; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); padding: 25px; }
        h1 { color: #2c3e50; margin-bottom: 10px; border-bottom: 3px solid #c0392b; padding-bottom: 10px; font-size: 28px; }
        .subtitulo { color: #7f8c8d; margin-bottom: 20px; font-size: 14px; }
        
        /* Mensajes */
        .mensaje { padding: 12px 20px; margin-bottom: 20px; border-radius: 5px; font-weight: 500; animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { transform: translateY(-10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .mensaje.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .mensaje.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .mensaje.warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .mensaje.info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        
        /* Filtros de fecha */
        .filtros-fecha { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; padding: 15px 20px; margin-bottom: 20px; border: 1px solid #dee2e6; }
        .filtros-fecha h3 { color: #2c3e50; font-size: 14px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .fecha-input-group { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .fecha-field { flex: 1; min-width: 200px; }
        .fecha-field label { display: block; font-size: 12px; font-weight: 600; color: #495057; margin-bottom: 5px; }
        .fecha-field input { width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 6px; font-size: 14px; transition: all 0.3s; }
        .fecha-field input:focus { outline: none; border-color: #3498db; box-shadow: 0 0 0 3px rgba(52,152,219,0.1); }
        .btn-filtrar { background: linear-gradient(135deg, #3498db, #2980b9); color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.3s; }
        .btn-filtrar:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(52,152,219,0.3); }
        .btn-limpiar-filtros { background: #6c757d; color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.3s; text-decoration: none; display: inline-block; text-align: center; }
        .btn-limpiar-filtros:hover { background: #5a6268; transform: translateY(-2px); }
        .rango-fechas-activo { background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 8px 12px; margin-top: 10px; font-size: 12px; color: #155724; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
        .btn-quitar-fechas { background: none; border: none; color: #155724; cursor: pointer; font-size: 16px; padding: 0 5px; transition: transform 0.2s; text-decoration: none; }
        .btn-quitar-fechas:hover { transform: scale(1.2); color: #c0392b; }
        
        /* Estadísticas de REGISTROS (con repetición) */
        .estadisticas { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; border-radius: 8px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.3s; cursor: pointer; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card .numero { font-size: 2em; font-weight: bold; }
        .stat-card .etiqueta { font-size: 0.9em; opacity: 0.9; }
        .stat-card.total { background: linear-gradient(135deg, #3498db, #2980b9); }
        .stat-card.activos { background: linear-gradient(135deg, #27ae60, #229954); }
        .stat-card.pendiente { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .stat-card.inactivos { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .stat-card.bloqueados { background: linear-gradient(135deg, #95a5a6, #7f8c8d); }
        .stat-card.usuarios { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        
        /* Sub stats - USUARIOS ÚNICOS por estatus */
        .sub-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 10px; margin-bottom: 15px; }
        .sub-stat-card { background: white; padding: 10px; border-radius: 6px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid; cursor: pointer; transition: transform 0.2s; }
        .sub-stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        .sub-stat-card .numero { font-size: 1.3em; font-weight: bold; }
        .sub-stat-card .etiqueta { font-size: 11px; color: #7f8c8d; }
        .sub-stat-card .registros { font-size: 9px; color: #999; margin-top: 3px; }
        .sub-stat-card.activos-sub { border-left-color: #27ae60; }
        .sub-stat-card.inactivos-sub { border-left-color: #e74c3c; }
        .sub-stat-card.pendiente-sub { border-left-color: #f39c12; }
        .sub-stat-card.bloqueados-sub { border-left-color: #95a5a6; }
        
        /* Botones */
        .btn-reportes { background: linear-gradient(135deg, #9b59b6, #8e44ad); color: white; padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-size: 15px; font-weight: 600; display: inline-flex; align-items: center; gap: 10px; margin-bottom: 20px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(155, 89, 182, 0.3); }
        .btn-reportes:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(155, 89, 182, 0.4); }
        
        /* Charts */
        .charts-section { background: white; border-radius: 12px; margin-bottom: 25px; overflow: hidden; transition: all 0.3s ease; border: 1px solid #e0e0e0; }
        .charts-section.collapsed { max-height: 0; opacity: 0; margin: 0; padding: 0; border: none; overflow: hidden; }
        .charts-section.expanded { max-height: 600px; opacity: 1; margin-bottom: 25px; }
        .charts-row { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px; padding: 20px; }
        .chart-container { background-color: #f8f9fa; border-radius: 8px; padding: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #ecf0f1; }
        .chart-container h3 { color: #2c3e50; margin-bottom: 15px; font-size: 14px; display: flex; align-items: center; gap: 5px; }
        .chart-wrapper { position: relative; height: 180px; width: 100%; }
        .chart-legend { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; font-size: 10px; max-height: 80px; overflow-y: auto; padding: 5px; background-color: white; border-radius: 5px; }
        .legend-item { display: flex; align-items: center; gap: 5px; padding: 2px 6px; background-color: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .legend-color { width: 10px; height: 10px; border-radius: 3px; }
        
        /* Barra herramientas */
        .barra-herramientas { display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap; align-items: center; background-color: #f8f9fa; padding: 15px; border-radius: 8px; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; transition: all 0.3s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
        .btn-primary { background-color: #3498db; color: white; }
        .btn-success { background-color: #27ae60; color: white; }
        .btn-danger { background-color: #e74c3c; color: white; }
        .btn-warning { background-color: #f39c12; color: white; }
        .btn-info { background-color: #9b59b6; color: white; }
        .btn-secondary { background-color: #95a5a6; color: white; }
        .btn-backup { background-color: #34495e; color: white; }
        
        /* Buscador */
        .buscador { flex: 1; display: flex; gap: 10px; min-width: 300px; }
        .buscador input { flex: 2; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .buscador input:focus { outline: none; border-color: #3498db; }
        .filtro-estatus { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; cursor: pointer; }
        
        /* Dropdown */
        .dropdown { position: relative; display: inline-block; }
        .dropdown-content { display: none; position: absolute; background-color: white; min-width: 220px; box-shadow: 0 8px 16px rgba(0,0,0,0.2); z-index: 1000; border-radius: 5px; margin-top: 5px; right: 0; }
        .dropdown-content a { color: #2c3e50; padding: 12px 16px; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: background 0.3s; border-bottom: 1px solid #eee; }
        .dropdown-content a:hover { background-color: #f8f9fa; color: #3498db; }
        .dropdown:hover .dropdown-content { display: block; }
        
        /* Tabla */
        .table-container { overflow-x: auto; margin-top: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; background-color: white; }
        th { background: linear-gradient(135deg, #2c3e50, #34495e); color: white; padding: 12px; text-align: left; font-size: 14px; font-weight: 600; white-space: nowrap; }
        td { padding: 12px; border-bottom: 1px solid #ecf0f1; vertical-align: middle; }
        tr:hover { background-color: #f5f9ff; }
        
        /* Estatus badges */
        .estatus { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-align: center; display: inline-block; min-width: 80px; }
        .estatus.Activo { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .estatus.Inactivo { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .estatus.Pendiente { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .estatus.Bloqueado { background-color: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }
        
        /* Rol badges */
        .rol-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; white-space: nowrap; max-width: 150px; overflow: hidden; text-overflow: ellipsis; }
        .rol-administrador { background-color: #c0392b; color: white; }
        .rol-supervisor { background-color: #e67e22; color: white; }
        .rol-operador { background-color: #2980b9; color: white; }
        .rol-consultor { background-color: #27ae60; color: white; }
        .rol-usuario { background-color: #8e44ad; color: white; }
        
        /* Acciones */
        .acciones { display: flex; gap: 5px; flex-wrap: wrap; }
        .acciones a { padding: 5px 10px; border-radius: 3px; text-decoration: none; font-size: 11px; color: white; transition: all 0.3s; display: inline-flex; align-items: center; gap: 3px; }
        .acciones a:hover { transform: translateY(-2px); box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .acciones .editar { background-color: #f39c12; }
        .acciones .eliminar { background-color: #e74c3c; }
        .acciones .imprimir { background-color: #3498db; }
        
        /* Paginación */
        .paginacion { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 5px; }
        .info-registros { color: #7f8c8d; font-size: 14px; }
        
        /* Loading */
        .search-loading { display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0,0,0,0.8); color: white; padding: 15px 30px; border-radius: 50px; font-size: 16px; z-index: 9999; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0% { opacity: 0.6; } 50% { opacity: 1; } 100% { opacity: 0.6; } }
        
        /* Filtro activo indicator */
        .filtro-activo { background-color: #9b59b6; color: white; padding: 5px 10px; border-radius: 20px; font-size: 12px; display: inline-flex; align-items: center; gap: 5px; }
        
        /* Responsive */
        @media (max-width: 1400px) { .charts-row { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 1200px) { 
            .sidebar { width: 80px; }
            .sidebar .menu-item span:not(.fa), .sidebar .menu-section, .sidebar-header p, .sidebar-stats, .sidebar-footer span { display: none; }
            .sidebar-header h3 { font-size: 14px; }
            .menu-item { justify-content: center; padding: 15px; }
            .menu-item i { font-size: 22px; margin: 0; }
            .main-content { margin-left: 80px; }
            .menu-toggle { display: block; }
        }
        @media (max-width: 992px) { 
            .sub-stats { grid-template-columns: repeat(2, 1fr); } 
            .estadisticas { grid-template-columns: repeat(3, 1fr); } 
            .fecha-input-group { flex-direction: column; }
            .fecha-field { width: 100%; }
        }
        @media (max-width: 768px) { 
            .sidebar { left: -280px; width: 280px; }
            .sidebar .menu-item span, .sidebar .menu-section, .sidebar-header p, .sidebar-stats, .sidebar-footer span { display: flex; }
            .sidebar-header h3 { font-size: 22px; }
            .menu-item { justify-content: flex-start; padding: 12px 25px; }
            .menu-item i { font-size: 18px; margin: 0; }
            .sidebar.active { left: 0; }
            .main-content { margin-left: 0; padding: 15px; }
            .menu-toggle { display: block; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
            .charts-row { grid-template-columns: 1fr; }
            .barra-herramientas { flex-direction: column; }
            .buscador { width: 100%; flex-direction: column; }
            .sub-stats { grid-template-columns: 1fr; }
            .estadisticas { grid-template-columns: repeat(2, 1fr); }
            .top-bar { flex-wrap: wrap; gap: 10px; }
        }
        @media print { 
            .no-print, .barra-herramientas, .acciones, .dashboard-grid, .charts-section, .sidebar, .top-bar, .mini-stats, .sub-stats, .filtros-fecha { display: none; } 
            body { background: white; padding: 0; display: block; } 
            .main-content { margin-left: 0; padding: 0; } 
            .container { box-shadow: none; padding: 10px; } 
            th { background-color: #ddd !important; color: black !important; } 
        }
    </style>
</head>
<body>
    <div id="sidebarOverlay" class="sidebar-overlay"></div>
    
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>📋 BITÁCORA</h3>
            <p>Sistema de Control</p>
        </div>
        <div class="sidebar-menu">
            <div class="menu-section">PRINCIPAL</div>
            <a href="index.php" class="menu-item active">
                <i class="fas fa-home" style="color: #3498db;"></i><span>Dashboard</span>
            </a>
            <a href="crear.php" class="menu-item" onclick="registrarNavegacion('Nuevo Registro')">
                <i class="fas fa-plus-circle" style="color: #2ecc71;"></i><span>Nuevo Registro</span>
            </a>
            <a href="logs.php" class="menu-item" onclick="registrarNavegacion('Logs')">
                <i class="fas fa-history" style="color: #f39c12;"></i><span>Logs</span>
            </a>
            
            <div class="menu-section">GESTIÓN</div>
            <a href="importar.php" class="menu-item" onclick="registrarNavegacion('Importar')">
                <i class="fas fa-file-import" style="color: #9b59b6;"></i><span>Importar</span>
            </a>
            <a href="exportar.php" class="menu-item" onclick="registrarNavegacion('Exportar')">
                <i class="fas fa-file-export" style="color: #1abc9c;"></i><span>Exportar</span>
            </a>
            <a href="respaldo.php" class="menu-item" onclick="registrarNavegacion('Respaldo')">
                <i class="fas fa-database" style="color: #e67e22;"></i><span>Respaldo</span>
            </a>
            
            <div class="menu-section">REPORTES</div>
            <a href="reportes.php" class="menu-item" onclick="registrarNavegacion('Estadísticas')">
                <i class="fas fa-chart-bar" style="color: #e74c3c;"></i><span>Estadísticas</span>
            </a>
            <a href="imprimir.php" class="menu-item" onclick="registrarNavegacion('Imprimir')">
                <i class="fas fa-print" style="color: #16a085;"></i><span>Imprimir</span>
            </a>
            
            <div class="menu-section">CONFIGURACIÓN</div>
            <a href="configuracion.php" class="menu-item" onclick="registrarNavegacion('Ajustes')">
                <i class="fas fa-cog" style="color: #95a5a6;"></i><span>Ajustes</span>
            </a>
            <a href="usuarios.php" class="menu-item" onclick="registrarNavegacion('Usuarios')">
                <i class="fa-regular fa-user" style="color: #3498db;"></i><span>Usuarios</span>
            </a>
            <a href="usuarios_unicos.php" class="menu-item" onclick="registrarNavegacion('Usuarios_Unicos')">
                <i class="fa-regular fa-address-card" style="color: #27ae60;"></i><span>Usuarios Unicos</span>
            </a> 
            <a href="roles.php" class="menu-item" onclick="registrarNavegacion('roles')">
                <i class="fa-regular fa-user" style="color: #9b59b6;"></i><span>roles</span>
            </a> 
            <a href="logout.php" class="menu-item" onclick="return confirmarCerrarSesion();">
                <i class="fas fa-sign-out-alt" style="color: #e74c3c;"></i><span>Cerrar Sesión</span>
            </a>
        </div>
        <div class="sidebar-stats">
            <div class="stat-mini"><span>📊 Total registros:</span><span><?php echo $stats['total']; ?></span></div>
            <div class="stat-mini"><span>👥 Usuarios únicos:</span><span><?php echo $stats['usuarios']['total_unicos']; ?></span></div>
            <div class="stat-mini"><span>🟢 Activos únicos:</span><span><?php echo $stats['usuarios']['activos_unicos']; ?></span></div>
            <div class="stat-mini"><span>🔴 Inactivos únicos:</span><span><?php echo $stats['usuarios']['inactivos_unicos']; ?></span></div>
            <div class="stat-mini"><span>🟡 Pendiente únicos:</span><span><?php echo $stats['usuarios']['pendiente_unicos']; ?></span></div>
            <div class="stat-mini"><span>⚫ Bloqueados únicos:</span><span><?php echo $stats['usuarios']['bloqueados_unicos']; ?></span></div>
            <div class="stat-mini"><span>📈 Promedio por usuario:</span><span><?php echo $stats['usuarios']['promedio_registros_por_usuario']; ?></span></div>
        </div>
        <div class="sidebar-footer"><span>© 2024 - v2.0</span></div>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
            <div class="user-info">
                <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario'] ?? 'Admin'); ?></span>
                <div class="user-avatar"><?php echo substr($_SESSION['usuario'] ?? 'A', 0, 1); ?></div>
            </div>
        </div>

        <div class="container">
            <h1>📋 Bitácora de Registro</h1>
            <div class="subtitulo">Sistema de gestión y control de personal militar</div>
            
            <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="mensaje <?php echo $_SESSION['tipo_mensaje']; ?>">
                    <?php echo htmlspecialchars($_SESSION['mensaje']); 
                    unset($_SESSION['mensaje']); 
                    unset($_SESSION['tipo_mensaje']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_db'])): ?>
                <div class="mensaje error">
                    <?php echo htmlspecialchars($_SESSION['error_db']); 
                    unset($_SESSION['error_db']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($debug_message)): ?>
                <div class="mensaje info">
                    <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($debug_message); ?>
                </div>
            <?php endif; ?>
            
            <!-- FILTROS DE FECHA -->
            <div class="filtros-fecha no-print">
                <h3><i class="fas fa-calendar-alt"></i> Filtrar por fecha de registro</h3>
                <form method="GET" action="index.php" id="filtroFechaForm">
                    <div class="fecha-input-group">
                        <div class="fecha-field">
                            <label>📅 Desde:</label>
                            <input type="date" id="fecha_desde" name="fecha_desde" value="<?php echo htmlspecialchars($fecha_desde); ?>">
                        </div>
                        <div class="fecha-field">
                            <label>📅 Hasta:</label>
                            <input type="date" id="fecha_hasta" name="fecha_hasta" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                        </div>
                        <div class="fecha-field">
                            <label>&nbsp;</label>
                            <div style="display: flex; gap: 10px;">
                                <button type="submit" class="btn-filtrar" onclick="registrarFiltroFecha()">
                                    <i class="fas fa-search"></i> Filtrar
                                </button>
                                <?php if (!empty($fecha_desde) || !empty($fecha_hasta)): ?>
                                    <a href="?limpiar_fechas=1" class="btn-limpiar-filtros" onclick="return confirmarLimpiarFechas()">
                                        <i class="fas fa-trash-alt"></i> Limpiar
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </form>
                
                <?php if (!empty($fecha_desde) || !empty($fecha_hasta)): ?>
                    <div class="rango-fechas-activo">
                        <span>
                            <i class="fas fa-filter"></i> 
                            <strong>Filtro activo:</strong> 
                            <?php if (!empty($fecha_desde)): ?>
                                Desde: <?php echo date('d/m/Y', strtotime($fecha_desde)); ?>
                            <?php endif; ?>
                            <?php if (!empty($fecha_desde) && !empty($fecha_hasta)): ?>
                                -
                            <?php endif; ?>
                            <?php if (!empty($fecha_hasta)): ?>
                                Hasta: <?php echo date('d/m/Y', strtotime($fecha_hasta)); ?>
                            <?php endif; ?>
                            (Total registros: <?php echo $stats['total']; ?>)
                        </span>
                        <a href="?limpiar_fechas=1" class="btn-quitar-fechas" onclick="return confirmarLimpiarFechas()" title="Quitar filtro de fechas">
                            <i class="fas fa-times-circle"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tarjetas de estadísticas por REGISTROS (con repetición) -->
            <div class="estadisticas no-print">
                <div class="stat-card total" onclick="aplicarFiltroPorEstatus('todos')">
                    <div class="numero"><?php echo $stats['total']; ?></div>
                    <div class="etiqueta">Total Registros</div>
                </div>
                <div class="stat-card activos" onclick="aplicarFiltroPorEstatus('Activo')">
                    <div class="numero"><?php echo $stats['activos']; ?></div>
                    <div class="etiqueta">Registros Activos</div>
                </div>
                <div class="stat-card pendiente" onclick="aplicarFiltroPorEstatus('Pendiente')">
                    <div class="numero"><?php echo $stats['pendiente']; ?></div>
                    <div class="etiqueta">Registros Pendiente</div>
                </div>
                <div class="stat-card inactivos" onclick="aplicarFiltroPorEstatus('Inactivo')">
                    <div class="numero"><?php echo $stats['inactivos']; ?></div>
                    <div class="etiqueta">Registros Inactivos</div>
                </div>
                <div class="stat-card bloqueados" onclick="aplicarFiltroPorEstatus('Bloqueado')">
                    <div class="numero"><?php echo $stats['bloqueados']; ?></div>
                    <div class="etiqueta">Registros Bloqueados</div>
                </div>
                <div class="stat-card usuarios" onclick="aplicarFiltroUsuariosUnicos()">
                    <div class="numero"><?php echo $stats['usuarios']['total_unicos']; ?></div>
                    <div class="etiqueta">Usuarios Únicos</div>
                    <div style="font-size: 11px; margin-top: 5px; opacity: 0.8;">Prom: <?php echo $stats['usuarios']['promedio_registros_por_usuario']; ?> c/u</div>
                </div>
            </div>
            
            <!-- Tarjetas de USUARIOS ÚNICOS por estatus (sin repetición) -->
            <div class="sub-stats no-print">
                <div class="sub-stat-card activos-sub" onclick="aplicarFiltroUsuarioUnico('activos_unicos')">
                    <div class="numero"><?php echo $stats['usuarios']['activos_unicos']; ?></div>
                    <div class="etiqueta">👥 Usuarios Activos Únicos</div>
                    <div style="font-size: 10px; color: #27ae60; font-weight: bold;">
                        <?php echo $stats['usuarios']['porcentaje_activos']; ?>% del total
                    </div>
                    <div class="registros">📄 <?php echo $stats['activos']; ?> registros activos</div>
                </div>
                <div class="sub-stat-card inactivos-sub" onclick="aplicarFiltroUsuarioUnico('inactivos_unicos')">
                    <div class="numero"><?php echo $stats['usuarios']['inactivos_unicos']; ?></div>
                    <div class="etiqueta">👥 Usuarios Inactivos Únicos</div>
                    <div style="font-size: 10px; color: #e74c3c; font-weight: bold;">
                        <?php echo $stats['usuarios']['porcentaje_inactivos']; ?>% del total
                    </div>
                    <div class="registros">📄 <?php echo $stats['inactivos']; ?> registros inactivos</div>
                </div>
                <div class="sub-stat-card pendiente-sub" onclick="aplicarFiltroUsuarioUnico('pendiente_unicos')">
                    <div class="numero"><?php echo $stats['usuarios']['pendiente_unicos']; ?></div>
                    <div class="etiqueta">👥 Usuarios Pendiente Únicos</div>
                    <div style="font-size: 10px; color: #f39c12; font-weight: bold;">
                        <?php echo $stats['usuarios']['porcentaje_pendiente']; ?>% del total
                    </div>
                    <div class="registros">📄 <?php echo $stats['pendiente']; ?> registros pendientes</div>
                </div>
                <div class="sub-stat-card bloqueados-sub" onclick="aplicarFiltroUsuarioUnico('bloqueados_unicos')">
                    <div class="numero"><?php echo $stats['usuarios']['bloqueados_unicos']; ?></div>
                    <div class="etiqueta">👥 Usuarios Bloqueados Únicos</div>
                    <div style="font-size: 10px; color: #95a5a6; font-weight: bold;">
                        <?php echo $stats['usuarios']['porcentaje_bloqueados']; ?>% del total
                    </div>
                    <div class="registros">📄 <?php echo $stats['bloqueados']; ?> registros bloqueados</div>
                </div>
            </div>
            
            <button class="btn-reportes no-print" id="btnMostrarReportes" onclick="toggleReportes()">
                <i class="fas fa-chart-pie" id="iconoReportes"></i>
                <span id="textoBotonReportes">Ocultar Reportes de Estadísticas</span>
            </button>
            
            <div id="chartsSection" class="charts-section expanded no-print">
                <div class="charts-row">
                    <div class="chart-container">
                        <h3><span>👥 Roles</span><span><?php echo array_sum($roles_data); ?></span></h3>
                        <div class="chart-wrapper"><canvas id="rolesChart"></canvas></div>
                        <div class="chart-legend" id="rolesLegend"></div>
                    </div>
                    <div class="chart-container">
                        <h3><span>👤 Top Usuarios</span><span><?php echo $stats['usuarios']['total_unicos']; ?> únicos</span></h3>
                        <div class="chart-wrapper"><canvas id="usuariosChart"></canvas></div>
                        <div class="chart-legend" id="usuariosLegend"></div>
                    </div>
                    <div class="chart-container">
                        <h3><span>📊 Usuarios por Estatus</span><span><?php echo $stats['usuarios']['total_unicos']; ?> únicos</span></h3>
                        <div class="chart-wrapper"><canvas id="usuariosEstatusChart"></canvas></div>
                        <div class="chart-legend" id="usuariosEstatusLegend"></div>
                    </div>
                    <div class="chart-container">
                        <h3><span>📈 Distribución General</span></h3>
                        <div class="chart-wrapper"><canvas id="distribucionChart"></canvas></div>
                        <div class="chart-legend" id="distribucionLegend"></div>
                    </div>
                </div>
            </div>
            
            <div class="barra-herramientas no-print">
                <a href="crear.php" class="btn btn-primary"><span>➕</span> Nuevo Registro</a>
                <div class="dropdown">
                    <button class="btn btn-info"><span>📥</span> Exportar ▼</button>
                    <div class="dropdown-content">
                        <a href="javascript:void(0);" onclick="registrarExportacion('CSV', 'todos', 'index.php')"><span>📊</span> Exportar TODO a CSV</a>
                        <a href="javascript:void(0);" onclick="registrarExportacion('Excel', 'todos', 'index.php')"><span>📗</span> Exportar TODO a Excel</a>
                        <a href="javascript:void(0);" onclick="registrarExportacion('PDF', 'todos', 'index.php')"><span>📘</span> Exportar TODO a PDF</a>
                    </div>
                </div>
                <a href="importar.php" class="btn btn-warning"><span>📤</span> Importar</a>
                <a href="respaldo.php" class="btn btn-backup"><span>💾</span> Respaldo</a>
                <a href="javascript:void(0);" onclick="imprimirSeleccionados()" class="btn btn-success"><span>🖨️</span> Imprimir Seleccionados</a>
                <button class="btn btn-secondary" id="btnQuitarFiltro" onclick="quitarFiltroUsuarioUnico()"><span>🗑️</span> Quitar filtro</button>
                <div class="buscador">
                    <input type="text" id="buscador" placeholder="🔍 Buscar en todas las columnas..." autocomplete="off">
                    <select id="filtroEstatus" class="filtro-estatus">
                        <option value="">Todos los estatus</option>
                        <option value="Activo">🟢 Activo</option>
                        <option value="Inactivo">🔴 Inactivo</option>
                        <option value="Pendiente">🟡 Pendiente</option>
                        <option value="Bloqueado">⚫ Bloqueado</option>
                    </select>
                    <input type="hidden" id="filtroUsuarioUnico" value="">
                </div>
            </div>
            
            <div id="filtroInfo" class="no-print" style="margin-bottom: 10px;"></div>
            
            <div id="searchLoading" class="search-loading">🔍 Buscando...</div>
            <form id="formSeleccionados" method="POST" action="imprimir_seleccionados.php" target="_blank">
                <div class="table-container">
                    <table id="tablaRegistros">
                        <thead>
                            <tr>
                                <th class="no-print" style="width: 30px;"><input type="checkbox" id="seleccionarTodos" class="seleccionar-todos" title="Seleccionar todos"></th>
                                <th>#</th>
                                <th>RANGO MILITAR</th>
                                <th>USUARIO</th>
                                <th>ROL</th>
                                <th>ESTATUS</th>
                                <th>DESIGNACIÓN / CONTROL</th>
                                <th>FECHA DE ACTUALIZACION</th>
                                <th>CONFIGURACIÓN</th>
                                <th class="no-print">ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($resultado && $resultado->num_rows > 0): ?>
                                <?php $contador = 1; ?>
                                <?php while ($row = $resultado->fetch_assoc()): ?>
                                    <tr>
                                        <td class="no-print" style="text-align: center;">
                                            <input type="checkbox" name="seleccionados[]" value="<?php echo htmlspecialchars($row['id_registro'] ?? ''); ?>" class="seleccionar-item">
                                        </td>
                                        <td style="text-align: center; font-weight: bold; background-color: #f8f9fa;">
                                            <?php echo $contador++; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['rango_militar'] ?? 'No especificado'); ?></td>
                                        <td>
                                            <?php if (isset($row['usuario']) && !empty(trim($row['usuario']))): 
                                                $usuario = trim($row['usuario']);
                                                $color_hash = abs(crc32($usuario)) % count($color_palette_usuarios);
                                                $user_color = $color_palette_usuarios[$color_hash];
                                            ?>
                                                <span style="background-color: <?php echo $user_color; ?>20; color: <?php echo $user_color; ?>; padding: 3px 8px; border-radius: 12px; font-weight: 500;">
                                                    <?php echo htmlspecialchars($usuario); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #999; font-style: italic;">— Sin usuario —</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($row['roles']) && !empty($row['roles'])): 
                                                $rol = $row['roles'];
                                                $rol_class = '';
                                                $rol_icon = '';
                                                if (stripos($rol, 'ADMIN') !== false) { 
                                                    $rol_class = 'rol-administrador'; 
                                                    $rol_icon = '👑'; 
                                                } elseif (stripos($rol, 'SUPER') !== false) { 
                                                    $rol_class = 'rol-supervisor'; 
                                                    $rol_icon = '👁️'; 
                                                } elseif (stripos($rol, 'OPER') !== false) { 
                                                    $rol_class = 'rol-operador'; 
                                                    $rol_icon = '⚙️'; 
                                                } elseif (stripos($rol, 'CONSUL') !== false) { 
                                                    $rol_class = 'rol-consultor'; 
                                                    $rol_icon = '📊'; 
                                                } else { 
                                                    $rol_class = 'rol-usuario'; 
                                                    $rol_icon = '👤'; 
                                                }
                                            ?>
                                                <span class="rol-badge <?php echo $rol_class; ?>" title="<?php echo htmlspecialchars($rol); ?>">
                                                    <?php echo $rol_icon . ' ' . htmlspecialchars(mb_strimwidth($rol, 0, 20, "...")); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #999;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="estatus <?php echo htmlspecialchars($row['estatus'] ?? 'Activo'); ?>">
                                                <?php echo htmlspecialchars($row['estatus'] ?? 'Activo'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars(substr($row['designacion'] ?? '', 0, 50)) . (strlen($row['designacion'] ?? '') > 50 ? '...' : ''); ?></td>
                                        <td style="white-space: nowrap;">
                                            <?php 
                                            $fecha_registro_raw = $row['fecha_registro'] ?? '';
                                            if (!empty($fecha_registro_raw) && $fecha_registro_raw != '0000-00-00') {
                                                $timestamp = strtotime($fecha_registro_raw);
                                                $formato = 'd/m/Y';
                                                if (date('H:i:s', $timestamp) != '00:00:00') {
                                                    $formato = 'd/m/Y H:i';
                                                }
                                                echo '<span title="' . htmlspecialchars($fecha_registro_raw) . '">' . date($formato, $timestamp) . '</span>';
                                            } else {
                                                echo '<span style="color: #999;">—</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars(substr($row['configuracion'] ?? '', 0, 30)) . (strlen($row['configuracion'] ?? '') > 30 ? '...' : ''); ?></td>
                                        <td class="acciones no-print">
                                            <a href="editar.php?id=<?php echo htmlspecialchars($row['id_registro'] ?? ''); ?>" class="editar" title="Editar registro" onclick="registrarEdicion(<?php echo $row['id_registro']; ?>)">✏️ Editar</a>
                                            <a href="index.php?eliminar=<?php echo htmlspecialchars($row['id_registro'] ?? ''); ?>" class="eliminar" onclick="return confirmarEliminacion(<?php echo $row['id_registro']; ?>, '<?php echo addslashes($row['rango_militar'] ?? 'No especificado'); ?>')" title="Eliminar registro">🗑️ Eliminar</a>
                                            <a href="imprimir.php?id=<?php echo htmlspecialchars($row['id_registro'] ?? ''); ?>" class="imprimir" target="_blank" title="Imprimir registro" onclick="registrarImpresion(<?php echo $row['id_registro']; ?>)">🖨️ Imprimir</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" style="text-align: center; padding: 40px; color: #7f8c8d;">
                                        <span style="font-size: 48px;">📭</span><br>
                                        <strong>No hay registros en la bitácora</strong><br>
                                        <span style="font-size: 14px;">Comienza creando un nuevo registro con el botón "Nuevo Registro"</span>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            
            <div class="paginacion no-print">
                <div class="info-registros">
                    <strong>Total registros:</strong> <?php echo $resultado ? $resultado->num_rows : 0; ?> | 
                    <strong>Usuarios únicos:</strong> <?php echo $stats['usuarios']['total_unicos']; ?> | 
                    <strong>Activos únicos:</strong> <?php echo $stats['usuarios']['activos_unicos']; ?> | 
                    <strong>Inactivos únicos:</strong> <?php echo $stats['usuarios']['inactivos_unicos']; ?> |
                    <strong>Pendiente únicos:</strong> <?php echo $stats['usuarios']['pendiente_unicos']; ?>
                </div>
                <div class="info-registros">
                    <span id="registrosVisibles">Mostrando todos los registros</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        let timeoutId = null;
        const totalRegistros = <?php echo $resultado ? $resultado->num_rows : 0; ?>;
        let reportesVisibles = true;
        
        // Datos para gráficos
        const rolesLabels = <?php echo json_encode($roles_labels); ?>;
        const rolesData = <?php echo json_encode($roles_data); ?>;
        const rolesColors = <?php echo json_encode($roles_colors); ?>;
        const usuariosLabels = <?php echo json_encode($usuarios_labels); ?>;
        const usuariosData = <?php echo json_encode($usuarios_data); ?>;
        const usuariosColors = <?php echo json_encode($usuarios_colors); ?>;
        const usuariosEstatusLabels = <?php echo json_encode($usuarios_estatus_labels); ?>;
        const usuariosEstatusData = <?php echo json_encode($usuarios_estatus_data); ?>;
        const usuariosEstatusColors = <?php echo json_encode($usuarios_estatus_colors); ?>;
        
        // Función para registrar acciones
        function registrarAccion(accion, detalles = '', modulo = 'index.php') {
            fetch('log_accion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    accion: accion,
                    detalles: typeof detalles === 'string' ? detalles : JSON.stringify(detalles),
                    modulo: modulo
                })
            }).catch(error => console.error('Error registrando acción:', error));
        }
        
        function confirmarLimpiarFechas() {
            registrarAccion('limpió_filtros_fecha', {}, 'index.php');
            return confirm('¿Estás seguro de que deseas limpiar los filtros de fecha?');
        }
        
        function confirmarEliminacion(id, rango) {
            const confirmado = confirm('¿Estás seguro de eliminar este registro?\nID: ' + id + '\nRango: ' + rango);
            if (confirmado) {
                registrarAccion('confirmó_eliminación', { id_registro: id, rango_militar: rango }, 'index.php');
            } else {
                registrarAccion('canceló_eliminación', { id_registro: id }, 'index.php');
            }
            return confirmado;
        }
        
        function confirmarCerrarSesion() {
            registrarAccion('cerró_sesión', { timestamp: new Date().toISOString() }, 'index.php');
            return confirm('¿Estás seguro de que deseas cerrar sesión?');
        }
        
        function registrarEdicion(id) {
            registrarAccion('inició_edición', { id_registro: id }, 'index.php');
        }
        
        function registrarImpresion(id) {
            registrarAccion('imprimió_registro_individual', { id_registro: id }, 'index.php');
        }
        
        function registrarNavegacion(modulo) {
            registrarAccion('navegó_a_' + modulo.toLowerCase().replace(/ /g, '_'), { destino: modulo }, modulo.toLowerCase());
        }
        
        function registrarExportacion(formato, tipo, desde) {
            registrarAccion('exportó_datos', { formato: formato, tipo: tipo, desde: desde }, 'index.php');
            const fechaDesde = document.getElementById('fecha_desde') ? document.getElementById('fecha_desde').value : '';
            const fechaHasta = document.getElementById('fecha_hasta') ? document.getElementById('fecha_hasta').value : '';
            let url = 'exportar.php?formato=' + formato.toLowerCase() + '&tipo=' + tipo;
            if (fechaDesde) url += '&fecha_desde=' + fechaDesde;
            if (fechaHasta) url += '&fecha_hasta=' + fechaHasta;
            setTimeout(() => {
                window.location.href = url;
            }, 100);
        }
        
        function registrarFiltro(tipo, valor) {
            registrarAccion('aplicó_filtro', { tipo: tipo, valor: valor }, 'index.php');
        }
        
        function registrarFiltroFecha() {
            const fechaDesde = document.getElementById('fecha_desde').value;
            const fechaHasta = document.getElementById('fecha_hasta').value;
            registrarAccion('aplicó_filtro_fecha', { 
                fecha_desde: fechaDesde, 
                fecha_hasta: fechaHasta 
            }, 'index.php');
            return true;
        }
        
        function aplicarFiltroPorEstatus(estatus) {
            const filtroEstatus = document.getElementById('filtroEstatus');
            if (filtroEstatus && estatus !== 'todos') {
                filtroEstatus.value = estatus;
                document.getElementById('filtroUsuarioUnico').value = '';
                registrarFiltro('estatus', estatus);
                buscarRegistros();
            } else if (estatus === 'todos') {
                filtroEstatus.value = '';
                document.getElementById('filtroUsuarioUnico').value = '';
                registrarFiltro('estatus', 'todos');
                buscarRegistros();
            }
        }
        
        function aplicarFiltroUsuarioUnico(tipo) {
            document.getElementById('filtroUsuarioUnico').value = tipo;
            document.getElementById('filtroEstatus').value = '';
            registrarFiltro('usuario_unico', tipo);
            buscarRegistros();
        }
        
        function aplicarFiltroUsuariosUnicos() {
            document.getElementById('filtroUsuarioUnico').value = 'todos_unicos';
            document.getElementById('filtroEstatus').value = '';
            document.getElementById('buscador').value = '';
            registrarFiltro('usuario_unico', 'todos_unicos');
            buscarRegistros();
        }
        
        function quitarFiltroUsuarioUnico() {
            document.getElementById('filtroUsuarioUnico').value = '';
            document.getElementById('buscador').value = '';
            document.getElementById('filtroEstatus').value = '';
            registrarAccion('quitó_filtro_usuario_unico', {}, 'index.php');
            buscarRegistros();
        }
        
        function actualizarInfoFiltro() {
            const filtroUsuario = document.getElementById('filtroUsuarioUnico').value;
            const filtroEstatus = document.getElementById('filtroEstatus').value;
            const filtroInfoDiv = document.getElementById('filtroInfo');
            
            if (filtroInfoDiv) {
                if (filtroUsuario || filtroEstatus) {
                    let textoFiltro = '';
                    if (filtroUsuario === 'todos_unicos') {
                        textoFiltro = '🟣 Mostrando solo usuarios únicos (un registro por usuario)';
                    } else if (filtroUsuario === 'activos_unicos') {
                        textoFiltro = '🟢 Mostrando usuarios únicos con estado ACTIVO';
                    } else if (filtroUsuario === 'inactivos_unicos') {
                        textoFiltro = '🔴 Mostrando usuarios únicos con estado INACTIVO';
                    } else if (filtroUsuario === 'pendiente_unicos') {
                        textoFiltro = '🟡 Mostrando usuarios únicos con estado PENDIENTE';
                    } else if (filtroUsuario === 'bloqueados_unicos') {
                        textoFiltro = '⚫ Mostrando usuarios únicos con estado BLOQUEADO';
                    } else if (filtroEstatus) {
                        textoFiltro = `📋 Mostrando registros con estatus: ${filtroEstatus}`;
                    }
                    
                    if (textoFiltro) {
                        filtroInfoDiv.innerHTML = `<div class="filtro-activo"><i class="fas fa-filter"></i> ${textoFiltro}</div>`;
                    } else {
                        filtroInfoDiv.innerHTML = '';
                    }
                } else {
                    filtroInfoDiv.innerHTML = '';
                }
            }
        }
        
        function buscarRegistros() {
            const texto = document.getElementById('buscador').value;
            const estatus = document.getElementById('filtroEstatus').value;
            const filtroUsuario = document.getElementById('filtroUsuarioUnico').value;
            const fechaDesde = document.getElementById('fecha_desde') ? document.getElementById('fecha_desde').value : '';
            const fechaHasta = document.getElementById('fecha_hasta') ? document.getElementById('fecha_hasta').value : '';
            const loading = document.getElementById('searchLoading');
            const tbody = document.querySelector('#tablaRegistros tbody');
            
            if (!tbody) return;
            
            actualizarInfoFiltro();
            
            const btnQuitar = document.getElementById('btnQuitarFiltro');
            if (btnQuitar) {
                if (filtroUsuario === 'todos_unicos') {
                    btnQuitar.innerHTML = '<span>🗑️</span> Quitar filtro (Usuarios Únicos)';
                } else if (filtroUsuario) {
                    btnQuitar.innerHTML = '<span>🗑️</span> Quitar filtro de usuarios';
                } else if (estatus) {
                    btnQuitar.innerHTML = '<span>🗑️</span> Quitar filtro de estatus';
                } else {
                    btnQuitar.innerHTML = '<span>🗑️</span> Quitar filtro';
                }
            }
            
            if (texto || estatus || filtroUsuario || fechaDesde || fechaHasta) {
                registrarAccion('realizó_búsqueda', { 
                    termino: texto, 
                    estatus: estatus, 
                    filtro_usuario: filtroUsuario,
                    fecha_desde: fechaDesde,
                    fecha_hasta: fechaHasta,
                    timestamp: new Date().toISOString()
                }, 'index.php');
            }
            
            loading.style.display = 'block';
            
            fetch(`buscar.php?texto=${encodeURIComponent(texto)}&estatus=${encodeURIComponent(estatus)}&filtro_usuario=${encodeURIComponent(filtroUsuario)}&fecha_desde=${encodeURIComponent(fechaDesde)}&fecha_hasta=${encodeURIComponent(fechaHasta)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.html) {
                        tbody.innerHTML = data.html;
                    }
                    document.getElementById('registrosVisibles').innerText = `Mostrando ${data.total || 0} de ${totalRegistros} registros`;
                    loading.style.display = 'none';
                    inicializarEventosCheckboxes();
                })
                .catch(error => {
                    console.error('Error:', error);
                    loading.style.display = 'none';
                    registrarAccion('error_búsqueda', { error: error.message }, 'index.php');
                });
        }
        
        function buscarConDebounce() {
            if (timeoutId) clearTimeout(timeoutId);
            timeoutId = setTimeout(buscarRegistros, 400);
        }
        
        function toggleReportes() {
            const chartsSection = document.getElementById('chartsSection');
            const btn = document.getElementById('btnMostrarReportes');
            const icono = document.getElementById('iconoReportes');
            const texto = document.getElementById('textoBotonReportes');
            const accion = reportesVisibles ? 'ocultó_reportes' : 'mostró_reportes';
            
            registrarAccion(accion, { seccion: 'gráficos_estadísticas' }, 'index.php');
            
            if (reportesVisibles) {
                chartsSection.classList.remove('expanded');
                chartsSection.classList.add('collapsed');
                if (icono) icono.style.transform = 'rotate(180deg)';
                if (texto) texto.innerText = 'Mostrar Reportes de Estadísticas';
            } else {
                chartsSection.classList.remove('collapsed');
                chartsSection.classList.add('expanded');
                if (icono) icono.style.transform = 'rotate(0deg)';
                if (texto) texto.innerText = 'Ocultar Reportes de Estadísticas';
            }
            reportesVisibles = !reportesVisibles;
        }
        
        function imprimirSeleccionados() {
            const seleccionados = document.querySelectorAll('.seleccionar-item:checked');
            if (seleccionados.length === 0) { 
                alert('⚠️ Por favor, selecciona al menos un registro para imprimir.'); 
                return false; 
            }
            
            const ids = Array.from(seleccionados).map(cb => cb.value);
            registrarAccion('imprimió_registros_seleccionados', { 
                ids: ids, 
                cantidad: ids.length,
                timestamp: new Date().toISOString()
            }, 'index.php');
            
            document.getElementById('formSeleccionados').submit();
        }
        
        function inicializarEventosCheckboxes() {
            const selectAll = document.getElementById('seleccionarTodos');
            if (selectAll) { 
                selectAll.removeEventListener('change', selectAllHandler); 
                selectAll.addEventListener('change', selectAllHandler); 
            }
            document.querySelectorAll('.seleccionar-item').forEach(checkbox => { 
                checkbox.removeEventListener('change', checkboxHandler); 
                checkbox.addEventListener('change', checkboxHandler); 
            });
        }
        
        function selectAllHandler(e) { 
            const checked = e.target.checked;
            document.querySelectorAll('.seleccionar-item').forEach(cb => { cb.checked = checked; });
            registrarAccion(checked ? 'seleccionó_todos' : 'deseleccionó_todos', 
                { cantidad: document.querySelectorAll('.seleccionar-item').length }, 'index.php');
        }
        
        function checkboxHandler() { 
            const total = document.querySelectorAll('.seleccionar-item').length; 
            const checked = document.querySelectorAll('.seleccionar-item:checked').length; 
            const selectAll = document.getElementById('seleccionarTodos'); 
            if (selectAll) { selectAll.checked = total === checked && total > 0; }
            
            if (checked > 0) {
                registrarAccion('seleccionó_registro', { 
                    seleccionados: checked,
                    total_disponibles: total
                }, 'index.php');
            }
        }
        
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const isMobile = window.innerWidth <= 768;
            
            sidebar.classList.toggle('active');
            if (overlay) overlay.classList.toggle('active');
            
            registrarAccion('toggle_sidebar', { 
                estado: sidebar.classList.contains('active') ? 'abierto' : 'cerrado',
                dispositivo: isMobile ? 'móvil' : 'desktop'
            }, 'index.php');
        }
        
        function cerrarSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                if (overlay) overlay.classList.remove('active');
                registrarAccion('sidebar_cerrado', {}, 'index.php');
            }
        }
        
        function ajustarSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            const isMobile = window.innerWidth <= 768;
            
            if (isMobile) {
                sidebar.classList.remove('active');
                if (mainContent) mainContent.style.marginLeft = '0';
            } else if (window.innerWidth <= 1200) {
                sidebar.classList.remove('active');
                if (mainContent) mainContent.style.marginLeft = '80px';
            } else {
                sidebar.classList.remove('active');
                if (mainContent) mainContent.style.marginLeft = '280px';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            registrarAccion('carga_completa_página', { 
                timestamp: new Date().toISOString(),
                total_registros: totalRegistros,
                usuarios_unicos: <?php echo $stats['usuarios']['total_unicos']; ?>
            }, 'index.php');
            
            const menuToggle = document.getElementById('menuToggle');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (menuToggle) {
                menuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleSidebar();
                });
            }
            
            if (overlay) {
                overlay.addEventListener('click', cerrarSidebar);
            }
            
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    cerrarSidebar();
                }
            });
            
            window.addEventListener('resize', ajustarSidebar);
            ajustarSidebar();
            
            const ctxRoles = document.getElementById('rolesChart');
            if (ctxRoles) {
                new Chart(ctxRoles.getContext('2d'), { 
                    type: 'pie', 
                    data: { labels: rolesLabels, datasets: [{ data: rolesData, backgroundColor: rolesColors, borderColor: 'white', borderWidth: 2 }] }, 
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(context) { const label = context.label || ''; const value = context.raw || 0; const total = context.dataset.data.reduce((a, b) => a + b, 0); const percentage = Math.round((value / total) * 100); return `${label}: ${value} (${percentage}%)`; } } } } } 
                });
            }
            
            const ctxUsuarios = document.getElementById('usuariosChart');
            if (ctxUsuarios) {
                new Chart(ctxUsuarios.getContext('2d'), { 
                    type: 'pie', 
                    data: { labels: usuariosLabels, datasets: [{ data: usuariosData, backgroundColor: usuariosColors, borderColor: 'white', borderWidth: 2 }] }, 
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(context) { const label = context.label || ''; const value = context.raw || 0; const total = context.dataset.data.reduce((a, b) => a + b, 0); const percentage = Math.round((value / total) * 100); return `${label}: ${value} (${percentage}%)`; } } } } } 
                });
            }
            
            const ctxUsuariosEstatus = document.getElementById('usuariosEstatusChart');
            if (ctxUsuariosEstatus) {
                new Chart(ctxUsuariosEstatus.getContext('2d'), { 
                    type: 'pie', 
                    data: { labels: usuariosEstatusLabels, datasets: [{ data: usuariosEstatusData, backgroundColor: usuariosEstatusColors, borderColor: 'white', borderWidth: 2 }] }, 
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(context) { const label = context.label || ''; const value = context.raw || 0; const total = context.dataset.data.reduce((a, b) => a + b, 0); const percentage = Math.round((value / total) * 100); return `${label}: ${value} (${percentage}%)`; } } } } } 
                });
            }
            
            const ctxDistribucion = document.getElementById('distribucionChart');
            if (ctxDistribucion) {
                new Chart(ctxDistribucion.getContext('2d'), { 
                    type: 'bar', 
                    data: { 
                        labels: ['Activos', 'Inactivos', 'Pendiente', 'Bloqueados'], 
                        datasets: [{ 
                            label: 'Cantidad de registros',
                            data: [<?php echo $stats['activos']; ?>, <?php echo $stats['inactivos']; ?>, <?php echo $stats['pendiente']; ?>, <?php echo $stats['bloqueados']; ?>], 
                            backgroundColor: ['#27ae60', '#e74c3c', '#f39c12', '#95a5a6'],
                            borderColor: 'white',
                            borderWidth: 2
                        }] 
                    }, 
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: true, position: 'bottom' } } } 
                });
            }
            
            const rolesLegend = document.getElementById('rolesLegend');
            if (rolesLegend) {
                const totalRoles = rolesData.reduce((a, b) => a + b, 0);
                rolesLabels.forEach((label, index) => { 
                    const value = rolesData[index]; 
                    const percentage = totalRoles > 0 ? Math.round((value / totalRoles) * 100) : 0;
                    const legendItem = document.createElement('div'); 
                    legendItem.className = 'legend-item'; 
                    legendItem.innerHTML = `<span class="legend-color" style="background-color: ${rolesColors[index]};"></span><span title="${label}">${label.length > 15 ? label.substring(0, 15) + '...' : label}</span><span style="font-weight: bold; margin-left: auto;">${percentage}%</span>`; 
                    rolesLegend.appendChild(legendItem); 
                });
            }
            
            const usuariosLegend = document.getElementById('usuariosLegend');
            if (usuariosLegend) {
                const totalUsuarios = usuariosData.reduce((a, b) => a + b, 0);
                usuariosLabels.forEach((label, index) => { 
                    const value = usuariosData[index]; 
                    const percentage = totalUsuarios > 0 ? Math.round((value / totalUsuarios) * 100) : 0;
                    const legendItem = document.createElement('div'); 
                    legendItem.className = 'legend-item'; 
                    legendItem.innerHTML = `<span class="legend-color" style="background-color: ${usuariosColors[index]};"></span><span title="${label}">${label.length > 15 ? label.substring(0, 15) + '...' : label}</span><span style="font-weight: bold; margin-left: auto;">${percentage}%</span>`; 
                    usuariosLegend.appendChild(legendItem); 
                });
            }
            
            const usuariosEstatusLegend = document.getElementById('usuariosEstatusLegend');
            if (usuariosEstatusLegend) {
                const totalUsuariosEstatus = usuariosEstatusData.reduce((a, b) => a + b, 0);
                usuariosEstatusLabels.forEach((label, index) => { 
                    const value = usuariosEstatusData[index]; 
                    const percentage = totalUsuariosEstatus > 0 ? Math.round((value / totalUsuariosEstatus) * 100) : 0;
                    const legendItem = document.createElement('div'); 
                    legendItem.className = 'legend-item'; 
                    legendItem.innerHTML = `<span class="legend-color" style="background-color: ${usuariosEstatusColors[index]};"></span><span>${label}</span><span style="font-weight: bold; margin-left: auto;">${percentage}%</span>`; 
                    usuariosEstatusLegend.appendChild(legendItem); 
                });
            }
            
            inicializarEventosCheckboxes();
            
            const buscador = document.getElementById('buscador');
            const filtroEstatus = document.getElementById('filtroEstatus');
            if (buscador) buscador.addEventListener('input', buscarConDebounce);
            if (filtroEstatus) filtroEstatus.addEventListener('change', buscarRegistros);
            
            const fechaDesde = document.getElementById('fecha_desde');
            const fechaHasta = document.getElementById('fecha_hasta');
            if (fechaDesde) fechaDesde.addEventListener('change', function() { 
                document.getElementById('filtroFechaForm').submit(); 
            });
            if (fechaHasta) fechaHasta.addEventListener('change', function() { 
                document.getElementById('filtroFechaForm').submit(); 
            });
            
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'n') { 
                    e.preventDefault(); 
                    registrarAccion('atajo_teclado', { tecla: 'Ctrl+N', accion: 'nuevo_registro' }, 'index.php');
                    window.location.href = 'crear.php'; 
                }
                if (e.ctrlKey && e.key === 'f') { 
                    e.preventDefault(); 
                    registrarAccion('atajo_teclado', { tecla: 'Ctrl+F', accion: 'enfocar_buscador' }, 'index.php');
                    const buscador = document.getElementById('buscador');
                    if (buscador) buscador.focus(); 
                }
                if (e.ctrlKey && e.key === 'p') { 
                    e.preventDefault(); 
                    registrarAccion('atajo_teclado', { tecla: 'Ctrl+P', accion: 'imprimir_todo' }, 'index.php');
                    window.open('imprimir.php', '_blank'); 
                }
                if (e.ctrlKey && e.key === 'a') { 
                    e.preventDefault(); 
                    registrarAccion('atajo_teclado', { tecla: 'Ctrl+A', accion: 'seleccionar_todos' }, 'index.php');
                    const selectAll = document.getElementById('seleccionarTodos'); 
                    if (selectAll) selectAll.click(); 
                }
            });
            
            window.pageLoadTime = new Date();
            
            window.addEventListener('beforeunload', function() {
                if (window.pageLoadTime) {
                    registrarAccion('salió_de_página', { 
                        tiempo_activo: Math.floor((new Date() - window.pageLoadTime) / 1000) + ' segundos'
                    }, 'index.php');
                }
            });
        });
    </script>
</body>
</html>