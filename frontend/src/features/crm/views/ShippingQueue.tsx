import React from "react";
import { fmtBRL, nextShippingDay } from "../helpers";
import { Customer, Order, ProductReturn } from "../types";
import { RETURN_TYPE_COLORS } from "../data/mock";
import { Card } from "../ui/Primitives";
import styles from "./ShippingQueue.module.css";

const TYPE_LABELS: Record<string, string> = {
  garantia: "Garantia",
  troca: "Troca",
  devolucao: "Devolução",
};

type Props = {
  orders: Order[];
  customers: Customer[];
  pendingReturns?: ProductReturn[];
};

const ShippingQueue: React.FC<Props> = ({ orders, customers, pendingReturns = [] }) => {
  const today = new Date();
  const dayName = [
    "Domingo",
    "Segunda",
    "Terça",
    "Quarta",
    "Quinta",
    "Sexta",
    "Sábado",
  ][today.getDay()];
  const shippingDays = [1, 3, 5];
  const isShippingDay = shippingDays.includes(today.getDay());

  const readyOrders = orders.filter((o) => o.status === "Pronto para Envio");

  return (
    <div>
      <h2 className={styles.title}>Fila de Envios</h2>
      <div className={styles.subtitle}>
        Hoje é {dayName} —{" "}
        {isShippingDay ? (
          <span className={styles.subtitleHighlight}>✅ Dia de postagem!</span>
        ) : (
          <span className={styles.subtitleMuted}>
            ⚠️ Próxima postagem: {nextShippingDay(new Date().toISOString().slice(0, 10))}
          </span>
        )}
      </div>

      {readyOrders.length === 0 ? (
        <Card>
          <div className={styles.empty}>Nenhum pedido pronto para envio</div>
        </Card>
      ) : (
        <div className={styles.list}>
          {readyOrders.map((o) => {
            const customer = customers.find((c) => c.id === o.customerId);
            return (
              <Card key={o.id} className={styles.item}>
                <div className={styles.iconBox}>📦</div>
                <div className={styles.info}>
                  <div className={styles.infoTitle}>
                    #{o.id} — {o.productName}
                  </div>
                  <div className={styles.infoMeta}>
                    {customer?.name} · {o.itemsCount} item(ns) · {o.shippingMethod} · {o.channel}
                  </div>
                </div>
                <div className={styles.right}>
                  <div className={styles.rightTitle}>
                    Postar em: {nextShippingDay(new Date().toISOString().slice(0, 10))}
                  </div>
                  <div className={styles.rightSub}>{fmtBRL(o.freight)} frete</div>
                </div>
                <div className={styles.check} title="Marcar como postado" />
              </Card>
            );
          })}
        </div>
      )}

      {pendingReturns.length > 0 && (
        <div className={styles.returnsSection}>
          <h3 className={styles.returnsTitle}>Reenvios — Garantias/Trocas</h3>
          <div className={styles.list}>
            {pendingReturns.map((r) => {
              const typeColor = RETURN_TYPE_COLORS[r.type] ?? "var(--crm-text-soft)";
              const firstItem = r.items[0];
              return (
                <Card key={`return-${r.id}`} className={styles.item}>
                  <div className={styles.iconBox}>🔄</div>
                  <div className={styles.info}>
                    <div className={styles.infoTitle}>
                      Garantia #{r.id} — {firstItem?.productName ?? "—"}
                      {r.items.length > 1 ? ` +${r.items.length - 1}` : ""}
                    </div>
                    <div className={styles.infoMeta}>
                      {r.customerName} · {r.customerPhone}
                      <span
                        className={styles.returnTypeBadge}
                        style={{ background: `${typeColor}22`, color: typeColor, borderColor: `${typeColor}44` }}
                      >
                        {TYPE_LABELS[r.type] ?? r.type}
                      </span>
                    </div>
                  </div>
                  <div className={styles.right}>
                    <div className={styles.rightTitle}>
                      {r.returnTrackingCode ? `Rastreio: ${r.returnTrackingCode}` : "Sem rastreio"}
                    </div>
                    <div className={styles.rightSub}>Custo frete: {fmtBRL(r.freightCostOut)}</div>
                  </div>
                </Card>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
};

export default ShippingQueue;
