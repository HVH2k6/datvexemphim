# Tạo Windows Task Scheduler chạy scripts/release-expired.php mỗi phút
#   powershell -ExecutionPolicy Bypass -File scripts\install-cron.ps1            # cài
#   powershell -ExecutionPolicy Bypass -File scripts\install-cron.ps1 -Remove    # gỡ
param(
    [switch]$Remove,
    [string]$Php = (Get-Command php -ErrorAction SilentlyContinue).Source
)

$taskName = 'CinemaBooking-ReleaseExpired'
$root = Split-Path -Parent $PSScriptRoot

if ($Remove) {
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue
    Write-Host "Đã gỡ task $taskName"
    exit 0
}

if (-not $Php -or -not (Test-Path $Php)) {
    Write-Error 'Không tìm thấy php.exe. Truyền đường dẫn: -Php "D:\laragon\bin\php\...\php.exe"'
    exit 1
}

$action = New-ScheduledTaskAction -Execute $Php -Argument "`"$root\scripts\release-expired.php`"" -WorkingDirectory $root
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1)
$settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 5) -StartWhenAvailable

Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Force | Out-Null
Write-Host "Đã tạo task $taskName chạy mỗi phút: $Php $root\scripts\release-expired.php"
