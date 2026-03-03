# Retirement Tracker (app)

Flagship paid SaaS for [RetirementCalculators.uk](https://retirementcalculators.uk). Users create a scenario (any retirement age), see deterministic “where you’re at” numbers, save/update over time, and get a monthly nudge to refresh.

## Stack

- **Next.js 15** (App Router), TypeScript, Tailwind
- **Supabase**: Auth (email/password), Postgres (scenarios, nudge_prefs)
- **Stripe**: (v1 placeholder; add subscription + gate in a follow-up)

## Setup

1. **Clone / open** this folder.

2. **Env**
   - Copy `.env.local.example` to `.env.local`.
   - Create a [Supabase](https://supabase.com) project.
   - Set `NEXT_PUBLIC_SUPABASE_URL` and `NEXT_PUBLIC_SUPABASE_ANON_KEY`.
   - For nudge cron (and optional auth admin): `SUPABASE_SERVICE_ROLE_KEY`, `CRON_SECRET`, `NEXT_PUBLIC_APP_URL`.

3. **Database**
   - In Supabase SQL Editor, run `supabase/migrations/001_scenarios.sql`.

4. **Auth**
   - In Supabase Dashboard → Authentication → URL Configuration, set Site URL and Redirect URLs to your app (e.g. `http://localhost:3000` and `http://localhost:3000/auth/callback`).
   - Enable Email provider if you want sign-up (and optionally disable “Confirm email” for local dev).

5. **Install and run**
   ```bash
   npm install
   npm run dev
   ```
   Open [http://localhost:3000](http://localhost:3000).

## Routes

- `/` — Landing (log in / sign up).
- `/login`, `/signup` — Auth.
- `/dashboard` — “Your numbers” summary and “Update my numbers” (requires auth).
- `/update` — Scenario form: ages, pots, state pension, spending, returns (requires auth).
- `/api/scenario` — GET (load), POST (save; runs projection and stores summary).
- `/api/auth/signout` — POST, clears session and redirects to `/`.
- `/api/cron/nudge` — GET, protected by `Authorization: Bearer CRON_SECRET`; finds users not updated in 30 days, updates `nudge_prefs.last_nudge_at`. Plug in Resend/SendGrid to send the actual email.
- `/api/nudge-unsubscribe` — GET, requires auth; sets `nudge_prefs.nudge_opted_out = true` and redirects to dashboard.

## Scope

See [RETIREMENT_TRACKER_SCOPE.md](../RETIREMENT_TRACKER_SCOPE.md) in the repo root for v1 scope, shipped definition, and changelog.

## Deploy

- **Vercel**: connect repo, set env vars, deploy. Add a cron (e.g. monthly) that calls `GET /api/cron/nudge` with `CRON_SECRET`.
- **Stripe**: add subscription product and webhook; gate `/dashboard` and `/update` on active subscription (or trial).
