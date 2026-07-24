# Certificate Collection Management System

A simple PHP web application for managing short-course certificates. It supports student record management, filtering/search, certificate collection tracking, and automated reminder emails.

## Features
- Add, edit, view, search, and delete student records
- Mark certificates as collected and automatically record the timestamp
- **Send real-time emails** to notify students when certificates are ready
- Send automated reminder emails every 30 days for uncollected certificates
- Manual reminder trigger from the web dashboard
- Email delivery logging and tracking
- Automatic daily scheduler (Windows Task Scheduler or Cron)
- Works with SQLite by default for quick local setup, and can be switched to MySQL

## Project structure
- index.php – admin dashboard interface
- includes/db.php – database connection and schema setup
- send_reminders.php – reminder scheduling script
- assets/styles.css – basic responsive styling
- schema.sql – MySQL schema definition

## Setup
1. Place the project in your web server root (for example, htdocs or www).
2. Ensure PHP and PDO are available.
3. Open the project in your browser.

### SQLite (default)
No extra database setup is needed. The app will create a local SQLite database file at database/certificate_management.sqlite on first run.

### MySQL
1. Create a database named certificate_management.
2. Import the SQL from schema.sql.
3. Set these environment variables before running the app:
   - DB_DRIVER=mysql
   - DB_HOST=127.0.0.1
   - DB_NAME=certificate_management
   - DB_USER=root
   - DB_PASS=

## Email Configuration

The system supports real-time email notifications with easy setup.

### Quick Setup

1. **Copy the example config:**
   ```bash
   cp config/smtp.json.example config/smtp.json
   ```

2. **Edit `config/smtp.json` with your email provider:**
   
   **Gmail (Recommended):**
   ```json
   {
     "host": "smtp.gmail.com",
     "port": 587,
     "username": "your-email@gmail.com",
     "password": "your-app-password",
     "encryption": "tls",
     "from_address": "your-email@gmail.com",
     "from_name": "Certificate Office"
   }
   ```

3. **Generate Gmail App Password:**
   - Go to [myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords)
   - Select Mail and Windows Computer
   - Copy the 16-character password into `config/smtp.json`

4. **Start sending emails!**
   - When adding a student, check "Send notification email"
   - Emails sent immediately to student

For detailed setup instructions and other email providers, see [EMAIL_SETUP.md](EMAIL_SETUP.md).

### Email Features

- **Real-time notifications:** Emails sent immediately when student record is added
- **30-day reminders:** Automatic reminders for uncollected certificates
- **Manual triggers:** Send reminders anytime from the dashboard
- **Email logging:** All delivery attempts tracked in database
- **Professional templates:** Pre-built email templates for notifications and reminders

## Automatic Daily Reminders

### Quick Setup

**Windows (Recommended):**
1. Open Command Prompt as Administrator
2. Run: `setup-scheduler-windows.bat`

**Linux/Mac:**
1. Open Terminal
2. Run: `bash setup-scheduler-linux.sh`

### Manual Reminder Trigger
- **Web Interface:** Go to `reminders-scheduler.php` to view status and manually trigger reminders
- **CLI:** Run `php scheduler.php` or `php send_reminders.php`

### Customization
For detailed setup, customization options, troubleshooting, and advanced configuration, see [SCHEDULER_SETUP.md](SCHEDULER_SETUP.md).

## Notes
- The admin interface intentionally does not include a login screen to keep the project simple.
- Reminder emails are only sent once per 30-day interval while the certificate remains uncollected.
- The scheduler automatically prevents duplicate daily runs to avoid sending multiple reminders in one day.
