"use client";
import React, { useMemo, useState } from "react";
import { CommissionReport } from "../types";
import { fmtBRL, fmtDate, fmtDateTime } from "../helpers";
import { Btn, Card, StatCard } from "../ui/Primitives";
import styles from "./Commissions.module.css";

type Props = {
  report: CommissionReport;
  canPay: boolean;
  startDate: string;
  endDate: string;
  sellerUserId: string;
  onStartDateChange: (value: string) => void;
  onEndDateChange: (value: string) => void;
  onSellerUserIdChange: (value: string) => void;
  onPay: (orderItemIds: number[]) => void;
  payLoading: boolean;
};

/**
 * TASK-005 — relatório de comissões (CA-02: fecha por vendedor e período).
 * `report.sellers` só vem preenchido pra quem tem visão de todos os
 * vendedores (owner/admin) — usado aqui só pra decidir se mostra o filtro e
 * a coluna "Vendedor"; RN-02 já é aplicada no backend.
 */
const Commissions: React.FC<Props> = ({
  report,
  canPay,
  startDate,
  endDate,
  sellerUserId,
  onStartDateChange,
  onEndDateChange,
  onSellerUserIdChange,
  onPay,
  payLoading,
}) => {
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const showSellerColumn = Boolean(report.sellers);

  // Uma nova busca (filtro/período) pode trazer um relatório sem alguns dos
  // ids selecionados anteriormente — filtra a seleção pelo conjunto atual
  // de itens em vez de resetar via efeito (evita setState síncrono no
  // corpo de um `useEffect`, ver react-hooks/set-state-in-effect).
  const currentItemIds = useMemo(() => new Set(report.items.map((item) => item.orderItemId)), [report.items]);
  const activeSelected = useMemo(
    () => new Set(Array.from(selected).filter((id) => currentItemIds.has(id))),
    [selected, currentItemIds]
  );

  const payableIds = report.items.filter((item) => !item.paid).map((item) => item.orderItemId);
  const allPayableSelected = payableIds.length > 0 && payableIds.every((id) => activeSelected.has(id));

  function toggle(id: number) {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  function toggleAllPayable() {
    setSelected(allPayableSelected ? new Set() : new Set(payableIds));
  }

  const selectedTotal = report.items
    .filter((item) => activeSelected.has(item.orderItemId))
    .reduce((sum, item) => sum + item.lineCommission, 0);

  return (
    <div>
      <div className={styles.headerRow}>
        <h2 className={styles.title}>Comissões</h2>
      </div>

      <div className={styles.statsRow}>
        <StatCard label="Apurado no período" value={fmtBRL(report.summary.accrued)} color="var(--crm-accent)" />
        <StatCard label="Já Pago" value={fmtBRL(report.summary.paid)} color="var(--crm-success)" />
        <StatCard label="A Pagar" value={fmtBRL(report.summary.pending)} color="var(--crm-primary)" />
      </div>

      <div className={styles.filterBar}>
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
        {report.sellers && (
          <label className={styles.filterLabel}>
            Vendedor
            <select
              className={styles.filterSelect}
              value={sellerUserId}
              onChange={(e) => onSellerUserIdChange(e.target.value)}
            >
              <option value="">Todos os vendedores</option>
              {report.sellers.map((seller) => (
                <option key={seller.id} value={seller.id}>
                  {seller.name}
                </option>
              ))}
            </select>
          </label>
        )}
      </div>

      <Card className={styles.tableCard}>
        {canPay && payableIds.length > 0 && (
          <div className={styles.payBar}>
            <span className={styles.payBarInfo}>
              {activeSelected.size > 0
                ? `${activeSelected.size} selecionado(s) · ${fmtBRL(selectedTotal)}`
                : `${payableIds.length} item(ns) pendente(s) de pagamento`}
            </span>
            <Btn
              onClick={() => {
                onPay(Array.from(activeSelected));
                // Otimista: os itens selecionados vão ficar pagos (ou já não
                // eram elegíveis) — não há razão pra mantê-los marcados.
                setSelected(new Set());
              }}
              variant="primary"
              small
              className={styles.actionButton}
              disabled={activeSelected.size === 0 || payLoading}
            >
              {payLoading ? "Marcando..." : "Marcar como pago"}
            </Btn>
          </div>
        )}
        <table className={styles.table}>
          <thead>
            <tr className={styles.theadRow}>
              {canPay && (
                <th className={styles.theadCell}>
                  <input
                    type="checkbox"
                    checked={allPayableSelected}
                    onChange={toggleAllPayable}
                    disabled={payableIds.length === 0}
                    aria-label="Selecionar todos os pendentes"
                  />
                </th>
              )}
              {showSellerColumn && <th className={styles.theadCell}>Vendedor</th>}
              <th className={styles.theadCell}>Produto</th>
              <th className={styles.theadCell}>Data da Venda</th>
              <th className={styles.theadCell}>Qtd.</th>
              <th className={styles.theadCell}>Comissão Unit.</th>
              <th className={styles.theadCell}>Comissão</th>
              <th className={styles.theadCell}>Status</th>
            </tr>
          </thead>
          <tbody>
            {report.items.length === 0 && (
              <tr>
                <td
                  colSpan={(canPay ? 1 : 0) + (showSellerColumn ? 1 : 0) + 6}
                  className={styles.empty}
                >
                  Nenhuma comissão apurada no período selecionado.
                </td>
              </tr>
            )}
            {report.items.map((item) => (
              <tr key={item.orderItemId} className={styles.row}>
                {canPay && (
                  <td className={styles.cell}>
                    <input
                      type="checkbox"
                      checked={activeSelected.has(item.orderItemId)}
                      onChange={() => toggle(item.orderItemId)}
                      disabled={item.paid}
                      aria-label={`Selecionar comissão do item ${item.orderItemId}`}
                    />
                  </td>
                )}
                {showSellerColumn && <td className={styles.cell}>{item.sellerUserName ?? "—"}</td>}
                <td className={styles.cell}>
                  <div className={styles.name}>{item.productName}</div>
                  {item.returnedQuantity > 0 && (
                    <div className={styles.sub}>
                      {item.returnedQuantity} devolvida(s) — comissão líquida de {item.netQuantity} un.
                    </div>
                  )}
                </td>
                <td className={styles.numericSoft}>{fmtDate(item.saleDate ?? undefined)}</td>
                <td className={styles.numericSoft}>{item.quantity}</td>
                <td className={styles.numericSoft}>{fmtBRL(item.unitCommission)}</td>
                <td className={styles.numericAccentStrong}>{fmtBRL(item.lineCommission)}</td>
                <td className={styles.cell}>
                  {item.paid ? (
                    <>
                      <span className={`${styles.pill} ${styles.pillPaid}`}>Pago</span>
                      <div className={styles.sub}>
                        {fmtDateTime(item.commissionPaidAt)}
                        {item.commissionPaidByUserName ? ` · ${item.commissionPaidByUserName}` : ""}
                      </div>
                    </>
                  ) : (
                    <span className={`${styles.pill} ${styles.pillPending}`}>Pendente</span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </div>
  );
};

export default Commissions;
