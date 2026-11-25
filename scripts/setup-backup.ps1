# إعداد النسخ الاحتياطي اليومي التلقائي على ويندوز (XAMPP)
# التشغيل من جذر المشروع:
#   powershell -ExecutionPolicy Bypass -File scripts\setup-backup.ps1
#   powershell -ExecutionPolicy Bypass -File scripts\setup-backup.ps1 -Time 23:00 -CopyPath "E:\SupermarketBackups"
param(
    [string]$Time = '23:30',
    [string]$CopyPath = ''
)
$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$utf8 = New-Object System.Text.UTF8Encoding($false)

$php = 'C:\xampp\php\php.exe'
if (-not (Test-Path $php)) { $php = (Get-Command php -ErrorAction Stop).Source }

# 1) إعدادات .env
$envPath = Join-Path $root '.env'
$text = [IO.File]::ReadAllText($envPath)
$map = [ordered]@{ 'BACKUP_KEEP_DAYS' = '30' }
if (Test-Path 'C:\xampp\mysql\bin\mysqldump.exe') {
    $map['MYSQLDUMP_PATH'] = 'C:\xampp\mysql\bin\mysqldump.exe'
    $map['MYSQL_PATH'] = 'C:\xampp\mysql\bin\mysql.exe'
}
if ($CopyPath -ne '') { $map['BACKUP_COPY_PATH'] = $CopyPath }
foreach ($k in $map.Keys) {
    $line = "$k=$($map[$k])"
    $pattern = "(?m)^#?\s*$k=.*"
    if ($text -match $pattern) { $text = [regex]::Replace($text, $pattern, $line.Replace('$', '$$')) }
    else { $text += "`n$line" }
}
[IO.File]::WriteAllText($envPath, $text, $utf8)

# 2) مهمة مجدولة يومية (تعمل لاحقاً إن كان الجهاز مغلقاً وقت الموعد)
$action = New-ScheduledTaskAction -Execute $php -Argument "`"$root\artisan`" db:backup" -WorkingDirectory $root
$trigger = New-ScheduledTaskTrigger -Daily -At $Time
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries
Register-ScheduledTask -TaskName 'SupermarketDbBackup' -Action $action -Trigger $trigger -Settings $settings -Force | Out-Null

Write-Host "تم: نسخة احتياطية يومية الساعة $Time (المهمة SupermarketDbBackup)."
Write-Host 'جرّب الآن: php artisan db:backup'
