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
4. Open http://localhost/ehailing/E-Hailing/admin/ (admin) or http://localhost/ehailing/E-Hailing/driver/ (driver portal)

**Already imported an older schema?** Run `database/migrations/001_driver_profiles.sql` once instead of re-importing — it adds the driver profile columns and the change-request table without touching existing data.

**Default admin login:** `admin@crab.com` / `admin123` — change it before going live.
Demo drivers and passengers all use the password `password` (e.g. driver `tan.boon.huat@crabdriver.com`, who has a pending profile change for the admin to review).

## Driver Profile Management

- **Drivers** register at `driver/register.php` (status *pending*), then manage their profile: contact details and photo save instantly; identity, licence and vehicle changes from an approved driver become a **change request**.
- **Admins** see new applications and change requests in the sidebar badge, dashboard banners and the *Profile updates* tab, then approve/reject them on `admin/driver.php` (with a note the driver sees). Admins can also edit any driver directly via *Edit profile*.
- Shared rules (validation, normalising phone/IC/plate, change-request logic) live in `includes/driver_profile.php`, so both sides behave the same. Photos go to `uploads/drivers/` (git-ignored, scripts blocked).

## Project layout

| Folder | Purpose |
|---|---|
| `admin/` | Admin console — dashboard, rides, drivers (approve/suspend, profile review & edit), passengers |
| `driver/` | Driver portal — register, login, home (go online), profile, licence & vehicle, password |
| `includes/` | Shared PHP: DB connection (`db.php`), helpers, admin auth & layout, SVG charts |
| `assets/css`, `assets/js` | Styles (`admin.css` — brand colours are the tokens at the top) and scripts |
| `database/` | `schema.sql` (tables + default admin), `seed.sql` (demo data), `migrations/` (upgrades for existing databases) |
| `config/` | `config.example.php` template |
| `auth/`, `passenger/`, `tracking/` | Reserved for the passenger app and live tracking |

Shared conventions: use `db()` for a PDO connection, always use prepared statements, escape output with `e()`, and add `csrf_field()` / `verify_csrf()` to every POST form.
