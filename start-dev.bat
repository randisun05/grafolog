@echo off
REM Menyalakan seluruh server dev Guratan: MySQL, API Laravel (:8123), frontend Vite (:5173).
REM Setiap server dibuka di jendela terpisah supaya log-nya kelihatan dan bisa
REM ditutup satu-satu (Ctrl+C di jendela itu, atau tutup jendelanya).
REM
REM Path mysqld di bawah sesuai instalasi Laragon di mesin ini
REM (C:\laragon\bin\mysql\mysql-8.4.3-winx64) - kalau Laragon di-upgrade dan
REM versi MySQL berubah, sesuaikan MYSQL_DIR.

setlocal
set MYSQL_DIR=C:\laragon\bin\mysql\mysql-8.4.3-winx64
set API_DIR=D:\Project\grafolog\guratan-api
set WEB_DIR=D:\Project\grafolog\guratan-web

echo ==========================================
echo   Guratan - Menyalakan server development
echo ==========================================
echo.

REM --- 1. MySQL ---
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I "mysqld.exe" >NUL
if %ERRORLEVEL%==0 (
    echo [1/5] MySQL sudah berjalan, dilewati.
) else (
    echo [1/5] Menyalakan MySQL...
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
echo [2/5] Menyalakan API Laravel di port 8123...
REM php artisan serve tidak meneruskan setting php.ini; batas unggah default PHP (2 MB) terlalu kecil
REM untuk hasil scan tulisan (maks. 30 MB x 5 file). Server bawaan PHP dijalankan langsung dengan
REM router yang sama dengan artisan serve.
start "Guratan - API (8123)" cmd /k "cd /d %API_DIR%\public && php -d upload_max_filesize=32M -d post_max_size=160M -S 127.0.0.1:8123 ..\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php"

REM --- 3. Tunnel Cloudflare (BE + FE) ---
REM Harus SEBELUM Vite: skrip ini menulis VITE_API_URL ke .env.development dan origin FE ke
REM CORS_ALLOWED_ORIGINS API, sedangkan Vite hanya membaca .env saat start. Gagal = mode lokal saja.
echo [3/5] Menyalakan tunnel Cloudflare (BE + FE), menunggu URL...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0start-tunnel.ps1"
if errorlevel 1 (
    echo       Tunnel gagal - lanjut mode lokal. Pastikan VITE_API_URL di guratan-web\.env.development = http://127.0.0.1:8123/api
)

REM --- 4. Frontend Vite ---
echo [4/5] Menyalakan frontend Vite di port 5173...
start "Guratan - Web (5173)" cmd /k "cd /d %WEB_DIR% && npm run dev"

REM --- 5. Queue worker (email "laporan siap", jadwal konsultasi, reset password) ---
echo [5/5] Menyalakan queue worker...
start "Guratan - Queue" cmd /k "cd /d %API_DIR% && php artisan queue:listen --tries=1"

echo.
echo Semua server sedang menyala di jendela masing-masing.
echo   - Email mode log: isi email ada di guratan-api\storage\logs\laravel.log
echo   - API   : http://127.0.0.1:8123
echo   - Web   : http://localhost:5173
if exist "%TEMP%\guratan-tunnel\urls.txt" (
    echo   - URL tunnel publik ^(buka FE dari HP^):
    type "%TEMP%\guratan-tunnel\urls.txt"
)
echo.
echo Tutup jendela masing-masing (atau Ctrl+C di dalamnya) untuk mematikan.
pause
