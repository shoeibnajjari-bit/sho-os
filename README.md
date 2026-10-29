# SHO OS

Personal finance and task-management progressive web app (PWA) for Persian RTL users.

## Current status

This repository is being prepared for the SHO OS PWA starter project.

## Setup overview

1. Upload the PHP project files to a PHP hosting account with MySQL support.
2. Copy `config.example.php` to `config.php` and configure the database credentials.
3. Open `install.php` once to initialize the database and create the first account.
4. Delete `install.php` after setup.
5. Use HTTPS for secure sessions and PWA installation.

Never commit production credentials, passwords, API keys, or banking OTPs.

## Important limitations

- The PWA does not automatically read Android SMS messages.
- Balances and reports reflect only transactions entered into the app.
- This project is a starter and should be tested on the target hosting environment before real use.
