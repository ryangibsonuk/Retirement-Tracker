-- Retirement Tracker: one scenario per user
-- Run this in Supabase SQL Editor (or via supabase db push if using CLI)

-- Scenarios: one row per user (upsert on save)
create table if not exists public.scenarios (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references auth.users(id) on delete cascade,
  inputs jsonb not null default '{}',
  summary jsonb,
  updated_at timestamptz not null default now(),
  unique(user_id)
);

-- RLS: users can only read/update their own scenario
alter table public.scenarios enable row level security;

create policy "Users can read own scenario"
  on public.scenarios for select
  using (auth.uid() = user_id);

create policy "Users can insert own scenario"
  on public.scenarios for insert
  with check (auth.uid() = user_id);

create policy "Users can update own scenario"
  on public.scenarios for update
  using (auth.uid() = user_id);

-- Nudge: track last nudge sent (for monthly nudge job)
create table if not exists public.nudge_prefs (
  user_id uuid primary key references auth.users(id) on delete cascade,
  nudge_opted_out boolean not null default false,
  last_nudge_at timestamptz,
  updated_at timestamptz not null default now()
);

alter table public.nudge_prefs enable row level security;

create policy "Users can read own nudge prefs"
  on public.nudge_prefs for select
  using (auth.uid() = user_id);

create policy "Users can update own nudge prefs"
  on public.nudge_prefs for all
  using (auth.uid() = user_id);
