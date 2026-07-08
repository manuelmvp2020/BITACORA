<?php
// backup_automatico.php - Backup Automático para Sistema de Usuarios
// Este script puede ejecutarse manualmente o por CRON/Task Scheduler

// Cargar configuración
require_once 'config.php';

// Configurar zona horaria de Santo Domingo, República Dominicana
date_default_timezone_set('America/Santo_Domingo');

// ============================================
// DETECTAR CONEXIÓN A BASE DE DATOS
// ============================================

// Detectar qué variable de conexión existe
$db = null;
if (isset($pdo) && $pdo !== null) {
    $db = $pdo;
    $db_type = 'PDO';
} elseif (isset($conn) && $conn !== null) {
    $db = $conn;
    $db_type = 'MySQLi';
} else {
    // Intentar crear conexión PDO
    try {
        $dbHost = 'localhost';
        $dbUser = 'root';
        $dbPass = 'mysql';
        $dbName = 'audit_system';  // Cambia esto si tu BD tiene otro nombre
        
        $db = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db_type = 'PDO';
    } catch (PDOException $e) {
        // Intentar con MySQLi
        $db = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
        if ($db->connect_error) {
            die("Error: No se pudo conectar a la base de datos. Verifica config.php");
        }
        $db_type = 'MySQLi';
    }
}

// ============================================
// CONFIGURACIÓN
// ============================================

// Directorios de backup
$backup_dirs = [
    __DIR__ . '/backups/',                                    // Carpeta local del sistema
    'C:/Users/mdelvallepaulus/Desktop/backup/'               // Carpeta en escritorio
];

// Configuración
$max_backups = 10;           // Número máximo de backups a mantener
$min_free_space = 50;        // Espacio mínimo en MB requerido

$timestamp = date('Ymd_His');
$sql_file_name = "backup_auto_{$timestamp}.sql";
$zip_file_name = "backup_auto_{$timestamp}.zip";

// ============================================
// FUNCIONES
// ============================================

/**
 * Escribir en archivo de log
 */
function writeLog($message, $log_file = null) {
    if ($log_file === null) {
        $log_file = __DIR__ . '/backups/auto_backup_log.txt';
    }
    
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0777, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    @file_put_contents($log_file, $log_entry, FILE_APPEND);
    
    // También mostrar en consola si se ejecuta desde CLI
    if (php_sapi_name() === 'cli') {
        echo $log_entry;
    }
}

/**
 * Exportar base de datos (soporta PDO y MySQLi)
 */
