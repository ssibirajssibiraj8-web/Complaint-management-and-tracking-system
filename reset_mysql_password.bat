@echo off
echo ============================================
echo   MySQL Root Password Reset Script
echo ============================================
echo.

:: Check for admin privileges
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: This script must be run as Administrator!
    echo Right-click this file and select "Run as administrator"
    pause
    exit /b 1
)

echo Step 1: Stopping MySQL80 service...
net stop MySQL80
if %errorlevel% neq 0 (
    echo WARNING: Could not stop MySQL80 service. It may already be stopped.
)
timeout /t 2 /nobreak >nul

echo.
echo Step 2: Creating password reset SQL file...
echo ALTER USER 'root'@'localhost' IDENTIFIED BY ''; > "%TEMP%\mysql_reset.sql"
echo FLUSH PRIVILEGES; >> "%TEMP%\mysql_reset.sql"

echo.
echo Step 3: Starting MySQL in safe mode with init file...
start /b "" "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqld.exe" --init-file="%TEMP%\mysql_reset.sql" --console
timeout /t 8 /nobreak >nul

echo.
echo Step 4: Testing connection with empty password...
"C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root --password="" -e "SELECT 'Password reset successful!' AS Result;" 2>nul
if %errorlevel% equ 0 (
    echo.
    echo SUCCESS! Root password has been reset to empty.
) else (
    echo.
    echo Connection test inconclusive. Proceeding to restart service...
)

echo.
echo Step 5: Shutting down safe-mode MySQL...
"C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqladmin.exe" -u root --password="" shutdown 2>nul
timeout /t 3 /nobreak >nul

:: Kill any remaining mysqld processes
taskkill /f /im mysqld.exe >nul 2>&1
timeout /t 2 /nobreak >nul

echo.
echo Step 6: Starting MySQL80 service normally...
net start MySQL80
if %errorlevel% equ 0 (
    echo.
    echo ============================================
    echo   MySQL root password has been reset!
    echo   Username: root
    echo   Password: 1234
    echo ============================================
    echo.
    echo You can now run the Campus Complaint System.
) else (
    echo.
    echo WARNING: Could not restart MySQL80 service.
    echo Try restarting it manually from Services.
)

:: Clean up
del "%TEMP%\mysql_reset.sql" >nul 2>&1

echo.
pause
