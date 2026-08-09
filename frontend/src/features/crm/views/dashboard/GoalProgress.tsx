"use client";
import React from "react";
import { fmtBRL } from "../../helpers";
import { DashboardGoalSummary } from "../../types";
import { Card } from "../../ui/Primitives";
import styles from "./GoalProgress.module.css";

type Props = {
  company: DashboardGoalSummary | null;
  individual?: DashboardGoalSummary | null;
};

function progressColor(pct: number): string {
  if (pct >= 100) return "var(--crm-success)";
  if (pct >= 70) return "#60a5fa";
  if (pct >= 40) return "#fbbf24";
  return "var(--crm-danger)";
}

function formatGoalValue(goal: DashboardGoalSummary, value: number): string {
  if (goal.calculationType === "quantity") {
    return `${new Intl.NumberFormat("pt-BR", { maximumFractionDigits: 1 }).format(value)} un.`;
  }

  return fmtBRL(value);
}

const GoalRow: React.FC<{ label: string; goal: DashboardGoalSummary }> = ({ label, goal }) => (
  <div className={styles.row}>
    <div className={styles.rowHeader}>
      <span className={styles.rowLabel}>{label}</span>
      <span className={styles.rowPct} style={{ color: progressColor(goal.totalPercentage) }}>
        {goal.totalPercentage}%
      </span>
    </div>
    <div className={styles.track}>
      <div
        className={styles.fill}
        style={{
          width: `${Math.min(goal.totalPercentage, 100)}%`,
          background: progressColor(goal.totalPercentage),
        }}
      />
    </div>
    <div className={styles.values}>
      {formatGoalValue(goal, goal.totalCurrent)} de {formatGoalValue(goal, goal.totalTarget)}
    </div>
  </div>
);

const GoalProgress: React.FC<Props> = ({ company, individual }) => {
  if (!company && !individual) {
    return (
      <Card>
        <div className={styles.title}>Metas</div>
        <div className={styles.empty}>Nenhuma meta ativa neste período.</div>
      </Card>
    );
  }

  return (
    <Card>
      <div className={styles.title}>Metas</div>
      {company && <GoalRow label={company.name || "Meta da empresa"} goal={company} />}
      {individual && <GoalRow label={individual.name || "Meta individual"} goal={individual} />}
    </Card>
  );
};

export default GoalProgress;
