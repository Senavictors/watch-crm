"use client";
import React from "react";
import { Goal } from "../types";
import { Btn } from "../ui/Primitives";
import styles from "./GoalList.module.css";

type Props = {
  goals: Goal[];
  search: string;
  scope: string;
  status: string;
  onSearchChange: (value: string) => void;
  onScopeChange: (value: string) => void;
  onStatusChange: (value: string) => void;
  canCreate: boolean;
  canUpdate: boolean;
  canDelete: boolean;
  onNew: () => void;
  onEdit: (goal: Goal) => void;
  onDelete: (goal: Goal) => void;
  onSelect: (goal: Goal) => void;
};

const SCOPE_LABELS: Record<string, string> = {
  company: "Empresa",
  user: "Vendedor",
};

const CALC_LABELS: Record<string, string> = {
  total_value: "Valor",
  quantity: "Qtd.",
};

const CYCLE_LABELS: Record<string, string> = {
  monthly: "Mensal",
  quarterly: "Trimestral",
  semiannual: "Semestral",
  annual: "Anual",
};

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

const GoalList: React.FC<Props> = ({
  goals,
  search,
  scope,
  status,
  onSearchChange,
  onScopeChange,
  onStatusChange,
  canCreate,
  canUpdate,
  canDelete,
  onNew,
  onEdit,
  onDelete,
  onSelect,
}) => {
  return (
    <div>
      <div className={styles.headerRow}>
        <h2 className={styles.title}>Metas</h2>
        {canCreate && (
          <Btn onClick={onNew} variant="primary" className={styles.actionButton}>
            + Nova Meta
          </Btn>
        )}
      </div>

      <div className={styles.filterBar}>
        <input
          value={search}
          onChange={(e) => onSearchChange(e.target.value)}
          placeholder="Buscar por nome ou vendedor..."
          className={styles.search}
        />
        <select
          value={scope}
          onChange={(e) => onScopeChange(e.target.value)}
          className={styles.filterSelect}
        >
          <option value="">Todos os escopos</option>
          <option value="company">Empresa</option>
          <option value="user">Vendedor</option>
        </select>
        <select
          value={status}
          onChange={(e) => onStatusChange(e.target.value)}
          className={styles.filterSelect}
        >
          <option value="">Todos os status</option>
          <option value="active">Ativas</option>
          <option value="archived">Arquivadas</option>
        </select>
      </div>

      <div className={styles.tableWrapper}>
        <table className={styles.table}>
          <thead>
            <tr>
              <th className={styles.th}>Meta</th>
              <th className={styles.th}>Escopo</th>
              <th className={styles.th}>Tipo</th>
              <th className={styles.th}>Ciclo</th>
              <th className={styles.th}>Progresso</th>
              <th className={styles.th}>Status</th>
              {(canUpdate || canDelete) && <th className={styles.th}>Ações</th>}
            </tr>
          </thead>
          <tbody>
            {goals.length === 0 && (
              <tr>
                <td colSpan={canUpdate || canDelete ? 7 : 6} className={styles.empty}>
                  Nenhuma meta encontrada.
                </td>
              </tr>
            )}
            {goals.map((g) => (
              <tr key={g.id} className={styles.row} onClick={() => onSelect(g)}>
                <td className={styles.td}>
                  <div>{g.name}</div>
                  {g.targetUserName && (
                    <div style={{ fontSize: 11, color: "var(--crm-text-muted)" }}>{g.targetUserName}</div>
                  )}
                </td>
                <td className={styles.td}>
                  <span className={styles.scopeChip}>{SCOPE_LABELS[g.scope] ?? g.scope}</span>
                </td>
                <td className={styles.td}>
                  {CALC_LABELS[g.calculationType] ?? g.calculationType}
                  {g.brandName && <span style={{ fontSize: 11, color: "var(--crm-text-muted)" }}> · {g.brandName}</span>}
                </td>
                <td className={styles.td}>{CYCLE_LABELS[g.periodCycle] ?? g.periodCycle}</td>
                <td className={styles.td}>
                  <div className={styles.progressBarWrapper}>
                    <div className={styles.progressBar}>
                      <div
                        className={styles.progressFill}
                        style={{
                          width: `${Math.min(g.totalPercentage, 100)}%`,
                          background: progressColor(g.totalPercentage),
                        }}
                      />
                    </div>
                    <span className={styles.progressLabel} style={{ color: progressColor(g.totalPercentage) }}>
                      {g.totalPercentage}%
                    </span>
                  </div>
                  <div style={{ fontSize: 11, color: "var(--crm-text-muted)", marginTop: 2 }}>
                    {fmtValue(g.totalCurrent, g.calculationType)} / {fmtValue(g.totalTarget, g.calculationType)}
                  </div>
                </td>
                <td className={styles.td}>
                  {g.status === "active" ? (
                    <span className={styles.statusActive}>Ativa</span>
                  ) : (
                    <span className={styles.statusArchived}>Arquivada</span>
                  )}
                </td>
                {(canUpdate || canDelete) && (
                  <td className={`${styles.td} ${styles.actionsCell}`} onClick={(e) => e.stopPropagation()}>
                    <div className={styles.actions}>
                      {canUpdate && (
                        <Btn onClick={() => onEdit(g)} variant="secondary" small>
                          Editar
                        </Btn>
                      )}
                      {canDelete && (
                        <Btn onClick={() => onDelete(g)} variant="danger" small>
                          Excluir
                        </Btn>
                      )}
                    </div>
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
};

export default GoalList;
