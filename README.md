# Certificate Collection Management System

A PHP application for managing short-course certificate records and tracking collection.

## Features

- Add, edit, search, and delete student records
- Track signed status and certificate collection
- Record when certificates are collected
- Uses MySQL by default and supports SQLite when explicitly selected

## Setup

1. Place the project in your web server root (for example, `htdocs` or `www`).
2. Ensure PHP and PDO are available.
3. Start MySQL and create a database named `certificate`.
4. Copy `.env.example` to `.env` and set `DB_USER` and `DB_PASS` to your MySQL credentials. The default connection is `127.0.0.1:3306`.
5. Open the project in your browser. The application initializes its required tables on first connection. Values already provided by the server environment take precedence over `.env`.

### SQLite (optional)

Set `DB_DRIVER=sqlite` in `.env`. The application creates `database/certificate_management.sqlite` on first run.
