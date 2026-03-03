import Link from "next/link";
import { createClient } from "@/lib/supabase/server";
import { redirect } from "next/navigation";

function formatMoney(n: number) {
  return new Intl.NumberFormat("en-GB", {
    style: "currency",
    currency: "GBP",
    maximumFractionDigits: 0,
  }).format(n);
}

function formatDate(s: string) {
  return new Date(s).toLocaleDateString("en-GB", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

export default async function DashboardPage() {
  const supabase = await createClient();
  const {
    data: { user },
  } = await supabase.auth.getUser();
  if (!user) redirect("/login");

  const { data: scenario } = await supabase
    .from("scenarios")
    .select("summary, updated_at")
    .eq("user_id", user.id)
    .single();

  const summary = scenario?.summary as
    | {
        potAtRetirement?: number;
        potAt67?: number;
        potAt90?: number;
        runOutAge?: number | null;
        lastAge?: number;
      }
    | null
    | undefined;

  return (
    <div className="min-h-screen flex flex-col">
      <header className="border-b border-[var(--calc-border)] bg-white/80 backdrop-blur">
        <div className="max-w-4xl mx-auto px-4 py-4 flex justify-between items-center">
          <Link href="/dashboard" className="font-semibold text-lg text-[var(--calc-text)]">
            Retirement Tracker
          </Link>
          <div className="flex items-center gap-4">
            <span className="text-sm text-[var(--calc-muted)]">{user.email}</span>
            <form action="/api/auth/signout" method="post">
              <button
                type="submit"
                className="text-sm text-[var(--calc-muted)] hover:text-[var(--calc-primary)]"
              >
                Sign out
              </button>
            </form>
          </div>
        </div>
      </header>

      <main className="flex-1 max-w-4xl mx-auto w-full px-4 py-8">
        <h1 className="text-2xl font-bold text-[var(--calc-text)] mb-2">
          Your numbers
        </h1>
        {scenario?.updated_at && (
          <p className="text-sm text-[var(--calc-muted)] mb-8">
            Last updated {formatDate(scenario.updated_at)}
          </p>
        )}

        {!summary ? (
          <div className="rounded-2xl border border-[var(--calc-border)] bg-[var(--calc-card)] p-8 text-center">
            <p className="text-[var(--calc-muted)] mb-6">
              You haven’t entered your numbers yet.
            </p>
            <Link
              href="/update"
              className="inline-block rounded-xl bg-[var(--calc-primary)] px-6 py-3 text-white font-medium hover:bg-[var(--calc-primary-hover)]"
            >
              Add my numbers
            </Link>
          </div>
        ) : (
          <>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
              <div className="rounded-2xl border border-[var(--calc-border)] bg-[var(--calc-card)] p-4">
                <p className="text-sm text-[var(--calc-muted)] mb-1">
                  Pot at retirement
                </p>
                <p className="text-xl font-semibold text-[var(--calc-text)]">
                  {formatMoney(summary.potAtRetirement ?? 0)}
                </p>
              </div>
              <div className="rounded-2xl border border-[var(--calc-border)] bg-[var(--calc-card)] p-4">
                <p className="text-sm text-[var(--calc-muted)] mb-1">
                  Pot at 67
                </p>
                <p className="text-xl font-semibold text-[var(--calc-text)]">
                  {formatMoney(summary.potAt67 ?? 0)}
                </p>
              </div>
              <div className="rounded-2xl border border-[var(--calc-border)] bg-[var(--calc-card)] p-4">
                <p className="text-sm text-[var(--calc-muted)] mb-1">
                  Pot at 90
                </p>
                <p className="text-xl font-semibold text-[var(--calc-text)]">
                  {formatMoney(summary.potAt90 ?? 0)}
                </p>
              </div>
              <div className="rounded-2xl border border-[var(--calc-border)] bg-[var(--calc-card)] p-4">
                <p className="text-sm text-[var(--calc-muted)] mb-1">
                  Sustainability
                </p>
                <p className="text-xl font-semibold text-[var(--calc-text)]">
                  {summary.runOutAge != null ? (
                    <span className="text-red-600">
                      Shortfall from {summary.runOutAge}
                    </span>
                  ) : (
                    <span className="text-green-600">OK to {summary.lastAge ?? "—"}</span>
                  )}
                </p>
              </div>
            </div>

            <Link
              href="/update"
              className="inline-block rounded-xl border-2 border-[var(--calc-border)] px-6 py-3 text-[var(--calc-text)] font-medium hover:bg-[var(--calc-soft)]"
            >
              Update my numbers
            </Link>
          </>
        )}
      </main>
    </div>
  );
}
