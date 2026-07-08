<?php
// generar_plantilla_militar.php - Genera plantilla Excel con rangos militares
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;

// Crear nuevo documento Excel
$spreadsheet = new Spreadsheet();
$hoja = $spreadsheet->getActiveSheet();
$hoja->setTitle('Plantilla Bitácora Militar');

// Encabezados EXACTOS que espera el sistema
$encabezados = [
    'RANGO',
    'USUARIO', 
    'ESTATUS',
    'DESIGNACIÓN / CONTROL',
    'FECHA',
    'CONFIGURACIÓN'
];

// Escribir encabezados en la primera fila
foreach (range('A', 'F') as $index => $columna) {
    $celda = $columna . '1';
    $hoja->setCellValue($celda, $encabezados[$index]);
}

// Datos de ejemplo con rangos militares
$datos_ejemplo = [
    ['Capitán', 'Juan Pérez', 'Activo', 'Comandancia - Pelotón Alpha', '2026-03-10', 'Zona Norte'],
    ['Teniente', 'María Gómez', 'Reservado', 'Comunicaciones - Radio HF', '2026-03-10', 'Frecuencia 150.5 MHz'],
    ['Sargento Primero', 'Carlos López', 'Activo', 'Logística - Vehículos', '2026-03-10', 'Unidad Móvil 3'],
    ['Cabo', 'Ana Martínez', 'Activo', 'Infantería - Primera Sección', '2026-03-10', 'Fusil M4 - Serie 123'],
    ['Subteniente', 'Pedro Sánchez', 'Inactivo', 'Estado Mayor - Planeación', '2026-03-09', 'Baja temporal'],
    ['Mayor', 'Laura Rodríguez', 'Activo', 'Operaciones - Centro de Mando', '2026-03-08', 'Puesto de Comando'],
    ['Soldado', 'José García', 'Activo', 'Seguridad - Puesto 5', '2026-03-07', 'Guardia nocturna'],
    ['Coronel', 'Ricardo Torres', 'Reservado', 'Estado Mayor - Estrategia', '2026-03-06', 'Documentación clasificada'],
    ['Teniente Coronel', 'Patricia Silva', 'Activo', 'Inteligencia - G2', '2026-03-05', 'Operación especial'],
    ['Sargento', 'Miguel Ángel', 'Activo', 'Armamento - Depósito', '2026-03-04', 'Inventario trimestral'],
];

// Escribir datos de ejemplo desde la fila 2
$fila = 2;
foreach ($datos_ejemplo as $dato) {
    foreach (range('A', 'F') as $index => $columna) {
        $celda = $columna . $fila;
        $hoja->setCellValue($celda, $dato[$index]);
    }
    $fila++;
}

// ESTILOS
// Estilo para encabezados
$estilo_encabezado = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 12,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '2C3E50'],
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000'],
        ],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
];

// Estilo para celdas de datos
$estilo_datos = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'CCCCCC'],
        ],
    ],
    'alignment' => [
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
];

// Estilo para la columna de estatus (con colores)
$estilo_activo = [
    'font' => ['color' => ['rgb' => '27AE60'], 'bold' => true],
];
$estilo_reservado = [
    'font' => ['color' => ['rgb' => 'F39C12'], 'bold' => true],
];
$estilo_inactivo = [
    'font' => ['color' => ['rgb' => 'E74C3C'], 'bold' => true],
];

// Aplicar estilo a encabezados
$hoja->getStyle('A1:F1')->applyFromArray($estilo_encabezado);

// Aplicar estilo a todos los datos
$ultima_fila = count($datos_ejemplo) + 1;
$hoja->getStyle('A2:F' . $ultima_fila)->applyFromArray($estilo_datos);

// Aplicar colores condicionales a la columna ESTATUS (columna C)
for ($i = 2; $i <= $ultima_fila; $i++) {
    $estatus = $hoja->getCell('C' . $i)->getValue();
    $celda = 'C' . $i;
    
    if ($estatus === 'Activo') {
        $hoja->getStyle($celda)->applyFromArray($estilo_activo);
    } elseif ($estatus === 'Reservado') {
        $hoja->getStyle($celda)->applyFromArray($estilo_reservado);
    } elseif ($estatus === 'Inactivo') {
        $hoja->getStyle($celda)->applyFromArray($estilo_inactivo);
    }
}

// Autoajustar ancho de columnas
foreach (range('A', 'F') as $columna) {
    $hoja->getColumnDimension($columna)->setAutoSize(true);
}

// Agregar instrucciones en una hoja separada
$hoja_instrucciones = $spreadsheet->createSheet();
$hoja_instrucciones->setTitle('Instrucciones');

$instrucciones = [
    ['INSTRUCCIONES PARA IMPORTAR DATOS'],
    [],
    ['📌 FORMATO OBLIGATORIO:'],
    ['Las columnas deben estar en este orden exacto:'],
    ['1. RANGO - Grado militar (Capitán, Teniente, Sargento, etc.)'],
    ['2. USUARIO - Nombre completo del militar'],
    ['3. ESTATUS - Valores permitidos: Activo, Inactivo, Reservado, Bloqueado'],
    ['4. DESIGNACIÓN / CONTROL - Unidad, puesto o destino'],
    ['5. FECHA - Formato YYYY-MM-DD (ejemplo: 2026-03-10)'],
    ['6. CONFIGURACIÓN - Información adicional (opcional)'],
    [],
    ['⚠️ REGLAS IMPORTANTES:'],
    ['• No modificar los nombres de las columnas'],
    ['• No eliminar columnas'],
    ['• La fecha debe estar en formato YYYY-MM-DD'],
    ['• Los estatus deben ser exactamente: Activo, Inactivo, Reservado, Bloqueado'],
    ['• Puedes borrar los datos de ejemplo y poner los tuyos'],
    [],
    ['✅ RANGOS MILITARES VÁLIDOS:'],
    ['Soldado, Cabo, Sargento, Sargento Primero, Sargento Mayor,'],
    ['Subteniente, Teniente, Capitán, Mayor, Teniente Coronel,'],
    ['Coronel, General de Brigada, General de División, General de Ejército,'],
    ['Almirante, Vicealmirante, Contraalmirante, Capitán de Navío'],
];

$fila_inst = 1;
foreach ($instrucciones as $linea) {
    if (empty($linea)) {
        $fila_inst++;
        continue;
    }
    
    if (is_array($linea)) {
        $texto = $linea[0];
    } else {
        $texto = $linea;
    }
    
    $hoja_instrucciones->setCellValue('A' . $fila_inst, $texto);
    
    // Aplicar negrita a los títulos
    if (strpos($texto, '📌') !== false || strpos($texto, '⚠️') !== false || strpos($texto, '✅') !== false) {
        $hoja_instrucciones->getStyle('A' . $fila_inst)->getFont()->setBold(true);
    }
    
    $fila_inst++;
}

$hoja_instrucciones->getColumnDimension('A')->setWidth(80);

// Activar la primera hoja por defecto
$spreadsheet->setActiveSheetIndex(0);

// Configurar headers para descarga
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="plantilla_bitacora_militar.xlsx"');
header('Cache-Control: max-age=0');

// Guardar y enviar archivo
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;