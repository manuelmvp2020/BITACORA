<?php
// generar_plantilla.php - Genera plantilla CSV automáticamente
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=plantilla_importacion.csv');

// Crear archivo CSV
$output = fopen('php://output', 'w');

// BOM para UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

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
    ['192.168.1.1 - 192.168.1.50', 'Juan Pérez', 'Activo', 'DHCP - Rango Principal', '2024-01-15', 'VLAN 10 - Oficinas'],
    ['10.0.0.100', 'María Gómez', 'Reservado', 'Reserva Servidor Correo', '2024-01-15', 'MAC: AA:BB:CC:DD:EE:FF'],
    ['172.16.5.1/24', 'Carlos López', 'Activo', 'Gateway Principal', '2024-01-14', 'Router Cisco 2901'],
    ['192.168.2.1 - 192.168.2.100', 'Ana Martínez', 'Reservado', 'DHCP - Invitados', '2024-01-13', 'VLAN 20 - WiFi'],
    ['10.1.1.50', 'Pedro Sánchez', 'Inactivo', 'IP en desuso', '2024-01-12', 'Esperando reasignación']
];

foreach ($ejemplos as $ejemplo) {
    fputcsv($output, $ejemplo, ';');
}

fclose($output);
?>