<?php
// generar_plantilla_final.php - Plantilla EXACTA para importar sin problemas
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

// Crear nuevo documento Excel
$spreadsheet = new Spreadsheet();
$hoja = $spreadsheet->getActiveSheet();
$hoja->setTitle('Bitacora Militar');

// =============================================
// ENCABEZADOS EXACTOS (NO MODIFICAR)
// =============================================
$encabezados = [
    'A1' => 'RANGO',
    'B1' => 'USUARIO',
    'C1' => 'ESTATUS',
    'D1' => 'DESIGNACIÓN / CONTROL',
    'E1' => 'FECHA',
    'F1' => 'CONFIGURACIÓN'
];

// Escribir encabezados
foreach ($encabezados as $celda => $valor) {
    $hoja->setCellValue($celda, $valor);
}

// =============================================
// DATOS DE EJEMPLO (CON FORMATO CORRECTO)
// =============================================
$datos = [
    // RANGO, USUARIO, ESTATUS, DESIGNACIÓN, FECHA (DD-MM-YYYY), CONFIGURACIÓN
    ['Capitán', 'Juan Pérez', 'Activo', 'Comandancia - Pelotón Alpha', '10-03-2026', 'Zona Norte - Base Militar'],
    ['Teniente', 'María Gómez', 'Reservado', 'Comunicaciones - Radio HF', '10-03-2026', 'Frecuencia 150.5 MHz'],
    ['Sargento Primero', 'Carlos López', 'Activo', 'Logística - Vehículos', '09-03-2026', 'Unidad Móvil 3 - Toyota'],
    ['Cabo', 'Ana Martínez', 'Activo', 'Infantería - Primera Sección', '08-03-2026', 'Fusil M4 - Serie 12345'],
    ['Subteniente', 'Pedro Sánchez', 'Inactivo', 'Estado Mayor - Planeación', '07-03-2026', 'Baja temporal por salud'],
    ['Mayor', 'Laura Rodríguez', 'Activo', 'Operaciones - Centro de Mando', '06-03-2026', 'Puesto de Comando Móvil'],
    ['Soldado', 'José García', 'Activo', 'Seguridad - Puesto 5', '05-03-2026', 'Guardia nocturna - Turno 20-06'],
    ['Coronel', 'Ricardo Torres', 'Reservado', 'Estado Mayor - Estrategia', '04-03-2026', 'Documentación clasificada'],
    ['Teniente Coronel', 'Patricia Silva', 'Activo', 'Inteligencia - G2', '03-03-2026', 'Operación Especial Silencio'],
    ['Sargento', 'Miguel Ángel', 'Activo', 'Armamento - Depósito', '02-03-2026', 'Inventario trimestral'],
    ['General de Brigada', 'Roberto Díaz', 'Activo', 'Comandancia General', '01-03-2026', 'Vehículo Comando 001'],
    ['Cabo Primero', 'Fernando Torres', 'Reservado', 'Enfermería', '28-02-2026', 'Curso de primeros auxilios'],
];

// Escribir datos desde la fila 2
$fila = 2;
foreach ($datos as $dato) {
    $hoja->setCellValue('A' . $fila, $dato[0]); // RANGO
    $hoja->setCellValue('B' . $fila, $dato[1]); // USUARIO
    $hoja->setCellValue('C' . $fila, $dato[2]); // ESTATUS
    $hoja->setCellValue('D' . $fila, $dato[3]); // DESIGNACIÓN
    $hoja->setCellValueExplicit('E' . $fila, $dato[4], DataType::TYPE_STRING); // FECHA como texto
    $hoja->setCellValue('F' . $fila, $dato[5]); // CONFIGURACIÓN
    $fila++;
}

// =============================================
// ESTILOS PROFESIONALES
// =============================================

// Estilo para encabezados
$estilo_encabezado = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 12,
        'name' => 'Arial'
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '2C3E50'], // Azul oscuro militar
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

// Estilo para fechas (formato texto)
$estilo_fecha = [
    'font' => [
        'color' => ['rgb' => 'C0392B'], // Rojo para fechas
        'bold' => true,
    ],
];

// Estilo condicional para estatus
$estilo_activo = [
    'font' => [
        'color' => ['rgb' => '27AE60'], // Verde
        'bold' => true,
    ],
];

