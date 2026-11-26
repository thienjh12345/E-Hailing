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
4. Open **http://localhost/ehailing/E-Hailing/** — the homepage has one sign-in form: pick *Passenger*, *Driver* or *Admin*, sign in, and you land in that portal. Logging out (or opening a portal page while signed out) brings you back here with your role preselected (e.g. `index.php?as=driver`). The per-portal pages (`admin/login.php`, `driver/login.php`, `passenger/login.php`) still work too.

**Already imported an older schema?** Run the files in `database/migrations/` that you haven't run yet, in order (`001_…`, `002_…`, then `003_in_app_chat.sql`) — they add the new columns/tables without touching your existing data. To get the latest demo data (e.g. the Kota Kinabalu rides and drivers), simply re-import `schema.sql` and then `seed.sql` (this resets the database to the demo data).

**Default admin login:** `admin@crab.com` / `admin123` — change it before going live.
The demo data covers two cities — the **Klang Valley** and **Kota Kinabalu, Sabah**. Each driver works in one city and every trip stays inside its driver's city. Demo drivers and passengers all use the password `password` — e.g. driver `tan.boon.huat@crabdriver.com` (has a pending profile change for the admin to review) and passenger `chloe.lim@mail.com` (has a live trip and trips to rate).

## In-App Chat

- **Passenger ↔ driver:** every trip with a driver has its own chat (`passenger/messages.php`, `driver/messages.php`). Open it from *Message driver* on a trip, the live-trip card, or the driver's recent trips. Both sides can reply any number of times, during or after the trip.
- **Passenger ↔ Crab Support:** each passenger has one ongoing support chat (*Messages → Crab Support*, or *Get help* on a trip, which pre-fills the trip details). Any admin answers from **Support** (`admin/support.php`) and can mark it *Resolved*; it reopens automatically when the passenger writes again.
- New messages appear within ~3 seconds without refreshing (polling), with unread badges in every sidebar and *Seen* receipts. Without JavaScript the form still sends with a normal page reload.
- Admins can read a trip's passenger–driver chat on the ride page (for disputes). Messages are stored in `chat_conversations` / `chat_messages`; logic lives in `includes/chat.php`, browser code in `assets/js/chat.js`.
- Existing databases: run `database/migrations/003_in_app_chat.sql` once.

## Passenger Profile Management

- **Passengers** register at `passenger/register.php`, then manage their profile (details, emergency contact, preferred payment, photo), **saved places** (Home, Work, favourites — up to 6) and password, and see their full ride history.
- **Link to drivers:** every trip shows the driver's photo, vehicle and plate (from the driver profile). Rating a completed trip recalculates that driver's overall rating, which the driver and admin see immediately. Drivers see each passenger's name and photo on their recent trips.
- **Link to admin:** `admin/passenger.php` shows the full profile, stats, saved places and trips (linked to each driver's profile); admins can suspend with a reason the passenger sees, reactivate, or edit via *Edit profile*.
- Shared rules live in `includes/passenger_profile.php`; generic validators (phone, names, passwords) in `includes/validation.php`.

## Driver Profile Management

- **Drivers** register at `driver/register.php` (status *pending*), then manage their profile: contact details and photo save instantly; identity, licence and vehicle changes from an approved driver become a **change request**.
- **Admins** see new applications and change requests in the sidebar badge, dashboard banners and the *Profile updates* tab, then approve/reject them on `admin/driver.php` (with a note the driver sees). Admins can also edit any driver directly via *Edit profile*.
- Shared rules (validation, normalising phone/IC/plate, change-request logic) live in `includes/driver_profile.php`, so both sides behave the same. Photos go to `uploads/drivers/` (git-ignored, scripts blocked).

## Project layout

| Folder | Purpose |
|---|---|
| `admin/` | Admin console — dashboard, rides, drivers (approve/suspend, profile review & edit), passengers (profile, suspend, edit) |
| `driver/` | Driver portal — register, login, home (go online), profile, licence & vehicle, password |
| `passenger/` | Passenger portal — register, login, home, ride history & ratings, profile, saved places, password |
| `includes/` | Shared PHP: DB connection (`db.php`), helpers, admin auth & layout, SVG charts |
| `assets/css`, `assets/js` | Styles (`admin.css` — brand colours are the tokens at the top) and scripts |
| `database/` | `schema.sql` (tables + default admin), `seed.sql` (demo data), `migrations/` (upgrades for existing databases) |
| `config/` | `config.example.php` template |
| `auth/`, `tracking/` | Reserved for shared auth and live tracking |

Shared conventions: use `db()` for a PDO connection, always use prepared statements, escape output with `e()`, and add `csrf_field()` / `verify_csrf()` to every POST form.
