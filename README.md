# Certificate Collection Management System

A simple PHP web application for managing short-course certificates. It supports student record management, filtering/search, certificate collection tracking, and automated reminder emails.

## Features
- Add, edit, view, search, and delete student records
- Mark certificates as collected and automatically record the timestamp
- Send reminder emails every 30 days while the certificate remains uncollected
- Keep a log of reminder attempts and failures
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

## Email configuration
The reminder script uses PHP's built-in mail function by default. For SMTP, update send_reminders.php to use PHP Mailer or configure your mail transport.

## Cron job
Run the reminder script every day with a cron entry like this:

```bash
0 0 * * * php /path/to/send_reminders.php >> /path/to/send_reminders.log 2>&1
```

## Notes
- The admin interface intentionally does not include a login screen to keep the project simple.
- Reminder emails are only sent once per 30-day interval while the certificate remains uncollected.