$estilo_reservado = [
    'font' => [
        'color' => ['rgb' => 'F39C12'], // Naranja
        'bold' => true,
    ],
];

$estilo_inactivo = [
    'font' => [
        'color' => ['rgb' => 'E74C3C'], // Rojo
        'bold' => true,
    ],
];

$estilo_bloqueado = [
    'font' => [
        'color' => ['rgb' => '000000'], // Negro
        'bold' => true,
    ],
];

// Aplicar estilo a encabezados
$hoja->getStyle('A1:F1')->applyFromArray($estilo_encabezado);

// Aplicar estilo a todos los datos
$ultima_fila = count($datos) + 1;
$hoja->getStyle('A2:F' . $ultima_fila)->applyFromArray($estilo_datos);

// Aplicar estilo especial a la columna de fechas (E)
$hoja->getStyle('E2:E' . $ultima_fila)->applyFromArray($estilo_fecha);
$hoja->getStyle('E2:E' . $ultima_fila)->getNumberFormat()->setFormatCode('@'); // Formato texto

// Aplicar colores según estatus (columna C)
for ($i = 2; $i <= $ultima_fila; $i++) {
    $estatus = $hoja->getCell('C' . $i)->getValue();
    $celda = 'C' . $i;
    
    if ($estatus === 'Activo') {
        $hoja->getStyle($celda)->applyFromArray($estilo_activo);
    } elseif ($estatus === 'Reservado') {
        $hoja->getStyle($celda)->applyFromArray($estilo_reservado);
    } elseif ($estatus === 'Inactivo') {
        $hoja->getStyle($celda)->applyFromArray($estilo_inactivo);
    } elseif ($estatus === 'Bloqueado') {
        $hoja->getStyle($celda)->applyFromArray($estilo_bloqueado);
    }
}

// Autoajustar ancho de columnas
foreach (range('A', 'F') as $columna) {
    $hoja->getColumnDimension($columna)->setAutoSize(true);
}

// =============================================
// HOJA DE INSTRUCCIONES
// =============================================
$hoja_instrucciones = $spreadsheet->createSheet();
$hoja_instrucciones->setTitle('INSTRUCCIONES');

$instrucciones = [
    ['INSTRUCCIONES PARA IMPORTAR DATOS - BITÁCORA MILITAR'],
    [],
    ['⚠️ REGLAS OBLIGATORIAS:'],
    ['1. NO modificar los nombres de las columnas (fila 1)'],
    ['2. NO eliminar columnas'],
    ['3. Completar TODOS los campos obligatorios'],
    [],
    ['📌 FORMATO DE COLUMNAS:'],
    ['A: RANGO - Grado militar (Capitán, Teniente, Sargento, etc.)'],
    ['B: USUARIO - Nombre completo del militar'],
    ['C: ESTATUS - Solo estos valores: Activo, Inactivo, Reservado, Bloqueado'],
    ['D: DESIGNACIÓN / CONTROL - Unidad, puesto o destino'],
    ['E: FECHA - Formato EXACTO: DD-MM-YYYY (ejemplo: 31-12-2026)'],
    ['F: CONFIGURACIÓN - Información adicional (opcional)'],
    [],
    ['✅ EJEMPLOS CORRECTOS:'],
    ['Capitán;Juan Pérez;Activo;Comandancia;15-03-2026;Pelotón Alpha'],
    ['Teniente;María Gómez;Reservado;Comunicaciones;15-03-2026;Radio HF'],
    ['Sargento;Carlos López;Inactivo;Logística;14-03-2026;Licencia médica'],
    [],
    ['❌ ERRORES COMUNES:'],
    ['• Fecha en formato incorrecto: 2026-03-15 (mal) → 15-03-2026 (bien)'],
    ['• Fecha con barras: 15/03/2026 (mal) → 15-03-2026 (bien)'],
    ['• Estatus inválido: "Baja" (mal) → "Inactivo" (bien)'],
    ['• Columna de fecha vacía: (mal) → Siempre debe tener fecha'],
    [],
    ['📥 CÓMO USAR ESTA PLANTILLA:'],
    ['1. Borra los datos de ejemplo (filas 2 en adelante)'],
    ['2. Escribe tus propios datos respetando el formato'],
    ['3. Guarda el archivo y súbelo al sistema'],
    ['4. El sistema acepta tanto .XLSX como .CSV'],
    [],
    ['🔴 IMPORTANTE:'],
    ['• La fecha DEBE ser DD-MM-YYYY con GUIONES (-)'],
    ['• Los estatus DEBEN ser exactamente: Activo, Inactivo, Reservado, Bloqueado'],
    ['• Puedes usar rangos militares no listados, no hay validación estricta'],
];

