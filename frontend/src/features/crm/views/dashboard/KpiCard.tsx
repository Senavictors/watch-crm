"use client";
import React from "react";
import { Card } from "../../ui/Primitives";
import styles from "./KpiCard.module.css";

type Props = {
  label: string;
  value: string;
  sub?: string;
  percentageChange?: number | null;
  changeSuffix?: "%" | "p.p.";
  accent?: string;
};

const KpiCard: React.FC<Props> = ({ label, value, sub, percentageChange, changeSuffix = "%", accent = "var(--crm-accent)" }) => {
  const hasChange = percentageChange !== undefined && percentageChange !== null;
  const isPositive = hasChange && (percentageChange as number) >= 0;

  return (
    <Card className={styles.card}>
      <div className={styles.label}>{label}</div>
      <div className={styles.value} style={{ color: accent }}>{value}</div>
      <div className={styles.footer}>
        {hasChange && (
          <span className={isPositive ? styles.changePositive : styles.changeNegative}>
            {isPositive ? "▲" : "▼"} {Math.abs(percentageChange as number).toFixed(1)}{changeSuffix === "%" ? "%" : " p.p."}
          </span>
        )}
        {sub && <span className={styles.sub}>{sub}</span>}
      </div>
    </Card>
  );
};

export default KpiCard;
