/** Scenario input: what the user enters (pots, spending, ages). */
export interface ScenarioInput {
  currentAge: number;
  retirementAge: number;
  lifeHorizon: number;

  /** Cash / emergency fund (accessible now). */
  cash: number;
  /** ISA (accessible now). */
  isa: number;
  /** General investment account (accessible now). */
  gia: number;
  /** DC pension pot(s) total (accessible from 57). */
  pensionPot: number;

  /** State pension start age (e.g. 67). */
  statePensionAge: number;
  /** State pension annual amount (£). */
  statePensionAnnual: number;

  /** Annual spending in retirement (£). */
  annualSpending: number;

  /** Real return assumption for investments (e.g. 0.04 = 4%). */
  realReturnPct: number;
  /** Real return for cash (e.g. 0.01). */
  realReturnCashPct: number;
}

/** One row of the projection (deterministic). */
export interface ProjectionYear {
  age: number;
  year: number;
  cash: number;
  isa: number;
  gia: number;
  pension: number;
  total: number;
  withdrawal: number;
  income: number;
  /** True if run out (shortfall). */
  fail?: boolean;
}

/** Summary numbers we store and show on dashboard. */
export interface ProjectionSummary {
  potAtRetirement: number;
  potAt67: number;
  potAt90: number;
  /** Age at which funds run out (null = still positive at life horizon). */
  runOutAge: number | null;
  /** Last projected age we have. */
  lastAge: number;
}

/** Scenario row from DB (user_id, inputs JSON, summary JSON, updated_at). */
export interface ScenarioRow {
  id: string;
  user_id: string;
  inputs: ScenarioInput;
  summary: ProjectionSummary | null;
  updated_at: string;
}
