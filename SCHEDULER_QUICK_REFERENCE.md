# Automatic Reminder Scheduler - Quick Reference

## What's New

The Certificate Management System now has fully automated daily reminder scheduling. Here's everything you need to know:

## Files Added

### 1. **scheduler.php** - Main scheduler script
   - Executes the reminder task
   - Prevents duplicate runs in the same day
   - Logs all execution details
   - Can be run via CLI, Windows Task Scheduler, or Linux Cron

### 2. **reminders-scheduler.php** - Web dashboard
   - View scheduler status
   - Manually trigger reminders
   - See recent email activity
   - Monitor pending reminders
   - Access setup instructions

### 3. **setup-scheduler-windows.bat** - Windows automated setup
   - Batch file for easy Windows Task Scheduler setup
   - Run as Administrator
   - Automatically creates daily scheduler task

### 4. **setup-scheduler-windows.ps1** - Windows PowerShell setup
   - Alternative to batch file using PowerShell
   - More flexible customization options
   - Useful if you encounter issues with batch file

### 5. **setup-scheduler-linux.sh** - Linux/Mac automated setup
   - Bash script for Linux/Mac cron setup
   - Automatically adds daily cron job
   - Easy removal and customization

### 6. **SCHEDULER_SETUP.md** - Comprehensive setup guide
   - Detailed instructions for all platforms
   - Troubleshooting guide
   - Configuration options
   - Log monitoring instructions

## Quick Start

### For Windows Users

```bash
cd "C:\Users\user\Desktop\CERTIFICATE MANAGEMENT SYSTEM"
setup-scheduler-windows.bat
```

**Or** using PowerShell (as Administrator):
```powershell
.\setup-scheduler-windows.ps1
```

### For Linux/Mac Users

```bash
cd /path/to/CERTIFICATE\ MANAGEMENT\ SYSTEM
bash setup-scheduler-linux.sh
```

## Monitor Your Scheduler

### Via Web Dashboard
1. Open your browser
2. Navigate to `reminders-scheduler.php`
3. View status, email logs, and trigger manually

### Via Logs
- **Scheduler logs:** `logs/scheduler.log`
- **Reminder logs:** `logs/reminders.log`

### View Recent Activity
```bash
# Windows (PowerShell)
Get-Content logs/scheduler.log -Tail 20

# Linux/Mac
tail -20 logs/scheduler.log
```

## How It Works

### Daily Flow
1. **6:00 AM** - Scheduler runs automatically
2. Checks which students haven't collected certificates
3. Checks if reminders were sent in last 30 days
4. Sends email reminders to students needing them
5. Logs all activities
6. Ensures it only runs once per day

### Manual Trigger
Any time you can:
- Click "Send Reminders Now" on `reminders-scheduler.php`
- Run: `php scheduler.php`
- Run: `php send_reminders.php`

## Customization

### Change Run Time

**Windows:**
1. Open Task Scheduler (Win + R → `taskschd.msc`)
2. Find "Certificate Management - Daily Reminders"
3. Right-click → Properties → Triggers tab
4. Edit the time

**Linux/Mac:**
```bash
crontab -e
# Change the first two numbers (minute and hour)
# Current: 0 6 * * *  (6:00 AM)
# Example: 0 2 * * *  (2:00 AM)
```

### Change Email Settings
See email configuration options in [README.md](README.md)

## Troubleshooting

### Reminders not running?

1. **Check scheduler logs:**
   ```bash
   cat logs/scheduler.log
   ```

2. **Verify task exists:**
   - Windows: Open Task Scheduler and search for the task
   - Linux: `crontab -l`

3. **Test manually:**
   ```bash
   php scheduler.php
   ```

4. **Review email settings:**
   - Check `config/smtp.json` or environment variables
   - Try test reminder from `reminders-scheduler.php`

### "PHP executable not found"?
- Windows: Make sure PHP is in your system PATH
- Linux/Mac: Run `which php` to find the correct path

## File Locations

```
CERTIFICATE MANAGEMENT SYSTEM/
├── scheduler.php                    # Main scheduler
├── reminders-scheduler.php          # Web dashboard
├── send_reminders.php               # Reminder sender
├── setup-scheduler-windows.bat      # Windows setup
├── setup-scheduler-windows.ps1      # Windows PowerShell setup
├── setup-scheduler-linux.sh         # Linux/Mac setup
├── SCHEDULER_SETUP.md               # Full guide
├── logs/
│   ├── scheduler.log               # Scheduler execution log
│   └── reminders.log               # Reminder email log
├── database/
│   └── .scheduler_last_run         # Last run timestamp
└── config/
    └── smtp.json                   # Email configuration
```

## Key Features

✅ **Automatic** - Runs daily without manual intervention  
✅ **Reliable** - Prevents duplicate sends on same day  
✅ **Flexible** - Works on Windows, Linux, and Mac  
✅ **Monitorable** - Detailed logs and web dashboard  
✅ **Customizable** - Change time, frequency, email settings  
✅ **Tested** - Can be run manually for testing  

## Support

For detailed information, see [SCHEDULER_SETUP.md](SCHEDULER_SETUP.md)

## Database Tables

### students
- `last_reminder_sent_at` - Timestamp of last reminder

### email_logs
- Tracks all email delivery attempts and results

## Environment Variables

Optional configuration via environment:
```
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password
SMTP_ENCRYPTION=tls
SMTP_FROM_ADDRESS=noreply@example.com
SMTP_FROM_NAME=Certificate Office
```
