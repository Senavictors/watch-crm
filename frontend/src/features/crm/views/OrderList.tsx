"use client";
import React, { useMemo, useState } from "react";
import { CHANNELS, SELLERS, STATUS_FLOW } from "../data/mock";
import { calcProfit, fmtBRL, fmtDate } from "../helpers";
import { Customer, Order, OrderStatus } from "../types";
import { Badge, Btn, Card } from "../ui/Primitives";

type Props = {
  orders: Order[];
  customers: Customer[];
  onView: (order: Order) => void;
  onNew: () => void;
  onUpdateStatus: (id: number, status: OrderStatus) => void;
};

const OrderList: React.FC<Props> = ({ orders, customers, onView, onNew, onUpdateStatus }) => {
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
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 20 }}>
        <h2 style={{ color: "var(--crm-text)", fontFamily: "'Inter', sans-serif", fontSize: 26, fontWeight: 600 }}>
          Pedidos
        </h2>
        <Btn onClick={onNew}>+ Novo Pedido</Btn>
      </div>

      <div style={{ display: "flex", gap: 10, marginBottom: 16, flexWrap: "wrap" }}>
        <input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Buscar pedido..."
          style={{
            background: "var(--crm-input-bg)",
            border: "1px solid var(--crm-input-border)",
            borderRadius: 12,
            color: "var(--crm-input-text)",
            padding: "7px 12px",
            fontSize: 13,
            outline: "none",
            flex: 1,
            minWidth: 160,
          }}
        />
        <select
          value={filterStatus}
          onChange={(e) => setFilterStatus(e.target.value as OrderStatus | "")}
          style={{
            background: "var(--crm-input-bg)",
            border: "1px solid var(--crm-input-border)",
            borderRadius: 12,
            color: filterStatus ? "var(--crm-input-text)" : "var(--crm-text-soft)",
            padding: "7px 12px",
            fontSize: 13,
            outline: "none",
          }}
        >
          <option value="">Todos status</option>
          {[...STATUS_FLOW, "Cancelado"].map((s) => (
            <option key={s}>{s}</option>
          ))}
        </select>
        <select
          value={filterChannel}
          onChange={(e) => setFilterChannel(e.target.value)}
          style={{
            background: "var(--crm-input-bg)",
            border: "1px solid var(--crm-input-border)",
            borderRadius: 12,
            color: filterChannel ? "var(--crm-input-text)" : "var(--crm-text-soft)",
            padding: "7px 12px",
            fontSize: 13,
            outline: "none",
          }}
        >
          <option value="">Todos canais</option>
          {CHANNELS.map((c) => (
            <option key={c}>{c}</option>
          ))}
        </select>
        <select
          value={filterSeller}
          onChange={(e) => setFilterSeller(e.target.value)}
          style={{
            background: "var(--crm-input-bg)",
            border: "1px solid var(--crm-input-border)",
            borderRadius: 12,
            color: filterSeller ? "var(--crm-input-text)" : "var(--crm-text-soft)",
            padding: "7px 12px",
            fontSize: 13,
            outline: "none",
          }}
        >
          <option value="">Todos vendedores</option>
          {SELLERS.map((s) => (
            <option key={s}>{s}</option>
          ))}
        </select>
      </div>

      <Card style={{ padding: 0, overflow: "hidden" }}>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 13 }}>
          <thead>
            <tr
              style={{
                borderBottom: "1px solid var(--crm-table-border)",
                background: "var(--crm-table-header-bg)",
              }}
            >
              {["#", "Data", "Cliente", "Produto", "Canal", "Vendedor", "Total", "Lucro", "Status", "Ações"].map(
                (h) => (
                  <th
                    key={h}
                    style={{
                    color: "var(--crm-text-muted)",
                      fontWeight: 600,
                      padding: "10px 14px",
                      textAlign: "left",
                      fontSize: 11,
                      textTransform: "uppercase",
                      letterSpacing: 0.5,
                    }}
                  >
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
                style={{ borderBottom: "1px solid var(--crm-table-border)", cursor: "pointer" }}
                onClick={() => onView(o)}
              >
                <td style={{ padding: "10px 14px", color: "var(--crm-accent)", fontWeight: 700 }}>
                  #{o.id}
                </td>
                <td style={{ padding: "10px 14px", color: "var(--crm-text-muted)" }}>{fmtDate(o.saleDate)}</td>
                <td style={{ padding: "10px 14px", color: "var(--crm-text)" }}>
                  {customers.find((c) => c.id === o.customerId)?.name || "—"}
                </td>
                <td style={{ padding: "10px 14px", color: "var(--crm-text)" }}>{o.productName}</td>
                <td style={{ padding: "10px 14px", color: "var(--crm-text-muted)" }}>{o.channel}</td>
                <td style={{ padding: "10px 14px", color: "var(--crm-text-muted)" }}>{o.seller}</td>
                <td style={{ padding: "10px 14px", color: "var(--crm-accent)", fontWeight: 600 }}>
                  {fmtBRL(o.salePrice - o.discount)}
                </td>
                <td
                  style={{
                    padding: "10px 14px",
                    color: calcProfit(o) > 0 ? "var(--crm-success)" : "var(--crm-danger)",
                    fontWeight: 600,
                  }}
                >
                  {fmtBRL(calcProfit(o))}
                </td>
                <td style={{ padding: "10px 14px" }}>
                  <Badge status={o.status} />
                </td>
                <td style={{ padding: "10px 14px" }} onClick={(e) => e.stopPropagation()}>
                  <select
                    value={o.status}
                    onChange={(e) => onUpdateStatus(o.id, e.target.value as OrderStatus)}
                    style={{
                      background: "var(--crm-input-bg)",
                      border: "1px solid var(--crm-input-border)",
                      borderRadius: 10,
                      color: "var(--crm-input-text)",
                      padding: "4px 6px",
                      fontSize: 11,
                      outline: "none",
                      cursor: "pointer",
                    }}
                  >
                    {[...STATUS_FLOW, "Cancelado"].map((s) => (
                      <option key={s}>{s}</option>
                    ))}
                  </select>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {filtered.length === 0 && (
          <div style={{ padding: 40, textAlign: "center", color: "var(--crm-text-soft)" }}>
            Nenhum pedido encontrado
          </div>
        )}
      </Card>
    </div>
  );
};

export default OrderList;
