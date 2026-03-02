import React from "react";
import { Badge, Btn } from "../ui/Primitives";
import { Customer, Order } from "../types";
import { calcMargin, calcProfit, fmtBRL, nextShippingDay } from "../helpers";
import modalStyles from "../components/Modal/Modal.module.css";
import styles from "./OrderDetail.module.css";

type Props = {
  order: Order;
  customers: Customer[];
  onClose: () => void;
};

const OrderDetail: React.FC<Props> = ({ order, customers, onClose }) => {
  const customer = customers.find((c) => c.id === order.customerId);
  const profit = calcProfit(order);
  const margin = calcMargin(order);
  const nextShip =
    order.status === "Pronto para Envio"
      ? nextShippingDay(new Date().toISOString().slice(0, 10))
      : null;

  return (
    <div className={modalStyles.overlay}>
      <div className={`${modalStyles.modal} ${styles.modal}`}>
        <div className={modalStyles.header}>
          <h3 className={modalStyles.title}>Pedido #{order.id}</h3>
          <button onClick={onClose} className={modalStyles.close}>
            ×
          </button>
        </div>

        <div className={styles.badges}>
          <Badge status={order.status} />
          <span className={styles.pill}>{order.channel}</span>
          <span className={styles.pill}>{order.seller}</span>
        </div>

        {nextShip && (
          <div className={styles.alertInfo}>📦 Próxima postagem sugerida: <strong>{nextShip}</strong></div>
        )}

        {order.status === "Separação/Fornecedor" && (
          <div className={styles.alertDanger}>
            ⚠️ Produto com fornecedor — tarefa: buscar/comprar antes do envio
          </div>
        )}

        <div className={styles.infoGrid}>
          <div>
            <div className={styles.infoLabel}>Cliente</div>
            <div className={styles.infoValue}>{customer?.name}</div>
            <div className={styles.infoMuted}>{customer?.phone}</div>
            <div className={styles.infoMuted}>{customer?.instagram}</div>
          </div>
          <div>
            <div className={styles.infoLabel}>Produto</div>
            <div className={styles.infoValue}>{order.productName}</div>
            <div className={styles.infoMuted}>Pagamento: {order.paymentMethod}</div>
            <div className={styles.infoMuted}>Envio: {order.shippingMethod}</div>
          </div>
        </div>

        <div className={styles.finance}>
          <div className={styles.financeGrid}>
            {[
              { l: "Venda", v: fmtBRL(order.salePrice), c: "var(--crm-text)" },
              { l: "Desconto", v: fmtBRL(order.discount), c: "var(--crm-text-muted)" },
              { l: "Frete", v: fmtBRL(order.freight), c: "var(--crm-text-muted)" },
              { l: "Taxa Canal", v: fmtBRL(order.channelFee), c: "var(--crm-text-muted)" },
              { l: "Custo Produto", v: fmtBRL(order.cost), c: "var(--crm-text-muted)" },
              { l: "Lucro", v: fmtBRL(profit), c: profit > 0 ? "var(--crm-success)" : "var(--crm-danger)" },
            ].map((item) => (
              <div key={item.l}>
                <div className={styles.financeLabel}>{item.l}</div>
                <div
                  style={{
                    color: item.c,
                  }}
                  className={styles.financeValue}
                >
                  {item.v}
                </div>
              </div>
            ))}
          </div>
          <div className={styles.financeDivider}>
            Margem: <span className={styles.accent}>{margin}%</span>
          </div>
        </div>

        {order.trackingCode && (
          <div className={styles.tracking}>
            🚚 Rastreio: <span className={styles.accent}>{order.trackingCode}</span>
          </div>
        )}

        {order.notes && (
          <div className={styles.notes}>📝 {order.notes}</div>
        )}

        <div className={styles.actions}>
          <Btn onClick={onClose} variant="secondary">
            Fechar
          </Btn>
        </div>
      </div>
    </div>
  );
};

export default OrderDetail;
