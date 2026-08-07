"use client";
import React, { useMemo, useState } from "react";
import { fmtBRL } from "../../helpers";
import { DashboardCategoryBreakdown } from "../../types";
import { Card } from "../../ui/Primitives";
import styles from "./CategoryDonut.module.css";

type MetricId = "revenue" | "units";

type Props = {
  categories: DashboardCategoryBreakdown[];
  onCategoryClick: (category: string) => void;
};

const PALETTE = [
  "var(--crm-primary)",
  "var(--crm-accent)",
  "var(--crm-success)",
  "var(--crm-danger)",
  "#a78bfa",
  "#f59e0b",
  "#22d3ee",
  "#f472b6",
];

const RADIUS = 60;
const STROKE = 22;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

const CategoryDonut: React.FC<Props> = ({ categories, onCategoryClick }) => {
  const [metric, setMetric] = useState<MetricId>("revenue");

  const total = useMemo(
    () => categories.reduce((sum, c) => sum + (metric === "revenue" ? c.revenue : c.units), 0),
    [categories, metric]
  );

  const fractions = categories.map((category) => {
    const value = metric === "revenue" ? category.revenue : category.units;
    return total > 0 ? value / total : 0;
  });
  const segments = categories.map((category, index) => {
    const fraction = fractions[index];
    const cumulativeBefore = fractions.slice(0, index).reduce((sum, f) => sum + f, 0);
    const dashArray = `${fraction * CIRCUMFERENCE} ${CIRCUMFERENCE - fraction * CIRCUMFERENCE}`;
    const dashOffset = CIRCUMFERENCE - cumulativeBefore * CIRCUMFERENCE;
    return {
      category,
      color: PALETTE[index % PALETTE.length],
      dashArray,
      dashOffset,
      percentage: fraction * 100,
    };
  });

  return (
    <Card>
      <div className={styles.header}>
        <div className={styles.title}>Categorias</div>
        <div className={styles.toggleRow}>
          <button
            type="button"
            onClick={() => setMetric("revenue")}
            className={`${styles.toggleButton} ${metric === "revenue" ? styles.toggleButtonActive : ""}`}
          >
            Faturamento
          </button>
          <button
            type="button"
            onClick={() => setMetric("units")}
            className={`${styles.toggleButton} ${metric === "units" ? styles.toggleButtonActive : ""}`}
          >
            Unidades
          </button>
        </div>
      </div>

      {categories.length === 0 ? (
        <div className={styles.empty}>Nenhum dado neste período.</div>
      ) : (
        <div className={styles.content}>
          <svg viewBox="0 0 160 160" className={styles.donut}>
            <circle cx="80" cy="80" r={RADIUS} fill="none" stroke="var(--crm-table-header-bg)" strokeWidth={STROKE} />
            {segments.map((segment) => (
              <circle
                key={segment.category.category}
                cx="80"
                cy="80"
                r={RADIUS}
                fill="none"
                stroke={segment.color}
                strokeWidth={STROKE}
                strokeDasharray={segment.dashArray}
                strokeDashoffset={segment.dashOffset}
                transform="rotate(-90 80 80)"
              />
            ))}
          </svg>

          <div className={styles.legend}>
            {segments.map((segment) => (
              <button
                key={segment.category.category}
                type="button"
                onClick={() => onCategoryClick(segment.category.category)}
                className={styles.legendRow}
              >
                <span className={styles.legendSwatch} style={{ background: segment.color }} />
                <span className={styles.legendName}>{segment.category.category}</span>
                <span className={styles.legendValue}>
                  {metric === "revenue" ? fmtBRL(segment.category.revenue) : `${segment.category.units} un.`}
                </span>
                <span className={styles.legendPct}>{segment.percentage.toFixed(1)}%</span>
              </button>
            ))}
          </div>
        </div>
      )}
    </Card>
  );
};

export default CategoryDonut;
