"use client";

import Link from "next/link";
import { OperationalAlert } from "../../types";
import styles from "./OperationalAlerts.module.css";

type Props = {
  alerts: OperationalAlert[];
};

const ICONS: Record<OperationalAlert["severity"], string> = {
  critical: "!",
  warning: "!",
  success: "✓",
};

export default function OperationalAlerts({ alerts }: Props) {
  if (alerts.length === 0) return null;

  return (
    <section className={styles.section} aria-labelledby="operational-alerts-title">
      <div className={styles.headingRow}>
        <div>
          <div className={styles.eyebrow}>PRIORIDADE DO DIA</div>
          <h3 id="operational-alerts-title" className={styles.title}>Atenção operacional</h3>
        </div>
        <span className={styles.count}>{alerts.length} {alerts.length === 1 ? "sinal" : "sinais"}</span>
      </div>

      <div className={styles.grid}>
        {alerts.map((alert) => (
          <article key={alert.type} className={`${styles.alert} ${styles[alert.severity]}`}>
            <span className={styles.icon} aria-hidden="true">{ICONS[alert.severity]}</span>
            <div className={styles.content}>
              <div className={styles.alertTitle}>{alert.title}</div>
              <p className={styles.message}>{alert.message}</p>
              {alert.action && (
                <Link href={alert.action.href} className={styles.action}>
                  {alert.action.label} <span aria-hidden="true">→</span>
                </Link>
              )}
            </div>
          </article>
        ))}
      </div>
    </section>
  );
}
