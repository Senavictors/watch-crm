"use client";
import React, { useState } from "react";
import { fmtBRL, fmtDate } from "../helpers";
import { ProductReturn, ReturnMetadata, ReturnStatus, ReturnType } from "../types";
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
  metadata: ReturnMetadata;
  search: string;
  type: ReturnType | "";
  status: ReturnStatus | "";
  assignedUserId: string;
  onSearchChange: (value: string) => void;
  onTypeChange: (value: ReturnType | "") => void;
  onStatusFilterChange: (value: ReturnStatus | "") => void;
  onAssignedUserChange: (value: string) => void;
  canCreate: boolean;
  canUpdateStatus: boolean;
  onView: (r: ProductReturn) => void;
  onNew: () => void;
  onUpdateStatus: (id: number, status: ReturnStatus) => Promise<void>;
};

const ReturnList: React.FC<Props> = ({
  returns,
  metadata,
  search,
  type,
  status,
  assignedUserId,
  onSearchChange,
  onTypeChange,
  onStatusFilterChange,
  onAssignedUserChange,
  canCreate,
  canUpdateStatus,
  onView,
  onNew,
  onUpdateStatus,
}) => {
  // TASK-017 — status "otimista" por linha enquanto a chamada de update está
  // em voo; some (volta a refletir `r.status`) quando a chamada termina,
  // com sucesso (prop já atualizada pelo handler) ou falha (reverte visual).
  const [pendingStatus, setPendingStatus] = useState<Record<number, ReturnStatus>>({});

  const statuses = metadata.statuses;

  function statusOptionsFor(currentStatus: ReturnStatus) {
    const nextStatuses = metadata.transitions?.[currentStatus] ?? [];
    return [currentStatus, ...nextStatuses.filter((s) => s !== currentStatus)];
  }

  async function handleStatusChange(id: number, nextStatus: ReturnStatus) {
    setPendingStatus((current) => ({ ...current, [id]: nextStatus }));
    try {
      await onUpdateStatus(id, nextStatus);
    } catch {
      // erro já foi mostrado via toast pelo handler; aqui só garantimos que
      // o select da linha reverta pro status confirmado (`r.status`).
    } finally {
      setPendingStatus((current) => {
        const next = { ...current };
        delete next[id];
        return next;
      });
    }
  }

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
          onChange={(e) => onSearchChange(e.target.value)}
          placeholder="Buscar por # ou cliente..."
          className={styles.search}
        />
        <select
          value={type}
          onChange={(e) => onTypeChange(e.target.value as ReturnType | "")}
          className={styles.select}
          style={{ color: type ? "var(--crm-input-text)" : "var(--crm-text-soft)" }}
        >
          <option value="">Todos tipos</option>
          <option value="garantia">Garantia</option>
          <option value="troca">Troca</option>
          <option value="devolucao">Devolução</option>
        </select>
        <select
          value={status}
          onChange={(e) => onStatusFilterChange(e.target.value)}
          className={styles.select}
          style={{ color: status ? "var(--crm-input-text)" : "var(--crm-text-soft)" }}
        >
          <option value="">Todos status</option>
          {statuses.map((s) => (
            <option key={s}>{s}</option>
          ))}
        </select>
        <select
          value={assignedUserId}
          onChange={(e) => onAssignedUserChange(e.target.value)}
          className={styles.select}
          style={{ color: assignedUserId ? "var(--crm-input-text)" : "var(--crm-text-soft)" }}
        >
          <option value="">Todos responsáveis</option>
          {metadata.assignableUsers.map((u) => (
            <option key={u.id} value={u.id}>{u.name}</option>
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
            {returns.map((r) => {
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
                        value={pendingStatus[r.id] ?? r.status}
                        onChange={(e) => handleStatusChange(r.id, e.target.value)}
                        className={styles.statusSelect}
                      >
                        {statusOptionsFor(r.status).map((s) => (
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
        {returns.length === 0 && (
          <div className={styles.empty}>Nenhum registro encontrado</div>
        )}
      </Card>
    </div>
  );
};

export default ReturnList;
