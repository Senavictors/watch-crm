/**
 * TASK-011 — presets de período do dashboard administrativo
 * (docs/regras-de-negocio-dashboard.md §2). Cálculo puro de datas no
 * frontend (não é regra financeira/de negócio, é apenas tradução de um
 * atalho de UI em `from`/`to` explícitos) — o backend só resolve "mês
 * atual" por conta própria quando nenhum dos dois vem na query string.
 */
export type PeriodPresetId =
  | "today"
  | "yesterday"
  | "last7"
  | "last30"
  | "thisMonth"
  | "lastMonth"
  | "custom";

export type PeriodPresetOption = { id: PeriodPresetId; label: string };

export const PERIOD_PRESETS: PeriodPresetOption[] = [
  { id: "thisMonth", label: "Mês atual" },
  { id: "today", label: "Hoje" },
  { id: "yesterday", label: "Ontem" },
  { id: "last7", label: "Últimos 7 dias" },
  { id: "last30", label: "Últimos 30 dias" },
  { id: "lastMonth", label: "Mês anterior" },
  { id: "custom", label: "Personalizado" },
];

function toIsoDate(date: Date): string {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}

/**
 * Retorna `{ from, to }` já resolvidos para o preset, ou `null` para
 * "custom" (nesse caso o chamador usa as datas escolhidas manualmente).
 */
export function resolvePresetRange(
  preset: PeriodPresetId,
  referenceDate: Date = new Date()
): { from: string; to: string } | null {
  const today = new Date(referenceDate);
  today.setHours(0, 0, 0, 0);

  switch (preset) {
    case "today":
      return { from: toIsoDate(today), to: toIsoDate(today) };
    case "yesterday": {
      const day = new Date(today);
      day.setDate(day.getDate() - 1);
      return { from: toIsoDate(day), to: toIsoDate(day) };
    }
    case "last7": {
      const start = new Date(today);
      start.setDate(start.getDate() - 6);
      return { from: toIsoDate(start), to: toIsoDate(today) };
    }
    case "last30": {
      const start = new Date(today);
      start.setDate(start.getDate() - 29);
      return { from: toIsoDate(start), to: toIsoDate(today) };
    }
    case "thisMonth": {
      const start = new Date(today.getFullYear(), today.getMonth(), 1);
      const end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
      return { from: toIsoDate(start), to: toIsoDate(end) };
    }
    case "lastMonth": {
      const start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
      const end = new Date(today.getFullYear(), today.getMonth(), 0);
      return { from: toIsoDate(start), to: toIsoDate(end) };
    }
    case "custom":
    default:
      return null;
  }
}
