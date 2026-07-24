@echo off
REM ============================================================================
REM Setup script for Certificate Management System - Automatic Reminder Scheduler
REM ============================================================================
REM 
REM This script sets up Windows Task Scheduler to run daily reminders automatically
REM 
REM Run this file as Administrator:
REM 1. Right-click on this file
REM 2. Select "Run as administrator"
REM

setlocal enabledelayedexpansion

REM Get the directory where this script is located
set SCRIPT_DIR=%~dp0
set SCRIPT_DIR=%SCRIPT_DIR:~0,-1%

REM Find PHP executable
for %%X in (php.exe) do (set PHP_PATH=%%~$PATH:X)

if "%PHP_PATH%"=="" (
    echo.
    echo ERROR: PHP executable not found in PATH!
    echo.
    echo Please ensure PHP is installed and added to your system PATH.
    echo Or manually specify the PHP path in this script.
    echo.
    pause
    exit /b 1
)

echo.
echo ============================================================================
echo Certificate Management System - Reminder Scheduler Setup
echo ============================================================================
echo.
echo Script Directory: %SCRIPT_DIR%
echo PHP Executable: %PHP_PATH%
echo.
echo This will create a Windows Task Scheduler job to run reminders daily at 06:00 AM
echo.

REM Create the scheduled task
echo Creating scheduled task...
echo.

schtasks /create ^
    /tn "Certificate Management - Daily Reminders" ^
    /tr "%PHP_PATH% \"%SCRIPT_DIR%\scheduler.php\"" ^
    /sc DAILY ^
    /st 06:00 ^
    /f

if %ERRORLEVEL%==0 (
    echo.
    echo SUCCESS! Scheduled task created successfully!
    echo.
    echo Task Name: Certificate Management - Daily Reminders
    echo Schedule: Every day at 06:00 AM
    echo Action: %PHP_PATH% "%SCRIPT_DIR%\scheduler.php"
    echo.
    echo You can manage this task in Windows Task Scheduler:
    echo 1. Press Windows + R
    echo 2. Type: taskschd.msc
    echo 3. Look for "Certificate Management - Daily Reminders" in the task list
    echo.
) else (
    echo.
    echo ERROR: Failed to create scheduled task!
    echo.
    echo Make sure you are running this script as Administrator.
    echo.
)

REM Ensure logs directory exists
if not exist "%SCRIPT_DIR%\logs" (
    mkdir "%SCRIPT_DIR%\logs"
    echo Created logs directory
)

echo.
pause
