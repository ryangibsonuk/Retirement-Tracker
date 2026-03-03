# Retirement Tracker – WordPress Plugin

Same behaviour as the Next.js app: one scenario per user, deterministic projection (cash → ISA → GIA → pension from 57, state from 67), dashboard, update form, monthly nudge email, unsubscribe.

## Requirements

- WordPress 5.9+
- PHP 7.4+
- Users must be logged in (WP users); no Supabase

## Install

1. Copy the `retirement-tracker-wp` folder to `wp-content/plugins/retirement-tracker/` (or zip the folder and install via Plugins → Add New → Upload).
2. Activate **Retirement Tracker** in the WordPress admin.
3. Go to **Settings → Retirement Tracker** and (optionally) select the page that contains the update form shortcode.

## Setup

1. **Pages**
   - Create a page for the dashboard and add the shortcode: `[retirement_tracker_dashboard]`.
   - Create a page for the update form and add: `[retirement_tracker_form]`.
   - In **Settings → Retirement Tracker**, set **Update form page** to the form page (used for “Add my numbers” and nudge links).

2. **Access**
   - Both shortcodes require the user to be logged in. Restrict those pages to logged-in users (e.g. theme, redirect, or a membership plugin).

3. **Nudge**
   - Monthly nudge is scheduled on plugin activation (WP-Cron event `wp_scheduled_rt_nudge`).
   - Unsubscribe: link to any URL with `?retirement_tracker_nudge_unsubscribe=1` (user must be logged in). Nudge emails include this link.

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| **`[retirement_tracker_app]`** | **Main app.** When logged out: landing with "Log in to your dashboard" and "View demo". When logged in: full SaaS-style dashboard (hero numbers, charts, progress, suggestions, monthly reminder). Add `?rt_dummy=1` for demo with sample data. |
| `[retirement_tracker_dashboard]` | Simple summary. |
| `[retirement_tracker_dashboard mode="full"]` | Drag-and-drop widgets. |
| `[retirement_tracker_form]` | Update-my-numbers form (requires login). Saves and redirects to dashboard. |

**Flow:** Create one page with `[retirement_tracker_app]` (e.g. `/retirement` or home). Create another with `[retirement_tracker_form]`. In Settings → Retirement Tracker, set **Dashboard page** = the app page, **Update form page** = the form page. Users log in → see their dashboard → click "Update my numbers" → fill form → save → return to dashboard. Progress and charts build up over time as they update.

## Database

- `wp_retirement_scenarios` – one row per user: `user_id`, `inputs` (JSON), `summary` (JSON), `updated_at`.
- `wp_retirement_nudge_prefs` – `user_id`, `nudge_opted_out`, `last_nudge_at`, `updated_at`.

Tables are created on plugin activation.

## Deploy

Zip the contents of `retirement-tracker-wp` (so that `retirement-tracker.php` is at the root of the zip) and install as a plugin, or copy the folder into `wp-content/plugins/retirement-tracker/`.
