# Corner Chair Appointment System

A local barbershop booking system built with PHP, MySQL, HTML, and CSS. It does not use JavaScript, email, SMS, or external notification services.

## Features

- Customer registration and login with PHP password hashing.
- Role-restricted customer, barber, and owner pages.
- Service and barber selection with available 30-minute start times.
- Server-side checks for working hours and overlapping appointments.
- Customer appointment history and cancellation of upcoming bookings.
- Barber daily schedule and pending/completed/no-show status updates.
- Owner management for barber accounts, services, weekly schedules, appointments, and walk-ins.
- Prepared PDO statements, CSRF tokens on state-changing forms, and escaped HTML output.

Unused staff accounts and services can be deleted. If appointment history references them, the account is deactivated or the service hidden instead, so old records remain intact.

## Requirements

- XAMPP with Apache, MySQL/MariaDB, and PHP 8.1 or later.
- PHP extensions `pdo_mysql` and `mbstring` enabled (both are normally enabled in XAMPP).

## Install on XAMPP

1. Copy the project folder into `C:\xampp\htdocs\Appointment`. The main `index.php` must be at `C:\xampp\htdocs\Appointment\index.php`.
2. Open the XAMPP Control Panel and start **Apache** and **MySQL**.
3. Visit `http://localhost/phpmyadmin/` in your browser.
4. Choose **Import**, select `C:\xampp\htdocs\Appointment\db\schema.sql`, then select **Import** at the bottom. The script creates the `barber_shop` database and three starter services.
5. Open `src/config.php`. The defaults match a typical local XAMPP install (`root` with a blank password). If your MySQL account differs, update `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS`. Set `APP_TIMEZONE` to your shop's PHP timezone, for example `Asia/Manila` or `America/New_York`.
6. Visit `http://localhost/Appointment/setup_admin.php` and create the owner account. This page stops creating accounts once an admin already exists.
7. Log in at `http://localhost/Appointment/login.php`. From the owner dashboard, add barber accounts, set each barber's working days and hours, and review the starter services.
8. Customers create accounts at `http://localhost/Appointment/register.php`. Barbers use the same login page as customers and owners; each is sent to their role-specific dashboard.

## Database Notes

The schema creates `users`, `services`, `barber_schedules`, and `appointments`. The schedule uses ISO weekdays (Monday = 1, Sunday = 7). Appointments store local shop time as `DATETIME`; make sure PHP's `APP_TIMEZONE` matches the shop.

## Security Notes

Do not expose a default XAMPP installation or its blank-password database account to the public internet. Before deploying outside localhost, set a strong MySQL password, use HTTPS, protect configuration files, and review Apache/PHP production settings. Registration only creates customer accounts; barber and owner accounts must be created through the owner tools or one-time setup page.