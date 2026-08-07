"use client";
import React from "react";
import { fmtBRL } from "../../helpers";
import { DashboardChannelBreakdown } from "../../types";
import { Card } from "../../ui/Primitives";
import styles from "./ChannelBars.module.css";

type Props = {
  channels: DashboardChannelBreakdown[];
};

const PALETTE = ["var(--crm-primary)", "var(--crm-accent)", "var(--crm-success)", "var(--crm-danger)"];

const ChannelBars: React.FC<Props> = ({ channels }) => {
  const sorted = [...channels].sort((a, b) => b.revenue - a.revenue);
  const max = Math.max(...sorted.map((c) => c.revenue), 1);

  return (
    <Card>
      <div className={styles.title}>Vendas por Canal</div>
      {sorted.length === 0 ? (
        <div className={styles.empty}>Nenhum dado neste período.</div>
      ) : (
        <div className={styles.list}>
          {sorted.map((channel, index) => (
            <div key={channel.channel} className={styles.row}>
              <div className={styles.line}>
                <span className={styles.label}>{channel.channel}</span>
                <div className={styles.track}>
                  <div
                    className={`crm-animate-width ${styles.bar}`}
                    style={{
                      background: PALETTE[index % PALETTE.length],
                      width: `${(channel.revenue / max) * 100}%`,
                    }}
                  />
                </div>
                <span className={styles.value}>{fmtBRL(channel.revenue)}</span>
              </div>
              <div className={styles.meta}>{channel.ordersCount} pedidos</div>
            </div>
          ))}
        </div>
      )}
    </Card>
  );
};

export default ChannelBars;
