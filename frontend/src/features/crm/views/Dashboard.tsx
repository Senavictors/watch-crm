import React from "react";
import { CHANNELS, SELLERS, STATUS_FLOW } from "../data/mock";
import { calcProfit, fmtBRL } from "../helpers";
import { Order } from "../types";
import { Badge, Card, StatCard } from "../ui/Primitives";

type Props = {
  orders: Order[];
};

const Dashboard: React.FC<Props> = ({ orders }) => {
  const paid = orders.filter(
    (o) => !["Novo", "Aguardando Pagamento", "Cancelado"].includes(o.status)
  );
  const totalRevenue = paid.reduce((s, o) => s + o.salePrice - o.discount, 0);
  const totalProfit = paid.reduce((s, o) => s + calcProfit(o), 0);
  const avgMargin = paid.length ? ((totalProfit / totalRevenue) * 100).toFixed(1) : "0";

  const byChannel = CHANNELS.map((ch) => ({
    name: ch,
    total: paid
      .filter((o) => o.channel === ch)
      .reduce((s, o) => s + o.salePrice - o.discount, 0),
    count: paid.filter((o) => o.channel === ch).length,
  })).sort((a, b) => b.total - a.total);

  const bySeller = SELLERS.map((s) => ({
    name: s,
    revenue: paid
      .filter((o) => o.seller === s)
      .reduce((acc, o) => acc + o.salePrice - o.discount, 0),
    profit: paid.filter((o) => o.seller === s).reduce((acc, o) => acc + calcProfit(o), 0),
    count: paid.filter((o) => o.seller === s).length,
  }));

  const funnel = STATUS_FLOW.map((st) => ({
    status: st,
    count: orders.filter((o) => o.status === st).length,
  }));

  const maxRev = Math.max(...byChannel.map((c) => c.total), 1);

  return (
    <div>
      <h2
        style={{
          color: "var(--crm-text)",
          marginBottom: 20,
          fontFamily: "'Inter', sans-serif",
          fontSize: 26,
          fontWeight: 600,
        }}
      >
        Dashboard
      </h2>

      <div style={{ display: "flex", gap: 14, flexWrap: "wrap", marginBottom: 24 }}>
        <StatCard
          label="Faturamento"
          value={fmtBRL(totalRevenue)}
          sub={`${paid.length} pedidos`}
          color="var(--crm-accent)"
        />
        <StatCard
          label="Lucro Líquido"
          value={fmtBRL(totalProfit)}
          sub={`Margem ${avgMargin}%`}
          color="var(--crm-primary)"
        />
        <StatCard
          label="Ticket Médio"
          value={paid.length ? fmtBRL(totalRevenue / paid.length) : "—"}
          color="var(--crm-text)"
        />
        <StatCard
          label="Pedidos Ativos"
          value={orders.filter((o) => !["Entregue", "Cancelado"].includes(o.status)).length}
          color="var(--crm-accent)"
        />
      </div>

      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16, marginBottom: 24 }}>
        <Card>
          <div
            style={{
              color: "var(--crm-text-muted)",
              fontSize: 12,
              marginBottom: 14,
              textTransform: "uppercase",
              letterSpacing: 1,
            }}
          >
            Vendas por Canal
          </div>
          {byChannel.map((ch) => (
            <div key={ch.name} style={{ marginBottom: 12 }}>
              <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 4 }}>
                <span style={{ color: "var(--crm-text)", fontSize: 13 }}>{ch.name}</span>
                <span style={{ color: "var(--crm-text)", fontSize: 13, fontWeight: 600 }}>
                  {fmtBRL(ch.total)}
                </span>
              </div>
              <div style={{ background: "var(--crm-table-header-bg)", borderRadius: 999, height: 6 }}>
                <div
                  style={{
                    background: "var(--crm-primary)",
                    borderRadius: 999,
                    height: 6,
                    width: `${(ch.total / maxRev) * 100}%`,
                    transition: "width 0.6s",
                  }}
                />
              </div>
              <div style={{ color: "var(--crm-text-soft)", fontSize: 11, marginTop: 4 }}>
                {ch.count} pedidos
              </div>
            </div>
          ))}
        </Card>

        <Card>
          <div
            style={{
              color: "var(--crm-text-muted)",
              fontSize: 12,
              marginBottom: 14,
              textTransform: "uppercase",
              letterSpacing: 1,
            }}
          >
            Funil de Pedidos
          </div>
          {funnel.map((f) => (
            <div
              key={f.status}
              style={{
                display: "flex",
                justifyContent: "space-between",
                alignItems: "center",
                marginBottom: 10,
              }}
            >
              <Badge status={f.status} />
              <span
                style={{
                  color: "var(--crm-text)",
                  fontWeight: 700,
                  fontFamily: "'Inter', sans-serif",
                  fontVariantNumeric: "tabular-nums",
                }}
              >
                {f.count}
              </span>
            </div>
          ))}
        </Card>
      </div>

      <Card>
        <div
          style={{
            color: "var(--crm-text-muted)",
            fontSize: 12,
            marginBottom: 14,
            textTransform: "uppercase",
            letterSpacing: 1,
          }}
        >
          Performance por Vendedor
        </div>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 14 }}>
          <thead>
            <tr style={{ borderBottom: "1px solid var(--crm-table-border)" }}>
              {["Vendedor", "Pedidos", "Faturamento", "Lucro"].map((h) => (
                <th
                  key={h}
                  style={{
                    color: "var(--crm-text-muted)",
                    fontWeight: 600,
                    padding: "6px 10px",
                    textAlign: "left",
                    fontSize: 12,
                  }}
                >
                  {h}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {bySeller.map((s) => (
              <tr key={s.name} style={{ borderBottom: "1px solid var(--crm-table-border)" }}>
                <td style={{ padding: "8px 10px", color: "var(--crm-text)", fontWeight: 600 }}>
                  {s.name}
                </td>
                <td style={{ padding: "8px 10px", color: "var(--crm-text-muted)" }}>{s.count}</td>
                <td style={{ padding: "8px 10px", color: "var(--crm-accent)", fontWeight: 600 }}>
                  {fmtBRL(s.revenue)}
                </td>
                <td style={{ padding: "8px 10px", color: "var(--crm-primary)", fontWeight: 600 }}>
                  {fmtBRL(s.profit)}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </div>
  );
};

export default Dashboard;
