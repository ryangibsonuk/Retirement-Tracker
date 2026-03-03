import type { ScenarioInput, ProjectionYear, ProjectionSummary } from "@/lib/types";

const PENSION_ACCESS_AGE = 57;

function clampNum(n: number): number {
  return Number.isFinite(n) ? n : 0;
}

/**
 * Deterministic projection with correct bridging:
 * - From currentAge to retirementAge: no withdrawals (accumulation).
 * - From retirementAge: spend annualSpending. Withdraw in order: cash → ISA → GIA (all accessible).
 *   Pension pot is only used from PENSION_ACCESS_AGE (57). State pension from statePensionAge.
 * - Run until lifeHorizon or until funds run out.
 */
export function runProjection(input: ScenarioInput): {
  years: ProjectionYear[];
  summary: ProjectionSummary;
} {
  const cur = input.currentAge;
  const retAge = input.retirementAge;
  const endAge = Math.max(retAge, input.lifeHorizon);
  const rInv = clampNum(input.realReturnPct);
  const rCash = clampNum(input.realReturnCashPct);

  let cash = clampNum(input.cash);
  let isa = clampNum(input.isa);
  let gia = clampNum(input.gia);
  let pension = clampNum(input.pensionPot);

  const years: ProjectionYear[] = [];
  let runOutAge: number | null = null;

  for (let age = cur; age <= endAge; age++) {
    const year = new Date().getFullYear() + (age - cur);

    // Growth (before withdrawals)
    cash = cash * (1 + rCash);
    isa = isa * (1 + rInv);
    gia = gia * (1 + rInv);
    pension = pension * (1 + rInv);

    let withdrawal = 0;
    let income = 0;
    let shortfall = 0;

    if (age >= retAge) {
      income =
        age >= input.statePensionAge
          ? clampNum(input.statePensionAnnual)
          : 0;
      const need = Math.max(0, clampNum(input.annualSpending) - income);

      if (need > 0) {
        let remaining = need;
        // Withdrawal order: cash → ISA → GIA; pension only from 57
        const take = (available: number) => Math.min(available, remaining);

        const fromCash = take(cash);
        cash -= fromCash;
        remaining -= fromCash;
        withdrawal += fromCash;

        if (remaining > 0) {
          const fromIsa = take(isa);
          isa -= fromIsa;
          remaining -= fromIsa;
          withdrawal += fromIsa;
        }
        if (remaining > 0) {
          const fromGia = take(gia);
          gia -= fromGia;
          remaining -= fromGia;
          withdrawal += fromGia;
        }
        if (remaining > 0 && age >= PENSION_ACCESS_AGE) {
          const fromPension = take(pension);
          pension -= fromPension;
          remaining -= fromPension;
          withdrawal += fromPension;
        }
        shortfall = remaining;
      }
    }

    const total = cash + isa + gia + pension;
    const fail = age >= retAge && shortfall > 0;
    if (fail && runOutAge === null) runOutAge = age;

    years.push({
      age,
      year,
      cash: Math.round(cash),
      isa: Math.round(isa),
      gia: Math.round(gia),
      pension: Math.round(pension),
      total: Math.round(total),
      withdrawal: Math.round(withdrawal),
      income: Math.round(income),
      ...(fail && { fail: true }),
    });
  }

  const last = years[years.length - 1];
  const atRet = years.find((y) => y.age === retAge);
  const at67 = years.find((y) => y.age === 67);
  const at90 = years.find((y) => y.age === 90);

  const summary: ProjectionSummary = {
    potAtRetirement: atRet ? atRet.total : 0,
    potAt67: at67 ? at67.total : 0,
    potAt90: at90 ? at90.total : 0,
    runOutAge,
    lastAge: last?.age ?? endAge,
  };

  return { years, summary };
}
