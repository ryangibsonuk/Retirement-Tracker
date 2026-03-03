import { createClient } from "@/lib/supabase/server";
import { NextResponse } from "next/server";

/** GET /api/nudge-unsubscribe — opt out of monthly nudge (requires logged-in user). */
export async function GET() {
  const supabase = await createClient();
  const {
    data: { user },
  } = await supabase.auth.getUser();
  if (!user) {
    return NextResponse.redirect(new URL("/login", process.env.NEXT_PUBLIC_APP_URL ?? "http://localhost:3000"));
  }

  await supabase
    .from("nudge_prefs")
    .upsert(
      { user_id: user.id, nudge_opted_out: true, updated_at: new Date().toISOString() },
      { onConflict: "user_id" }
    );

  return NextResponse.redirect(
    new URL("/dashboard?nudge=unsubscribed", process.env.NEXT_PUBLIC_APP_URL ?? "http://localhost:3000")
  );
}
