import React from "react";
import { OrderStatus } from "../types";
import { STATUS_COLORS } from "../data/mock";

export const Badge: React.FC<{ status: OrderStatus }> = ({ status }) => (
  <span
    style={{
      background: STATUS_COLORS[status] + "26",
      color: STATUS_COLORS[status],
      border: `1px solid ${STATUS_COLORS[status]}55`,
      borderRadius: 999,
      padding: "4px 10px",
      fontSize: 11,
      fontWeight: 600,
      whiteSpace: "nowrap",
    }}
  >
    {status}
  </span>
);

export const Card: React.FC<{ style?: React.CSSProperties; children: React.ReactNode }> = ({
  children,
  style = {},
}) => (
  <div
    style={{
      background: "rgba(255, 255, 255, 0.04)",
      border: "1px solid rgba(255, 255, 255, 0.08)",
      borderRadius: 16,
      padding: 20,
      boxShadow: "0 12px 30px rgba(0, 0, 0, 0.35)",
      backdropFilter: "blur(6px)",
      ...style,
    }}
  >
    {children}
  </div>
);

export const StatCard: React.FC<{
  label: string;
  value: React.ReactNode;
  sub?: React.ReactNode;
  color?: string;
}> = ({ label, value, sub, color = "#93C5FD" }) => (
  <Card style={{ flex: 1, minWidth: 160 }}>
    <div
      style={{
        color: "#94A3B8",
        fontSize: 12,
        marginBottom: 6,
        textTransform: "uppercase",
        letterSpacing: 1,
      }}
    >
      {label}
    </div>
    <div
      style={{
        color,
        fontSize: 28,
        fontWeight: 700,
        fontFamily: "'Inter', sans-serif",
        fontVariantNumeric: "tabular-nums",
      }}
    >
      {value}
    </div>
    {sub && <div style={{ color: "#64748B", fontSize: 12, marginTop: 6 }}>{sub}</div>}
  </Card>
);

export const Input: React.FC<React.InputHTMLAttributes<HTMLInputElement> & { label?: string }> = ({
  label,
  ...props
}) => (
  <div style={{ marginBottom: 14 }}>
    {label && (
      <label
        style={{
          display: "block",
          color: "#94A3B8",
          fontSize: 11,
          marginBottom: 5,
          textTransform: "uppercase",
          letterSpacing: 0.5,
        }}
      >
        {label}
      </label>
    )}
    <input
      style={{
        width: "100%",
        background: "rgba(255, 255, 255, 0.03)",
        border: "1px solid rgba(255, 255, 255, 0.08)",
        borderRadius: 12,
        color: "#E2E8F0",
        padding: "8px 12px",
        fontSize: 14,
        outline: "none",
        boxSizing: "border-box",
      }}
      {...props}
    />
  </div>
);

export const Select: React.FC<
  React.SelectHTMLAttributes<HTMLSelectElement> & { label?: string }
> = ({ label, children, ...props }) => (
  <div style={{ marginBottom: 14 }}>
    {label && (
      <label
        style={{
          display: "block",
          color: "#94A3B8",
          fontSize: 11,
          marginBottom: 5,
          textTransform: "uppercase",
          letterSpacing: 0.5,
        }}
      >
        {label}
      </label>
    )}
    <select
      style={{
        width: "100%",
        background: "rgba(255, 255, 255, 0.03)",
        border: "1px solid rgba(255, 255, 255, 0.08)",
        borderRadius: 12,
        color: "#E2E8F0",
        padding: "8px 12px",
        fontSize: 14,
        outline: "none",
        boxSizing: "border-box",
      }}
      {...props}
    >
      {children}
    </select>
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
}> = ({ children, onClick, onMouseEnter, onMouseLeave, variant = "primary", small, style = {} }) => (
  <button
    onClick={onClick}
    onMouseEnter={onMouseEnter}
    onMouseLeave={onMouseLeave}
    style={{
      background:
        variant === "primary"
          ? "#FFFFFF"
          : variant === "danger"
          ? "#EF4444"
          : variant === "success"
          ? "#60A5FA"
          : "rgba(255, 255, 255, 0.06)",
      color: variant === "primary" ? "#0A0A0A" : "#E2E8F0",
      border: "1px solid rgba(255, 255, 255, 0.12)",
      borderRadius: 999,
      padding: small ? "5px 12px" : "9px 18px",
      fontSize: small ? 12 : 14,
      fontWeight: 600,
      cursor: "pointer",
      boxShadow: "0 10px 20px rgba(0, 0, 0, 0.25)",
      backdropFilter: "blur(8px)",
      ...style,
    }}
  >
    {children}
  </button>
);
