"use client";

import React, { useState } from "react";
import { fmtBRL, fmtDate } from "../../helpers";
import { DashboardEvolutionBucket, DashboardKpi } from "../../types";
import { Card } from "../../ui/Primitives";
import { PERIOD_PRESETS, PeriodPresetId } from "./periodPresets";
import styles from "./EvolutionChart.module.css";

type MetricId = "revenue" | "salesProfit" | "watchesSold" | "ordersCount";

type Props = {
  buckets: DashboardEvolutionBucket[];
  grouping: "day" | "week" | "month";
  period: { from: string; to: string };
  comparison: { from: string; to: string };
  preset: PeriodPresetId;
  refreshing: boolean;
  onPresetChange: (preset: PeriodPresetId) => void;
  totals: Partial<Record<MetricId, DashboardKpi>>;
};

const MONTHS = ["jan", "fev", "mar", "abr", "mai", "jun", "jul", "ago", "set", "out", "nov", "dez"];

function formatBucketLabel(bucket: string, grouping: "day" | "week" | "month"): string {
  const [, month, day] = bucket.split("-");
  if (grouping === "month") {
    const yy = bucket.slice(2, 4);
    return `${MONTHS[Number(month) - 1] ?? month}/${yy}`;
  }
  return `${day}/${month}`;
}

function formatBucketDetail(bucket: string, grouping: "day" | "week" | "month"): string {
  if (grouping === "month") return `Mês de ${formatBucketLabel(bucket, grouping)}`;
  if (grouping === "week") return `Semana de ${fmtDate(bucket)}`;
  return fmtDate(bucket);
}

function niceAxisMax(value: number): number {
  if (value <= 0) return 1;
  const magnitude = 10 ** Math.floor(Math.log10(value));
  const normalized = value / magnitude;
  const rounded = normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 5 ? 5 : 10;
  return rounded * magnitude;
}

function formatAxisValue(value: number, isMoney: boolean): string {
  const prefix = isMoney ? "R$ " : "";
  if (value >= 1_000_000) return `${prefix}${(value / 1_000_000).toLocaleString("pt-BR", { maximumFractionDigits: 1 })} mi`;
  if (value >= 1_000) return `${prefix}${(value / 1_000).toLocaleString("pt-BR", { maximumFractionDigits: 1 })} mil`;
  return `${prefix}${Math.round(value).toLocaleString("pt-BR")}`;
}

const METRICS: { id: MetricId; label: string; summaryLabel: string; requiresProfit?: boolean; requiresRevenue?: boolean }[] = [
  { id: "revenue", label: "Faturamento", summaryLabel: "Faturamento no período", requiresRevenue: true },
  { id: "salesProfit", label: "Lucro", summaryLabel: "Lucro no período", requiresProfit: true },
  { id: "watchesSold", label: "Relógios vendidos", summaryLabel: "Relógios vendidos no período" },
  { id: "ordersCount", label: "Pedidos", summaryLabel: "Pedidos no período" },
];

