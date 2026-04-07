"use client";
import React, { useMemo, useState } from "react";
import { calcProfit, fmtBRL, fmtDate } from "../helpers";
import { Customer, Order, OrderStatus } from "../types";
import { Badge, Btn, Card } from "../ui/Primitives";
import styles from "./OrderList.module.css";

type Props = {
  orders: Order[];
  customers: Customer[];
  channels: string[];
  sellers: string[];
  statuses: string[];
  canCreate: boolean;
  canUpdateStatus: boolean;
  onView: (order: Order) => void;
  onNew: () => void;
  onUpdateStatus: (id: number, status: OrderStatus) => void;
};

const OrderList: React.FC<Props> = ({
  orders,
  customers,
  channels,
  sellers,
  statuses,
  canCreate,
  canUpdateStatus,
  onView,
  onNew,
  onUpdateStatus,
}) => {
  const [search, setSearch] = useState("");
  const [filterStatus, setFilterStatus] = useState<OrderStatus | "">("");
  const [filterChannel, setFilterChannel] = useState("");
  const [filterSeller, setFilterSeller] = useState("");

  const filtered = useMemo(
    () =>
      orders.filter((o) => {
        if (filterStatus && o.status !== filterStatus) return false;
        if (filterChannel && o.channel !== filterChannel) return false;
        if (filterSeller && o.seller !== filterSeller) return false;
        if (
          search &&
          !`${o.id} ${o.productName}`.toLowerCase().includes(search.toLowerCase())
        )
          return false;
        return true;
      }),
    [orders, filterStatus, filterChannel, filterSeller, search]
  );

  return (
    <div>
      <div className={styles.headerRow}>
        <h2 className={styles.title}>Pedidos</h2>
        {canCreate && (
          <Btn onClick={onNew} variant="primary" className={styles.actionButton}>
            + Novo Pedido
          </Btn>
        )}
      </div>

      <div className={styles.filters}>
        <input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Buscar pedido..."
          className={styles.search}
        />
        <select
          value={filterStatus}
          onChange={(e) => setFilterStatus(e.target.value as OrderStatus | "")}
          className={styles.select}
          style={{ color: filterStatus ? "var(--crm-input-text)" : "var(--crm-text-soft)" }}
        >
          <option value="">Todos status</option>
          {statuses.map((s) => (
            <option key={s}>{s}</option>
          ))}
        </select>
        <select
          value={filterChannel}
          onChange={(e) => setFilterChannel(e.target.value)}
          className={styles.select}
          style={{ color: filterChannel ? "var(--crm-input-text)" : "var(--crm-text-soft)" }}
        >
          <option value="">Todos canais</option>
          {channels.map((c) => (
            <option key={c}>{c}</option>
          ))}
        </select>
        <select
          value={filterSeller}
          onChange={(e) => setFilterSeller(e.target.value)}
          className={styles.select}
          style={{ color: filterSeller ? "var(--crm-input-text)" : "var(--crm-text-soft)" }}
        >
          <option value="">Todos vendedores</option>
          {sellers.map((s) => (
            <option key={s}>{s}</option>
          ))}
        </select>
      </div>

      <Card className={styles.tableCard}>
        <table className={styles.table}>
          <thead>
            <tr className={styles.theadRow}>
              {["#", "Data", "Cliente", "Produto", "Canal", "Vendedor", "Total", "Lucro", "Status", "Ações"].map(
                (h) => (
                  <th key={h} className={styles.theadCell}>
                    {h}
                  </th>
                )
              )}
            </tr>
          </thead>
          <tbody>
            {filtered.map((o) => (
              <tr
                key={o.id}
                className={styles.tbodyRow}
                onClick={() => onView(o)}
              >
                <td className={styles.cellId}>#{o.id}</td>
                <td className={styles.cellMuted}>{fmtDate(o.saleDate)}</td>
                <td className={styles.cellText}>
                  {customers.find((c) => c.id === o.customerId)?.name || "—"}
                </td>
                <td className={styles.cellText}>{o.productName}</td>
                <td className={styles.cellMuted}>{o.channel}</td>
                <td className={styles.cellMuted}>{o.seller}</td>
                <td className={styles.cellAccent}>
                  {fmtBRL(o.salePrice - o.discount)}
                </td>
                <td
                  className={styles.cellProfit}
                  style={{ color: calcProfit(o) > 0 ? "var(--crm-success)" : "var(--crm-danger)" }}
                >
                  {fmtBRL(calcProfit(o))}
                </td>
                <td className={styles.cell}>
                  <Badge status={o.status} />
                </td>
                <td className={styles.cell} onClick={(e) => e.stopPropagation()}>
                  {canUpdateStatus ? (
                    <select
                      value={o.status}
                      onChange={(e) => onUpdateStatus(o.id, e.target.value as OrderStatus)}
                      className={styles.statusSelect}
                    >
                      {statuses.map((s) => (
                        <option key={s}>{s}</option>
                      ))}
                    </select>
                  ) : (
                    <span className={styles.cellMuted}>Sem ação</span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {filtered.length === 0 && (
          <div className={styles.empty}>Nenhum pedido encontrado</div>
        )}
      </Card>
    </div>
  );
};

export default OrderList;
