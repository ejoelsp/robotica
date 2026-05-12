@echo off
REM ============================================================
REM  BACKUP AUTOMÁTICO DE LA BD "robotica" (PostgreSQL 17)
REM  Proyecto: RoboESPOCH - Backend Laravel
REM ============================================================

REM === CONFIGURACIÓN ===

REM Ruta a pg_dump (AJUSTAR SI TU INSTALACIÓN ES DIFERENTE)
set "PG_DUMP=C:\Program Files\PostgreSQL\17\bin\pg_dump.exe"

REM Carpeta donde guardar los backups
set "BACKUP_DIR=C:\laragon\www\robotica\backend\backup"

REM Configuración de la BD (según tu .env real)
set "PGHOST=127.0.0.1"
set "PGPORT=5434"
set "PGDATABASE=robotica"
set "PGUSER=postgres"
set "PGPASSWORD=postgre"

REM === OBTENER FECHA Y HORA ===
set "DATESTR=%date:~-4%%date:~3,2%%date:~0,2%"
set "TIMESTR=%time:~0,2%%time:~3,2%%time:~6,2%"
set "TIMESTR=%TIMESTR: =0%"
set "STAMP=%DATESTR%_%TIMESTR%"

REM Nombre del archivo
set "FILENAME=backup_robotica_%STAMP%.sql"
set "FULLPATH=%BACKUP_DIR%\%FILENAME%"

REM Crear directorio si no existe
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

REM Exportar password
set PGPASSWORD=%PGPASSWORD%

REM === REALIZAR BACKUP ===
"%PG_DUMP%" -h %PGHOST% -p %PGPORT% -U %PGUSER% -F p -d %PGDATABASE% -f "%FULLPATH%"

REM ==============
REM  CALCULAR HASH SHA256
REM ==============
for /f "tokens=* USEBACKQ" %%F in (`powershell -command "(Get-FileHash '%FULLPATH%' -Algorithm SHA256).Hash"`) do (
    set FILEHASH=%%F
)

REM ==============
REM  CALCULAR PESO EN BYTES
REM ==============
for %%A in ("%FULLPATH%") do set FILESIZE=%%~zA

echo Backup generado: %FULLPATH%
echo HASH: %FILEHASH%
echo BYTES: %FILESIZE%

REM Vendrá ahora la conexión hacia Laravel para insertar en backups_log
REM Eso lo haremos en el siguiente paso.

REM === REGISTRAR EN LARAVEL ===
cd C:\laragon\www\robotica\backend
php artisan backup:registrar "%FILENAME%" "%FILEHASH%" "%FILESIZE%" "OK"

exit /b 0
