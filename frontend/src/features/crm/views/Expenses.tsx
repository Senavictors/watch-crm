"use client";
import React from "react";
import { Expense, ExpenseMetadata } from "../types";
import { fmtBRL, fmtDate } from "../helpers";
import { Btn, Card, StatCard } from "../ui/Primitives";
import styles from "./Expenses.module.css";

type Props = {
  expenses: Expense[];
  metadata: ExpenseMetadata;
  summary: { totalAmount: number; totalCount: number };
  canCreate: boolean;
  canUpdate: boolean;
  canDelete: boolean;
  category: string;
  startDate: string;
  endDate: string;
  onCategoryChange: (value: string) => void;
  onStartDateChange: (value: string) => void;
  onEndDateChange: (value: string) => void;
  onNew: () => void;
  onEdit: (expense: Expense) => void;
  onDelete: (expense: Expense) => void;
};

/**
 * TASK-006 — módulo de despesas gerais. CA-01: filtros por categoria e
 * período (aplicados no backend, ver `app/(app)/despesas/page.tsx`).
 */
const Expenses: React.FC<Props> = ({
  expenses,
  metadata,
  summary,
  canCreate,
  canUpdate,
  canDelete,
  category,
  startDate,
  endDate,
  onCategoryChange,
  onStartDateChange,
  onEndDateChange,
  onNew,
  onEdit,
  onDelete,
}) => {
  const showActions = canUpdate || canDelete;

  return (
    <div>
      <div className={styles.headerRow}>
        <h2 className={styles.title}>Despesas</h2>
        {canCreate && (
          <Btn onClick={onNew} variant="primary" className={styles.actionButton}>
            + Nova Despesa
          </Btn>
        )}
      </div>

      <div className={styles.statsRow}>
        <StatCard label="Total no período" value={fmtBRL(summary.totalAmount)} color="var(--crm-accent)" />
        <StatCard label="Lançamentos" value={summary.totalCount} color="var(--crm-text)" />
      </div>

      <div className={styles.filterBar}>
        <label className={styles.filterLabel}>
          Categoria
          <select
            className={styles.filterSelect}
            value={category}
            onChange={(e) => onCategoryChange(e.target.value)}
          >
            <option value="">Todas as categorias</option>
            {metadata.categories.map((c) => (
              <option key={c} value={c}>
                {c}
              </option>
            ))}
          </select>
        </label>
        <label className={styles.filterLabel}>
          Início
          <input
            type="date"
            className={styles.filterInput}
            value={startDate}
            onChange={(e) => onStartDateChange(e.target.value)}
          />
        </label>
        <label className={styles.filterLabel}>
          Fim
          <input
            type="date"
            className={styles.filterInput}
            value={endDate}
            onChange={(e) => onEndDateChange(e.target.value)}
          />
        </label>
      </div>

      <Card>
        <div className={styles.tableWrapper}>
          <table className={styles.table}>
            <thead>
              <tr>
                <th className={styles.th}>Categoria</th>
                <th className={styles.th}>Descrição</th>
                <th className={styles.th}>Data</th>
                <th className={styles.th}>Valor</th>
                {showActions && <th className={styles.th}>Ações</th>}
              </tr>
            </thead>
            <tbody>
              {expenses.length === 0 && (
                <tr>
                  <td colSpan={showActions ? 5 : 4} className={styles.empty}>
                    Nenhuma despesa encontrada no período selecionado.
                  </td>
                </tr>
              )}
              {expenses.map((expense) => (
                <tr key={expense.id}>
                  <td className={styles.td}>
                    <span className={styles.categoryChip}>{expense.category}</span>
                  </td>
                  <td className={styles.td}>
                    {expense.description}
                    {expense.createdByUserName && (
                      <div className={styles.sub}>lançada por {expense.createdByUserName}</div>
                    )}
                  </td>
                  <td className={styles.td}>{fmtDate(expense.expenseDate)}</td>
                  <td className={`${styles.td} ${styles.amount}`}>{fmtBRL(expense.amount)}</td>
                  {showActions && (
                    <td className={styles.td}>
                      <div className={styles.actionsCell}>
                        {canUpdate && (
                          <Btn onClick={() => onEdit(expense)} variant="secondary" small>
                            Editar
                          </Btn>
                        )}
                        {canDelete && (
                          <Btn onClick={() => onDelete(expense)} variant="danger" small>
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
      </Card>
    </div>
  );
};

export default Expenses;
