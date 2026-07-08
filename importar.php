<?php
// importar.php - Importar datos con rangos militares y formato DD-MM-YYYY
require_once 'config.php';

// Cargar autoload de Composer para PhpSpreadsheet
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    $archivo = $_FILES['archivo'];
    $tipo_archivo = $_POST['tipo_archivo'] ?? 'csv';
    $delimitador = $_POST['delimitador'] ?? ';';
    
    // Validar archivo
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $mensaje = "Error al subir el archivo";
        $tipo_mensaje = "error";
    } else {
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        
        // Procesar según el tipo de archivo
        if ($tipo_archivo === 'xlsx' && in_array($extension, ['xlsx', 'xls'])) {
            $resultado = procesarExcel($archivo['tmp_name']);
        } elseif ($tipo_archivo === 'csv' && $extension === 'csv') {
            $resultado = procesarCSV($archivo['tmp_name'], $delimitador);
        } else {
            $mensaje = "Tipo de archivo no válido. Extensiones permitidas: .csv, .xlsx, .xls";
            $tipo_mensaje = "error";
        }
        
        if (isset($resultado)) {
            $mensaje = $resultado['mensaje'];
            $tipo_mensaje = $resultado['tipo'];
        }
    }
}

function procesarCSV($archivo_tmp, $delimitador) {
    global $conn;
    
    $handle = fopen($archivo_tmp, 'r');
    if (!$handle) {
        return ['mensaje' => "Error al abrir el archivo CSV", 'tipo' => 'error'];
    }
    
    // Leer encabezados
    $encabezados = fgetcsv($handle, 0, $delimitador);
    
    // Limpiar encabezados (quitar BOM si existe)
    $encabezados[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $encabezados[0]);
    
    return procesarFilas($handle, $encabezados);
}

function procesarExcel($archivo_tmp) {
    global $conn;
    
    try {
        $spreadsheet = IOFactory::load($archivo_tmp);
        $hoja = $spreadsheet->getActiveSheet();
        $filas = $hoja->toArray();
        
        if (empty($filas)) {
            return ['mensaje' => "El archivo Excel está vacío", 'tipo' => 'error'];
        }
        
        $encabezados = array_shift($filas);
        
        $handle = fopen('php://temp', 'r+');
        foreach ($filas as $fila) {
            fputcsv($handle, $fila, ';');
        }
        rewind($handle);
        
        return procesarFilas($handle, $encabezados);
        
    } catch (Exception $e) {
        return ['mensaje' => "Error al procesar Excel: " . $e->getMessage(), 'tipo' => 'error'];
    }
}

