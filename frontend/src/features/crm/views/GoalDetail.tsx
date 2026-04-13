"use client";
import React from "react";
import { Goal } from "../types";
import { Btn } from "../ui/Primitives";
import modalStyles from "../components/Modal/Modal.module.css";
import styles from "./GoalDetail.module.css";

type Props = {
  goal: Goal;
  onClose: () => void;
};

const SCOPE_LABELS: Record<string, string> = { company: "Empresa", user: "Vendedor" };
const CALC_LABELS: Record<string, string> = { total_value: "Valor Total de Vendas", quantity: "Quantidade de Itens" };
const CYCLE_LABELS: Record<string, string> = { monthly: "Mensal", quarterly: "Trimestral", semiannual: "Semestral", annual: "Anual" };
const PRODUCT_LABELS: Record<string, string> = { WATCH: "Relógios", BOX: "Caixas" };

function progressColor(pct: number): string {
  if (pct >= 100) return "#34d399";
  if (pct >= 70) return "#60a5fa";
  if (pct >= 40) return "#fbbf24";
  return "#f87171";
}

function fmtValue(value: number, type: string): string {
  if (type === "total_value") {
    return value.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  }
  return `${value} un.`;
}

function fmtDate(d: string): string {
  try {
    const [y, m, day] = d.split("-");
    return `${day}/${m}/${y}`;
  } catch {
    return d;
  }
}

const GoalDetail: React.FC<Props> = ({ goal, onClose }) => {
  return (
    <div className={modalStyles.overlay}>
      <div className={`${modalStyles.modal} ${styles.modal}`}>
        <div className={modalStyles.header}>
          <h3 className={modalStyles.title}>{goal.name}</h3>
          <button onClick={onClose} className={modalStyles.close}>×</button>
        </div>

        <div className={styles.totalProgress}>
          <div className={styles.totalPct} style={{ color: progressColor(goal.totalPercentage) }}>
            {goal.totalPercentage}%
          </div>
          <div className={styles.totalValues}>
            {fmtValue(goal.totalCurrent, goal.calculationType)} de {fmtValue(goal.totalTarget, goal.calculationType)}
          </div>
          <div className={styles.progressBarLarge}>
            <div
              className={styles.progressFill}
              style={{
                width: `${Math.min(goal.totalPercentage, 100)}%`,
                background: progressColor(goal.totalPercentage),
              }}
            />
          </div>
        </div>

        <div className={styles.section}>
          <div className={styles.metaGrid}>
            <div className={styles.metaItem}>
              <span className={styles.metaLabel}>Escopo</span>
              <span className={styles.metaValue}>
                {SCOPE_LABELS[goal.scope] ?? goal.scope}
                {goal.targetUserName && ` — ${goal.targetUserName}`}
              </span>
            </div>
            <div className={styles.metaItem}>
              <span className={styles.metaLabel}>Tipo de Cálculo</span>
              <span className={styles.metaValue}>{CALC_LABELS[goal.calculationType]}</span>
            </div>
            <div className={styles.metaItem}>
              <span className={styles.metaLabel}>Ciclo</span>
              <span className={styles.metaValue}>{CYCLE_LABELS[goal.periodCycle]}</span>
            </div>
            <div className={styles.metaItem}>
              <span className={styles.metaLabel}>Período</span>
              <span className={styles.metaValue}>{fmtDate(goal.startDate)} — {fmtDate(goal.endDate)}</span>
            </div>
            {goal.productTypeFilter && (
              <div className={styles.metaItem}>
                <span className={styles.metaLabel}>Tipo de Produto</span>
                <span className={styles.metaValue}>{PRODUCT_LABELS[goal.productTypeFilter] ?? goal.productTypeFilter}</span>
              </div>
            )}
            {goal.brandName && (
              <div className={styles.metaItem}>
                <span className={styles.metaLabel}>Marca</span>
                <span className={styles.metaValue}>{goal.brandName}</span>
              </div>
            )}
            {goal.modelName && (
              <div className={styles.metaItem}>
                <span className={styles.metaLabel}>Modelo</span>
                <span className={styles.metaValue}>{goal.modelName}</span>
              </div>
            )}
          </div>
        </div>

        {goal.description && (
          <div className={styles.section}>
            <div className={styles.sectionTitle}>Descrição</div>
            <div style={{ color: "var(--crm-text)", fontSize: 13, lineHeight: 1.5 }}>{goal.description}</div>
          </div>
        )}

        <div className={styles.section}>
          <div className={styles.sectionTitle}>Intervalos</div>
          {goal.intervals.map((interval) => (
            <div key={interval.id} className={styles.intervalCard}>
              <div className={styles.intervalHeader}>
                <span className={styles.intervalPeriod}>
                  {fmtDate(interval.startDate)} — {fmtDate(interval.endDate)}
                </span>
                <span className={styles.intervalPct} style={{ color: progressColor(interval.percentage) }}>
                  {interval.percentage}%
                </span>
              </div>
              <div className={styles.progressBarSmall}>
                <div
                  className={styles.progressFill}
                  style={{
                    width: `${Math.min(interval.percentage, 100)}%`,
                    background: progressColor(interval.percentage),
                  }}
                />
              </div>
              <div className={styles.intervalValues}>
                {fmtValue(interval.currentValue, goal.calculationType)} / {fmtValue(interval.targetValue, goal.calculationType)}
              </div>
            </div>
          ))}
        </div>

        <div className={styles.closeRow}>
          <Btn onClick={onClose} variant="secondary" className={styles.actionButton}>
            Fechar
          </Btn>
        </div>
      </div>
    </div>
  );
};

export default GoalDetail;
