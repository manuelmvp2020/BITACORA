<?php
// exportar.php - Exporta los registros a diferentes formatos
require_once 'config.php';

// Incluir librería FPDF (debes descargarla y colocarla en la carpeta vendor/fpdf)
require_once 'vendor/fpdf186/fpdf.php';

// Obtener formato de exportación
$formato = $_GET['formato'] ?? 'csv';
$tipo = $_GET['tipo'] ?? 'todos'; // todos, seleccionados
$ids = $_GET['ids'] ?? '';

// Configurar la consulta según el tipo
if ($tipo === 'seleccionados' && !empty($ids)) {
    $array_ids = explode(',', $ids);
    $placeholders = implode(',', array_fill(0, count($array_ids), '?'));
    $tipos = str_repeat('i', count($array_ids));
    
    $sql = "SELECT * FROM registros_ids WHERE id_registro IN ($placeholders) ORDER BY fecha_registro DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($tipos, ...$array_ids);
    $stmt->execute();
    $resultado = $stmt->get_result();
} else {
    // Exportar todos
    $sql = "SELECT * FROM registros_ids ORDER BY fecha_registro DESC";
    $resultado = $conn->query($sql);
}

// Definir nombres de columnas (CON ROLES)
$columnas = [
    'ID',
    'RANGO / IP',
    'USUARIO',
    'ROL / PERMISOS',      // Nueva columna
    'ESTATUS',
    'DESIGNACIÓN / CONTROL',
    'FECHA',
    'CONFIGURACIÓN',
    'FECHA CREACIÓN',
    'ÚLTIMA ACTUALIZACIÓN'
];

// Exportar según formato
switch ($formato) {
    case 'csv':
        exportarCSV($resultado, $columnas);
        break;
    case 'excel':
        exportarExcel($resultado, $columnas);
        break;
    case 'pdf':
        exportarPDF($resultado, $columnas, $tipo, $ids);
        break;
    default:
        exportarCSV($resultado, $columnas);
}

function exportarCSV($resultado, $columnas) {
    // Configurar headers para descarga CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=bitacora_ids_' . date('Y-m-d') . '.csv');
    
    // Crear archivo CSV
    $output = fopen('php://output', 'w');
    
    // Escribir BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Escribir encabezados
    fputcsv($output, $columnas, ';');
    
    // Escribir datos
    if ($resultado && $resultado->num_rows > 0) {
        while ($row = $resultado->fetch_assoc()) {
            fputcsv($output, [
                $row['id_registro'] ?? '',
                $row['rango_militar'] ?? '',
                $row['usuario'] ?? '',
                $row['roles'] ?? 'No asignado',
                $row['estatus'] ?? '',
                $row['designacion'] ?? '',
                isset($row['fecha_registro']) ? date('d/m/Y', strtotime($row['fecha_registro'])) : '',
                $row['configuracion'] ?? '',
                isset($row['created_at']) ? date('d/m/Y H:i', strtotime($row['created_at'])) : '',
                isset($row['updated_at']) ? date('d/m/Y H:i', strtotime($row['updated_at'])) : ''
            ], ';');
        }
    }
    
    fclose($output);
    exit();
}

