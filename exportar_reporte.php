<?php
// exportar_reporte.php - Exportar reportes en diferentes formatos
require_once 'config.php';

// ============================================
// FUNCIONES DE EXPORTACIÓN
// ============================================

/**
 * Exportar a CSV
 */
function exportarCSV($datos, $nombre_archivo) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Cabeceras
    fputcsv($output, array_keys($datos[0]));
    
    // Datos
    foreach ($datos as $fila) {
        fputcsv($output, $fila);
    }
    
    fclose($output);
    exit();
}

/**
 * Exportar a Excel (usando HTML)
 */
function exportarExcel($datos, $nombre_archivo, $titulo = '') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>' . $nombre_archivo . '</title>';
    echo '<style>';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th { background: #2c3e50; color: white; padding: 10px; font-weight: bold; }';
    echo 'td { padding: 8px; border: 1px solid #ddd; }';
    echo 'tr:nth-child(even) { background: #f9f9f9; }';
    echo '.titulo { font-size: 20px; font-weight: bold; margin: 20px 0; color: #2c3e50; }';
    echo '.subtitulo { font-size: 16px; font-weight: bold; margin: 15px 0; color: #3498db; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    if ($titulo) {
        echo '<div class="titulo">' . $titulo . '</div>';
    }
    
    echo '<table border="1">';
    
    // Cabeceras
    echo '<tr>';
    foreach (array_keys($datos[0]) as $cabecera) {
        echo '<th>' . htmlspecialchars($cabecera) . '</th>';
    }
    echo '</tr>';
    
    // Datos
    foreach ($datos as $fila) {
        echo '<tr>';
        foreach ($fila as $valor) {
            echo '<td>' . htmlspecialchars($valor) . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body></html>';
    exit();
}

/**
 * Exportar a PDF (usando HTML2PDF - requiere librería externa)
 * Nota: Esta función requiere tener instalada la librería HTML2PDF
 * Si no la tienes, usa la versión simplificada con TCPDF
 */
function exportarPDF($datos_completos, $nombre_archivo) {
    // Verificar si existe la clase (requiere instalar librería)
    if (class_exists('TCPDF')) {
        exportarPDF_TCPDF($datos_completos, $nombre_archivo);
    } else {
        // Versión simplificada - genera HTML para imprimir
        exportarPDF_HTML($datos_completos, $nombre_archivo);
    }
}

/**
 * Exportar a PDF usando TCPDF (si está instalado)
 */
function exportarPDF_TCPDF($datos_completos, $nombre_archivo) {
    require_once('vendor/tecnickcom/tcpdf/tcpdf.php');
    
    // Crear nuevo documento PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Configurar documento
    $pdf->SetCreator('Sistema de Bitácora');
    $pdf->SetAuthor('Admin');
    $pdf->SetTitle($nombre_archivo);
    $pdf->SetSubject('Reporte de Estadísticas');
    
    // Eliminar cabecera y pie por defecto
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Agregar página
    $pdf->AddPage();
    
    // Título
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, $nombre_archivo, 0, 1, 'C');
    $pdf->Ln(5);
    
    // Fecha
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'Generado el: ' . date('d/m/Y H:i:s'), 0, 1, 'R');
    $pdf->Ln(10);
    
    // Contenido
    foreach ($datos_completos as $seccion => $datos) {
        // Título de sección
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetFillColor(52, 152, 219);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(0, 10, $seccion, 0, 1, 'L', true);
        $pdf->Ln(5);
        
        // Tabla de datos
        if (!empty($datos)) {
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor(0, 0, 0);
            
            // Crear tabla
            $html = '<table border="1" cellpadding="4" style="border-collapse: collapse;">';
            
            // Cabeceras
            $html .= '<tr style="background-color: #2c3e50; color: white;">';
            foreach (array_keys($datos[0]) as $cabecera) {
                $html .= '<th>' . htmlspecialchars($cabecera) . '</th>';
            }
            $html .= '</tr>';
            
            // Datos
            foreach ($datos as $fila) {
                $html .= '<tr>';
                foreach ($fila as $valor) {
                    $html .= '<td>' . htmlspecialchars($valor) . '</td>';
                }
                $html .= '</tr>';
            }
            
            $html .= '</table>';
            
            $pdf->writeHTML($html, true, false, true, false, '');
        }
        
        $pdf->Ln(10);
    }
    
    // Salida del PDF
    $pdf->Output($nombre_archivo . '.pdf', 'D');
    exit();
}