function exportDatabase($db, $db_type, $sql_file) {
    $tables = [];
    
    try {
        if ($db_type === 'PDO') {
            $stmt = $db->query("SHOW TABLES");
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
        } else {
            $result = $db->query("SHOW TABLES");
            while ($row = $result->fetch_array()) {
                $tables[] = $row[0];
            }
        }
    } catch (Exception $e) {
        throw new Exception("Error al obtener tablas: " . $e->getMessage());
    }
    
    if (empty($tables)) {
        throw new Exception("No se encontraron tablas en la base de datos");
    }
    
    $sql = "-- ============================================\n";
    $sql .= "-- BACKUP AUTOMÁTICO - Sistema de Usuarios\n";
    $sql .= "-- ============================================\n";
    $sql .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Zona horaria: " . date_default_timezone_get() . "\n";
    $sql .= "-- Hora local: " . date('H:i:s') . " (Santo Domingo)\n";
    $sql .= "-- ============================================\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $sql .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
    $sql .= "SET time_zone = '-04:00';\n\n";
    
    foreach ($tables as $table) {
        try {
            // Estructura de la tabla
            if ($db_type === 'PDO') {
                $stmt = $db->query("SHOW CREATE TABLE `$table`");
                $row = $stmt->fetch(PDO::FETCH_NUM);
                $create_sql = $row[1];
                
                // Datos
                $stmt = $db->query("SELECT * FROM `$table`");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $result = $db->query("SHOW CREATE TABLE `$table`");
                $row = $result->fetch_assoc();
                $create_sql = $row['Create Table'];
                
                // Datos
                $result = $db->query("SELECT * FROM `$table`");
                $rows = [];
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
            }
            
            $sql .= "-- --------------------------------------------------------\n";
            $sql .= "-- Estructura de tabla `$table`\n";
            $sql .= "-- --------------------------------------------------------\n\n";
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql .= $create_sql . ";\n\n";
            
            if (count($rows) > 0) {
                $sql .= "-- Datos de tabla `$table` (" . count($rows) . " registros)\n\n";
                
                foreach ($rows as $row) {
                    $columns = array_keys($row);
                    
                    if ($db_type === 'PDO') {
                        $values = array_map(function($value) use ($db) {
                            if ($value === null) return 'NULL';
                            return $db->quote($value);
                        }, array_values($row));
                    } else {
                        $values = array_map(function($value) use ($db) {
                            if ($value === null) return 'NULL';
                            return "'" . $db->real_escape_string($value) . "'";
                        }, array_values($row));
                    }
                    
                    $sql .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql .= "\n";
            }
        } catch (Exception $e) {
            $sql .= "-- ERROR en tabla `$table`: " . $e->getMessage() . "\n\n";
            writeLog("ADVERTENCIA: Error al procesar tabla `$table` - " . $e->getMessage());
        }
    }
    
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    $sql .= "-- ============================================\n";
    $sql .= "-- FIN DEL BACKUP AUTOMÁTICO\n";
    $sql .= "-- ============================================\n";
    
    if (@file_put_contents($sql_file, $sql) === false) {
        throw new Exception("Error al escribir el archivo SQL: $sql_file");
    }
    
    return $sql_file;
}

/**
 * Crear archivo ZIP
 */
function createZip($zip_file, $files_to_add) {
    if (!class_exists('ZipArchive')) {
        throw new Exception("ZipArchive no está disponible. Habilita la extensión php_zip en php.ini");
    }
    
    $zip = new ZipArchive();
    $result = $zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    
    if ($result !== TRUE) {
        throw new Exception("Error al crear ZIP (código: $result)");
    }
    
    $added = 0;
    foreach ($files_to_add as $file => $local_name) {
        if (file_exists($file) && is_file($file)) {
            if ($zip->addFile($file, $local_name)) {
                $added++;
            }
        }
    }
    
    if ($added === 0) {
        $zip->close();
        @unlink($zip_file);
        throw new Exception("No se pudo agregar ningún archivo al ZIP");
    }
    
    if (!$zip->close()) {
        @unlink($zip_file);
        throw new Exception("Error al cerrar el archivo ZIP. Verifica permisos de escritura.");
    }
    
    return true;
}

/**
 * Limpiar backups antiguos
 */
function cleanOldBackups($directory, $max_backups) {
    if (!is_dir($directory) || !is_readable($directory)) {
        return;
    }
    
    $backups = [];
    $files = @scandir($directory);
    
    if ($files) {
        foreach ($files as $file) {
            if (preg_match('/^backup_auto_.*\.zip$/', $file)) {
                $filepath = $directory . $file;
                if (is_file($filepath)) {
                    $backups[$filepath] = filemtime($filepath);
                }
            }
        }
        
        // Ordenar por fecha (más antiguos primero)
        asort($backups);
        
        // Eliminar excedentes
        $excess = count($backups) - $max_backups;
        if ($excess > 0) {
            $deleted = 0;
            foreach ($backups as $filepath => $timestamp) {
                if ($deleted >= $excess) break;
                if (@unlink($filepath)) {
                    $deleted++;
                    writeLog("Backup antiguo eliminado: " . basename($filepath));
                }
            }
        }
    }
}

/**
 * Formatear tamaño de archivo
 */
function formatSize($bytes) {
    if ($bytes === false || $bytes === null || $bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes, 1024));
    $i = min($i, count($units) - 1);
    return round($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
}

// ============================================
// PROCESO PRINCIPAL
// ============================================

writeLog("===========================================");
writeLog("INICIANDO BACKUP AUTOMÁTICO");
writeLog("Fecha/Hora: " . date('Y-m-d H:i:s T'));
writeLog("Tipo de conexión: $db_type");
writeLog("Zona horaria: " . date_default_timezone_get() . " (Santo Domingo)");
writeLog("===========================================");

$backup_success = false;
$errors = [];
$zip_created = 0;

try {
    // 1. Verificar espacio en disco
    foreach ($backup_dirs as $dir) {
        if (is_dir($dir)) {
            $free = @disk_free_space($dir);
            if ($free !== false) {
                $free_mb = round($free / 1024 / 1024, 2);
                writeLog("Espacio libre en $dir: $free_mb MB");
                
                if ($free < $min_free_space * 1024 * 1024) {
                    writeLog("⚠️ ADVERTENCIA: Espacio bajo en $dir");
                }
            }
        }
    }
    
    // 2. Preparar directorios
    foreach ($backup_dirs as $dir) {
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0777, true)) {
                throw new Exception("No se puede crear el directorio: $dir");
            }
            writeLog("Directorio creado: $dir");
        }
        
        if (!is_writable($dir)) {
            @chmod($dir, 0777);
            if (!is_writable($dir)) {
                throw new Exception("Sin permisos de escritura: $dir");
            }
        }
    }
    
    // 3. Exportar base de datos
    writeLog("Exportando base de datos...");
    $temp_dir = sys_get_temp_dir();
    $sql_file = $temp_dir . '/' . $sql_file_name;
    
    exportDatabase($db, $db_type, $sql_file);
    
    $sql_size = filesize($sql_file);
    writeLog("SQL exportado: " . basename($sql_file) . " (" . formatSize($sql_size) . ")");
    
    // 4. Crear archivos ZIP en cada ubicación
    $files_for_zip = [
        $sql_file => $sql_file_name
    ];
    
    // Agregar archivos de uploads si existen
    if (is_dir('uploads')) {
        $upload_files = @scandir('uploads');
        if ($upload_files) {
            foreach ($upload_files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $filepath = 'uploads/' . $file;
                    if (is_file($filepath)) {
                        $files_for_zip[$filepath] = 'uploads/' . $file;
                    }
                }
            }
        }
    }
    
    foreach ($backup_dirs as $dir) {
        $zip_path = $dir . $zip_file_name;
        
        try {
            writeLog("Creando ZIP en: $zip_path");
            if (createZip($zip_path, $files_for_zip)) {
                $zip_size = filesize($zip_path);
                writeLog("✅ ZIP creado: " . basename($zip_path) . " (" . formatSize($zip_size) . ")");
                $zip_created++;
                
                // Limpiar backups antiguos
                cleanOldBackups($dir, $max_backups);
            }
        } catch (Exception $e) {
            $errors[] = "Error en $dir: " . $e->getMessage();
            writeLog("❌ ERROR en $dir: " . $e->getMessage());
        }
    }
    
    // 5. Limpiar archivo SQL temporal
    if (file_exists($sql_file)) {
        @unlink($sql_file);
        writeLog("Archivo temporal eliminado");
    }
    
    // 6. Resultado final
    if ($zip_created > 0) {
        $backup_success = true;
        writeLog("✅ BACKUP COMPLETADO: $zip_created de " . count($backup_dirs) . " ubicaciones");
    } else {
        throw new Exception("No se pudo crear el backup en ninguna ubicación");
    }
    
} catch (Exception $e) {
    $errors[] = $e->getMessage();
    writeLog("❌ ERROR CRÍTICO: " . $e->getMessage());
    
    // Limpiar archivos temporales
    if (isset($sql_file) && file_exists($sql_file)) {
        @unlink($sql_file);
    }
}

