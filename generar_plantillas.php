<?php
// generar_plantillas.php - Genera plantillas para importar
require_once 'config.php';

// Cargar autoload de Composer
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

$formato = $_GET['formato'] ?? 'xlsx';

if ($formato === 'csv') {
    // Generar CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=plantilla_importacion.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    
    // Encabezados
    fputcsv($output, [
        'RANGO / IP',
        'USUARIO',
        'ESTATUS',
        'DESIGNACIÓN / CONTROL',
        'FECHA',
        'CONFIGURACIÓN'
    ], ';');
    
    // Datos de ejemplo
    $ejemplos = [
        ['192.168.1.1 - 192.168.1.50', 'Juan Pérez', 'Activo', 'DHCP - Rango Principal', date('Y-m-d'), 'VLAN 10 - Oficinas'],
        ['10.0.0.100', 'María Gómez', 'Reservado', 'Reserva Servidor Correo', date('Y-m-d'), 'MAC: AA:BB:CC:DD:EE:FF'],
        ['172.16.5.1/24', 'Carlos López', 'Activo', 'Gateway Principal', date('Y-m-d'), 'Router Cisco 2901'],
    ];
    
    foreach ($ejemplos as $ejemplo) {
        fputcsv($output, $ejemplo, ';');
    }
    
    fclose($output);
    
} else {
    // Generar Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Título
    $sheet->setCellValue('A1', 'PLANTILLA DE IMPORTACIÓN - BITÁCORA DE IDs');
    $sheet->mergeCells('A1:F1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Instrucciones
    $sheet->setCellValue('A2', 'INSTRUCCIONES: Complete los datos respetando el formato. Las columnas con * son obligatorias.');
    $sheet->mergeCells('A2:F2');
    $sheet->getStyle('A2')->getFont()->setItalic(true);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Encabezados
    $encabezados = ['RANGO / IP *', 'USUARIO *', 'ESTATUS *', 'DESIGNACIÓN / CONTROL *', 'FECHA *', 'CONFIGURACIÓN'];
    $columna = 'A';
    foreach ($encabezados as $encabezado) {
        $sheet->setCellValue($columna . '3', $encabezado);
        $sheet->getStyle($columna . '3')->getFont()->setBold(true);
        $sheet->getStyle($columna . '3')->getFill()
              ->setFillType(Fill::FILL_SOLID)
              ->getStartColor()->setARGB('FF3498DB');
        $sheet->getStyle($columna . '3')->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->getColumnDimension($columna)->setAutoSize(true);
        $columna++;
    }
    
    // Datos de ejemplo
    $ejemplos = [
        ['192.168.1.1 - 192.168.1.50', 'Juan Pérez', 'Activo', 'DHCP - Rango Principal', date('Y-m-d'), 'VLAN 10 - Oficinas'],
        ['10.0.0.100', 'María Gómez', 'Reservado', 'Reserva Servidor Correo', date('Y-m-d'), 'MAC: AA:BB:CC:DD:EE:FF'],
        ['172.16.5.1/24', 'Carlos López', 'Activo', 'Gateway Principal', date('Y-m-d'), 'Router Cisco 2901'],
        ['192.168.2.1 - 192.168.2.100', 'Ana Martínez', 'Reservado', 'DHCP - Invitados', date('Y-m-d'), 'VLAN 20 - WiFi'],
        ['10.1.1.50', 'Pedro Sánchez', 'Inactivo', 'IP en desuso', date('Y-m-d'), 'Esperando reasignación'],
    ];
    
    $fila = 4;
    foreach ($ejemplos as $ejemplo) {
        $columna = 'A';
        foreach ($ejemplo as $valor) {
            $sheet->setCellValue($columna . $fila, $valor);
            $columna++;
        }
        $fila++;
    }
    
    // Bordes para toda la tabla
    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF000000'],
            ],
        ],
    ];
    $sheet->getStyle('A3:F' . ($fila - 1))->applyFromArray($styleArray);
    
    // Validación de datos para columna ESTATUS
    $validation = $sheet->getCell('C4')->getDataValidation();
    $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
    $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
    $validation->setAllowBlank(false);
    $validation->setShowDropDown(true);
    $validation->setFormula1('"Activo,Inactivo,Reservado,Bloqueado"');
    $validation->setPromptTitle('Seleccione estatus');
    $validation->setPrompt('Valores permitidos: Activo, Inactivo, Reservado, Bloqueado');
    $validation->setErrorTitle('Valor no válido');
    $validation->setError('Solo se permiten: Activo, Inactivo, Reservado, Bloqueado');
    
    // Aplicar validación a toda la columna C
    for ($i = 4; $i <= 100; $i++) {
        $sheet->getCell('C' . $i)->setDataValidation(clone $validation);
    }
    
    // Formato de fecha
    $sheet->getStyle('E4:E100')->getNumberFormat()->setFormatCode('YYYY-MM-DD');
    
    // Hoja de instrucciones
    $spreadsheet->createSheet();
    $spreadsheet->setActiveSheetIndex(1);
    $sheet2 = $spreadsheet->getActiveSheet();
    $sheet2->setTitle('Instrucciones');
    
    $instrucciones = [
        ['📋 GUÍA PARA IMPORTAR DATOS', ''],
        ['', ''],
        ['1. FORMATO DE FECHA:', 'Use YYYY-MM-DD (ej: 2024-01-15)'],
        ['2. ESTATUS VÁLIDOS:', 'Activo, Inactivo, Reservado, Bloqueado'],
        ['3. RANGOS/IP:', 'Puede usar: IP única, rango con guión, notación CIDR'],
        ['   Ejemplos:', '192.168.1.1, 10.0.0.1-10.0.0.50, 172.16.0.0/24'],
        ['4. CONFIGURACIÓN:', 'Campo opcional para notas adicionales'],
        ['5. COLUMNAS OBLIGATORIAS:', 'Todas excepto CONFIGURACIÓN'],
        ['', ''],
        ['⚠️ RECOMENDACIONES:', ''],
        ['• No deje filas vacías al inicio', ''],
        ['• Use UTF-8 para caracteres especiales', ''],
        ['• Los textos con comas deben ir entre comillas', ''],
        ['• Haga un respaldo antes de importar', ''],
    ];
    
    $fila = 1;
    foreach ($instrucciones as $instruccion) {
        $sheet2->setCellValue('A' . $fila, $instruccion[0]);
        $sheet2->setCellValue('B' . $fila, $instruccion[1]);
        $fila++;
    }
    
    $sheet2->getColumnDimension('A')->setWidth(30);
    $sheet2->getColumnDimension('B')->setWidth(40);
    $sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    
    // Volver a la primera hoja
    $spreadsheet->setActiveSheetIndex(0);
    $sheet->setTitle('Datos');
    
    // Configurar headers para descarga
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="plantilla_importacion.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
}
?>