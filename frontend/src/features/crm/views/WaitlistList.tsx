"use client";
import React, { useState } from "react";
import { fmtDate } from "../helpers";
import { WaitlistEntry, WaitlistMetadata, WaitlistStatus } from "../types";
import { Btn, Card } from "../ui/Primitives";
import { WAITLIST_STATUS_COLORS } from "../data/mock";
import styles from "./WaitlistList.module.css";

type Props = {
  entries: WaitlistEntry[];
  metadata: WaitlistMetadata;
  search: string;
  status: WaitlistStatus | "";
  sellerUserId: string;
  onSearchChange: (value: string) => void;
  onStatusFilterChange: (value: WaitlistStatus | "") => void;
  onSellerChange: (value: string) => void;
  canCreate: boolean;
  canUpdate: boolean;
  canDelete: boolean;
  onNew: () => void;
  onEdit: (entry: WaitlistEntry) => void;
  onDelete: (entry: WaitlistEntry) => void;
  onUpdateStatus: (id: number, status: WaitlistStatus) => Promise<void>;
};

const WaitlistList: React.FC<Props> = ({
  entries,
  metadata,
  search,
  status,
  sellerUserId,
  onSearchChange,
  onStatusFilterChange,
  onSellerChange,
  canCreate,
  canUpdate,
  canDelete,
  onNew,
  onEdit,
  onDelete,
  onUpdateStatus,
}) => {
  // TASK-018 — status "otimista" por linha enquanto a chamada de update está
  // em voo; some (volta a refletir `entry.status`) quando a chamada termina,
  // com sucesso (prop já atualizada pelo handler) ou falha (reverte visual).
  // Mesmo padrão de `ReturnList.tsx` (TASK-017).
  const [pendingStatus, setPendingStatus] = useState<Record<number, WaitlistStatus>>({});

  // TASK-018 (CA-02) — filtro por vendedor é client-side, mesmo padrão do
  // filtro "Responsável" de `ReturnList.tsx`; lista de opções vem de
  // `metadata.assignableUsers` (todos os vendedores elegíveis), não só dos
  // que já têm entrada.
  const sellerOptions = metadata.assignableUsers;

  async function handleStatusChange(id: number, nextStatus: WaitlistStatus) {
    setPendingStatus((current) => ({ ...current, [id]: nextStatus }));
    try {
      await onUpdateStatus(id, nextStatus);
    } catch {
      // erro já foi mostrado via toast pelo handler; aqui só garantimos que
      // o select da linha reverta pro status confirmado (`entry.status`).
    } finally {
      setPendingStatus((current) => {
        const next = { ...current };
        delete next[id];
        return next;
      });
    }
  }

  const showActions = canUpdate || canDelete;

  return (
    <div>
      <div className={styles.headerRow}>
        <h2 className={styles.title}>Lista de Espera</h2>
        {canCreate && (
          <Btn onClick={onNew} variant="primary" className={styles.actionButton}>
            + Nova Entrada
          </Btn>
        )}
      </div>

      <div className={styles.filters}>
        <input
          value={search}
          onChange={(e) => onSearchChange(e.target.value)}
          placeholder="Buscar por cliente ou produto..."
          className={styles.search}
        />
        <select
          value={status}
          onChange={(e) => onStatusFilterChange(e.target.value as WaitlistStatus | "")}
          className={styles.select}
          style={{ color: status ? "var(--crm-input-text)" : "var(--crm-text-soft)" }}
        >
          <option value="">Todos status</option>
          {metadata.statuses.map((s) => (
            <option key={s}>{s}</option>
          ))}
        </select>
        <select
          value={sellerUserId}
          onChange={(e) => onSellerChange(e.target.value)}
          className={styles.select}
          style={{ color: sellerUserId ? "var(--crm-input-text)" : "var(--crm-text-soft)" }}
        >
          <option value="">Todos vendedores</option>
          {sellerOptions.map((u) => (
            <option key={u.id} value={u.id}>{u.name}</option>
          ))}
        </select>
      </div>

      <Card className={styles.tableCard}>
        <table className={styles.table}>
          <thead>
            <tr className={styles.theadRow}>
              {["Cliente", "Produto", "Vendedor", "Data", "Status", "Disponibilidade",
                ...(showActions ? ["Ações"] : []),
              ].map((h) => (
                <th key={h} className={styles.theadCell}>
                  {h}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {entries.map((entry) => {
              const statusColor = WAITLIST_STATUS_COLORS[entry.status] ?? "var(--crm-text-soft)";
              const variationLabel = [entry.brandName, entry.modelName, entry.qualityName]
                .filter(Boolean)
                .join(" · ");

              return (
                <tr key={entry.id} className={styles.tbodyRow}>
                  <td className={styles.cellText}>
                    {entry.customerName}
                    <div className={styles.cellSub}>{entry.customerPhone}</div>
                  </td>
                  <td className={styles.cellText}>
                    {entry.productName}
                    {variationLabel && <div className={styles.cellSub}>{variationLabel}</div>}
                  </td>
                  <td className={styles.cellMuted}>{entry.sellerUserName ?? "—"}</td>
                  <td className={styles.cellMuted}>{fmtDate(entry.createdAt)}</td>
                  <td className={styles.cell}>
                    {canUpdate ? (
                      <select
                        value={pendingStatus[entry.id] ?? entry.status}
                        onChange={(e) => handleStatusChange(entry.id, e.target.value as WaitlistStatus)}
                        className={styles.statusSelect}
                      >
                        {metadata.statuses.map((s) => (
                          <option key={s}>{s}</option>
                        ))}
                      </select>
                    ) : (
                      <span
                        className={styles.statusPill}
                        style={{ background: `${statusColor}22`, color: statusColor, borderColor: `${statusColor}44` }}
                      >
                        {entry.status}
                      </span>
                    )}
                  </td>
                  <td className={styles.cell}>
                    {entry.isAvailable ? (
                      <span className={styles.availablePill}>✅ Disponível agora</span>
                    ) : (
                      <span className={styles.cellMutedInline}>
                        {entry.productCurrentQty !== null ? "Sem estoque" : "—"}
                      </span>
                    )}
                  </td>
                  {showActions && (
                    <td className={styles.cell}>
                      <div className={styles.rowActions}>
                        {canUpdate && (
                          <Btn onClick={() => onEdit(entry)} variant="secondary" small>Editar</Btn>
                        )}
                        {canDelete && (
                          <Btn onClick={() => onDelete(entry)} variant="danger" small>Excluir</Btn>
                        )}
                      </div>
                    </td>
                  )}
                </tr>
              );
            })}
          </tbody>
        </table>
        {entries.length === 0 && (
          <div className={styles.empty}>Nenhum registro encontrado</div>
        )}
      </Card>
    </div>
  );
};

export default WaitlistList;