// Guardar información del último backup
$backup_info = [
    'last_backup' => date('Y-m-d H:i:s'),
    'timezone' => date_default_timezone_get(),
    'local_time' => date('H:i:s'),
    'status' => $backup_success ? 'success' : 'failed',
    'file' => $zip_file_name,
    'db_type' => $db_type,
    'errors' => $errors,
    'zip_created' => $zip_created
];

$info_file = __DIR__ . '/backups/backup_info.json';
@file_put_contents($info_file, json_encode($backup_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

writeLog("===========================================");
writeLog("PROCESO FINALIZADO - Estado: " . ($backup_success ? 'ÉXITO' : 'FALLIDO'));
writeLog("===========================================\n");

// ============================================
// SALIDA (si se ejecuta desde navegador)
// ============================================

if (php_sapi_name() !== 'cli') {
    $log_content = @file_get_contents(__DIR__ . '/backups/auto_backup_log.txt');
    $log_content = $log_content ? htmlspecialchars($log_content) : 'No hay log disponible';
    
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Backup Automático - Resultado</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            body { 
                background: #f4f6f9; 
                font-family: 'Segoe UI', sans-serif; 
                padding: 20px; 
            }
            .container { 
                max-width: 800px; 
                margin: 0 auto; 
                background: white; 
                padding: 30px; 
                border-radius: 12px; 
                box-shadow: 0 0 20px rgba(0,0,0,0.08); 
            }
            .header {
                background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
                color: white;
                padding: 20px;
                border-radius: 12px;
                margin-bottom: 20px;
                text-align: center;
            }
            .success-box { 
                color: #155724; 
                background: #d4edda; 
                border: 1px solid #c3e6cb; 
                padding: 15px; 
                border-radius: 8px; 
                margin-bottom: 20px;
            }
            .error-box { 
                color: #721c24; 
                background: #f8d7da; 
                border: 1px solid #f5c6cb; 
                padding: 15px; 
                border-radius: 8px; 
                margin-bottom: 20px;
            }
            pre { 
                background: #1e1e1e; 
                color: #d4d4d4; 
                padding: 15px; 
                border-radius: 8px; 
                overflow-x: auto; 
                font-size: 13px;
                max-height: 400px;
                overflow-y: auto;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2><i class="fas fa-robot me-2"></i>Backup Automático</h2>
                <p class="mb-0">🕐 Santo Domingo, República Dominicana (AST/UTC-4)</p>
            </div>
            
            <div class="<?= $backup_success ? 'success-box' : 'error-box' ?>">
                <strong>
                    <?= $backup_success ? '✅ Backup creado exitosamente' : '❌ Error en el backup' ?>
                </strong>
                <br>
                <small>📅 Fecha: <?= date('Y-m-d H:i:s T') ?></small>
                <?php if ($backup_success): ?>
                <br><small>📁 Archivo: <?= htmlspecialchars($zip_file_name) ?></small>
                <br><small>📂 Ubicaciones: <?= $zip_created ?> de <?= count($backup_dirs) ?></small>
                <?php endif; ?>
                <br><small>🔌 Conexión: <?= htmlspecialchars($db_type) ?></small>
            </div>
            
            <?php if (!empty($errors)): ?>
            <div class="error-box">
                <strong>⚠️ Errores encontrados:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <h5><i class="fas fa-terminal me-2"></i>Log del proceso:</h5>
            <pre><?= $log_content ?></pre>
            
            <div class="text-center mt-4">
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-2"></i>Volver al Sistema
                </a>
                <a href="backup_automatico.php" class="btn btn-success">
                    <i class="fas fa-sync me-2"></i>Ejecutar Nuevamente
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
}

// Retornar código de salida para CRON (0 = éxito, 1 = error)
exit($backup_success ? 0 : 1);
?>