const EvolutionChart: React.FC<Props> = ({
  buckets,
  grouping,
  period,
  comparison,
  preset,
  refreshing,
  onPresetChange,
  totals,
}) => {
  const hasProfit = buckets.some((bucket) => bucket.salesProfit !== undefined);
  const hasRevenue = buckets.some((bucket) => bucket.revenue !== undefined);
  const availableMetrics = METRICS.filter(
    (item) => (!item.requiresProfit || hasProfit) && (!item.requiresRevenue || hasRevenue)
  );
  const [metric, setMetric] = useState<MetricId>("revenue");
  const activeMetric = availableMetrics.some((item) => item.id === metric)
    ? metric
    : availableMetrics[0]?.id ?? "watchesSold";
  const activeMetricDefinition = METRICS.find((item) => item.id === activeMetric) ?? METRICS[2];
  const activeTotal = totals[activeMetric];
  const values = buckets.map((bucket) => (bucket[activeMetric] as number | undefined) ?? 0);
  const axisMax = niceAxisMax(Math.max(...values, 0));
  const axisTicks = Array.from({ length: 5 }, (_, index) => axisMax - (axisMax / 4) * index);
  const isMoney = activeMetric === "revenue" || activeMetric === "salesProfit";
  const percentageChange = activeTotal?.percentageChange;
  const hasComparison = percentageChange !== undefined && percentageChange !== null;
  const trendClass = !hasComparison || percentageChange === 0
    ? styles.trendNeutral
    : percentageChange > 0
      ? styles.trendPositive
      : styles.trendNegative;
  const activePreset = PERIOD_PRESETS.find((option) => option.id === preset);
  const selectedPeriodLabel = activePreset?.label ?? "Período";
  const selectedPeriodMonth = MONTHS[Number(period.from.slice(5, 7)) - 1];
  const selectedPeriodOptionLabel = (preset === "thisMonth" || preset === "lastMonth") && selectedPeriodMonth
    ? `${selectedPeriodLabel} (${selectedPeriodMonth})`
    : selectedPeriodLabel;
  const plotMinWidth = Math.max(420, buckets.length * 32);

  return (
    <Card className={styles.card}>
      <div className={styles.headerTop}>
        <div>
          <div className={styles.title}>Evolução</div>
          <div className={styles.periodContext}>{fmtDate(period.from)} — {fmtDate(period.to)}</div>
        </div>
        <label className={styles.periodSelectLabel}>
          <span className={styles.srOnly}>Período da evolução</span>
          <select
            value={preset}
            onChange={(event) => onPresetChange(event.target.value as PeriodPresetId)}
            className={styles.periodSelect}
            disabled={refreshing}
            aria-label="Período da evolução"
          >
            {PERIOD_PRESETS.map((option) => (
              <option key={option.id} value={option.id}>
                {option.id === preset ? selectedPeriodOptionLabel : option.label}
              </option>
            ))}
          </select>
        </label>
      </div>

      <div className={styles.summaryRow}>
        <div className={styles.summaryPanel}>
          <div className={styles.summaryLabel}>{activeMetricDefinition.summaryLabel}</div>
          <div className={styles.summaryValue}>
            {isMoney
              ? fmtBRL(activeTotal?.value ?? 0)
              : (activeTotal?.value ?? 0).toLocaleString("pt-BR")}
          </div>
          <span
            className={`${styles.trendBadge} ${trendClass}`}
            title={`Comparado com ${fmtDate(comparison.from)} — ${fmtDate(comparison.to)}`}
          >
            {hasComparison
              ? `${percentageChange >= 0 ? "+" : "−"}${Math.abs(percentageChange).toFixed(1)}% vs anterior`
              : "Sem base comparável"}
          </span>
        </div>

        <div className={styles.toggleRow} aria-label="Métrica da evolução">
          {availableMetrics.map((item) => (
            <button
              key={item.id}
              type="button"
              onClick={() => setMetric(item.id)}
              className={`${styles.toggleButton} ${activeMetric === item.id ? styles.toggleButtonActive : ""}`}
              aria-pressed={activeMetric === item.id}
            >
              {item.label}
            </button>
          ))}
        </div>
      </div>

      {buckets.length === 0 ? (
        <div className={styles.empty}>Nenhum dado neste período.</div>
      ) : (
        <div className={styles.chartFrame}>
          <div className={styles.yAxis} aria-hidden="true">
            {axisTicks.map((tick) => (
              <span key={tick}>{formatAxisValue(tick, isMoney)}</span>
            ))}
          </div>

          <div className={styles.plotScroll}>
            <div className={styles.plot} style={{ minWidth: `${plotMinWidth}px` }}>
              <div className={styles.gridLines} aria-hidden="true">
                {axisTicks.map((tick) => <span key={tick} />)}
              </div>
              <div className={styles.bars}>
                {buckets.map((bucket, index) => {
                  const value = values[index];
                  const heightPct = Math.max(0, Math.min(100, (value / axisMax) * 100));
                  const formattedValue = isMoney ? fmtBRL(value) : value.toLocaleString("pt-BR");
                  const detailLabel = formatBucketDetail(bucket.bucket, grouping);
                  return (
                    <div
                      key={bucket.bucket}
                      className={styles.barColumn}
                      style={{ "--bar-height": `${heightPct}%` } as React.CSSProperties}
                      tabIndex={0}
                      role="img"
                      aria-label={`${detailLabel}: ${formattedValue}`}
                    >
                      <div className={styles.barTrack}>
                        <div className={styles.tooltip} aria-hidden="true">
                          <span>{detailLabel}</span>
                          <strong>{formattedValue}</strong>
                        </div>
                        <div className={`crm-animate-width ${styles.bar}`} />
                      </div>
                      <div className={styles.barLabel}>{formatBucketLabel(bucket.bucket, grouping)}</div>
                    </div>
                  );
                })}
              </div>
            </div>
          </div>
        </div>
      )}
    </Card>
  );
};

export default EvolutionChart;
