# Email Configuration Guide

This guide explains how to set up email notifications for the Certificate Management System so students receive real-time notifications about their certificates.

## Quick Setup (Gmail)

The easiest way to get started is with a Gmail account.

### Step 1: Create or Use a Gmail Account

You can use any Gmail account. For better organization, consider creating a dedicated email like `certificates@yourdomain.com` or use an existing account.

### Step 2: Enable 2-Factor Authentication

1. Go to [Google Account Security](https://myaccount.google.com/security)
2. Enable "2-Step Verification"

### Step 3: Generate App Password

1. Go to [Google Account App Passwords](https://myaccount.google.com/apppasswords)
2. Select "Mail" and "Windows Computer"
3. Copy the 16-character password

### Step 4: Configure the System

Create a file at `config/smtp.json` with:

```json
{
  "host": "smtp.gmail.com",
  "port": 587,
  "username": "your-email@gmail.com",
  "password": "xxxx xxxx xxxx xxxx",
  "encryption": "tls",
  "from_address": "your-email@gmail.com",
  "from_name": "Certificate Office"
}
```

**Replace:**
- `your-email@gmail.com` - Your Gmail address
- `xxxx xxxx xxxx xxxx` - Your 16-character app password (remove spaces or keep them, either works)
- `Certificate Office` - Your organization name

### Step 5: Test It

1. Go to the dashboard
2. Add a new student with a valid email
3. **Check the "Send notification email" checkbox**
4. Submit the form
5. Check the student's inbox for the email

## Other Email Providers

### Outlook/Office 365

```json
{
  "host": "smtp.office365.com",
  "port": 587,
  "username": "your-email@outlook.com",
  "password": "your-password",
  "encryption": "tls",
  "from_address": "your-email@outlook.com",
  "from_name": "Certificate Office"
}
```

### SendGrid

```json
{
  "host": "smtp.sendgrid.net",
  "port": 587,
  "username": "apikey",
  "password": "SG.your-sendgrid-api-key",
  "encryption": "tls",
  "from_address": "noreply@yourdomain.com",
  "from_name": "Certificate Office"
}
```

### Custom SMTP Server

Replace the host, port, username, and password with your provider's details.

## Environment Variables (Alternative)

Instead of creating a config file, you can set environment variables:

```bash
# Windows (Command Prompt)
set SMTP_HOST=smtp.gmail.com
set SMTP_PORT=587
set SMTP_USER=your-email@gmail.com
set SMTP_PASS=xxxx xxxx xxxx xxxx
set SMTP_ENCRYPTION=tls
set SMTP_FROM_ADDRESS=your-email@gmail.com
set SMTP_FROM_NAME=Certificate Office
```

```bash
# Linux/Mac (.env or export)
export SMTP_HOST=smtp.gmail.com
export SMTP_PORT=587
export SMTP_USER=your-email@gmail.com
export SMTP_PASS="xxxx xxxx xxxx xxxx"
export SMTP_ENCRYPTION=tls
export SMTP_FROM_ADDRESS=your-email@gmail.com
export SMTP_FROM_NAME="Certificate Office"
```

## Email Workflows

### 1. New Student Added → Immediate Notification

When you add a new student:
- ✓ Check "Send notification email"
- ✓ Email sent immediately
- ✓ Logged in email_logs table

### 2. Automatic Daily Reminders

The scheduler automatically sends reminders:
- **First reminder:** 30 days after certificate completion
- **Subsequent reminders:** Every 30 days after the previous reminder
- **Schedule:** Daily at 6:00 AM (configurable)
- **Only to:** Uncollected certificates

### 3. Manual Reminder Trigger

Visit `reminders-scheduler.php`:
- Click "Send Reminders Now"
- Reminders sent to all pending students who are due
- Results displayed immediately

## Troubleshooting

### Email Configuration Status

On the dashboard:
- ✓ Green badge = Email is configured and working
- ✗ Red badge = Email not configured

### Emails Not Sending

**Check these:**

1. **Configuration is set:**
   - Verify `config/smtp.json` exists
   - Check all required fields are filled

2. **SMTP credentials are correct:**
   - Test with your email client first
   - For Gmail, use app password (not regular password)
   - Verify no typos in username/password

3. **Firewall/Network:**
   - Port 587 (TLS) or 465 (SSL) must be open
   - Some networks block SMTP
   - Try from a different network to test

4. **Check logs:**
   - `logs/reminders.log` - Reminder sending logs
   - `email_logs` table - Email delivery attempts
   - Settings page shows configuration status

5. **Test manually:**
   - Go to `reminders-scheduler.php`
   - Click "Send Reminders Now"
   - Check email_logs table for results

### "Email not configured" Message

This means:
- `config/smtp.json` is not set up, OR
- Environment variables are not set, OR
- SMTP_HOST or SMTP_USER is empty

**Solution:**
1. Create `config/smtp.json` with valid SMTP settings
2. Or set environment variables
3. Refresh the page

### "Email delivery failed"

Check:
1. **SMTP settings:** Host, port, encryption type
2. **Credentials:** Username and password
3. **Firewall:** Port 587/465 accessibility
4. **Email limits:** Gmail limits ~500 emails/day for free accounts
5. **Recipient:** Email address is valid

## File Locations

- **Configuration:** `config/smtp.json`
- **Logs:** `logs/reminders.log`, `logs/scheduler.log`
- **Database logs:** `email_logs` table
- **Email service code:** `includes/email.php`

## Features

### Email Templates

Two built-in templates:

1. **Certificate Ready** - When student is added
   - Notifies student certificate is ready
   - Provides office hours
   - Professional format

2. **Certificate Reminder** - 30-day automated reminder
   - Friendly reminder to collect
   - Office hours included
   - Sent every 30 days until collected

## Security Notes

⚠️ **Important:**
- Do NOT commit `config/smtp.json` to Git if it contains real credentials
- Add to `.gitignore`: `config/smtp.json`
- Use app-specific passwords for Gmail, not your main password
- For production, use environment variables instead of config files
- Consider using dedicated email service like SendGrid for large volumes

## Best Practices

1. **Use dedicated email account:**
   - Create `certificates@yourdomain.com` or similar
   - Easier to manage and track

2. **Monitor email delivery:**
   - Check `email_logs` table regularly
   - Review failed emails and retry

3. **Test first:**
   - Add a test student with your email
   - Verify templates look good
   - Check spam folder

4. **Set up reminders:**
   - Use automatic scheduler for daily reminders
   - Or manually trigger from dashboard as needed

5. **Document settings:**
   - Keep SMTP configuration documented
   - Note the email account used
   - Record when it was set up

## Support

For issues:
1. Check configuration status on dashboard
2. Review logs: `logs/reminders.log`, `email_logs` table
3. Verify SMTP settings with your email provider
4. Test connectivity to SMTP server
5. Review email templates in `includes/email.php`
