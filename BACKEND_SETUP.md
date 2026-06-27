# MatchPulse PHP + MySQL Backend

This backend is designed to work on both cPanel hosting and a VPS.

## What It Adds

- Admin login API
- Article/news create, edit, delete, publish API
- Match update API
- SEO, socials, and public pages settings API
- Image upload API
- MySQL database tables
- Local browser fallback when the PHP API is not available

## Files

- `api/config.example.php` - copy this to `api/config.php` and add your real database details.
- `database/schema.sql` - import this into MySQL.
- `api/articles.php` - articles/news API.
- `api/settings.php` - SEO, socials, and public pages API.
- `api/login.php`, `api/logout.php`, `api/me.php` - admin auth.
- `api/create-admin.php` - one-time admin creator using your private setup key.
- `api/upload.php` - authenticated image upload endpoint.

## Setup On cPanel

1. Create a MySQL database and database user in cPanel.
2. Import `database/schema.sql` using phpMyAdmin.
3. Copy `api/config.example.php` to `api/config.php`.
4. Put your database name, username, password, and a long private `setup_key` into `api/config.php`.
5. Upload the site files to your domain or subdomain.
6. Create the first admin user by sending a POST request to:

```text
https://yourdomain.com/api/create-admin.php
```

With JSON:

```json
{
  "setupKey": "your_private_setup_key",
  "name": "Admin",
  "email": "you@example.com",
  "password": "use-a-strong-password"
}
```

After the admin is created, change the `setup_key` again or remove it.

7. Open `admin.html`. If the PHP backend is reachable, the News & Articles panel will show **Backend Login**. Log in with the admin email/password to save articles, SEO settings, socials, and pages into MySQL.

## Setup On VPS

Use the same files with Apache or Nginx + PHP-FPM + MySQL/MariaDB.

Recommended stack:

```text
Nginx or Apache
PHP 8.1+
MySQL or MariaDB
SSL certificate
```

Point your web root to this project folder, create `api/config.php`, import the SQL schema, and test:

```text
https://yourdomain.com/api/health.php
```

## Important Security Notes

- Never commit `api/config.php`.
- Use HTTPS before logging in.
- Keep `uploads/.htaccess` so PHP files cannot run from uploads.
- Change or remove `setup_key` after creating the first admin.
- The current GitHub Pages version cannot run PHP; deploy to cPanel/VPS for the backend to work.