$fila_inst = 1;
foreach ($instrucciones as $linea) {
    if (empty($linea)) {
        $fila_inst++;
        continue;
    }
    
    $texto = is_array($linea) ? $linea[0] : $linea;
    $hoja_instrucciones->setCellValue('A' . $fila_inst, $texto);
    
    // Formato para títulos
    if (strpos($texto, '⚠️') !== false || strpos($texto, '📌') !== false || 
        strpos($texto, '✅') !== false || strpos($texto, '❌') !== false ||
        strpos($texto, '📥') !== false || strpos($texto, '🔴') !== false) {
        $hoja_instrucciones->getStyle('A' . $fila_inst)->getFont()->setBold(true);
        $hoja_instrucciones->getStyle('A' . $fila_inst)->getFont()->setSize(12);
    }
    
    // Color para ejemplos correctos (✅)
    if (strpos($texto, '✅') !== false) {
        $hoja_instrucciones->getStyle('A' . $fila_inst)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('27AE60'));
    }
    
    // Color para errores (❌)
    if (strpos($texto, '❌') !== false) {
        $hoja_instrucciones->getStyle('A' . $fila_inst)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('E74C3C'));
    }
    
    // Color para advertencias (⚠️, 🔴)
    if (strpos($texto, '⚠️') !== false || strpos($texto, '🔴') !== false) {
        $hoja_instrucciones->getStyle('A' . $fila_inst)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('E67E22'));
    }
    
    $fila_inst++;
}

$hoja_instrucciones->getColumnDimension('A')->setWidth(90);
$hoja_instrucciones->getStyle('A1:A' . ($fila_inst-1))->getAlignment()->setWrapText(true);

// =============================================
// HOJA DE AYUDA CON RANGOS MILITARES
// =============================================
$hoja_ayuda = $spreadsheet->createSheet();
$hoja_ayuda->setTitle('Rangos Militares');

$rangos = [
    ['RANGOS MILITARES COMUNES'],
    [],
    ['EJÉRCITO / FUERZA AÉREA:', 'ARMADA:'],
    ['Soldado', 'Marinero'],
    ['Cabo', 'Cabo'],
    ['Sargento', 'Sargento'],
    ['Sargento Primero', 'Sargento Primero'],
    ['Sargento Mayor', 'Sargento Mayor'],
    ['Subteniente', 'Subteniente'],
    ['Teniente', 'Teniente de Navío'],
    ['Capitán', 'Capitán de Corbeta'],
    ['Mayor', 'Capitán de Fragata'],
    ['Teniente Coronel', 'Capitán de Navío'],
    ['Coronel', 'Contraalmirante'],
    ['General de Brigada', 'Vicealmirante'],
    ['General de División', 'Almirante'],
    ['General de Ejército', 'Almirante General'],
];

$fila_rangos = 1;
foreach ($rangos as $linea) {
    if (empty($linea)) {
        $fila_rangos++;
        continue;
    }
    
    if (count($linea) == 2) {
        $hoja_ayuda->setCellValue('A' . $fila_rangos, $linea[0]);
        $hoja_ayuda->setCellValue('B' . $fila_rangos, $linea[1]);
        $hoja_ayuda->getStyle('A' . $fila_rangos)->getFont()->setBold(true);
        $hoja_ayuda->getStyle('B' . $fila_rangos)->getFont()->setBold(true);
    } else {
        $hoja_ayuda->setCellValue('A' . $fila_rangos, $linea[0]);
    }
    $fila_rangos++;
}

$hoja_ayuda->getColumnDimension('A')->setWidth(25);
$hoja_ayuda->getColumnDimension('B')->setWidth(25);

// =============================================
// ACTIVAR HOJA PRINCIPAL Y DESCARGAR
// =============================================
$spreadsheet->setActiveSheetIndex(0);

// Configurar headers para descarga
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="PLANTILLA_BITACORA_MILITAR.xlsx"');
header('Cache-Control: max-age=0');

// Guardar y enviar archivo
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>