# Setup guide — plain PHP on Windows/XAMPP

## Requirements

- PHP 8.1 or newer with `pdo_mysql`, `fileinfo`, `mbstring`, and sessions enabled (the inspected XAMPP PHP 8.1 has these).
- XAMPP Apache and MySQL/MariaDB, or PHP's built-in server and a compatible MySQL server.
- Internet access to load the Tailwind Play CDN and the sample remote court photos.

## Database setup

1. Start MySQL in XAMPP Control Panel.
2. In phpMyAdmin create an empty database named `courtly_raw_php` using `utf8mb4`.
3. Select that database and import `database/schema.sql`, then import `database/seed.sql`. Both files are safe to import again; `schema.sql` uses `CREATE TABLE IF NOT EXISTS` and sample courts use `INSERT IGNORE`.
4. Check the `database` section of the root `config.php`. Values can also be overridden through `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` environment variables.

The app does not run schema changes automatically. It uses explicit SQL so the database setup is visible and reviewable.

## Run the site

### XAMPP Apache

Put the project under `C:\xampp\htdocs\bookingApp_jetro_raw_php`, start Apache and MySQL, then browse to `http://localhost/bookingApp_jetro_raw_php/public/`. Configure the Apache document root to this project's `public` folder for a cleaner URL. The included `.htaccess` disables directory listings and maps REST API URLs to `public/index.php`; XAMPP's `mod_rewrite` and `AllowOverride All` must be enabled for those API paths. Normal pages also work with query links such as `/?r=home`.

The app detects its public subfolder for normal navigation and allows absolute-link detection for localhost and IP-based LAN URLs. Set `APP_URL` explicitly to the externally reachable URL when using a public domain or reverse proxy, because password-reset email links must be absolute. For example, for XAMPP use `http://localhost/bookingApp_jetro_raw_php/public`; for the built-in server use `http://127.0.0.1:8001`; for another device use the host PC's LAN IP and port. Configure the HTTPS URL when TLS is enabled.

### PHP development server

```powershell
cd C:\xampp\htdocs\bookingApp_jetro_raw_php
php -S 127.0.0.1:8001 -t public public/router.php
```

Open `http://127.0.0.1:8001`. To reach it from another device on the same network, bind to `0.0.0.0` and open `http://<PC-LAN-IP>:8001`; allow the port in Windows Firewall if Windows asks. Stop the server with Ctrl+C.

## Create an administrator

Register through the site first, then run this from the project directory (use an existing registered account email):

```powershell
php bin/make-admin.php your-email@example.com
```

Sign out and back in to see the admin navigation. Customer registration always assigns the customer role.

## Photos and email

Uploaded court photos are validated by MIME type and size and saved under `storage/court-photos`; PHP streams those files through an allow-listed photo endpoint. `storage` is outside the public document root and has an Apache deny rule.

Email defaults to `MAIL_MODE=log`, which writes readable messages and reset links to `storage/mail/outbox.log`. Configure `MAIL_MODE=mail` and a valid `MAIL_FROM` when PHP's `mail()` transport is set up. A successful local log write is not inbox delivery.

## Security and deployment notes

PDO uses exceptions and native prepared statements. Browser forms use session CSRF tokens; passwords use PHP `password_hash`; reset tokens are random, stored as hashes, expire after one hour, and are single-use. Login, registration, and reset requests have file-based rate limits. Use HTTPS and secure session cookies on a public deployment; configure credentials through environment variables; keep `config.php` and `database/*.sql` outside the document root; compile Tailwind instead of using the CDN; set up SMTP; and review `storage` permissions. The app logs unexpected errors to `storage/app.log` and returns generic server errors to users.
