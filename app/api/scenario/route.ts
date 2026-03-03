import { NextResponse } from "next/server";
import { createClient } from "@/lib/supabase/server";
import { runProjection } from "@/lib/projection/engine";
import type { ScenarioInput } from "@/lib/types";

export async function GET() {
  const supabase = await createClient();
  const {
    data: { user },
  } = await supabase.auth.getUser();
  if (!user) {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const { data, error } = await supabase
    .from("scenarios")
    .select("id, inputs, summary, updated_at")
    .eq("user_id", user.id)
    .single();

  if (error && error.code !== "PGRST116") {
    return NextResponse.json(
      { error: error.message ?? "Failed to load scenario" },
      { status: 500 }
    );
  }

  return NextResponse.json(
    data
      ? {
          id: data.id,
          inputs: data.inputs as ScenarioInput,
          summary: data.summary,
          updated_at: data.updated_at,
        }
      : null
  );
}

export async function POST(request: Request) {
  const supabase = await createClient();
  const {
    data: { user },
  } = await supabase.auth.getUser();
  if (!user) {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  let body: { inputs: ScenarioInput };
  try {
    body = await request.json();
  } catch {
    return NextResponse.json(
      { error: "Invalid JSON body" },
      { status: 400 }
    );
  }

  if (!body.inputs || typeof body.inputs !== "object") {
    return NextResponse.json(
      { error: "Missing or invalid inputs" },
      { status: 400 }
    );
  }

  const { summary } = runProjection(body.inputs as ScenarioInput);

  const { data, error } = await supabase
    .from("scenarios")
    .upsert(
      {
        user_id: user.id,
        inputs: body.inputs,
        summary,
        updated_at: new Date().toISOString(),
      },
      { onConflict: "user_id" }
    )
    .select("id, updated_at")
    .single();

  if (error) {
    return NextResponse.json(
      { error: error.message ?? "Failed to save scenario" },
      { status: 500 }
    );
  }

  return NextResponse.json({
    id: data.id,
    summary,
    updated_at: data.updated_at,
  });
}