function procesarFilas($handle, $encabezados) {
    global $conn;
    
    // Validar estructura - AHORA CON 10 COLUMNAS (incluyendo roles)
    $encabezados_esperados = [
        'ID', 
        'RANGO / IP', 
        'USUARIO', 
        'ROL / PERMISOS', 
        'ESTATUS', 
        'DESIGNACIÓN / CONTROL', 
        'FECHA', 
        'CONFIGURACIÓN', 
        'FECHA CREACIÓN', 
        'ÚLTIMA ACTUALIZACIÓN'
    ];
    
    // Mapeo flexible de encabezados
    $mapeo_encabezados = [
        0 => ['ID', 'ID_REGISTRO', 'REGISTRO ID', 'NÚMERO'],
        1 => ['RANGO / IP', 'RANGO', 'RANGO MILITAR', 'IP', 'GRADO', 'JERARQUÍA'],
        2 => ['USUARIO', 'NOMBRE', 'APELLIDO', 'SOLDADO'],
        3 => ['ROL / PERMISOS', 'ROL', 'PERMISOS', 'ROLES', 'CARGO'],
        4 => ['ESTATUS', 'ESTADO', 'SITUACIÓN'],
        5 => ['DESIGNACIÓN / CONTROL', 'DESIGNACIÓN', 'CONTROL', 'UNIDAD', 'DESTINO'],
        6 => ['FECHA', 'DATE', 'FECHA DE REGISTRO', 'FECHA ALTA'],
        7 => ['CONFIGURACIÓN', 'CONFIG', 'OBSERVACIONES', 'NOTAS'],
        8 => ['FECHA CREACIÓN', 'CREACIÓN', 'CREATED_AT', 'FECHA CREACION'],
        9 => ['ÚLTIMA ACTUALIZACIÓN', 'ACTUALIZACIÓN', 'UPDATED_AT', 'FECHA ACTUALIZACION']
    ];
    
    // Limpiar y normalizar encabezados
    $encabezados_limpios = array_map(function($e) {
        return trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $e));
    }, $encabezados);
    
    // Verificar que al menos tengamos las columnas principales (RANGO, USUARIO, ESTATUS, DESIGNACIÓN, FECHA)
    $encabezados_validos = true;
    $columnas_requeridas = [1, 2, 4, 5, 6]; // Índices de columnas requeridas
    
    foreach ($columnas_requeridas as $i) {
        $encontrado = false;
        $valor_actual = isset($encabezados_limpios[$i]) ? strtoupper(trim($encabezados_limpios[$i])) : '';
        
        foreach ($mapeo_encabezados[$i] as $posible) {
            if (strpos($valor_actual, strtoupper($posible)) !== false) {
                $encontrado = true;
                break;
            }
        }
        
        if (!$encontrado && $i != 0 && $i != 3 && $i != 7 && $i != 8 && $i != 9) {
            // Solo requerimos ID, ROL, CONFIGURACIÓN, FECHA CREACIÓN y ACTUALIZACIÓN son opcionales
            $encabezados_validos = false;
            break;
        }
    }
    
    if (!$encabezados_validos) {
        fclose($handle);
        return [
            'mensaje' => "La estructura del archivo no es válida. Debe tener: RANGO / IP, USUARIO, ESTATUS, DESIGNACIÓN / CONTROL, FECHA",
            'tipo' => 'error'
        ];
    }
    
    // Procesar filas
    $contador = 0;
    $errores = [];
    $fila_num = 2;
    
    while (($fila = fgetcsv($handle, 0, ';')) !== FALSE) {
        // Saltar filas vacías
        if (empty(array_filter($fila))) {
            $fila_num++;
            continue;
        }
        
        // Validar cantidad de columnas mínimas
        if (count($fila) < 6) {
            $errores[] = "Fila $fila_num: Número incorrecto de columnas (mínimo 6)";
            $fila_num++;
            continue;
        }
        
        // Limpiar y validar datos
        $id = trim($fila[0] ?? ''); // ID (opcional, no se usa para importar)
        $rango = trim($fila[1] ?? '');
        $usuario = trim($fila[2] ?? '');
        $rol = trim($fila[3] ?? ''); // NUEVO CAMPO ROL
        $estatus = trim($fila[4] ?? '');
        $designacion = trim($fila[5] ?? '');
        $fecha = trim($fila[6] ?? '');
        $configuracion = trim($fila[7] ?? '');
        // $fecha_creacion y $fecha_actualizacion se ignoran porque se generan automáticamente
        
        // Validar campos requeridos
        if (empty($rango) || empty($usuario) || empty($estatus) || empty($designacion) || empty($fecha)) {
            $errores[] = "Fila $fila_num: Campos requeridos incompletos (Rango, Usuario, Estatus, Designación, Fecha son obligatorios)";
            $fila_num++;
            continue;
        }
        
        // Validar estatus
        $estatus_validos = ['Activo', 'Inactivo', 'Reservado', 'Bloqueado'];
        if (!in_array($estatus, $estatus_validos)) {
            $errores[] = "Fila $fila_num: Estatus '$estatus' no válido. Debe ser: " . implode(', ', $estatus_validos);
            $fila_num++;
            continue;
        }
        
        // ===== VALIDACIÓN DE FECHA CON FORMATO DD-MM-YYYY =====
        // Validar fecha (puede venir como número de Excel o string)
        if (is_numeric($fecha)) {
            // Convertir fecha de Excel a formato PHP
            $fecha_obj = Date::excelToDateTimeObject($fecha);
            $fecha = $fecha_obj->format('Y-m-d');
        } else {
            $fecha = trim($fecha);
            
            // DETECTAR FORMATO DD-MM-YYYY (ej: 15-03-2026)
            if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $fecha, $matches)) {
                $dia = $matches[1];
                $mes = $matches[2];
                $anio = $matches[3];
                
                if (checkdate($mes, $dia, $anio)) {
                    $fecha = "$anio-$mes-$dia";
                } else {
                    $errores[] = "Fila $fila_num: Fecha no válida ($fecha)";
                    $fila_num++;
                    continue;
                }
            }
            // DETECTAR FORMATO DD/MM/YYYY (ej: 15/03/2026)
            elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $fecha, $matches)) {
                $dia = $matches[1];
                $mes = $matches[2];
                $anio = $matches[3];
                
                if (checkdate($mes, $dia, $anio)) {
                    $fecha = "$anio-$mes-$dia";
                } else {
                    $errores[] = "Fila $fila_num: Fecha no válida ($fecha)";
                    $fila_num++;
                    continue;
                }
            }
            // DETECTAR FORMATO YYYY-MM-DD (por compatibilidad)
            elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                $partes = explode('-', $fecha);
                if (!checkdate($partes[1], $partes[2], $partes[0])) {
                    $errores[] = "Fila $fila_num: Fecha no válida ($fecha)";
                    $fila_num++;
                    continue;
                }
            }
            // DETECTAR FORMATO YYYY-MM-DD HH:MM:SS (como en tu archivo: 2026-11-03 15:40:00)
            elseif (preg_match('/^(\d{4}-\d{2}-\d{2})\s/', $fecha, $matches)) {
                $fecha_solo = $matches[1];
                $partes = explode('-', $fecha_solo);
                if (checkdate($partes[1], $partes[2], $partes[0])) {
                    $fecha = $fecha_solo; // Solo tomamos la fecha, ignoramos la hora
                } else {
                    $errores[] = "Fila $fila_num: Fecha no válida ($fecha)";
                    $fila_num++;
                    continue;
                }
            }
            else {
                $errores[] = "Fila $fila_num: Formato de fecha incorrecto. Use DD-MM-YYYY (ej: 15-03-2026)";
                $fila_num++;
                continue;
            }
        }
        
        // Validar formato final de fecha (debe ser YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $errores[] = "Fila $fila_num: Error al procesar la fecha";
            $fila_num++;
            continue;
        }
        // ===== FIN DE VALIDACIÓN DE FECHA =====
        
        // Si el rol está vacío, lo dejamos como NULL
        if (empty($rol)) {
            $rol = null;
        }
        
        // Insertar en base de datos (ahora con roles)
        $sql = "INSERT INTO registros_ids (rango_militar, usuario, roles, estatus, designacion, fecha_registro, configuracion) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssss", $rango, $usuario, $rol, $estatus, $designacion, $fecha, $configuracion);
        
        if ($stmt->execute()) {
            $contador++;
        } else {
            $errores[] = "Fila $fila_num: Error al insertar - " . $conn->error;
        }
        
        $fila_num++;
    }
    
    fclose($handle);
    
    if (empty($errores)) {
        return [
            'mensaje' => "¡Importación exitosa! Se importaron $contador registros.",
            'tipo' => 'success'
        ];
    } else {
        $mensaje = "Se importaron $contador registros con " . count($errores) . " errores:<br>" . implode('<br>', array_slice($errores, 0, 10));
        if (count($errores) > 10) {
            $mensaje .= "<br>... y " . (count($errores) - 10) . " errores más";
        }
        return [
            'mensaje' => $mensaje,
            'tipo' => 'warning'
        ];
    }
}

