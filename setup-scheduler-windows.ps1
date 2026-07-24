# ============================================================================
# Setup script for Certificate Management System - Automatic Reminder Scheduler
# ============================================================================
#
# This PowerShell script sets up Windows Task Scheduler to run daily reminders
#
# Usage:
# 1. Open PowerShell as Administrator
# 2. Navigate to the script directory
# 3. Run: .\setup-scheduler-windows.ps1
#
# If you get an execution policy error, run this first:
# Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
#

param(
    [string]$PHPPath = $null,
    [string]$ScheduleTime = "06:00",
    [switch]$Force
)

function Write-Header {
    Write-Host ""
    Write-Host "============================================================================" -ForegroundColor Cyan
    Write-Host "Certificate Management System - Reminder Scheduler Setup (PowerShell)" -ForegroundColor Cyan
    Write-Host "============================================================================" -ForegroundColor Cyan
    Write-Host ""
}

function Test-AdminPrivileges {
    $currentUser = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($currentUser)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Get-PHPExecutable {
    param($ProvidedPath)

    if ($ProvidedPath) {
        if (Test-Path $ProvidedPath) {
            return $ProvidedPath
        }
        Write-Host "ERROR: Provided PHP path not found: $ProvidedPath" -ForegroundColor Red
        return $null
    }

    # Try to find PHP in PATH
    $phpExe = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($phpExe) {
        return $phpExe.Source
    }

    # Common PHP installation paths
    $commonPaths = @(
        "C:\php\php.exe",
        "C:\Program Files\php\php.exe",
        "C:\Program Files (x86)\php\php.exe",
        "C:\xampp\php\php.exe",
        "C:\wamp\bin\php\php.exe",
        "C:\laragon\bin\php\php.exe"
    )

    foreach ($path in $commonPaths) {
        if (Test-Path $path) {
            return $path
        }
    }

    return $null
}

function New-ReminderTask {
    param(
        [string]$PHPExecutable,
        [string]$ScriptPath,
        [string]$ScheduleTime
    )

    $taskName = "Certificate Management - Daily Reminders"
    $taskDescription = "Automatically send certificate collection reminders to uncollected certificates"
    $action = New-ScheduledTaskAction -Execute $PHPExecutable -Argument $ScriptPath
    $trigger = New-ScheduledTaskTrigger -Daily -At $ScheduleTime
    $settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -RunOnlyIfNetworkAvailable -StartWhenAvailable

    # Try to create the task
    try {
        $task = New-ScheduledTask -Action $action -Trigger $trigger -Settings $settings -Description $taskDescription -Force:$Force

        Register-ScheduledTask -TaskName $taskName -InputObject $task -Force:$Force | Out-Null

        return $true
    }
    catch {
        Write-Host "ERROR: Failed to create task: $_" -ForegroundColor Red
        return $false
    }
}

function Remove-OldTask {
    $taskName = "Certificate Management - Daily Reminders"
    $existingTask = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue

    if ($existingTask) {
        Write-Host "Found existing scheduler task. Removing..." -ForegroundColor Yellow
        Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
        Write-Host "Old task removed." -ForegroundColor Green
    }
}

# Main execution
Write-Header

# Check admin privileges
if (-not (Test-AdminPrivileges)) {
    Write-Host "ERROR: This script must be run as Administrator!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Please run PowerShell as Administrator and try again." -ForegroundColor Yellow
    Write-Host ""
    exit 1
}

# Get script directory
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Write-Host "Script Directory: $scriptDir" -ForegroundColor Green

# Find PHP executable
$phpExecutable = Get-PHPExecutable -ProvidedPath $PHPPath

if (-not $phpExecutable) {
    Write-Host "ERROR: PHP executable not found!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Please ensure PHP is installed and try again:" -ForegroundColor Yellow
    Write-Host "  .\setup-scheduler-windows.ps1 -PHPPath 'C:\path\to\php.exe'" -ForegroundColor Yellow
    Write-Host ""
    exit 1
}

Write-Host "PHP Executable: $phpExecutable" -ForegroundColor Green
Write-Host ""

# Confirm details
Write-Host "Configuration:" -ForegroundColor Cyan
Write-Host "  Task Name: Certificate Management - Daily Reminders"
Write-Host "  Schedule: Every day at $ScheduleTime"
Write-Host "  Command: $phpExecutable $scriptDir\scheduler.php"
Write-Host ""

# Check if task already exists
$taskName = "Certificate Management - Daily Reminders"
$existingTask = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue

if ($existingTask -and -not $Force) {
    Write-Host "WARNING: Task already exists!" -ForegroundColor Yellow
    $response = Read-Host "Do you want to replace it? (Y/N)"
    if ($response -ne "Y" -and $response -ne "y") {
        Write-Host "Setup cancelled." -ForegroundColor Yellow
        exit 0
    }
    $Force = $true
}

# Remove old task if exists
if ($Force) {
    Remove-OldTask
}

# Create the scheduled task
Write-Host "Creating scheduled task..." -ForegroundColor Yellow

$taskPath = "$scriptDir\scheduler.php"

if (New-ReminderTask -PHPExecutable $phpExecutable -ScriptPath $taskPath -ScheduleTime $ScheduleTime) {
    Write-Host ""
    Write-Host "SUCCESS! Scheduled task created successfully!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Task Details:" -ForegroundColor Cyan
    Write-Host "  Name: $taskName"
    Write-Host "  Schedule: Every day at $ScheduleTime"
    Write-Host "  Status: Ready"
    Write-Host ""
    Write-Host "You can manage this task in Windows Task Scheduler:" -ForegroundColor Yellow
    Write-Host "  1. Press Win + R"
    Write-Host "  2. Type: taskschd.msc"
    Write-Host "  3. Find 'Certificate Management - Daily Reminders' in the task list"
    Write-Host ""

    # Ensure logs directory exists
    $logsDir = Join-Path $scriptDir "logs"
    if (-not (Test-Path $logsDir)) {
        New-Item -ItemType Directory -Path $logsDir -Force | Out-Null
        Write-Host "Created logs directory: $logsDir" -ForegroundColor Green
    }

    Write-Host "To view logs:" -ForegroundColor Yellow
    Write-Host "  Get-Content $logsDir\scheduler.log -Tail 50"
    Write-Host ""
}
else {
    Write-Host ""
    Write-Host "ERROR: Failed to create scheduled task!" -ForegroundColor Red
    Write-Host ""
    exit 1
}
