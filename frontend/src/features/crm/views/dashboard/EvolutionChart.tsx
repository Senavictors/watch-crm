"use client";
import React, { useMemo, useState } from "react";
import { fmtBRL } from "../../helpers";
import { DashboardEvolutionBucket } from "../../types";
import { Card } from "../../ui/Primitives";
import styles from "./EvolutionChart.module.css";

type MetricId = "revenue" | "salesProfit" | "watchesSold" | "ordersCount";

type Props = {
  buckets: DashboardEvolutionBucket[];
  grouping: "day" | "week" | "month";
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

const METRICS: { id: MetricId; label: string; requiresProfit?: boolean }[] = [
  { id: "revenue", label: "Faturamento" },
  { id: "salesProfit", label: "Lucro", requiresProfit: true },
  { id: "watchesSold", label: "Relógios vendidos" },
  { id: "ordersCount", label: "Pedidos" },
];

const EvolutionChart: React.FC<Props> = ({ buckets, grouping }) => {
  const hasProfit = buckets.some((b) => b.salesProfit !== undefined);
  const availableMetrics = METRICS.filter((m) => !m.requiresProfit || hasProfit);
  const [metric, setMetric] = useState<MetricId>("revenue");
  const activeMetric = availableMetrics.some((m) => m.id === metric) ? metric : "revenue";

  const values = useMemo(
    () => buckets.map((b) => (b[activeMetric] as number | undefined) ?? 0),
    [buckets, activeMetric]
  );
  const max = Math.max(...values, 1);
  const isMoney = activeMetric === "revenue" || activeMetric === "salesProfit";

  // CA-03: eixo mostra no máximo ~8 rótulos pra não sobrepor em períodos longos.
  const labelStep = Math.max(1, Math.ceil(buckets.length / 8));

  return (
    <Card>
      <div className={styles.header}>
        <div className={styles.title}>Evolução</div>
        <div className={styles.toggleRow}>
          {availableMetrics.map((m) => (
            <button
              key={m.id}
              type="button"
              onClick={() => setMetric(m.id)}
              className={`${styles.toggleButton} ${activeMetric === m.id ? styles.toggleButtonActive : ""}`}
            >
              {m.label}
            </button>
          ))}
        </div>
      </div>

      {buckets.length === 0 ? (
        <div className={styles.empty}>Nenhum dado neste período.</div>
      ) : (
        <div className={styles.chartArea}>
          <div className={styles.bars}>
            {buckets.map((bucket, index) => {
              const value = values[index];
              const heightPct = (value / max) * 100;
              return (
                <div key={bucket.bucket} className={styles.barColumn}>
                  <div
                    className={`crm-animate-width ${styles.bar}`}
                    style={{ height: `${heightPct}%` }}
                    title={isMoney ? fmtBRL(value) : String(value)}
                  />
                  <div className={styles.barLabel}>
                    {index % labelStep === 0 ? formatBucketLabel(bucket.bucket, grouping) : ""}
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}
    </Card>
  );
};

export default EvolutionChart;
