import React from "react";
import { CHANNELS, SELLERS, STATUS_FLOW } from "../data/mock";
import { calcProfit, fmtBRL } from "../helpers";
import { Order } from "../types";
import { Badge, Card, StatCard } from "../ui/Primitives";
import styles from "./Dashboard.module.css";

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
      <h2 className={styles.title}>Dashboard</h2>

      <div className={styles.statsRow}>
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

      <div className={styles.gridTwo}>
        <Card className={styles.cardRelative}>
          <div className={styles.cardGlow} />
          <div className={styles.cardPattern} />
          <div className={styles.cardContent}>
            <div className={styles.cardHeader}>
              <div className={styles.cardHeaderTitle}>Vendas por Canal</div>
              <button type="button" className={styles.pillButton}>
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
                  className={styles.pillIcon}
                >
                  <path d="m6 9 6 6 6-6" />
                </svg>
              </button>
            </div>
            <div className={styles.channelList}>
              {byChannel.map((ch, index) => {
                const delay = 0.1 + index * 0.12;
                return (
                  <div key={ch.name} className={styles.channelRow}>
                    <div className={styles.channelLine}>
                      <span className={styles.channelLabel}>{ch.name}</span>
                      <div className={styles.channelTrack}>
                        <div
                          className={`crm-animate-width ${styles.channelBar}`}
                          style={{
                            background: channelPalette[index % channelPalette.length],
                            width: `${(ch.total / maxRev) * 100}%`,
                            animationDelay: `${delay}s`,
                          }}
                        />
                      </div>
                      <span
                        className={`crm-animate-fade ${styles.channelValue}`}
                        style={{ animationDelay: `${delay}s` }}
                      >
                        {fmtBRL(ch.total)}
                      </span>
                    </div>
                    <div className={styles.channelMeta}>{ch.count} pedidos</div>
                  </div>
                );
              })}
            </div>
          </div>
        </Card>

        <Card>
          <div className={styles.sectionLabel}>Funil de Pedidos</div>
          {funnel.map((f) => (
            <div key={f.status} className={styles.funnelRow}>
              <Badge status={f.status} />
              <span className={styles.funnelValue}>{f.count}</span>
            </div>
          ))}
        </Card>
      </div>

      <Card>
        <div className={styles.sectionLabel}>Performance por Vendedor</div>
        <table className={styles.table}>
          <thead>
            <tr className={styles.tableHeadRow}>
              {["Vendedor", "Pedidos", "Faturamento", "Lucro"].map((h) => (
                <th key={h} className={styles.tableHeadCell}>
                  {h}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {bySeller.map((s) => (
              <tr key={s.name} className={styles.tableRow}>
                <td className={styles.tableCellName}>{s.name}</td>
                <td className={styles.tableCellMuted}>{s.count}</td>
                <td className={styles.tableCellAccent}>{fmtBRL(s.revenue)}</td>
                <td className={styles.tableCellPrimary}>{fmtBRL(s.profit)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </div>
  );
};

export default Dashboard;