function exportarExcel($resultado, $columnas) {
    // Configurar headers para descarga Excel (formato HTML)
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename=bitacora_ids_' . date('Y-m-d') . '.xls');
    
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<style>';
    echo 'th { background-color: #3498db; color: white; font-weight: bold; text-align: center; padding: 8px; }';
    echo 'td { border: 1px solid #ccc; padding: 6px; }';
    echo '.activo { background-color: #d4edda; }';
    echo '.inactivo { background-color: #f8d7da; }';
    echo '.reservado { background-color: #fff3cd; }';
    echo '.bloqueado { background-color: #e2e3e5; }';
    echo '.rol-administrador { background-color: #c0392b; color: white; font-weight: bold; }';
    echo '.rol-supervisor { background-color: #e67e22; color: white; }';
    echo '.rol-operador { background-color: #2980b9; color: white; }';
    echo '.rol-consultor { background-color: #27ae60; color: white; }';
    echo '.rol-usuario { background-color: #8e44ad; color: white; }';
    echo '.rol-invitado { background-color: #7f8c8d; color: white; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    echo '<table border="1" cellspacing="0" style="border-collapse: collapse;">';
    
    // Encabezados
    echo '<tr>';
    foreach ($columnas as $columna) {
        echo '<th>' . $columna . '</th>';
    }
    echo '</tr>';
    
    // Datos
    if ($resultado && $resultado->num_rows > 0) {
        while ($row = $resultado->fetch_assoc()) {
            $clase_estatus = strtolower($row['estatus'] ?? '');
            
            // Determinar clase para el rol
            $clase_rol = '';
            $rol = $row['roles'] ?? '';
            switch($rol) {
                case 'Administrador': $clase_rol = 'rol-administrador'; break;
                case 'Supervisor': $clase_rol = 'rol-supervisor'; break;
                case 'Operador': $clase_rol = 'rol-operador'; break;
                case 'Consultor': $clase_rol = 'rol-consultor'; break;
                case 'Usuario': $clase_rol = 'rol-usuario'; break;
                case 'Invitado': $clase_rol = 'rol-invitado'; break;
                default: $clase_rol = ''; break;
            }
            
            echo '<tr>';
            echo '<td style="text-align: center;">' . ($row['id_registro'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['rango_militar'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['usuario'] ?? '') . '</td>';
            
            // Columna de ROL con estilo especial
            echo '<td class="' . $clase_rol . '" style="text-align: center;">';
            if (!empty($rol)) {
                // Agregar icono según el rol
                $icono = '';
                switch($rol) {
                    case 'Administrador': $icono = '👑 '; break;
                    case 'Supervisor': $icono = '👁️ '; break;
                    case 'Operador': $icono = '⚙️ '; break;
                    case 'Consultor': $icono = '📊 '; break;
                    case 'Usuario': $icono = '👤 '; break;
                    case 'Invitado': $icono = '🔑 '; break;
                    default: $icono = '📌 ';
                }
                echo $icono . htmlspecialchars($rol);
            } else {
                echo '<span style="color: #999;">No asignado</span>';
            }
            echo '</td>';
            
            echo '<td class="' . $clase_estatus . '" style="text-align: center;">' . ($row['estatus'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['designacion'] ?? '') . '</td>';
            echo '<td style="text-align: center;">' . (isset($row['fecha_registro']) ? date('d/m/Y', strtotime($row['fecha_registro'])) : '') . '</td>';
            echo '<td>' . htmlspecialchars($row['configuracion'] ?? '') . '</td>';
            echo '<td style="text-align: center;">' . (isset($row['created_at']) ? date('d/m/Y H:i', strtotime($row['created_at'])) : '') . '</td>';
            echo '<td style="text-align: center;">' . (isset($row['updated_at']) ? date('d/m/Y H:i', strtotime($row['updated_at'])) : '') . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="' . count($columnas) . '" style="text-align: center; padding: 20px;">No hay registros para exportar</td></tr>';
    }
    
    echo '</table>';
    
    // Agregar información de exportación
    echo '<br><hr>';
    echo '<p style="font-size: 12px; color: #666;">';
    echo 'Exportado el: ' . date('d/m/Y H:i:s') . '<br>';
    echo 'Total de registros: ' . ($resultado ? $resultado->num_rows : 0);
    echo '</p>';
    
    echo '</body>';
    echo '</html>';
    exit();
}

function exportarPDF($resultado, $columnas, $tipo, $ids) {
    // Crear nuevo PDF
    $pdf = new FPDF('L', 'mm', 'A4'); // Landscape orientation
    $pdf->AddPage();
    
    // Título
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'BITACORA DE REGISTRO DE IDS - FARD', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Subtítulo según tipo
    $pdf->SetFont('Arial', 'B', 12);
    if ($tipo === 'seleccionados') {
        $pdf->Cell(0, 10, 'REGISTROS SELECCIONADOS (IDs: ' . $ids . ')', 0, 1, 'C');
    } else {
        $pdf->Cell(0, 10, 'TODOS LOS REGISTROS', 0, 1, 'C');
    }
    
    // Fecha de exportación
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 10, 'Exportado el: ' . date('d/m/Y H:i:s'), 0, 1, 'R');
    $pdf->Ln(5);
    
    // Encabezados de tabla
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(52, 152, 219); // Azul
    $pdf->SetTextColor(255, 255, 255);
    
    // Anchos de columna
    $w = [15, 40, 35, 35, 20, 60, 20, 40];
    
    // Imprimir encabezados
    $pdf->Cell($w[0], 10, 'ID', 1, 0, 'C', true);
    $pdf->Cell($w[1], 10, 'RANGO / IP', 1, 0, 'C', true);
    $pdf->Cell($w[2], 10, 'USUARIO', 1, 0, 'C', true);
    $pdf->Cell($w[3], 10, 'ROL', 1, 0, 'C', true);
    $pdf->Cell($w[4], 10, 'ESTATUS', 1, 0, 'C', true);
    $pdf->Cell($w[5], 10, 'DESIGNACIÓN', 1, 0, 'C', true);
    $pdf->Cell($w[6], 10, 'FECHA', 1, 0, 'C', true);
    $pdf->Cell($w[7], 10, 'CONFIGURACIÓN', 1, 1, 'C', true);
    
    // Restaurar colores
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 7);
    
    // Datos
    $fill = false;
    $total_registros = 0;
    
    if ($resultado && $resultado->num_rows > 0) {
        while ($row = $resultado->fetch_assoc()) {
            $total_registros++;
            
            // Verificar si necesitamos una nueva página
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
                // Repetir encabezados
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->SetFillColor(52, 152, 219);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->Cell($w[0], 10, 'ID', 1, 0, 'C', true);
                $pdf->Cell($w[1], 10, 'RANGO / IP', 1, 0, 'C', true);
                $pdf->Cell($w[2], 10, 'USUARIO', 1, 0, 'C', true);
                $pdf->Cell($w[3], 10, 'ROL', 1, 0, 'C', true);
                $pdf->Cell($w[4], 10, 'ESTATUS', 1, 0, 'C', true);
                $pdf->Cell($w[5], 10, 'DESIGNACIÓN', 1, 0, 'C', true);
                $pdf->Cell($w[6], 10, 'FECHA', 1, 0, 'C', true);
                $pdf->Cell($w[7], 10, 'CONFIGURACIÓN', 1, 1, 'C', true);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('Arial', '', 7);
            }
            
            // Color de fondo para estatus
            $estatus = $row['estatus'] ?? 'Activo';
            $fill = !$fill;
            
            // ID
            $pdf->Cell($w[0], 8, $row['id_registro'] ?? '', 1, 0, 'C', $fill);
            
            // RANGO / IP
            $rango = htmlspecialchars_decode($row['rango_militar'] ?? '');
            $rango = mb_convert_encoding($rango, 'ISO-8859-1', 'UTF-8');
            $pdf->Cell($w[1], 8, substr($rango, 0, 25), 1, 0, 'L', $fill);
            
            // USUARIO
            $usuario = htmlspecialchars_decode($row['usuario'] ?? '');
            $usuario = mb_convert_encoding($usuario, 'ISO-8859-1', 'UTF-8');
            $pdf->Cell($w[2], 8, substr($usuario, 0, 15), 1, 0, 'L', $fill);
            
            // ROL
            $rol = htmlspecialchars_decode($row['roles'] ?? 'No asignado');
            $rol = mb_convert_encoding($rol, 'ISO-8859-1', 'UTF-8');
            $pdf->Cell($w[3], 8, substr($rol, 0, 15), 1, 0, 'L', $fill);
            
            // ESTATUS con color
            $x = $pdf->GetX();
            $y = $pdf->GetY();
            
            // Color según estatus
            if ($estatus == 'Activo') {
                $pdf->SetFillColor(212, 237, 218); // Verde claro
                $pdf->SetTextColor(21, 87, 36);
            } elseif ($estatus == 'Inactivo') {
                $pdf->SetFillColor(248, 215, 218); // Rojo claro
                $pdf->SetTextColor(114, 28, 36);
            } elseif ($estatus == 'Reservado') {
                $pdf->SetFillColor(255, 243, 205); // Amarillo claro
                $pdf->SetTextColor(133, 100, 4);
            } elseif ($estatus == 'Bloqueado') {
                $pdf->SetFillColor(226, 227, 229); // Gris claro
                $pdf->SetTextColor(56, 61, 65);
            }
            
            $pdf->Cell($w[4], 8, $estatus, 1, 0, 'C', true);
            
            // Restaurar colores
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFillColor(255, 255, 255);
            
            // DESIGNACIÓN
            $designacion = htmlspecialchars_decode($row['designacion'] ?? '');
            $designacion = mb_convert_encoding($designacion, 'ISO-8859-1', 'UTF-8');
            $pdf->Cell($w[5], 8, substr($designacion, 0, 35), 1, 0, 'L', $fill);
            
            // FECHA
            $fecha = isset($row['fecha_registro']) ? date('d/m/Y', strtotime($row['fecha_registro'])) : '';
            $pdf->Cell($w[6], 8, $fecha, 1, 0, 'C', $fill);
            
            // CONFIGURACIÓN
            $config = htmlspecialchars_decode($row['configuracion'] ?? '');
            $config = mb_convert_encoding($config, 'ISO-8859-1', 'UTF-8');
            $pdf->Cell($w[7], 8, substr($config, 0, 20), 1, 1, 'L', $fill);
        }
    } else {
        $pdf->Cell(array_sum($w), 10, 'No hay registros para exportar', 1, 1, 'C');
    }
    
    // Pie de página
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 10, 'Total de registros: ' . $total_registros, 0, 1, 'L');
    $pdf->Cell(0, 10, 'Documento generado por Sistema de Bitácora de IDs - FARD', 0, 1, 'L');
    $pdf->Cell(0, 10, 'Documento confidencial - Uso exclusivo militar', 0, 1, 'L');
    
    // Salida del PDF
    $filename = 'bitacora_ids_' . date('Y-m-d') . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $pdf->Output('D', $filename);
    exit();
}

// Función adicional para exportar a JSON (útil para APIs)
function exportarJSON($resultado) {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename=bitacora_ids_' . date('Y-m-d') . '.json');
    
    $datos = [];
    if ($resultado && $resultado->num_rows > 0) {
        while ($row = $resultado->fetch_assoc()) {
            $datos[] = [
                'id' => $row['id_registro'],
                'rango_militar' => $row['rango_militar'],
                'usuario' => $row['usuario'],
                'roles' => $row['roles'] ?? null,
                'estatus' => $row['estatus'],
                'designacion' => $row['designacion'],
                'fecha_registro' => $row['fecha_registro'],
                'configuracion' => $row['configuracion'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }
    }
    
    echo json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

// Si se solicita formato JSON (útil para debugging o integraciones)
if (isset($_GET['formato']) && $_GET['formato'] === 'json') {
    exportarJSON($resultado);
}
?>