# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**BerberBook** — A barber appointment system (PHP 8.1+, MySQL, Vanilla JS). No framework; pure PHP with PDO. Deployed on a standard LAMP/XAMPP stack.

## Setup

1. Import `database.sql` into MySQL — creates `berber_db` with all tables and seeds 39 Istanbul districts.
2. Edit `config/db.php` — set `DB_USER` and `DB_PASS`.
3. Copy `config/mail.php.example` → `config/mail.php` (not tracked by git) and fill in SMTP credentials.
4. Run `composer install` to install PHPMailer.
5. Schedule CRON: `*/5 * * * * php /path/to/berber/cron_reminder.php >> /dev/null 2>&1`

## Architecture

### Routing
No framework router. Each panel is a single PHP file that `include`s sub-pages based on `?page=` GET parameter:
- `index.php` — Auth (login/register), redirects on session
- `berber_paneli.php` — Barber panel, includes `berber/{page}.php`
- `musteri_paneli.php` — Customer panel, includes `musteri/{page}.php`

### Auth & Sessions
- `auth_handler.php` — JSON API for login/register POST requests
- Sessions store: `user_id`, `full_name`, `email`, `role` (`berber`|`musteri`)
- Each panel PHP file enforces its own session check at the top
- Passwords: `password_hash` (BCRYPT) / `password_verify`

### API Pattern
Each role has a dedicated `api.php` that returns JSON:
- `berber/api.php` — barber-gated actions (save_shop, add/edit/delete service, manage employees, update appointment status)
- `musteri/api.php` — customer-gated actions (get_slots, book_appointment, cancel_appointment)

All actions go through `action` POST param. Responses: `{"success": bool, "message": "..."}`.

### Database Access
`config/db.php` exports a `getPDO()` singleton. Always use prepared statements — never string-interpolate user input into SQL.

### Frontend
- Global modal: one `<div class="modal-backdrop">` in each panel layout; content is injected dynamically from `<template>` tags via `window.openModal(title, content)` in `assets/js/panel.js`.
- Global toasts: `window.showToast(type, message)` — types: `success`, `error`, `info`.
- JS files are IIFEs to avoid global scope pollution (except for `window.*` helpers explicitly exposed).
- No build step, no bundler — plain JS and CSS files linked directly.

### Plus Membership Gating
`is_plus` boolean on `users` table gates the "Çalışanlarım" (employees) feature for barbers. Enforced in `berber/calisanlar.php` and `berber/api.php`.

### Time Slot Algorithm
`musteri/api.php` → `get_slots`: generates 30-min slots from 09:00–19:00, then marks slots unavailable if they overlap any existing appointment using: `slot_start < booked_end AND slot_end > booked_start` (where `*_end = *_start + duration`).

### CRON Reminder System
`cron_reminder.php` runs every 5 min, queries appointments where `status='bekliyor' AND reminder_sent=FALSE AND appointment_time BETWEEN NOW() AND NOW()+12min`, sends HTML email via PHPMailer, then sets `reminder_sent=TRUE`. Logs to `cron_log/reminder.log`.

## Key Files

| File | Purpose |
|------|---------|
| `database.sql` | Full schema + district seed data |
| `config/db.php` | PDO singleton (`getPDO()`) |
| `config/mail.php` | SMTP constants (git-ignored) |
| `assets/css/panel.css` | Shared panel styles (modal, toast, sidebar, tables) |
| `assets/js/panel.js` | Global modal + toast functions |
| `assets/js/musteri.js` | 4-step booking flow state machine |

## CSS Design System
Dark glassmorphism theme. Key CSS variables in `panel.css`:
- `--bg-dark: #0a0a0f`, `--bg-card: #16161f`, `--accent: #8b5cf6`
- Sidebar width: `--sidebar-w: 260px`, header height: `--header-h: 64px`
- Status badge classes: `.badge-bekliyor` (amber), `.badge-tamamlandi` (green), `.badge-iptal` (red)
