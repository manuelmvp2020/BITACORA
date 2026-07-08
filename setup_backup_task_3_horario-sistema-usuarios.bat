@echo off
title Configurar Backup Automático Programado - 3 Horarios
echo =====================================================
echo   Configurar Tarea Programada Backup - 3 Horarios
echo =====================================================
echo.
echo Este script creara 3 tareas programadas para backups automaticos
echo en los siguientes horarios:
echo   - 9:00 AM
echo   - 10:40 AM (Mediodia)
echo   - 5:00 PM
echo.
echo Zona horaria: America/Santo_Domingo (UTC-4)
echo.

:: Configuracion
set TASK_NAME_PREFIX=Backup-sistema-usuarios
set PHP_PATH=C:\Ampps\php\php.exe
set BACKUP_SCRIPT=C:\Ampps\www\sistema-usuarios\backup_automatico.php
set LOG_DIR=C:\Ampps\www\sistema-usuarios\backups
set SCRIPT_DIR=C:\Ampps\www\sistema-usuarios

:: Verificar archivos
echo Verificando requisitos...
if not exist "%PHP_PATH%" (
    echo [ERROR] No se encuentra PHP en: %PHP_PATH%
    echo.
    echo Por favor, verifique la ruta de instalacion de PHP
    pause
    exit /b 1
) else (
    echo [OK] PHP encontrado
)

if not exist "%BACKUP_SCRIPT%" (
    echo [ERROR] No se encuentra el script de backup en: %BACKUP_SCRIPT%
    echo.
    echo Por favor, verifique la ruta del script
    pause
    exit /b 1
) else (
    echo [OK] Script de backup encontrado
)

echo.
echo =====================================================
echo Eliminando tareas anteriores (si existen)...
echo =====================================================

:: Eliminar tareas existentes
schtasks /delete /tn "%TASK_NAME_PREFIX%_09h" /f 2>nul
schtasks /delete /tn "%TASK_NAME_PREFIX%_12h" /f 2>nul
schtasks /delete /tn "%TASK_NAME_PREFIX%_17h" /f 2>nul
schtasks /delete /tn "%TASK_NAME_PREFIX%" /f 2>nul

echo Tareas anteriores eliminadas (si existian)
echo.

echo =====================================================
echo Creando nuevas tareas programadas...
echo =====================================================
echo.

:: Tarea 1: 9:00 AM
echo [1/3] Creando tarea para las 9:00 AM...
schtasks /create ^
    /tn "%TASK_NAME_PREFIX%_09h" ^
    /tr "\"%PHP_PATH%\" -f \"%BACKUP_SCRIPT%\" ^> \"%LOG_DIR%\\backup_09h_log.txt\" 2^>^&1" ^
    /sc daily ^
    /st 09:00 ^
    /sd 01/01/2024 ^
    /f ^
    /ru "SYSTEM" ^
    /ri 1 ^
    /v1

if %errorlevel% equ 0 (
    echo [OK] Tarea 9:00 AM creada exitosamente
) else (
    echo [ERROR] No se pudo crear la tarea de las 9:00 AM
    echo        Asegurese de ejecutar como Administrador
)

echo.

:: Tarea 2: 12:00 PM
echo [2/3] Creando tarea para las 12:00 PM (Mediodia)...
schtasks /create ^
    /tn "%TASK_NAME_PREFIX%_12h" ^
    /tr "\"%PHP_PATH%\" -f \"%BACKUP_SCRIPT%\" ^> \"%LOG_DIR%\\backup_12h_log.txt\" 2^>^&1" ^
    /sc daily ^
    /st 12:00 ^
    /sd 01/01/2024 ^
    /f ^
    /ru "SYSTEM" ^
    /ri 1 ^
    /v1

if %errorlevel% equ 0 (
    echo [OK] Tarea 12:00 PM creada exitosamente
) else (
    echo [ERROR] No se pudo crear la tarea de las 12:00 PM
)

echo.

:: Tarea 3: 5:00 PM
echo [3/3] Creando tarea para las 5:00 PM...
schtasks /create ^
    /tn "%TASK_NAME_PREFIX%_17h" ^
    /tr "\"%PHP_PATH%\" -f \"%BACKUP_SCRIPT%\" ^> \"%LOG_DIR%\\backup_17h_log.txt\" 2^>^&1" ^
    /sc daily ^
    /st 17:00 ^
    /sd 01/01/2024 ^
    /f ^
    /ru "SYSTEM" ^
    /ri 1 ^
    /v1

if %errorlevel% equ 0 (
    echo [OK] Tarea 5:00 PM creada exitosamente
) else (
    echo [ERROR] No se pudo crear la tarea de las 5:00 PM
)

echo.
echo =====================================================
echo Verificando tareas creadas...
echo =====================================================
echo.

:: Listar las tareas creadas
echo Tareas programadas para backup:
echo ----------------------------------------
schtasks /query /tn "%TASK_NAME_PREFIX%*" /fo LIST | findstr "Nombre Tarea Proxima"
echo ----------------------------------------

echo.
echo =====================================================
echo CONFIGURACION COMPLETADA
echo =====================================================
echo.
echo Resumen de tareas programadas:
echo   [1] %TASK_NAME_PREFIX%_09h - 9:00 AM
echo   [2] %TASK_NAME_PREFIX%_12h - 12:00 PM
echo   [3] %TASK_NAME_PREFIX%_17h - 5:00 PM
echo.
echo Los logs de ejecucion se guardan en:
echo   %LOG_DIR%\backup_09h_log.txt
echo   %LOG_DIR%\backup_12h_log.txt
echo   %LOG_DIR%\backup_17h_log.txt
echo.
echo Los archivos de backup se guardan en:
echo   %LOG_DIR%
echo.
echo =====================================================
echo Para ver/editar las tareas manualmente:
echo   1. Ejecute: taskschd.msc
echo   2. Busque las tareas con nombre: %TASK_NAME_PREFIX%_*
echo.
echo Para eliminar todas las tareas:
echo   Ejecute: eliminar_backups_automaticos.bat
echo =====================================================
echo.
pause