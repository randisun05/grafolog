@echo off
REM Menyalakan seluruh server dev Guratan: MySQL, API Laravel (:8123), frontend Vite (:5173).
REM Setiap server dibuka di jendela terpisah supaya log-nya kelihatan dan bisa
REM ditutup satu-satu (Ctrl+C di jendela itu, atau tutup jendelanya).
REM
REM Path mysqld di bawah sesuai instalasi Laragon di mesin ini
REM (D:\laragon\bin\mysql\mysql-8.0.46-winx64) - kalau Laragon di-upgrade dan
REM versi MySQL berubah, sesuaikan MYSQL_DIR.

setlocal
set MYSQL_DIR=D:\laragon\bin\mysql\mysql-8.0.46-winx64
set API_DIR=D:\Project\grafolog\guratan-api
set WEB_DIR=D:\Project\grafolog\guratan-web

echo ==========================================
echo   Guratan - Menyalakan server development
echo ==========================================
echo.

REM --- 1. MySQL ---
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I "mysqld.exe" >NUL
if %ERRORLEVEL%==0 (
    echo [1/3] MySQL sudah berjalan, dilewati.
) else (
    echo [1/3] Menyalakan MySQL...
    start "Guratan - MySQL" "%MYSQL_DIR%\bin\mysqld.exe" --defaults-file="%MYSQL_DIR%\my.ini"
)

echo       Menunggu MySQL siap menerima koneksi...
:wait_mysql
"%MYSQL_DIR%\bin\mysql.exe" -u root -e "SELECT 1;" >NUL 2>&1
if errorlevel 1 (
    timeout /t 2 /nobreak >NUL
    goto wait_mysql
)
echo       MySQL siap.
echo.

REM --- 2. API Laravel ---
echo [2/3] Menyalakan API Laravel di port 8123...
start "Guratan - API (8123)" cmd /k "cd /d %API_DIR% && php artisan serve --port=8123"

REM --- 3. Frontend Vite ---
echo [3/3] Menyalakan frontend Vite di port 5173...
start "Guratan - Web (5173)" cmd /k "cd /d %WEB_DIR% && npm run dev"

echo.
echo Semua server sedang menyala di jendela masing-masing.
echo   - API   : http://127.0.0.1:8123
echo   - Web   : http://localhost:5173
echo.
echo Tutup jendela masing-masing (atau Ctrl+C di dalamnya) untuk mematikan.
pause
