#!/bin/bash
# ============================================================================
# Setup script for Certificate Management System - Automatic Reminder Scheduler
# ============================================================================
#
# This script sets up a cron job to run daily reminders automatically on Linux/Mac
#
# Usage: bash setup-scheduler-linux.sh
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_EXECUTABLE="${1:-php}"

echo ""
echo "============================================================================"
echo "Certificate Management System - Reminder Scheduler Setup (Linux/Mac)"
echo "============================================================================"
echo ""
echo "Script Directory: $SCRIPT_DIR"
echo "PHP Executable: $PHP_EXECUTABLE"
echo ""

# Verify PHP executable
if ! command -v "$PHP_EXECUTABLE" &> /dev/null; then
    echo "ERROR: PHP executable not found: $PHP_EXECUTABLE"
    echo ""
    echo "Please ensure PHP is installed and try again:"
    echo "  bash setup-scheduler-linux.sh /path/to/php"
    echo ""
    exit 1
fi

# Display current cron jobs
echo "Current cron jobs:"
crontab -l 2>/dev/null || echo "No cron jobs found"
echo ""

# Create cron entry
CRON_ENTRY="0 6 * * * $PHP_EXECUTABLE $SCRIPT_DIR/scheduler.php"

echo "Adding cron job..."
echo "Schedule: Every day at 06:00 AM"
echo "Command: $CRON_ENTRY"
echo ""

# Add to crontab (avoiding duplicates)
if (crontab -l 2>/dev/null | grep -q "scheduler.php"); then
    echo "Cron job already exists!"
    echo ""
    echo "Current cron jobs:"
    crontab -l
else
    (crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -
    echo "SUCCESS! Cron job added successfully!"
    echo ""
    echo "Current cron jobs:"
    crontab -l
fi

# Ensure logs directory exists
mkdir -p "$SCRIPT_DIR/logs"

echo ""
echo "To view scheduler logs:"
echo "  tail -f $SCRIPT_DIR/logs/scheduler.log"
echo ""
echo "To remove the scheduler:"
echo "  crontab -e"
echo "  (then find and delete the scheduler.php line)"
echo ""