// Obtener estadísticas para mostrar
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as total FROM registros_ids")->fetch_assoc()['total'];
$stats['activos'] = $conn->query("SELECT COUNT(*) as total FROM registros_ids WHERE estatus='Activo'")->fetch_assoc()['total'];
$stats['inactivos'] = $conn->query("SELECT COUNT(*) as total FROM registros_ids WHERE estatus='Inactivo'")->fetch_assoc()['total'];
$stats['reservados'] = $conn->query("SELECT COUNT(*) as total FROM registros_ids WHERE estatus='Reservado'")->fetch_assoc()['total'];
$stats['bloqueados'] = $conn->query("SELECT COUNT(*) as total FROM registros_ids WHERE estatus='Bloqueado'")->fetch_assoc()['total'];

// Obtener estadísticas de roles
$stats_roles = [];
$roles_query = $conn->query("SELECT roles, COUNT(*) as total FROM registros_ids WHERE roles IS NOT NULL AND roles != '' GROUP BY roles ORDER BY total DESC LIMIT 5");
while ($rol = $roles_query->fetch_assoc()) {
    $stats_roles[] = $rol;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Datos - Bitácora Militar</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #c0392b;
            padding-bottom: 10px;
            margin-bottom: 30px;
            background-color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            margin-bottom: 0;
        }
        .card {
            background: white;
            border-radius: 0 0 10px 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .mensaje {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: 500;
            border-left: 4px solid;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border-left-color: #27ae60;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border-left-color: #c0392b;
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
            border-left-color: #f39c12;
        }
        
        /* Estilos mejorados para los cuadros de estadísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-box {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .stat-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .stat-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        
        .stat-box.total::before { background: linear-gradient(90deg, #3498db, #2980b9); }
        .stat-box.activos::before { background: linear-gradient(90deg, #27ae60, #229954); }
        .stat-box.inactivos::before { background: linear-gradient(90deg, #e74c3c, #c0392b); }
        .stat-box.reservados::before { background: linear-gradient(90deg, #f39c12, #e67e22); }
        .stat-box.bloqueados::before { background: linear-gradient(90deg, #95a5a6, #7f8c8d); }
        
        .stat-icon {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-label {
            font-size: 0.9em;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        
        .stat-percentage {
            font-size: 0.8em;
            color: #999;
            margin-top: 5px;
        }
        
        .stat-box.total .stat-number { color: #2980b9; }
        .stat-box.activos .stat-number { color: #27ae60; }
        .stat-box.inactivos .stat-number { color: #e74c3c; }
        .stat-box.reservados .stat-number { color: #e67e22; }
        .stat-box.bloqueados .stat-number { color: #7f8c8d; }
        
        .roles-stats {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .roles-title {
            font-size: 1.1em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .roles-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .badge-rol {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            gap: 8px;
            transition: transform 0.2s ease;
        }
        
        .badge-rol:hover {
            transform: scale(1.05);
        }
        
        .rol-administrador { background: linear-gradient(135deg, #c0392b, #e74c3c); color: white; }
        .rol-supervisor { background: linear-gradient(135deg, #e67e22, #f39c12); color: white; }
        .rol-operador { background: linear-gradient(135deg, #2980b9, #3498db); color: white; }
        .rol-consultor { background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; }
        .rol-usuario { background: linear-gradient(135deg, #8e44ad, #9b59b6); color: white; }
        .rol-invitado { background: linear-gradient(135deg, #7f8c8d, #95a5a6); color: white; }
        
        .rol-count {
            background: rgba(255,255,255,0.3);
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 11px;
        }
        
        .instrucciones {
            background: linear-gradient(135deg, #e8f4fd, #d1ecf1);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }
        
        .tabla-preview {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 0.9em;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .tabla-preview th {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 12px;
            text-align: left;
        }
        
        .tabla-preview td {
            padding: 12px;
            border: 1px solid #ddd;
        }
        
        .tabla-preview tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            border: none;
            cursor: pointer;
            font-size: 1em;
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(46, 204, 113, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(149, 165, 166, 0.3);
        }
        
        .btn-info {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(52, 152, 219, 0.3);
        }
        
        .alert {
            background-color: #fff3cd;
            border-left: 5px solid #e74c3c;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 5px;
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .radio-option input[type="radio"] {
            width: auto;
            margin-right: 5px;
        }
        
        input[type="file"], select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            box-sizing: border-box;
            transition: border 0.3s;
        }
        
        input[type="file"]:focus, select:focus {
            border-color: #3498db;
            outline: none;
        }
        
        .icono {
            margin-right: 5px;
        }
        
        .highlight {
            background-color: #f1c40f;
            color: #2c3e50;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .fecha-ejemplo {
            background-color: #2c3e50;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: monospace;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
            }
            
            .stat-number {
                font-size: 1.8em;
            }
            
            .stat-icon {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📤 Importar Datos - Bitácora Militar</h1>
        
        <div class="card">
            <?php if ($mensaje): ?>
                <div class="mensaje <?php echo $tipo_mensaje; ?>">
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>
            
            <!-- CUADROS DE ESTADÍSTICAS MEJORADOS -->
            <div class="stats-grid">
                <div class="stat-box total">
                    <div class="stat-icon">📊</div>
                    <div class="stat-number"><?php echo number_format($stats['total'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Total Registros</div>
                    <div class="stat-percentage">100%</div>
                </div>
                
                <div class="stat-box activos">
                    <div class="stat-icon">✅</div>
                    <div class="stat-number"><?php echo number_format($stats['activos'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Activos</div>
                    <div class="stat-percentage">
                        <?php echo $stats['total'] > 0 ? round(($stats['activos'] / $stats['total']) * 100, 1) : 0; ?>%
                    </div>
                </div>
                
                <div class="stat-box inactivos">
                    <div class="stat-icon">⛔</div>
                    <div class="stat-number"><?php echo number_format($stats['inactivos'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Inactivos</div>
                    <div class="stat-percentage">
                        <?php echo $stats['total'] > 0 ? round(($stats['inactivos'] / $stats['total']) * 100, 1) : 0; ?>%
                    </div>
                </div>
                
                <div class="stat-box reservados">
                    <div class="stat-icon">🔒</div>
                    <div class="stat-number"><?php echo number_format($stats['reservados'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Reservados</div>
                    <div class="stat-percentage">
                        <?php echo $stats['total'] > 0 ? round(($stats['reservados'] / $stats['total']) * 100, 1) : 0; ?>%
                    </div>
                </div>
                
                <div class="stat-box bloqueados">
                    <div class="stat-icon">🚫</div>
                    <div class="stat-number"><?php echo number_format($stats['bloqueados'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Bloqueados</div>
                    <div class="stat-percentage">
                        <?php echo $stats['total'] > 0 ? round(($stats['bloqueados'] / $stats['total']) * 100, 1) : 0; ?>%
                    </div>
                </div>
            </div>
            
            <?php if (!empty($stats_roles)): ?>
            <div class="roles-stats">
                <div class="roles-title">
                    <span>👥</span> Roles más comunes
                </div>
                <div class="roles-container">
                    <?php foreach ($stats_roles as $rol): ?>
                        <?php 
                        $clase_rol = '';
                        switch($rol['roles']) {
                            case 'Administrador': $clase_rol = 'rol-administrador'; break;
                            case 'Supervisor': $clase_rol = 'rol-supervisor'; break;
                            case 'Operador': $clase_rol = 'rol-operador'; break;
                            case 'Consultor': $clase_rol = 'rol-consultor'; break;
                            case 'Usuario': $clase_rol = 'rol-usuario'; break;
                            case 'Invitado': $clase_rol = 'rol-invitado'; break;
                            default: $clase_rol = 'rol-invitado';
                        }
                        ?>
                        <span class="badge-rol <?php echo $clase_rol; ?>">
                            <?php echo htmlspecialchars($rol['roles']); ?>
                            <span class="rol-count"><?php echo $rol['total']; ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>📥 Importar Archivo</h2>
            
            <div class="instrucciones">
                <strong>📌 Instrucciones para importar:</strong>
                <ol>
                    <li>Exporta tus datos desde Excel o CSV con el formato mostrado abajo</li>
                    <li><strong style="color: #c0392b;">Columnas esperadas (en este orden):</strong> 
                        ID, RANGO / IP, USUARIO, ROL / PERMISOS, ESTATUS, DESIGNACIÓN / CONTROL, FECHA, CONFIGURACIÓN, FECHA CREACIÓN, ÚLTIMA ACTUALIZACIÓN
                    </li>
                    <li>Solo son obligatorias: <span class="highlight">RANGO / IP, USUARIO, ESTATUS, DESIGNACIÓN / CONTROL, FECHA</span></li>
                    <li><strong style="color: #27ae60;">El campo ROL es opcional</strong> - se puede dejar vacío</li>
                    <li><strong>Formato de fecha aceptado:</strong> 
                        <span class="fecha-ejemplo">DD-MM-YYYY</span> (ej: 15-03-2026) o 
                        <span class="fecha-ejemplo">YYYY-MM-DD HH:MM:SS</span> (ej: 2026-11-03 15:40:00)
                    </li>
                    <li>Estatus válidos: <span style="background-color: #27ae60; color: white; padding: 2px 8px; border-radius: 4px;">Activo</span> 
                        <span style="background-color: #e74c3c; color: white; padding: 2px 8px; border-radius: 4px;">Inactivo</span>
                        <span style="background-color: #f39c12; color: white; padding: 2px 8px; border-radius: 4px;">Reservado</span>
                        <span style="background-color: #7f8c8d; color: white; padding: 2px 8px; border-radius: 4px;">Bloqueado</span>
                    </li>
                </ol>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="tipo_archivo">Tipo de archivo a importar:</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="tipo_csv" name="tipo_archivo" value="csv" checked>
                            <label for="tipo_csv" style="display: inline;">📊 CSV</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="tipo_xlsx" name="tipo_archivo" value="xlsx">
                            <label for="tipo_xlsx" style="display: inline;">📗 Excel</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" id="grupo_delimitador">
                    <label for="delimitador">Separador de columnas (solo para CSV):</label>
                    <select id="delimitador" name="delimitador">
                        <option value=";">Punto y coma (;) - Recomendado</option>
                        <option value=",">Coma (,)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="archivo">Seleccionar archivo:</label>
                    <input type="file" id="archivo" name="archivo" accept=".csv,.xlsx,.xls" required>
                </div>
                
                <div class="form-group">
                    <label>📋 Vista previa del formato esperado:</label>
                    <table class="tabla-preview">
                        <tr>
                            <th>ID</th>
                            <th>RANGO / IP</th>
                            <th>USUARIO</th>
                            <th>ROL / PERMISOS</th>
                            <th>ESTATUS</th>
                            <th>DESIGNACIÓN</th>
                            <th>FECHA</th>
                        </tr>
                        <tr>
                            <td>1</td>
                            <td>Capitán</td>
                            <td>jperez</td>
                            <td>Administrador</td>
                            <td>Activo</td>
                            <td>Compañía Alpha</td>
                            <td>15-03-2026</td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td>Teniente</td>
                            <td>mlopez</td>
                            <td>Supervisor</td>
                            <td>Activo</td>
                            <td>Batallón Bravo</td>
                            <td>20-03-2026</td>
                        </tr>
                    </table>
                    <p style="font-size: 0.9em; color: #7f8c8d; margin-top: 10px;">
                        <span class="icono">💡</span> Las columnas CONFIGURACIÓN, FECHA CREACIÓN y ÚLTIMA ACTUALIZACIÓN son opcionales. 
                        El sistema las generará automáticamente si no se proporcionan.
                    </p>
                </div>
                
                <div class="alert">
                    <strong>⚠️ Importante:</strong> 
                    <ul style="margin-top: 5px; margin-bottom: 0;">
                        <li>Los datos se <strong>agregarán</strong> a los existentes (no se reemplazarán)</li>
                        <li><strong>RANGO / IP</strong> y <strong>USUARIO</strong> son campos obligatorios</li>
                        <li>Si el campo <strong>ROL</strong> está vacío, quedará como "No asignado"</li>
                        <li>La fecha puede estar en formato <strong>DD-MM-YYYY</strong> o <strong>YYYY-MM-DD HH:MM:SS</strong></li>
                    </ul>
                </div>
                
                <div style="margin-top: 30px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="submit" class="btn btn-primary">
                        <span class="icono">📤</span> Importar Datos
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <span class="icono">↩️</span> Volver
                    </a>
                    <a href="exportar.php?formato=csv&tipo=todos" class="btn btn-info">
                        <span class="icono">📥</span> Descargar Plantilla
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.querySelectorAll('input[name="tipo_archivo"]').forEach(radio => {
            radio.addEventListener('change', function() {
                let grupoDelimitador = document.getElementById('grupo_delimitador');
                let inputArchivo = document.getElementById('archivo');
                
                if (this.value === 'csv') {
                    grupoDelimitador.style.display = 'block';
                    inputArchivo.accept = '.csv';
                } else {
                    grupoDelimitador.style.display = 'none';
                    inputArchivo.accept = '.xlsx,.xls';
                }
            });
        });

        // Validar archivo antes de enviar
        document.querySelector('form').addEventListener('submit', function(e) {
            let archivo = document.getElementById('archivo').value;
            if (!archivo) {
                e.preventDefault();
                alert('❌ Por favor selecciona un archivo para importar');
            }
        });
    </script>
</body>
</html>