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
  const channelPalette = [
    "var(--crm-primary)",
    "var(--crm-accent)",
    "var(--crm-success)",
    "var(--crm-danger)",
  ];

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
        <Card style={{ position: "relative", overflow: "hidden" }}>
          <div
            style={{
              position: "absolute",
              inset: 0,
              background:
                "radial-gradient(60% 60% at 0% 0%, var(--crm-primary-soft), transparent 70%)",
              opacity: 0.8,
            }}
          />
          <div
            style={{
              position: "absolute",
              inset: 0,
              opacity: 0.15,
              backgroundImage: "radial-gradient(var(--crm-text-soft) 1px, transparent 1px)",
              backgroundSize: "26px 26px",
            }}
          />
          <div style={{ position: "relative", zIndex: 1 }}>
            <div
              style={{
                display: "flex",
                alignItems: "center",
                justifyContent: "space-between",
                marginBottom: 18,
              }}
            >
              <div
                style={{
                  color: "var(--crm-text)",
                  fontSize: 15,
                  fontWeight: 700,
                  letterSpacing: 0.2,
                }}
              >
                Vendas por Canal
              </div>
              <button
                type="button"
                style={{
                  display: "flex",
                  alignItems: "center",
                  gap: 6,
                  borderRadius: 999,
                  border: "1px solid var(--crm-button-border)",
                  background: "var(--crm-button-secondary-bg)",
                  padding: "6px 10px",
                  fontSize: 11,
                  fontWeight: 600,
                  color: "var(--crm-text-muted)",
                  cursor: "pointer",
                }}
              >
                Últimos 30d
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="12"
                  height="12"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  style={{ opacity: 0.7 }}
                >
                  <path d="m6 9 6 6 6-6" />
                </svg>
              </button>
            </div>
            <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
              {byChannel.map((ch, index) => {
                const delay = 0.1 + index * 0.12;
                return (
                  <div key={ch.name} style={{ display: "flex", flexDirection: "column", gap: 6 }}>
                    <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
                      <span
                        style={{
                          width: 110,
                          flexShrink: 0,
                          fontSize: 13,
                          fontWeight: 600,
                          color: "var(--crm-text-muted)",
                        }}
                      >
                        {ch.name}
                      </span>
                      <div
                        style={{
                          flex: 1,
                          height: 8,
                          borderRadius: 999,
                          background: "var(--crm-table-header-bg)",
                          overflow: "hidden",
                        }}
                      >
                        <div
                          className="crm-animate-width"
                          style={{
                            height: "100%",
                            borderRadius: 999,
                            background: channelPalette[index % channelPalette.length],
                            width: `${(ch.total / maxRev) * 100}%`,
                            animationDelay: `${delay}s`,
                          }}
                        />
                      </div>
                      <span
                        className="crm-animate-fade"
                        style={{
                          width: 86,
                          textAlign: "right",
                          fontSize: 13,
                          fontWeight: 600,
                          color: "var(--crm-text)",
                          fontVariantNumeric: "tabular-nums",
                          animationDelay: `${delay}s`,
                        }}
                      >
                        {fmtBRL(ch.total)}
                      </span>
                    </div>
                    <div style={{ color: "var(--crm-text-soft)", fontSize: 11 }}>{ch.count} pedidos</div>
                  </div>
                );
              })}
            </div>
          </div>
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