/**
 * Exportar a PDF versión HTML (simplificada)
 */
function exportarPDF_HTML($datos_completos, $nombre_archivo) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $nombre_archivo; ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #2c3e50; border-bottom: 3px solid #c0392b; padding-bottom: 10px; }
            h2 { color: #3498db; margin-top: 30px; }
            table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
            th { background: #2c3e50; color: white; padding: 10px; text-align: left; }
            td { padding: 8px; border: 1px solid #ddd; }
            tr:nth-child(even) { background: #f9f9f9; }
            .fecha { color: #7f8c8d; text-align: right; }
            .resumen { background: #ecf0f1; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <h1><?php echo $nombre_archivo; ?></h1>
        <div class="fecha">Generado el: <?php echo date('d/m/Y H:i:s'); ?></div>
        
        <?php foreach ($datos_completos as $seccion => $datos): ?>
            <h2><?php echo $seccion; ?></h2>
            <?php if (!empty($datos)): ?>
                <table>
                    <thead>
                        <tr>
                            <?php foreach (array_keys($datos[0]) as $cabecera): ?>
                                <th><?php echo htmlspecialchars($cabecera); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($datos as $fila): ?>
                            <tr>
                                <?php foreach ($fila as $valor): ?>
                                    <td><?php echo htmlspecialchars($valor); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No hay datos disponibles</p>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <script>
            window.onload = function() {
                window.print();
            }
        </script>
    </body>
    </html>
    <?php
    exit();
}

/**
 * Obtener datos completos para el reporte
 */
function obtenerDatosReporte($conn, $tipo = 'completo') {
    $datos = [];
    
    // Resumen general
    $datos['Resumen General'] = [];
    $total = $conn->query("SELECT COUNT(*) as total FROM registros_ids")->fetch_assoc()['total'];
    $activos = $conn->query("SELECT COUNT(*) as total FROM registros_ids WHERE estatus='Activo'")->fetch_assoc()['total'];
    $inactivos = $conn->query("SELECT COUNT(*) as total FROM registros_ids WHERE estatus='Inactivo'")->fetch_assoc()['total'];
    $reservados = $conn->query("SELECT COUNT(*) as total FROM registros_ids WHERE estatus='Reservado'")->fetch_assoc()['total'];
    $bloqueados = $conn->query("SELECT COUNT(*) as total FROM registros_ids WHERE estatus='Bloqueado'")->fetch_assoc()['total'];
    $usuarios_unicos = $conn->query("SELECT COUNT(DISTINCT usuario) as total FROM registros_ids WHERE usuario IS NOT NULL AND usuario != ''")->fetch_assoc()['total'];
    
    $datos['Resumen General'][] = [
        'Métrica' => 'Total Registros',
        'Valor' => $total
    ];
    $datos['Resumen General'][] = [
        'Métrica' => 'Registros Activos',
        'Valor' => $activos
    ];
    $datos['Resumen General'][] = [
        'Métrica' => 'Registros Inactivos',
        'Valor' => $inactivos
    ];
    $datos['Resumen General'][] = [
        'Métrica' => 'Registros Reservados',
        'Valor' => $reservados
    ];
    $datos['Resumen General'][] = [
        'Métrica' => 'Registros Bloqueados',
        'Valor' => $bloqueados
    ];
    $datos['Resumen General'][] = [
        'Métrica' => 'Usuarios Únicos',
        'Valor' => $usuarios_unicos
    ];
    
    // Top Usuarios
    $datos['Top 10 Usuarios'] = [];
    $top_usuarios = $conn->query("
        SELECT 
            usuario,
            COUNT(*) as total_registros,
            SUM(CASE WHEN estatus='Activo' THEN 1 ELSE 0 END) as activos,
            SUM(CASE WHEN estatus='Inactivo' THEN 1 ELSE 0 END) as inactivos
        FROM registros_ids 
        WHERE usuario IS NOT NULL AND usuario != '' 
        GROUP BY usuario 
        ORDER BY total_registros DESC 
        LIMIT 10
    ");
    
    while ($row = $top_usuarios->fetch_assoc()) {
        $datos['Top 10 Usuarios'][] = [
            'Usuario' => $row['usuario'],
            'Total Registros' => $row['total_registros'],
            'Activos' => $row['activos'],
            'Inactivos' => $row['inactivos']
        ];
    }
    
    // Distribución por Roles
    $datos['Roles'] = [];
    $roles = $conn->query("
        SELECT 
            roles,
            COUNT(*) as total,
            SUM(CASE WHEN estatus='Activo' THEN 1 ELSE 0 END) as activos
        FROM registros_ids 
        WHERE roles IS NOT NULL AND roles != '' 
        GROUP BY roles 
        ORDER BY total DESC
    ");
    
    while ($row = $roles->fetch_assoc()) {
        $datos['Roles'][] = [
            'Rol' => $row['roles'],
            'Total' => $row['total'],
            'Activos' => $row['activos']
        ];
    }
    
    // Sin rol
    $sin_rol = $conn->query("SELECT COUNT(*) as total FROM registros_ids WHERE roles IS NULL OR roles = ''")->fetch_assoc()['total'];
    $datos['Roles'][] = [
        'Rol' => 'Sin Rol',
        'Total' => $sin_rol,
        'Activos' => 0
    ];
    
    // Configuraciones
    $datos['Configuraciones'] = [];
    $configs = $conn->query("
        SELECT 
            configuracion,
            COUNT(*) as total
        FROM registros_ids 
        WHERE configuracion IS NOT NULL AND configuracion != '' 
        GROUP BY configuracion 
        ORDER BY total DESC 
        LIMIT 15
    ");
    
    while ($row = $configs->fetch_assoc()) {
        $datos['Configuraciones'][] = [
            'Configuración' => $row['configuracion'],
            'Total' => $row['total']
        ];
    }
    
    // Sin configuración
    $sin_config = $conn->query("SELECT COUNT(*) as total FROM registros_ids WHERE configuracion IS NULL OR configuracion = ''")->fetch_assoc()['total'];
    $datos['Configuraciones'][] = [
        'Configuración' => 'Sin Configuración',
        'Total' => $sin_config
    ];
    
    // Rangos Militares
    $datos['Rangos Militares'] = [];
    $rangos = $conn->query("
        SELECT 
            rango_militar,
            COUNT(*) as total
        FROM registros_ids 
        WHERE rango_militar IS NOT NULL AND rango_militar != '' 
        GROUP BY rango_militar 
        ORDER BY total DESC 
        LIMIT 10
    ");
    
    while ($row = $rangos->fetch_assoc()) {
        $datos['Rangos Militares'][] = [
            'Rango' => $row['rango_militar'],
            'Total' => $row['total']
        ];
    }
    
    // Actividad por Mes
    $datos['Actividad por Mes'] = [];
    $meses = $conn->query("
        SELECT 
            DATE_FORMAT(fecha_registro, '%Y-%m') as mes,
            DATE_FORMAT(fecha_registro, '%M %Y') as mes_nombre,
            COUNT(*) as total
        FROM registros_ids 
        WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(fecha_registro, '%Y-%m')
        ORDER BY mes DESC
    ");
    
    while ($row = $meses->fetch_assoc()) {
        $datos['Actividad por Mes'][] = [
            'Mes' => $row['mes_nombre'],
            'Registros' => $row['total']
        ];
    }
    
    // Actividad por Día de la Semana
    $datos['Actividad por Día'] = [];
    $dias = $conn->query("
        SELECT 
            DAYNAME(fecha_registro) as dia,
            COUNT(*) as total
        FROM registros_ids 
        GROUP BY DAYNAME(fecha_registro)
        ORDER BY DAYOFWEEK(fecha_registro)
    ");
    
    while ($row = $dias->fetch_assoc()) {
        $datos['Actividad por Día'][] = [
            'Día' => $row['dia'],
            'Registros' => $row['total']
        ];
    }
    
    // Actividad por Hora
    $datos['Actividad por Hora'] = [];
    $horas = $conn->query("
        SELECT 
            HOUR(fecha_registro) as hora,
            COUNT(*) as total
        FROM registros_ids 
        GROUP BY HOUR(fecha_registro)
        ORDER BY hora
    ");
    
    while ($row = $horas->fetch_assoc()) {
        $hora_formato = $row['hora'] . ':00 - ' . ($row['hora'] + 1) . ':00';
        $datos['Actividad por Hora'][] = [
            'Hora' => $hora_formato,
            'Registros' => $row['total']
        ];
    }
    
    // Últimos 50 Registros
    if ($tipo == 'completo' || $tipo == 'detallado') {
        $datos['Últimos 50 Registros'] = [];
        $ultimos = $conn->query("
            SELECT 
                id_registro,
                rango_militar,
                usuario,
                roles,
                estatus,
                designacion,
                configuracion,
                DATE_FORMAT(fecha_registro, '%d/%m/%Y %H:%i') as fecha
            FROM registros_ids 
            ORDER BY fecha_registro DESC 
            LIMIT 50
        ");
        
        while ($row = $ultimos->fetch_assoc()) {
            $datos['Últimos 50 Registros'][] = [
                'ID' => $row['id_registro'],
                'Rango' => $row['rango_militar'] ?: 'N/A',
                'Usuario' => $row['usuario'] ?: 'N/A',
                'Rol' => $row['roles'] ?: 'N/A',
                'Estatus' => $row['estatus'],
                'Configuración' => $row['configuracion'] ?: 'N/A',
                'Fecha' => $row['fecha']
            ];
        }
    }
    
    return $datos;
}

// ============================================
// PROCESAR SOLICITUD
// ============================================

// Obtener parámetros
$tipo = $_GET['tipo'] ?? 'completo';
$formato = $_GET['formato'] ?? 'pdf';

// Validar formato
$formatos_permitidos = ['csv', 'excel', 'pdf'];
if (!in_array($formato, $formatos_permitidos)) {
    $formato = 'pdf';
}

// Validar tipo
$tipos_permitidos = ['completo', 'resumen', 'usuarios', 'roles', 'detallado'];
if (!in_array($tipo, $tipos_permitidos)) {
    $tipo = 'completo';
}

// Obtener datos
$datos_completos = obtenerDatosReporte($conn, $tipo);

// Nombre del archivo
$nombre_archivo = 'Reporte_Bitacora_' . date('Y-m-d_H-i');

// Exportar según formato
switch ($formato) {
    case 'csv':
        // Para CSV, necesitamos aplanar los datos
        $datos_planos = [];
        foreach ($datos_completos as $seccion => $datos) {
            foreach ($datos as $fila) {
                $fila['Sección'] = $seccion;
                $datos_planos[] = $fila;
            }
        }
        exportarCSV($datos_planos, $nombre_archivo);
        break;
        
    case 'excel':
        // Para Excel, también aplanamos
        $datos_planos = [];
        foreach ($datos_completos as $seccion => $datos) {
            foreach ($datos as $fila) {
                $fila['Sección'] = $seccion;
                $datos_planos[] = $fila;
            }
        }
        exportarExcel($datos_planos, $nombre_archivo, 'Reporte de Bitácora');
        break;
        
    case 'pdf':
    default:
        exportarPDF($datos_completos, $nombre_archivo);
        break;
}
?>