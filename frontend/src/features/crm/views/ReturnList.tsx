"use client";
import React, { useMemo, useState } from "react";
import { fmtBRL, fmtDate } from "../helpers";
import { ProductReturn, ReturnStatus, ReturnType } from "../types";
import { Btn, Card } from "../ui/Primitives";
import { RETURN_STATUS_COLORS, RETURN_TYPE_COLORS } from "../data/mock";
import styles from "./ReturnList.module.css";

const TYPE_LABELS: Record<ReturnType, string> = {
  garantia: "Garantia",
  troca: "Troca",
  devolucao: "Devolução",
};

type Props = {
  returns: ProductReturn[];
  statuses: string[];
  canCreate: boolean;
  canUpdateStatus: boolean;
  onView: (r: ProductReturn) => void;
  onNew: () => void;
  onUpdateStatus: (id: number, status: ReturnStatus) => void;
};

const ReturnList: React.FC<Props> = ({
  returns,
  statuses,
  canCreate,
  canUpdateStatus,
  onView,
  onNew,
  onUpdateStatus,
}) => {
  const [search, setSearch] = useState("");
  const [filterType, setFilterType] = useState<ReturnType | "">("");
  const [filterStatus, setFilterStatus] = useState<ReturnStatus | "">("");

  const filtered = useMemo(
    () =>
      returns.filter((r) => {
        if (filterType && r.type !== filterType) return false;
        if (filterStatus && r.status !== filterStatus) return false;
        if (
          search &&
          !`${r.id} ${r.customerName}`.toLowerCase().includes(search.toLowerCase())
        )
          return false;
        return true;
      }),
    [returns, filterType, filterStatus, search]
  );

  return (
    <div>
      <div className={styles.headerRow}>
        <h2 className={styles.title}>Garantias / Trocas</h2>
        {canCreate && (
          <Btn onClick={onNew} variant="primary" className={styles.actionButton}>
            + Nova Garantia/Troca
          </Btn>
        )}
      </div>

      <div className={styles.filters}>
        <input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Buscar por # ou cliente..."
          className={styles.search}
        />
        <select
          value={filterType}
          onChange={(e) => setFilterType(e.target.value as ReturnType | "")}
          className={styles.select}
          style={{ color: filterType ? "var(--crm-input-text)" : "var(--crm-text-soft)" }}
        >
          <option value="">Todos tipos</option>
          <option value="garantia">Garantia</option>
          <option value="troca">Troca</option>
          <option value="devolucao">Devolução</option>
        </select>
        <select
          value={filterStatus}
          onChange={(e) => setFilterStatus(e.target.value)}
          className={styles.select}
          style={{ color: filterStatus ? "var(--crm-input-text)" : "var(--crm-text-soft)" }}
        >
          <option value="">Todos status</option>
          {statuses.map((s) => (
            <option key={s}>{s}</option>
          ))}
        </select>
      </div>

      <Card className={styles.tableCard}>
        <table className={styles.table}>
          <thead>
            <tr className={styles.theadRow}>
              {["#", "Data", "Cliente", "Item", "Tipo", "Status", "Custo Total", "Responsável",
                ...(canUpdateStatus ? ["Ações"] : []),
              ].map((h) => (
                <th key={h} className={styles.theadCell}>
                  {h}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {filtered.map((r) => {
              const firstItem = r.items[0];
              const itemLabel = firstItem
                ? `${firstItem.productName}${r.items.length > 1 ? ` +${r.items.length - 1}` : ""}`
                : "—";
              const typeColor = RETURN_TYPE_COLORS[r.type] ?? "var(--crm-text-soft)";
              const statusColor = RETURN_STATUS_COLORS[r.status] ?? "var(--crm-text-soft)";

              return (
                <tr key={r.id} className={styles.tbodyRow} onClick={() => onView(r)}>
                  <td className={styles.cellId}>#{r.id}</td>
                  <td className={styles.cellMuted}>{fmtDate(r.createdAt)}</td>
                  <td className={styles.cellText}>{r.customerName}</td>
                  <td className={styles.cellText}>{itemLabel}</td>
                  <td className={styles.cell}>
                    <span
                      className={styles.typePill}
                      style={{ background: `${typeColor}22`, color: typeColor, borderColor: `${typeColor}44` }}
                    >
                      {TYPE_LABELS[r.type] ?? r.type}
                    </span>
                  </td>
                  <td className={styles.cell}>
                    <span
                      className={styles.statusPill}
                      style={{ background: `${statusColor}22`, color: statusColor, borderColor: `${statusColor}44` }}
                    >
                      {r.status}
                    </span>
                  </td>
                  <td className={styles.cellAccent}>{fmtBRL(r.totalCost)}</td>
                  <td className={styles.cellMuted}>{r.assignedUserName ?? "—"}</td>
                  {canUpdateStatus && (
                    <td className={styles.cell} onClick={(e) => e.stopPropagation()}>
                      <select
                        value={r.status}
                        onChange={(e) => onUpdateStatus(r.id, e.target.value)}
                        className={styles.statusSelect}
                      >
                        {statuses.map((s) => (
                          <option key={s}>{s}</option>
                        ))}
                      </select>
                    </td>
                  )}
                </tr>
              );
            })}
          </tbody>
        </table>
        {filtered.length === 0 && (
          <div className={styles.empty}>Nenhum registro encontrado</div>
        )}
      </Card>
    </div>
  );
};

export default ReturnList;
