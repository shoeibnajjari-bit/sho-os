# SHO OS

A Persian right-to-left personal finance and task-management Progressive Web App (PWA), built with PHP and MySQL.

## Features

- Login with password hashing and session protections
- Income and expense tracking in toman
- Monthly income/expense totals and overall recorded balance
- Category-level spending summary and monthly budget limits
- Task list with due dates, priorities, completion toggles, and deletion
- CSV export for transactions
- Responsive Persian RTL interface and installable PWA shell
- CSRF tokens and prepared SQL statements

## Requirements

- PHP 8.1+ recommended, with PDO MySQL and mbstring extensions
- MySQL or MariaDB
- HTTPS in production (needed for secure session cookies and PWA features)

## Installation

1. Upload the repository files to a PHP hosting account with MySQL support.
2. Create an empty database and database user in your hosting control panel.
3. Copy `config.example.php` to `config.php` and enter the database host, name, username, and password. Keep `config.php` private and never commit real credentials.
4. Ensure `config.php` is writable only by the account that manages the app where your host allows it.
5. Visit `/install.php`, create the first account with a strong password (at least 12 characters), then delete `install.php` from the server.
6. Visit `/index.php` and sign in. Use HTTPS for production.

## Security and limitations

- Do not store bank login credentials, card PINs, or SMS one-time passwords in this app.
- The PWA cannot read Android SMS automatically; automatic SMS import would require a separate Android app and explicit permissions.
- Balances reflect only transactions entered into SHO OS; they are not live bank balances.
- Financial advice is simple rule-based guidance, not a machine-learning model or professional financial advice.
- Test on your hosting provider before entering real financial records. Back up the database regularly.
