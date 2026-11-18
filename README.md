# E-Hailing
An e-hailing website that supposed to resembles like the application 'Grab'.

## Getting started (XAMPP)

1. Clone into `C:\xampp\htdocs\ehailing\E-Hailing` and start **Apache** and **MySQL** in XAMPP.
2. Create the database — in phpMyAdmin import `database/schema.sql`, then `database/seed.sql` (optional demo data). Or from a terminal:
   ```
   C:\xampp\mysql\bin\mysql -u root < database\schema.sql
   C:\xampp\mysql\bin\mysql -u root < database\seed.sql
   ```
3. Copy `config/config.example.php` to `config/config.php` and adjust the DB settings / `BASE_URL` if needed. (`config.php` is git-ignored.)
4. Open http://localhost/ehailing/E-Hailing/admin/

**Default admin login:** `admin@crab.com` / `admin123` — change it before going live.
Demo drivers and passengers all use the password `password`.

## Project layout

| Folder | Purpose |
|---|---|
| `admin/` | Admin console — dashboard, rides, drivers (approve/suspend), passengers |
| `includes/` | Shared PHP: DB connection (`db.php`), helpers, admin auth & layout, SVG charts |
| `assets/css`, `assets/js` | Styles (`admin.css` — brand colours are the tokens at the top) and scripts |
| `database/` | `schema.sql` (tables + default admin) and `seed.sql` (demo data) |
| `config/` | `config.example.php` template |
| `auth/`, `passenger/`, `driver/`, `tracking/` | Reserved for the passenger/driver apps |

Shared conventions: use `db()` for a PDO connection, always use prepared statements, escape output with `e()`, and add `csrf_field()` / `verify_csrf()` to every POST form.
