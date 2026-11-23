# يُشغَّل من جذر المشروع:  powershell -ExecutionPolicy Bypass -File scripts\configure-env.ps1
$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$utf8 = New-Object System.Text.UTF8Encoding($false)

# 1) .env
$envPath = Join-Path $root '.env'
$text = [IO.File]::ReadAllText($envPath)
$map = [ordered]@{
    'APP_NAME' = 'Supermarket'
    'APP_LOCALE' = 'ar'
    'APP_FALLBACK_LOCALE' = 'ar'
    'DB_CONNECTION' = 'mysql'
    'DB_HOST' = '127.0.0.1'
    'DB_PORT' = '3306'
    'DB_DATABASE' = 'supermarket'
    'DB_USERNAME' = 'root'
    'DB_PASSWORD' = ''
}
foreach ($k in $map.Keys) {
    $line = "$k=$($map[$k])"
    $pattern = "(?m)^#?\s*$k=.*"
    if ($text -match $pattern) { $text = [regex]::Replace($text, $pattern, $line) }
    else { $text += "`n$line" }
}
[IO.File]::WriteAllText($envPath, $text, $utf8)

# 2) التوقيت
$appPath = Join-Path $root 'config\app.php'
$app = [IO.File]::ReadAllText($appPath)
$app = $app -replace "'timezone' => 'UTC'", "'timezone' => 'Asia/Hebron'"
[IO.File]::WriteAllText($appPath, $app, $utf8)

# 3) قاعدة البيانات (XAMPP)
$mysql = 'C:\xampp\mysql\bin\mysql.exe'
if (Test-Path $mysql) {
    & $mysql -u root -e "CREATE DATABASE IF NOT EXISTS supermarket CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    Write-Host 'Database "supermarket" ready.'
} else {
    Write-Host 'mysql.exe not found: create database "supermarket" (utf8mb4_unicode_ci) manually.'
}
Write-Host 'Done. Next: php artisan migrate:fresh --seed'
