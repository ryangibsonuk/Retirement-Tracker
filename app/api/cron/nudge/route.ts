import { createClient } from "@supabase/supabase-js";
import { NextResponse } from "next/server";

/**
 * Monthly nudge: find users who haven't updated their scenario in ~30 days
 * and haven't opted out, then send "Update your numbers" email.
 *
 * Call from a cron (e.g. Vercel Cron, or external cron hitting this URL with
 * CRON_SECRET in header) once per month.
 *
 * To send email: plug in Resend, SendGrid, or Postmark. Example with Resend:
 *   await fetch("https://api.resend.com/emails", {
 *     method: "POST",
 *     headers: { "Authorization": `Bearer ${process.env.RESEND_API_KEY}`, "Content-Type": "application/json" },
 *     body: JSON.stringify({ from: "nudges@yourdomain.com", to: user.email, subject: "Time to update your retirement numbers", html: `... <a href="${appUrl}/update">Update my numbers</a> ...` }),
 *   });
 */
export async function GET(request: Request) {
  const authHeader = request.headers.get("authorization");
  const cronSecret = process.env.CRON_SECRET;
  if (cronSecret && authHeader !== `Bearer ${cronSecret}`) {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const supabaseUrl = process.env.NEXT_PUBLIC_SUPABASE_URL!;
  const serviceKey = process.env.SUPABASE_SERVICE_ROLE_KEY;
  if (!supabaseUrl || !serviceKey) {
    return NextResponse.json(
      { error: "Missing SUPABASE_SERVICE_ROLE_KEY" },
      { status: 500 }
    );
  }

  const supabase = createClient(supabaseUrl, serviceKey);
  const appUrl = process.env.NEXT_PUBLIC_APP_URL ?? "https://app.retirementcalculators.uk";

  // Users who have a scenario and updated_at older than 30 days
  const cutoff = new Date();
  cutoff.setDate(cutoff.getDate() - 30);
  const cutoffIso = cutoff.toISOString();

  const { data: scenarios } = await supabase
    .from("scenarios")
    .select("user_id, updated_at")
    .lt("updated_at", cutoffIso);

  if (!scenarios?.length) {
    return NextResponse.json({ sent: 0, message: "No users to nudge" });
  }

  const userIds = [...new Set(scenarios.map((s) => s.user_id))];

  // Exclude opted-out
  const { data: prefs } = await supabase
    .from("nudge_prefs")
    .select("user_id")
    .in("user_id", userIds)
    .eq("nudge_opted_out", true);
  const optedOut = new Set((prefs ?? []).map((p) => p.user_id));
  const toNudge = userIds.filter((id) => !optedOut.has(id));

  // Get user emails (auth.users is not directly queryable; use a profile table or admin API)
  // Supabase doesn't expose auth.users to anon/service by default. Options:
  // 1. Create a table public.user_emails (user_id, email) updated by trigger on auth.users.
  // 2. Or use Supabase Auth Admin API to list users and filter by id.
  let sent = 0;
  for (const userId of toNudge) {
    const { data: { user } } = await supabase.auth.admin.getUserById(userId);
    if (!user?.email) continue;

    // TODO: send email via Resend/SendGrid/Postmark (see comment at top of file)
    sent += 1;

    await supabase
      .from("nudge_prefs")
      .upsert({ user_id: userId, last_nudge_at: new Date().toISOString(), updated_at: new Date().toISOString() }, { onConflict: "user_id" });
  }

  return NextResponse.json({
    sent,
    eligible: toNudge.length,
    message: sent ? "Nudge run; add Resend/SendGrid to send real emails." : "No users to nudge.",
  });
}
