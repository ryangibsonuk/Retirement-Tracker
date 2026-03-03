import Link from "next/link";
import { createClient } from "@/lib/supabase/server";
import { redirect } from "next/navigation";
import { ScenarioForm } from "./ScenarioForm";

export default async function UpdatePage() {
  const supabase = await createClient();
  const {
    data: { user },
  } = await supabase.auth.getUser();
  if (!user) redirect("/login");

  const { data: scenario } = await supabase
    .from("scenarios")
    .select("inputs")
    .eq("user_id", user.id)
    .single();

  const initialInputs = (scenario?.inputs ?? null) as Record<string, unknown> | null;

  return (
    <div className="min-h-screen flex flex-col">
      <header className="border-b border-[var(--calc-border)] bg-white/80 backdrop-blur">
        <div className="max-w-4xl mx-auto px-4 py-4 flex justify-between items-center">
          <Link href="/dashboard" className="font-semibold text-lg text-[var(--calc-text)]">
            ← Dashboard
          </Link>
        </div>
      </header>

      <main className="flex-1 max-w-2xl mx-auto w-full px-4 py-8">
        <h1 className="text-2xl font-bold text-[var(--calc-text)] mb-2">
          Update my numbers
        </h1>
        <p className="text-sm text-[var(--calc-muted)] mb-8">
          Any retirement age is supported. We use cash and ISAs first, then pension from 57, and state pension when it starts.
        </p>

        <ScenarioForm initialInputs={initialInputs} />
      </main>
    </div>
  );
}
