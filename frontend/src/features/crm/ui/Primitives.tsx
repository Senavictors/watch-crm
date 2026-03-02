import React from "react";
import { OrderStatus } from "../types";
import { STATUS_COLORS } from "../data/mock";
import styles from "./Primitives.module.css";

export const Badge: React.FC<{ status: OrderStatus }> = ({ status }) => {
  const color = STATUS_COLORS[status];
  const style: React.CSSProperties & { ["--badge-color"]?: string } = {
    "--badge-color": color,
  };
  return (
    <span className={styles.badge} style={style}>
      {status}
    </span>
  );
};

export const Card: React.FC<{ style?: React.CSSProperties; className?: string; children: React.ReactNode }> = ({
  children,
  style = {},
  className,
}) => {
  const cn = [styles.card, className].filter(Boolean).join(" ");
  return <div className={cn} style={style}>{children}</div>;
};

export const StatCard: React.FC<{
  label: string;
  value: React.ReactNode;
  sub?: React.ReactNode;
  color?: string;
}> = ({ label, value, sub, color = "var(--crm-accent)" }) => (
  <Card style={{ flex: 1, minWidth: 160 }}>
    <div className={styles.statLabel}>{label}</div>
    <div className={styles.statValue} style={{ color }}>{value}</div>
    {sub && <div className={styles.statSub}>{sub}</div>}
  </Card>
);

export const Input: React.FC<React.InputHTMLAttributes<HTMLInputElement> & { label?: string }> = ({
  label,
  ...props
}) => (
  <div className={styles.fieldGroup}>
    {label && <label className={styles.fieldLabel}>{label}</label>}
    <input className={styles.fieldControl} {...props} />
  </div>
);

export const Select: React.FC<
  React.SelectHTMLAttributes<HTMLSelectElement> & { label?: string }
> = ({ label, children, ...props }) => (
  <div className={styles.fieldGroup}>
    {label && <label className={styles.fieldLabel}>{label}</label>}
    <select className={styles.fieldControl} {...props}>{children}</select>
  </div>
);

export const Btn: React.FC<{
  children: React.ReactNode;
  onClick?: () => void;
  onMouseEnter?: () => void;
  onMouseLeave?: () => void;
  variant?: "primary" | "danger" | "success" | "secondary";
  small?: boolean;
  style?: React.CSSProperties;
  className?: string;
}> = ({
  children,
  onClick,
  onMouseEnter,
  onMouseLeave,
  variant = "primary",
  small,
  style = {},
  className,
}) => {
  const classNames = [
    styles.btn,
    styles[variant],
    small ? styles.small : undefined,
    className,
  ]
    .filter(Boolean)
    .join(" ");
  return (
    <button className={classNames} onClick={onClick} onMouseEnter={onMouseEnter} onMouseLeave={onMouseLeave} style={style}>
      {children}
    </button>
  );
};
