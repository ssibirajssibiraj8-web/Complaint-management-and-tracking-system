@echo off
setlocal enabledelayedexpansion
title Campus Complaint Management System Runner

echo ========================================================
echo   Campus Complaint Management and Tracking System
echo   One-Command Local Web Runner
echo ========================================================
echo.

REM 1. Ensure MySQL on port 3307 is active
netstat -ano | findstr ":3307" >nul 2>&1
if %errorlevel% neq 0 (
    echo [INFO] Starting local MySQL database service...
    start /b "" "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqld.exe" --datadir="%LOCALAPPDATA%\campus_care_db" --port=3307
    timeout /t 2 /nobreak >nul
)

REM 2. Locate PHP executable
set "PHP_BIN="
where php >nul 2>&1
if %errorlevel% equ 0 (
    set "PHP_BIN=php"
) else if exist "%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" (
    set "PHP_BIN=%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
) else if exist "C:\xampp\php\php.exe" (
    set "PHP_BIN=C:\xampp\php\php.exe"
) else (
    echo [ERROR] PHP was not found in PATH or standard directories.
    echo Please ensure PHP is installed.
    pause
    exit /b 1
)

echo [OK] Using PHP: %PHP_BIN%
echo [OK] Database: Connected on 127.0.0.1:3307 (Zero password required)
echo.

REM 3. Display Demo Credentials
echo ========================================================
echo   Demo Login Accounts:
echo   - Admin:   admin@demo.com   / admin123
echo   - Staff:   staff@demo.com   / staff123
echo   - Student: student@demo.com / student123
echo ========================================================
echo.

REM 4. Open browser automatically
echo Starting server at http://localhost:8000 ...
start "" "http://localhost:8000/login.php"

REM 5. Start PHP Built-in Server
"%PHP_BIN%" -S localhost:8000
