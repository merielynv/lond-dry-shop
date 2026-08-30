# LOND Dry Shop — Log In Page

ITP104 Information Management 2 · Semestral Project

This delivers the **Log In Page** module: HTML, CSS, JS, and PHP, wired to the
`lond_dry_shop_db` MySQL database, following the wireframe in
`Log_In_Page.png`, the brand kit (logos + color palette), and the class's
Semestral Project Minimum Requirements.

## What's included

```
lond-dry-shop/
├── index.php                 # The Log In Page itself
├── auth/
│   ├── login_process.php     # Validates + authenticates, starts the session
│   └── logout.php            # Destroys the session
├── admin/dashboard.php       # Placeholder — role-protected, admin only
├── staff/dashboard.php       # Placeholder — role-protected, staff only
├── customer/track.php        # Public "Customer? Click here" claim-code page
├── includes/
│   ├── db.php                 # PDO connection (edit your DB credentials here)
│   └── session.php            # Session bootstrap, role guard, CSRF helpers
├── assets/
│   ├── css/style.css
│   ├── js/login.js
│   └── images/                # Logos + favicon from your brand kit
├── database/lond_dry_shop_db.sql   # Your schema + seed data
├── tools/generate_hash.php    # Dev helper — see below
└── README.md
```

## Setup

1. **Import the database.** In phpMyAdmin/MySQL CLI, run
   `database/lond_dry_shop_db.sql`. This creates `lond_dry_shop_db` and seeds
   sample users, services, customers, orders, etc.

2. **Set your DB credentials.** Open `includes/db.php` and update
   `DB_USER` / `DB_PASS` to match your local MySQL setup (defaults to
   `root` / empty password, the typical XAMPP/Laragon setup).

3. **Log in with the seed accounts.** `database/lond_dry_shop_db.sql` now
   ships with **real, working password hashes** (generated with PHP's
   `password_hash()`), so these work immediately after import:

   | Username       | Password    | Role  |
   |----------------|-------------|-------|
   | `admin_meri`   | `Admin@123` | admin |
   | `admin_yesha`  | `Admin@123` | admin |
   | `staff_leslie` | `Staff@123` | staff |
   | `staff_april`  | `Staff@123` | staff |

   These are **development/demo credentials only** — change them (or add
   new accounts) via `tools/generate_hash.php` + an `UPDATE` statement, or
   once the admin's "Add Staff" feature is built, do it through the app
   itself. Either way, don't use these as your real defense-day passwords.

4. **Serve the project** from a PHP-capable server (XAMPP/Laragon `htdocs`,
   `php -S localhost:8000`, etc.) and open `index.php`.

   > If you already imported the database *before* this update and still
   > see "Invalid username or password", either re-import
   > `database/lond_dry_shop_db.sql` (drops and recreates the DB, so
   > you'll lose any manual edits you've made), or run this once against
   > your existing database:
   > ```sql
   > UPDATE users SET password_hash = '$2y$10$hVHpr1haXWf3doi6/.bDbuS7RrQUL98kZ9OXjW6MEj.4OyPVcx3/S' WHERE username = 'admin_meri';
   > UPDATE users SET password_hash = '$2y$10$HPHuE..rCCjGTFCqrrrrpu4QYNR2jNJh/LDLxX9WMvs9je/nqnJ2.' WHERE username = 'staff_leslie';
   > UPDATE users SET password_hash = '$2y$10$1GnOqkStx753b0xlHhAQIOr2sEgIUPISjrwzY0iGlqqyphryWL5su' WHERE username = 'staff_april';
   >
   > -- New: Yeshabelle Olango added as a second admin
   > INSERT INTO users (full_name, username, password_hash, role) VALUES
   > ('Yeshabelle Olango', 'admin_yesha', '$2y$10$VWrtIilxYERYmjOa7oZTjO4KaWQW7wbqyEvmbvo/z5lU.CFMkuOqa', 'admin');
   >
   > -- Her old customer record (customer_id 3) is kept for order history,
   > -- just renamed so it's no longer her name:
   > UPDATE customers SET full_name = 'Kristine Bautista' WHERE customer_id = 3;
   > ```

## How the login flow satisfies the rubric

| Requirement | How it's covered |
|---|---|
| #1 Authentication & Authorization | Login form → `login_process.php` verifies credentials, `role` (`admin`/`staff`) drives which dashboard the session is redirected to. `requireRole()` blocks the wrong role from a page. `logout.php` ends the session. |
| #6 Input Validation | Client-side (`login.js`) blocks empty submissions before the request even goes out; server-side (`login_process.php`) re-checks required fields and length, since JS can be bypassed. |
| #7 Error Handling | Failed DB connections, missing fields, wrong credentials, and disabled accounts all show a plain-language message (never a raw SQL/PHP error) via the `?error=` query string rendered on `index.php`. |
| #11 System Security | Passwords hashed with `password_hash()`/verified with `password_verify()` (never stored/compared as plain text); all queries use PDO **prepared statements**; CSRF token on the form; `session_regenerate_id()` on login to block session fixation; `HttpOnly` session cookie; basic per-session brute-force throttling (5 attempts → 30s cooldown). |

## Design notes

- Colors and type follow your brand kit exactly: Deep Blue `#0B3D91`, Sky Blue
  `#1E88E5`, Light Aqua `#87CEEB`, Pale Blue `#E3F2FD`, Sunny Yellow `#FFD93D`.
- **Baloo 2** (rounded/bubbly, matches the logo's lettering) for headings,
  **Nunito Sans** for body text and form fields.
- Split layout: branded panel with your logo + tagline on the left (stacks on
  top on mobile), the login card on the right — mirrors the
  `web-theme.png` / `ex-ui-preview.png` direction you shared.
- The **"Customer? Click here"** link exactly matches your `Log_In_Page.png`
  wireframe and routes to the public, no-login claim-code lookup.

## Next steps (not part of this request, flagging for later)

- Build out `customer/status.php` (the actual stepper timeline + digital
  receipt view) — `customer/track.php` currently just points to it.
- Build the real Admin/Staff dashboards to replace the placeholders.
- Add a `remember me` / session timeout policy if you want one.

Let me know what you'd like to adjust!
