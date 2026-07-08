<?php
session_start();
require 'log.php';

$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = 'mysql';
$dbName = 'bitacora_ids';

// Definir ambas rutas
$backupDir1 = 'C:/Users/mdelvallepaulus/Desktop/backup/';   
$backupDir2 = 'C:/Ampps/www/sistema-usuarios/backups/';

$timestamp = date('Ymd_His');
$sqlFile = "backup_{$dbName}_{$timestamp}.sql";
$zipFile = "backup_{$dbName}_{$timestamp}.zip";

// Rutas para la primera ubicación
$sqlPath1 = $backupDir1 . $sqlFile;
$zipPath1 = $backupDir1 . $zipFile;

// Rutas para la segunda ubicación
$sqlPath2 = $backupDir2 . $sqlFile;
$zipPath2 = $backupDir2 . $zipFile;

$usuario = $_SESSION['usuario'] ?? 'desconocido';
$ip = $_SERVER['REMOTE_ADDR'];
$fecha = date('Y-m-d H:i:s');

// Asegura ambas carpetas
if (!is_dir($backupDir1)) {
    mkdir($backupDir1, 0777, true);
}
if (!is_dir($backupDir2)) {
    mkdir($backupDir2, 0777, true);
}

// Verifica permisos MySQL
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
$permisosFaltantes = [];

if (!$conn->connect_error) {
    $resultPermisos = $conn->query("SHOW GRANTS FOR '{$dbUser}'@'localhost'");
    if ($resultPermisos) {
        $grants = '';
        while ($row = $resultPermisos->fetch_row()) {
            $grants .= implode(' ', $row);
        }

        $requeridos = ['SELECT', 'TRIGGER', 'EVENT', 'SHOW VIEW', 'LOCK TABLES', 'EXECUTE'];
        foreach ($requeridos as $permiso) {
            if (stripos($grants, $permiso) === false && stripos($grants, 'ALL PRIVILEGES') === false) {
                $permisosFaltantes[] = $permiso;
            }
        }
    }
    $conn->close();
}

if (count($permisosFaltantes) > 0) {
    $evento = "Backup cancelado: faltan permisos MySQL (" . implode(', ', $permisosFaltantes) . ")";
    guardar_log($usuario, $evento, $ip, $fecha, 'respaldos');

    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if (!$conn->connect_error) {
        $stmt = $conn->prepare("INSERT INTO auditoria (usuario, evento, fecha, ip) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $usuario, $evento, $fecha, $ip);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    }

    header("Location: index.php?backup=permiso_error");
    exit;
}

// Crear archivo temporal para el backup
$tempSqlFile = sys_get_temp_dir() . '/' . $sqlFile;

// ✅ Respaldar todas las tablas y registros a un archivo temporal
$command = "C:\\Ampps\\mysql\\bin\\mysqldump --user={$dbUser} --password={$dbPass} --host={$dbHost} --routines --triggers --events --complete-insert {$dbName} > \"$tempSqlFile\"";
exec($command, $output, $result);

// Si el backup fue exitoso, guardar en ambas ubicaciones
if ($result === 0 && file_exists($tempSqlFile)) {
    // Guardar en la PRIMERA ubicación
    copy($tempSqlFile, $sqlPath1);
    $zip1 = new ZipArchive();
    if ($zip1->open($zipPath1, ZipArchive::CREATE) === TRUE) {
        $zip1->addFile($tempSqlFile, $sqlFile);
        $zip1->close();
        unlink($sqlPath1); // Elimina el .sql después de comprimir
    }
    
    // Guardar en la SEGUNDA ubicación
    copy($tempSqlFile, $sqlPath2);
    $zip2 = new ZipArchive();
    if ($zip2->open($zipPath2, ZipArchive::CREATE) === TRUE) {
        $zip2->addFile($tempSqlFile, $sqlFile);
        $zip2->close();
        unlink($sqlPath2); // Elimina el .sql después de comprimir
    }
    
    // Eliminar archivo temporal
    unlink($tempSqlFile);
}

// Auditoría final
$evento = ($result === 0)
    ? "Backup completo ZIP creado en ambas ubicaciones: {$zipFile} por {$usuario} desde IP {$ip}"
    : "Error al crear backup completo por {$usuario} desde IP {$ip}";

$pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
guardar_log($pdo, $usuario, $evento, $ip, $fecha, 'respaldos');

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if (!$conn->connect_error) {
    $stmt = $conn->prepare("INSERT INTO auditoria (usuario, evento, fecha, ip) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $usuario, $evento, $fecha, $ip);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

header("Location: index.php?backup=" . ($result === 0 ? "ok" : "error"));
exit;
?>