# Run the site through ngrok so SePay can reach the webhook on this machine.
#   powershell -ExecutionPolicy Bypass -File scripts\ngrok-dev.ps1
# First time only: ngrok config add-authtoken <TOKEN>
#   (token: https://dashboard.ngrok.com/get-started/your-authtoken)
# Optional: -Php "D:\laragon\bin\php\...\php.exe"  -Port 8000

param(
    [string]$Php = 'D:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe',
    [string]$Ngrok = 'D:\laragon\bin\ngrok\ngrok.exe',
    [int]$Port = 8000
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot

if (-not (Test-Path $Php)) { throw "PHP not found: $Php (pass -Php <path to php.exe>)" }
if (-not (Test-Path $Ngrok)) { throw "ngrok not found: $Ngrok (pass -Ngrok <path to ngrok.exe>)" }

# 1) Start ngrok tunnel -> localhost:$Port
$ngrokProc = Start-Process -FilePath $Ngrok -ArgumentList @('http', "$Port") -PassThru -WindowStyle Hidden

# 2) Read the public URL from the local ngrok API
$publicUrl = $null
for ($i = 0; $i -lt 30 -and -not $publicUrl; $i++) {
    Start-Sleep -Milliseconds 500
    try {
        $tunnels = Invoke-RestMethod 'http://127.0.0.1:4040/api/tunnels'
        $publicUrl = ($tunnels.tunnels | Where-Object { $_.public_url -like 'https://*' } | Select-Object -First 1).public_url
    } catch { }
}
if (-not $publicUrl) {
    Stop-Process -Id $ngrokProc.Id -ErrorAction SilentlyContinue
    throw 'Could not get the ngrok URL. Did you run "ngrok config add-authtoken <TOKEN>"?'
}

Write-Host ''
Write-Host "Public site     : $publicUrl"
Write-Host "SePay webhook   : $publicUrl/payment/sepay-webhook"
Write-Host "ngrok inspector : http://127.0.0.1:4040"
Write-Host 'Press Ctrl+C to stop.'
Write-Host ''

# 3) Run the PHP server with APP_URL = ngrok URL (real env vars take priority over .env)
$env:APP_URL = $publicUrl
try {
    & $Php -S "127.0.0.1:$Port" -t "$root\public" "$root\public\index.php"
} finally {
    Stop-Process -Id $ngrokProc.Id -ErrorAction SilentlyContinue
}
