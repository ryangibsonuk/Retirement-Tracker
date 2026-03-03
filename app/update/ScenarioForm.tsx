"use client";

import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import type { ScenarioInput } from "@/lib/types";

const defaultInputs: ScenarioInput = {
  currentAge: 40,
  retirementAge: 65,
  lifeHorizon: 95,
  cash: 0,
  isa: 0,
  gia: 0,
  pensionPot: 0,
  statePensionAge: 67,
  statePensionAnnual: 0,
  annualSpending: 30000,
  realReturnPct: 0.04,
  realReturnCashPct: 0.01,
};

function formatMoney(n: number) {
  return new Intl.NumberFormat("en-GB", {
    style: "currency",
    currency: "GBP",
    maximumFractionDigits: 0,
  }).format(n);
}

function parseMoney(s: string): number {
  const n = parseFloat(String(s).replace(/[^0-9.-]/g, ""));
  return Number.isFinite(n) ? n : 0;
}

function parsePct(s: string): number {
  const n = parseFloat(String(s).replace(/[^0-9.-]/g, ""));
  return Number.isFinite(n) ? n / 100 : 0;
}

export function ScenarioForm({
  initialInputs,
}: {
  initialInputs: Record<string, unknown> | null;
}) {
  const router = useRouter();
  const [inputs, setInputs] = useState<ScenarioInput>(() =>
    initialInputs ? { ...defaultInputs, ...initialInputs } as ScenarioInput : defaultInputs
  );
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<{ type: "ok" | "err"; text: string } | null>(null);

  useEffect(() => {
    if (initialInputs && Object.keys(initialInputs).length > 0) {
      setInputs((prev) => ({ ...prev, ...initialInputs } as ScenarioInput));
    }
  }, [initialInputs]);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setMessage(null);
    setSaving(true);
    const res = await fetch("/api/scenario", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ inputs }),
    });
    const data = await res.json().catch(() => ({}));
    setSaving(false);
    if (!res.ok) {
      setMessage({ type: "err", text: data.error ?? "Failed to save" });
      return;
    }
    router.push("/dashboard");
    router.refresh();
  }

  function update<K extends keyof ScenarioInput>(key: K, value: ScenarioInput[K]) {
    setInputs((prev) => ({ ...prev, [key]: value }));
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      <section className="rounded-2xl border border-[var(--calc-border)] bg-[var(--calc-card)] p-6">
        <h2 className="text-lg font-semibold text-[var(--calc-text)] mb-4">
          Ages
        </h2>
        <div className="grid gap-4 sm:grid-cols-3">
          <div>
            <label className="block text-sm font-medium text-[var(--calc-muted)] mb-1">
              Current age
            </label>
            <input
              type="number"
              min={18}
              max={100}
              value={inputs.currentAge}
              onChange={(e) => update("currentAge", parseInt(e.target.value, 10) || 0)}
              className="w-full rounded-xl border border-[var(--calc-border)] px-4 py-2 bg-white"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-[var(--calc-muted)] mb-1">
              Retirement age
            </label>
            <input
              type="number"
              min={40}
              max={90}
              value={inputs.retirementAge}
              onChange={(e) => update("retirementAge", parseInt(e.target.value, 10) || 0)}
              className="w-full rounded-xl border border-[var(--calc-border)] px-4 py-2 bg-white"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-[var(--calc-muted)] mb-1">
              Plan to age
            </label>
            <input
              type="number"
              min={70}
              max={100}
              value={inputs.lifeHorizon}
              onChange={(e) => update("lifeHorizon", parseInt(e.target.value, 10) || 0)}
              className="w-full rounded-xl border border-[var(--calc-border)] px-4 py-2 bg-white"
            />
          </div>
        </div>
      </section>

      <section className="rounded-2xl border border-[var(--calc-border)] bg-[var(--calc-card)] p-6">
        <h2 className="text-lg font-semibold text-[var(--calc-text)] mb-4">
          Pots (today)
        </h2>
        <div className="grid gap-4 sm:grid-cols-2">
          {(
            [
              ["cash", "Cash / emergency fund", "£"],
              ["isa", "ISA", "£"],
              ["gia", "GIA / other investments", "£"],
              ["pensionPot", "DC pension pot(s)", "£"],
            ] as const
          ).map(([key, label, prefix]) => (
            <div key={key}>
              <label className="block text-sm font-medium text-[var(--calc-muted)] mb-1">
                {label}
              </label>
              <input
                type="text"
                inputMode="decimal"
                value={inputs[key] === 0 ? "" : formatMoney(inputs[key]).replace("£", "").trim()}
                onChange={(e) => update(key, parseMoney(e.target.value))}
                placeholder="0"
                className="w-full rounded-xl border border-[var(--calc-border)] px-4 py-2 bg-white"
              />
            </div>
          ))}
        </div>
      </section>

      <section className="rounded-2xl border border-[var(--calc-border)] bg-[var(--calc-card)] p-6">
        <h2 className="text-lg font-semibold text-[var(--calc-text)] mb-4">
          State pension
        </h2>
        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <label className="block text-sm font-medium text-[var(--calc-muted)] mb-1">
              State pension age
            </label>
            <input
              type="number"
              min={66}
              max={68}
              value={inputs.statePensionAge}
              onChange={(e) => update("statePensionAge", parseInt(e.target.value, 10) || 67)}
              className="w-full rounded-xl border border-[var(--calc-border)] px-4 py-2 bg-white"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-[var(--calc-muted)] mb-1">
              Annual amount (£)
            </label>
            <input
              type="text"
              inputMode="decimal"
              value={inputs.statePensionAnnual === 0 ? "" : String(inputs.statePensionAnnual)}
              onChange={(e) => update("statePensionAnnual", parseMoney(e.target.value))}
              placeholder="0"
              className="w-full rounded-xl border border-[var(--calc-border)] px-4 py-2 bg-white"
            />
          </div>
        </div>
      </section>

      <section className="rounded-2xl border border-[var(--calc-border)] bg-[var(--calc-card)] p-6">
        <h2 className="text-lg font-semibold text-[var(--calc-text)] mb-4">
          Spending & returns
        </h2>
        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <label className="block text-sm font-medium text-[var(--calc-muted)] mb-1">
              Annual spending in retirement (£)
            </label>
            <input
              type="text"
              inputMode="decimal"
              value={inputs.annualSpending === 0 ? "" : String(inputs.annualSpending)}
              onChange={(e) => update("annualSpending", parseMoney(e.target.value))}
              placeholder="30000"
              className="w-full rounded-xl border border-[var(--calc-border)] px-4 py-2 bg-white"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-[var(--calc-muted)] mb-1">
              Investment return (real) %
            </label>
            <input
              type="text"
              inputMode="decimal"
              value={inputs.realReturnPct === 0 ? "" : String((inputs.realReturnPct * 100).toFixed(1))}
              onChange={(e) => update("realReturnPct", parsePct(e.target.value))}
              placeholder="4"
              className="w-full rounded-xl border border-[var(--calc-border)] px-4 py-2 bg-white"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-[var(--calc-muted)] mb-1">
              Cash return (real) %
            </label>
            <input
              type="text"
              inputMode="decimal"
              value={inputs.realReturnCashPct === 0 ? "" : String((inputs.realReturnCashPct * 100).toFixed(1))}
              onChange={(e) => update("realReturnCashPct", parsePct(e.target.value))}
              placeholder="1"
              className="w-full rounded-xl border border-[var(--calc-border)] px-4 py-2 bg-white"
            />
          </div>
        </div>
      </section>

      {message && (
        <p
          className={
            message.type === "err"
              ? "text-red-600 text-sm"
              : "text-green-600 text-sm"
          }
        >
          {message.text}
        </p>
      )}

      <button
        type="submit"
        disabled={saving}
        className="w-full rounded-xl bg-[var(--calc-primary)] py-3 text-white font-semibold hover:bg-[var(--calc-primary-hover)] disabled:opacity-60"
      >
        {saving ? "Saving…" : "Save and see my numbers"}
      </button>
    </form>
  );
}
