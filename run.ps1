# Campus Complaint Management System - PowerShell Runner
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "  Campus Complaint Management and Tracking System" -ForegroundColor White
Write-Host "  One-Command Local Web Runner" -ForegroundColor Yellow
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Ensure MySQL on port 3307 is running
$isMysqlRunning = Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue
if (-not $isMysqlRunning) {
    Write-Host "[INFO] Starting dedicated MySQL instance on port 3307..." -ForegroundColor Yellow
    Start-Process "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqld.exe" -ArgumentList "--datadir=$env:LOCALAPPDATA\campus_care_db", "--port=3307" -WindowStyle Hidden
    Start-Sleep -Seconds 2
}

# 2. Locate PHP
$phpBin = $null
if (Get-Command php -ErrorAction SilentlyContinue) {
    $phpBin = "php"
} elseif (Test-Path "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe") {
    $phpBin = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
} elseif (Test-Path "C:\xampp\php\php.exe") {
    $phpBin = "C:\xampp\php\php.exe"
}

if (-not $phpBin) {
    Write-Host "[ERROR] PHP was not found on your system." -ForegroundColor Red
    exit 1
}

Write-Host "[OK] Using PHP: $phpBin" -ForegroundColor Green
Write-Host "[OK] Database: Connected on 127.0.0.1:3307 (No password needed)" -ForegroundColor Green
Write-Host ""
Write-Host "Demo Credentials:" -ForegroundColor Cyan
Write-Host "  Admin:   admin@demo.com   / admin123" -ForegroundColor White
Write-Host "  Staff:   staff@demo.com   / staff123" -ForegroundColor White
Write-Host "  Student: student@demo.com / student123" -ForegroundColor White
Write-Host ""
Write-Host "Starting web server at http://localhost:8000 ..." -ForegroundColor Green

Start-Process "http://localhost:8000/login.php"
& $phpBin -S localhost:8000
