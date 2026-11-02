# Reserve-it (Raw PHP)

A plain-PHP sports court reservation app developed as an internship project. It uses PDO prepared statements, MySQL/InnoDB, PHP sessions and password hashing, a small query-string router, REST-style JSON endpoints, and Tailwind CSS via its CDN. It has no Composer dependencies or PHP framework.

## Quick start with XAMPP

1. Start **Apache** and **MySQL** in XAMPP.
2. In phpMyAdmin create a database named `courtly_raw_php`; import `database/schema.sql`, then `database/seed.sql`.
3. Review the database values in [config.php](config.php). Defaults are `127.0.0.1:3306`, database `courtly_raw_php`, user `root`, blank password. Set a password if your XAMPP root account uses one. Set `APP_URL` to the externally reachable site origin for absolute links in reset emails; normal navigation stays on the host used to open the site.
4. Open `http://localhost/bookingApp_jetro_raw_php/public/` or use the PHP development server below.
5. Register a customer, then in a terminal promote that account to admin: `php bin/make-admin.php your-email@example.com`.

Run with PHP's development server (same computer):

```powershell
cd C:\xampp\htdocs\bookingApp_jetro_raw_php
php -S 127.0.0.1:8001 -t public public/router.php
```

For a device on your Wi-Fi use `php -S 0.0.0.0:8001 -t public public/router.php`, then browse to `http://<this-PC-LAN-IP>:8001`. Allow inbound TCP 8001 through Windows Firewall if needed.

## Feature map

- Customer accounts, login, logout, and one-hour password reset links.
- Court catalog, photos, rates, operating hours, availability lookup, and booking forms.
- Pending/confirmed/rejected/cancelled/completed reservation lifecycle, booking ownership, cancellation and overlap checks.
- Admin dashboard, booking filters and decisions, court and photo management, maintenance blocks, and user role/access management.
- REST API v1 with bearer tokens for auth, courts, availability, bookings, and admin court operations.
- Booking and password email messages. Local mail mode writes them to `storage/mail/outbox.log`.

See [`docs/setup-guide.md`](docs/setup-guide.md), [`docs/api-documentation.md`](docs/api-documentation.md), and [`docs/database-design.md`](docs/database-design.md).

## Styling and mail

Tailwind Play CDN keeps setup simple, but needs internet access and is intended for development. For public deployment, compile Tailwind locally and serve the built CSS. The default `MAIL_MODE=log` is safe for local use; to deliver messages, set `MAIL_MODE=mail` and configure PHP's mail transport and a real sender in `config.php` or environment variables.
