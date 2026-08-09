import React from "react";
import { Badge, Btn } from "../ui/Primitives";
import { Order } from "../types";
import { calcMargin, calcProfit, fmtBRL, fmtDate, fmtDateTime } from "../helpers";
import ModalBackdrop from "../components/Modal/ModalBackdrop";
import modalStyles from "../components/Modal/Modal.module.css";
import styles from "./OrderDetail.module.css";

type Props = {
  order: Order;
  canCreateReturn?: boolean;
  onClose: () => void;
  onCreateReturn?: (order: Order) => void;
};

const OrderDetail: React.FC<Props> = ({ order, canCreateReturn = false, onClose, onCreateReturn }) => {
  const profit = calcProfit(order);
  const margin = calcMargin(order);
  const nextPostingLabel = order.nextPostingDate ? fmtDate(order.nextPostingDate) : null;

  return (
    <ModalBackdrop onClose={onClose}>
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

        {nextPostingLabel && (
          <div className={order.isLate ? styles.alertDanger : styles.alertInfo}>
            📦 Próxima postagem sugerida: <strong>{nextPostingLabel}</strong>
            {order.isLate && <span className={styles.lateBadge}>Atrasado</span>}
          </div>
        )}

        {order.status === "Separação/Fornecedor" && (
          <div className={styles.alertDanger}>
            ⚠️ Produto com fornecedor — tarefa: buscar/comprar antes do envio
          </div>
        )}

        {order.paidAt && (
          <div className={styles.alertInfo}>
            ✅ Pago em <strong>{fmtDateTime(order.paidAt)}</strong>
            {order.paidByUserName ? ` por ${order.paidByUserName}` : ""}
          </div>
        )}

        <div className={styles.infoGrid}>
          <div>
            <div className={styles.infoLabel}>Cliente</div>
            <div className={styles.infoValue}>{order.customerName ?? `Cliente #${order.customerId}`}</div>
          </div>
          <div>
            <div className={styles.infoLabel}>Resumo do Pedido</div>
            <div className={styles.infoValue}>{order.productName}</div>
            <div className={styles.infoMuted}>{order.itemsCount} item(ns)</div>
            <div className={styles.infoMuted}>Pagamento: {order.paymentMethod}</div>
            {!order.paidAt && order.paymentExpiresAt && (
              <div className={styles.infoMuted}>Vence em {fmtDateTime(order.paymentExpiresAt)}</div>
            )}
            <div className={styles.infoMuted}>Envio: {order.shippingMethod}</div>
          </div>
        </div>

        <div className={styles.itemsSection}>
          <div className={styles.infoLabel}>Itens</div>
          <div className={styles.itemsList}>
            {order.items.map((item, index) => (
              <div key={`${item.productId}-${index}`} className={styles.itemCard}>
                <div>
                  <div className={styles.itemTitle}>{item.productName}</div>
                  <div className={styles.infoMuted}>
                    {item.productType}
                    {item.qualityName ? ` · ${item.qualityName}` : ""}
                  </div>
                </div>
                <div className={styles.itemNumbers}>
                  <span>{item.quantity} un.</span>
                  <span>{fmtBRL(item.linePrice - item.lineDiscount)}</span>
                </div>
              </div>
            ))}
          </div>
        </div>

        <div className={styles.finance}>
          <div className={styles.financeGrid}>
            {[
              { l: "Venda", v: fmtBRL(order.salePrice), c: "var(--crm-text)" },
              { l: "Desconto", v: fmtBRL(order.discount), c: "var(--crm-text-muted)" },
              { l: "Frete", v: fmtBRL(order.freight), c: "var(--crm-text-muted)" },
              { l: "Taxa Canal", v: fmtBRL(order.channelFee), c: "var(--crm-text-muted)" },
              // TASK-013: "Custo Produto"/"Lucro" só aparecem quando a API
              // retornou `cost` (dashboard.financial.view) — profit === null
              // quer dizer "sem permissão", não "lucro zero".
              ...(profit !== null
                ? [
                    { l: "Custo Produto", v: fmtBRL(order.cost), c: "var(--crm-text-muted)" },
                    { l: "Lucro", v: fmtBRL(profit), c: profit > 0 ? "var(--crm-success)" : "var(--crm-danger)" },
                  ]
                : []),
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
          {margin !== null && (
            <div className={styles.financeDivider}>
              Margem: <span className={styles.accent}>{margin}%</span>
            </div>
          )}
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
          {canCreateReturn && onCreateReturn && (
            <Btn onClick={() => onCreateReturn(order)} variant="secondary">
              Registrar Garantia/Troca
            </Btn>
          )}
        </div>
      </div>
    </ModalBackdrop>
  );
};

export default OrderDetail;
