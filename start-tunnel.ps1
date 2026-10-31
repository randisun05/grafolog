# Menyalakan 2 tunnel Cloudflare (BE :8123 dan FE :5173), menunggu URL-nya keluar,
# lalu otomatis menulis ke:
#   - guratan-web\.env.development  -> VITE_API_URL=<tunnel BE>/api
#   - guratan-api\.env              -> CORS_ALLOWED_ORIGINS (origin *.trycloudflare.com diganti tunnel FE)
# Dipanggil start-dev.bat SEBELUM Vite menyala (Vite hanya membaca .env saat start).
# Gagal / cloudflared tidak ada -> exit 1, env tidak diubah, start-dev.bat lanjut mode lokal.

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$webEnv = Join-Path $root 'guratan-web\.env.development'
$apiEnv = Join-Path $root 'guratan-api\.env'
$apiDir = Join-Path $root 'guratan-api'
$logDir = Join-Path $env:TEMP 'guratan-tunnel'
Remove-Item (Join-Path $logDir "urls.txt") -ErrorAction SilentlyContinue

$cf = (Get-Command cloudflared -ErrorAction SilentlyContinue).Source
if (-not $cf) { $cf = 'C:\Program Files (x86)\cloudflared\cloudflared.exe' }
if (-not (Test-Path $cf)) {
    Write-Host '      cloudflared tidak ditemukan - tunnel dilewati (mode lokal).' -ForegroundColor Yellow
    exit 1
}

# Tunnel lama sudah tidak berguna (URL berganti tiap dijalankan) - matikan supaya tidak menumpuk.
Get-Process cloudflared -ErrorAction SilentlyContinue | Stop-Process -Force
New-Item -ItemType Directory -Force $logDir | Out-Null

function Start-Tunnel($name, $target) {
    $log = Join-Path $logDir "$name.log"
    Remove-Item $log -ErrorAction SilentlyContinue
    # Jendela kecil terpisah supaya bisa dilihat / ditutup; log ke file untuk dibaca URL-nya.
    Start-Process cmd -WindowStyle Minimized -ArgumentList "/c title Guratan - Tunnel $name && `"$cf`" tunnel --no-autoupdate --url $target --logfile `"$log`""
    return $log
}

function Wait-Url($log, $timeoutSec = 60) {
    $deadline = (Get-Date).AddSeconds($timeoutSec)
    while ((Get-Date) -lt $deadline) {
        if (Test-Path $log) {
            $m = Select-String -Path $log -Pattern 'https://(?!api\.)[a-z0-9-]+\.trycloudflare\.com' -ErrorAction SilentlyContinue | Select-Object -First 1
            if ($m) { return $m.Matches[0].Value }
        }
        Start-Sleep -Milliseconds 700
    }
    return $null
}

# BE ke 127.0.0.1 (php -S bind di sana); FE ke "localhost" karena Vite hanya mendengarkan di IPv6 ([::1]).
$beLog = Start-Tunnel 'BE' 'http://127.0.0.1:8123'
$feLog = Start-Tunnel 'FE' 'http://localhost:5173'
$beUrl = Wait-Url $beLog
$feUrl = Wait-Url $feLog
if (-not $beUrl -or -not $feUrl) {
    Write-Host "      Gagal mendapat URL tunnel (BE=$beUrl FE=$feUrl). Cek log di $logDir" -ForegroundColor Red
    exit 1
}

$utf8 = New-Object System.Text.UTF8Encoding($false)

# --- FE: VITE_API_URL ---
$txt = [IO.File]::ReadAllText($webEnv)
if ($txt -match '(?m)^VITE_API_URL=') {
    $txt = [regex]::Replace($txt, '(?m)^VITE_API_URL=.*$', "VITE_API_URL=$beUrl/api")
} else {
    $txt = $txt.TrimEnd() + "`nVITE_API_URL=$beUrl/api`n"
}
[IO.File]::WriteAllText($webEnv, $txt, $utf8)

# --- BE: CORS_ALLOWED_ORIGINS (buang origin trycloudflare lama, tambah FE baru) ---
$txt = [IO.File]::ReadAllText($apiEnv)
$m = [regex]::Match($txt, '(?m)^CORS_ALLOWED_ORIGINS=(.*)$')
if ($m.Success) {
    $origins = $m.Groups[1].Value.Trim() -split ',' | Where-Object { $_ -and ($_ -notmatch 'trycloudflare\.com') }
    $line = 'CORS_ALLOWED_ORIGINS=' + (($origins + $feUrl) -join ',')
    $txt = $txt.Remove($m.Index, $m.Length).Insert($m.Index, $line)
} else {
    $txt = $txt.TrimEnd() + "`nCORS_ALLOWED_ORIGINS=http://localhost:5173,http://127.0.0.1:5173,$feUrl`n"
}
[IO.File]::WriteAllText($apiEnv, $txt, $utf8)

Push-Location $apiDir
try { php artisan config:clear | Out-Null } finally { Pop-Location }

Write-Host "      Tunnel BE : $beUrl" -ForegroundColor Green
Write-Host "      Tunnel FE : $feUrl   <- buka ini dari HP / perangkat lain" -ForegroundColor Green
Set-Content -Path (Join-Path $logDir 'urls.txt') -Value "BE=$beUrl`nFE=$feUrl"
exit 0
