# Automatic Daily Reminder Scheduler Setup Guide

This guide will help you set up automatic daily reminders for certificate collection.

## Overview

The Certificate Management System now includes automatic reminder scheduling. The system will:

- Run daily at **6:00 AM** by default
- Check for students who need reminders (haven't collected their certificates)
- Send reminder emails with a 30-day interval between reminders
- Log all activities for monitoring

## Setup Instructions

### Option 1: Windows Task Scheduler (Recommended for Windows)

**Requirements:**
- Windows 7 or later
- Administrator access
- PHP installed on your system

**Steps:**

1. **Open Command Prompt as Administrator:**
   - Press `Win + X`
   - Select "Command Prompt (Admin)" or "Windows PowerShell (Admin)"

2. **Navigate to the application directory:**
   ```
   cd "C:\Users\user\Desktop\CERTIFICATE MANAGEMENT SYSTEM"
   ```

3. **Run the setup script:**
   ```
   setup-scheduler-windows.bat
   ```

4. **Verify the task was created:**
   - Press `Win + R`
   - Type `taskschd.msc` and press Enter
   - Look for "Certificate Management - Daily Reminders" in the task list

**Customizing the Schedule:**

If you want to change the run time from 6:00 AM:

1. Open Task Scheduler (Win + R → taskschd.msc)
2. Find "Certificate Management - Daily Reminders"
3. Right-click → "Properties"
4. Click the "Triggers" tab
5. Select the trigger and click "Edit"
6. Change the time under "Start date and time"
7. Click OK

### Option 2: Linux/Mac Cron Job

**Requirements:**
- Bash shell
- PHP installed on your system
- Cron daemon enabled

**Steps:**

1. **Open Terminal**

2. **Navigate to the application directory:**
   ```bash
   cd "/path/to/CERTIFICATE MANAGEMENT SYSTEM"
   ```

3. **Run the setup script:**
   ```bash
   bash setup-scheduler-linux.sh
   ```

4. **Verify the cron job:**
   ```bash
   crontab -l
   ```

   You should see an entry like:
   ```
   0 6 * * * php /path/to/scheduler.php
   ```

**Customizing the Schedule:**

To change the time, edit your crontab:
```bash
crontab -e
```

Edit the time fields (first 5 columns):
```
0 6 * * * php /path/to/scheduler.php
↑ ↑ ↑ ↑ ↑
│ │ │ │ └─ Day of week (0-6, Sunday is 0)
│ │ │ └─── Month (1-12)
│ │ └───── Day of month (1-31)
│ └─────── Hour (0-23)
└───────── Minute (0-59)
```

Examples:
- `0 6 * * * ` = Every day at 6:00 AM
- `0 2 * * * ` = Every day at 2:00 AM
- `0 6 * * 1` = Every Monday at 6:00 AM
- `0 6 1 * * ` = Every 1st of the month at 6:00 AM

### Option 3: Manual Execution

You can also run the scheduler manually at any time:

**Windows (Command Prompt):**
```
php scheduler.php
```

**Linux/Mac (Terminal):**
```bash
php scheduler.php
```

Or use the main reminder script directly:
```bash
php send_reminders.php
```

## Monitoring

### View Scheduler Logs

Logs are stored in the `logs/` directory:

**Scheduler logs:**
- Windows: Open `logs/scheduler.log`
- Linux/Mac: `tail -f logs/scheduler.log`

**Email event logs:**
- `logs/reminders.log` - Contains details of sent reminders

**Database logs:**
- `email_logs` table - All email delivery events
- `students` table - `last_reminder_sent_at` column shows last reminder time

### Check Scheduler Status

**Windows:**
1. Open Task Scheduler (Win + R → taskschd.msc)
2. Find "Certificate Management - Daily Reminders"
3. Check the "Status" column (Should be "Ready")
4. Check "Last Run Result" and "Last Run Time"

**Linux/Mac:**
```bash
# View system logs
grep scheduler /var/log/syslog  # Debian/Ubuntu
grep scheduler /var/log/system.log  # macOS
tail -f logs/scheduler.log  # Application logs
```

## Troubleshooting

### The scheduler isn't running

**Windows:**
1. Check that the task exists in Task Scheduler
2. Ensure PHP is in your system PATH
3. Check the log file at `logs/scheduler.log`
4. Right-click the task → "Run" to test manually

**Linux/Mac:**
1. Verify cron is running: `sudo systemctl status cron`
2. Check cron logs: `grep CRON /var/log/syslog`
3. Verify PHP path: `which php`
4. Run manually: `php scheduler.php`

### PHP executable not found

**Windows:**
1. Verify PHP is installed: `where php`
2. If not found, install PHP from https://www.php.net/downloads
3. Make sure PHP is added to your system PATH
4. Re-run the setup script

**Linux/Mac:**
1. Find PHP: `which php`
2. If not found, install PHP: `sudo apt-get install php` (Ubuntu/Debian) or `brew install php` (macOS)
3. Modify the setup script to use the correct PHP path

### Reminders not sending

Check these in order:

1. **Verify students exist:**
   - Log in to the system
   - Check that students without collected status exist

2. **Check email configuration:**
   - Verify SMTP settings in `config/smtp.json` or environment variables
   - Test by manually sending a reminder: `php send_reminders.php`

3. **Review logs:**
   - Check `logs/scheduler.log` for execution errors
   - Check `logs/reminders.log` for email delivery details
   - Check the `email_logs` database table

4. **Test manually:**
   ```bash
   php send_reminders.php
   ```

## Configuration

### Email Settings

Email configuration is read from (in priority order):

1. **Environment variables:**
   ```
   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_USER=your-email@gmail.com
   SMTP_PASS=your-app-password
   SMTP_ENCRYPTION=tls
   SMTP_FROM_ADDRESS=noreply@example.com
   SMTP_FROM_NAME=Certificate Office
   ```

2. **Configuration file:** `config/smtp.json`
   ```json
   {
     "host": "smtp.gmail.com",
     "port": 587,
     "username": "your-email@gmail.com",
     "password": "your-app-password",
     "encryption": "tls",
     "from_address": "noreply@example.com",
     "from_name": "Certificate Office"
   }
   ```

3. **PHP mail() function** (fallback)

### Schedule Time

Change the default run time by editing the setup script:

**Windows:**
- Edit `setup-scheduler-windows.bat`
- Find `/st 06:00` and change to your desired time (24-hour format)
- Re-run the setup script

**Linux/Mac:**
- Edit `setup-scheduler-linux.sh`
- Find `0 6` and change the hour (0-23)
- Re-run the setup script

## Removing the Scheduler

### Windows

1. Open Task Scheduler (Win + R → taskschd.msc)
2. Find "Certificate Management - Daily Reminders"
3. Right-click → Delete
4. Confirm deletion

### Linux/Mac

Edit your crontab:
```bash
crontab -e
```

Find and delete the line containing `scheduler.php`, then save.

Or remove all cron jobs (if this is the only one):
```bash
crontab -r
```

## Support

For issues or questions:

1. Check the logs: `logs/scheduler.log` and `logs/reminders.log`
2. Test manually: `php scheduler.php`
3. Review email configuration in settings
4. Check the application's main README for general troubleshooting
