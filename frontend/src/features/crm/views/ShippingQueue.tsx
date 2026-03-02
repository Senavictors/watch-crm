import React from "react";
import { fmtBRL, nextShippingDay } from "../helpers";
import { Customer, Order } from "../types";
import { Card } from "../ui/Primitives";
import styles from "./ShippingQueue.module.css";

type Props = {
  orders: Order[];
  customers: Customer[];
};

const ShippingQueue: React.FC<Props> = ({ orders, customers }) => {
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
                    {customer?.name} · {o.shippingMethod} · {o.channel}
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
    </div>
  );
};

export default ShippingQueue;
