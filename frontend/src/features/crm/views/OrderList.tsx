"use client";

import React from "react";
import { calcProfit, fmtBRL, fmtDate } from "../helpers";
import { Order, OrderStatus, UserOption } from "../types";
import { Badge, Btn, Card } from "../ui/Primitives";
import styles from "./OrderList.module.css";

type Props = {
  orders: Order[];
  channels: string[];
  sellers: UserOption[];
  statuses: string[];
  categories: string[];
  search: string;
  status: OrderStatus | "";
  paymentStatus: string;
  channel: string;
  sellerUserId: string;
  category: string;
  from: string;
  to: string;
  onSearchChange: (value: string) => void;
  onStatusChange: (value: OrderStatus | "") => void;
  onPaymentStatusChange: (value: string) => void;
  onChannelChange: (value: string) => void;
  onSellerChange: (value: string) => void;
  onCategoryChange: (value: string) => void;
  onFromChange: (value: string) => void;
  onToChange: (value: string) => void;
  canCreate: boolean;
  canUpdateStatus: boolean;
  canViewProfit: boolean;
  onView: (order: Order) => void;
  onNew: () => void;
  onUpdateStatus: (id: number, status: OrderStatus) => void;
};

const OrderList: React.FC<Props> = ({
  orders,
  channels,
  sellers,
  statuses,
  categories,
  search,
  status,
  paymentStatus,
  channel,
  sellerUserId,
  category,
  from,
  to,
  onSearchChange,
  onStatusChange,
  onPaymentStatusChange,
  onChannelChange,
  onSellerChange,
  onCategoryChange,
  onFromChange,
  onToChange,
  canCreate,
  canUpdateStatus,
  canViewProfit,
  onView,
  onNew,
  onUpdateStatus,
}) => {
  const activeChips = [
    category ? { key: "category", label: `Categoria: ${category}`, onRemove: () => onCategoryChange("") } : null,
    paymentStatus === "pending" ? { key: "paymentStatus", label: "Pagamento pendente", onRemove: () => onPaymentStatusChange("") } : null,
    from ? { key: "from", label: `De: ${fmtDate(from)}`, onRemove: () => onFromChange("") } : null,
    to ? { key: "to", label: `Até: ${fmtDate(to)}`, onRemove: () => onToChange("") } : null,
  ].filter((chip): chip is { key: string; label: string; onRemove: () => void } => chip !== null);

  return (
    <div>
      <div className={styles.headerRow}>
        <h2 className={styles.title}>Pedidos</h2>
        {canCreate && <Btn onClick={onNew} variant="primary" className={styles.actionButton}>+ Novo Pedido</Btn>}
      </div>

      <div className={styles.filters}>
        <input value={search} onChange={(event) => onSearchChange(event.target.value)} placeholder="Buscar pedido..." className={styles.search} />
        <select value={status} onChange={(event) => onStatusChange(event.target.value as OrderStatus | "")} className={styles.select}>
          <option value="">Todos status</option>
          {statuses.map((item) => <option key={item}>{item}</option>)}
        </select>
        <select value={channel} onChange={(event) => onChannelChange(event.target.value)} className={styles.select}>
          <option value="">Todos canais</option>
          {channels.map((item) => <option key={item}>{item}</option>)}
        </select>
        <select value={sellerUserId} onChange={(event) => onSellerChange(event.target.value)} className={styles.select}>
          <option value="">Todos vendedores</option>
          {sellers.map((seller) => <option key={seller.id} value={seller.id}>{seller.name}</option>)}
        </select>
        <select value={category} onChange={(event) => onCategoryChange(event.target.value)} className={styles.select}>
          <option value="">Todas categorias</option>
          {categories.map((item) => <option key={item}>{item}</option>)}
        </select>
        <input type="date" value={from} onChange={(event) => onFromChange(event.target.value)} className={styles.select} aria-label="Pago a partir de" />
        <input type="date" value={to} onChange={(event) => onToChange(event.target.value)} className={styles.select} aria-label="Pago até" />
      </div>

      {activeChips.length > 0 && (
        <div className={styles.chips}>
          {activeChips.map((chip) => (
            <span key={chip.key} className={styles.chip}>
              {chip.label}
              <button type="button" onClick={chip.onRemove} className={styles.chipRemove} aria-label={`Remover filtro: ${chip.label}`}>×</button>
            </span>
          ))}
        </div>
      )}

      <Card className={styles.tableCard}>
        <table className={styles.table}>
          <thead>
            <tr className={styles.theadRow}>
              {["#", "Data", "Cliente", "Produto", "Canal", "Vendedor", "Total", ...(canViewProfit ? ["Lucro"] : []), "Status", ...(canUpdateStatus ? ["Ações"] : [])].map((heading) => (
                <th
                  key={heading}
                  className={`${styles.theadCell} ${heading === "Ações" ? styles.actionHeader : ""}`}
                >
                  {heading}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {orders.map((order) => {
              const profit = calcProfit(order);
              return (
                <tr key={order.id} className={styles.tbodyRow} onClick={() => onView(order)}>
                  <td className={styles.cellId}>#{order.id}</td>
                  <td className={styles.cellMuted}>{fmtDate(order.saleDate)}</td>
                  <td className={styles.cellText}>{order.customerName || "—"}</td>
                  <td className={styles.cellText}>{order.productName}{order.itemsCount > 1 ? ` (${order.itemsCount} itens)` : ""}</td>
                  <td className={styles.cellMuted}>{order.channel}</td>
                  <td className={styles.cellMuted}>{order.seller}</td>
                  <td className={styles.cellAccent}>{fmtBRL(order.salePrice - order.discount)}</td>
                  {canViewProfit && profit !== null && <td className={styles.cellProfit} style={{ color: profit > 0 ? "var(--crm-success)" : "var(--crm-danger)" }}>{fmtBRL(profit)}</td>}
                  <td className={styles.cell}><Badge status={order.status} /></td>
                  {canUpdateStatus && (
                    <td className={`${styles.cell} ${styles.actionCell}`} onClick={(event) => event.stopPropagation()}>
                      <select value={order.status} onChange={(event) => onUpdateStatus(order.id, event.target.value as OrderStatus)} className={styles.statusSelect}>
                        {statuses.map((item) => <option key={item}>{item}</option>)}
                      </select>
                    </td>
                  )}
                </tr>
              );
            })}
          </tbody>
        </table>
        {orders.length === 0 && <div className={styles.empty}>Nenhum pedido encontrado</div>}
      </Card>
    </div>
  );
};

export default OrderList;
