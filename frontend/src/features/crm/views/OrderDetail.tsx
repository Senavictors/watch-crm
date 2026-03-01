import React from "react";
import { Badge, Btn } from "../ui/Primitives";
import { Customer, Order } from "../types";
import { calcMargin, calcProfit, fmtBRL, nextShippingDay } from "../helpers";

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
    <div
      style={{
        position: "fixed",
        inset: 0,
        background: "var(--crm-overlay)",
        zIndex: 100,
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
      }}
    >
      <div
        style={{
          background: "var(--crm-modal-bg)",
          border: "1px solid var(--crm-modal-border)",
          borderRadius: 20,
          padding: 32,
          width: 580,
          maxHeight: "85vh",
          overflowY: "auto",
          boxShadow: "0 24px 60px rgba(15, 23, 42, 0.35)",
          backdropFilter: "blur(10px)",
        }}
      >
        <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 20 }}>
          <h3
            style={{
              color: "var(--crm-text)",
              fontFamily: "'Inter', sans-serif",
              fontSize: 22,
              fontWeight: 600,
            }}
          >
            Pedido #{order.id}
          </h3>
          <button
            onClick={onClose}
            style={{
              background: "none",
              border: "none",
              color: "var(--crm-text-muted)",
              fontSize: 22,
              cursor: "pointer",
            }}
          >
            ×
          </button>
        </div>

        <div style={{ display: "flex", gap: 8, marginBottom: 20, flexWrap: "wrap" }}>
          <Badge status={order.status} />
          <span
            style={{
              background: "var(--crm-button-secondary-bg)",
              color: "var(--crm-text)",
              borderRadius: 999,
              padding: "4px 10px",
              fontSize: 11,
            }}
          >
            {order.channel}
          </span>
          <span
            style={{
              background: "var(--crm-button-secondary-bg)",
              color: "var(--crm-text)",
              borderRadius: 999,
              padding: "4px 10px",
              fontSize: 11,
            }}
          >
            {order.seller}
          </span>
        </div>

        {nextShip && (
          <div
            style={{
              background: "var(--crm-primary-soft)",
              border: "1px solid var(--crm-primary)",
              borderRadius: 12,
              padding: "10px 14px",
              marginBottom: 16,
              color: "var(--crm-accent)",
              fontSize: 13,
            }}
          >
            📦 Próxima postagem sugerida: <strong>{nextShip}</strong>
          </div>
        )}

        {order.status === "Separação/Fornecedor" && (
          <div
            style={{
              background: "rgba(239, 68, 68, 0.12)",
              border: "1px solid var(--crm-danger)",
              borderRadius: 12,
              padding: "10px 14px",
              marginBottom: 16,
              color: "var(--crm-danger)",
              fontSize: 13,
            }}
          >
            ⚠️ Produto com fornecedor — tarefa: buscar/comprar antes do envio
          </div>
        )}

        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 14, marginBottom: 20 }}>
          <div>
            <div style={{ color: "var(--crm-text-soft)", fontSize: 11, marginBottom: 4, textTransform: "uppercase" }}>
              Cliente
            </div>
            <div style={{ color: "var(--crm-text)", fontWeight: 600 }}>{customer?.name}</div>
            <div style={{ color: "var(--crm-text-muted)", fontSize: 12 }}>{customer?.phone}</div>
            <div style={{ color: "var(--crm-text-muted)", fontSize: 12 }}>{customer?.instagram}</div>
          </div>
          <div>
            <div style={{ color: "var(--crm-text-soft)", fontSize: 11, marginBottom: 4, textTransform: "uppercase" }}>
              Produto
            </div>
            <div style={{ color: "var(--crm-text)", fontWeight: 600 }}>{order.productName}</div>
            <div style={{ color: "var(--crm-text-muted)", fontSize: 12 }}>Pagamento: {order.paymentMethod}</div>
            <div style={{ color: "var(--crm-text-muted)", fontSize: 12 }}>Envio: {order.shippingMethod}</div>
          </div>
        </div>

        <div
          style={{
            background: "var(--crm-input-bg)",
            borderRadius: 14,
            padding: 16,
            marginBottom: 20,
            border: "1px solid var(--crm-table-border)",
          }}
        >
          <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 10 }}>
            {[
              { l: "Venda", v: fmtBRL(order.salePrice), c: "var(--crm-text)" },
              { l: "Desconto", v: fmtBRL(order.discount), c: "var(--crm-text-muted)" },
              { l: "Frete", v: fmtBRL(order.freight), c: "var(--crm-text-muted)" },
              { l: "Taxa Canal", v: fmtBRL(order.channelFee), c: "var(--crm-text-muted)" },
              { l: "Custo Produto", v: fmtBRL(order.cost), c: "var(--crm-text-muted)" },
              { l: "Lucro", v: fmtBRL(profit), c: profit > 0 ? "var(--crm-success)" : "var(--crm-danger)" },
            ].map((item) => (
              <div key={item.l}>
                <div style={{ color: "var(--crm-text-soft)", fontSize: 10, textTransform: "uppercase" }}>
                  {item.l}
                </div>
                <div
                  style={{
                    color: item.c,
                    fontWeight: 700,
                    fontFamily: "'Inter', sans-serif",
                    fontSize: 15,
                    fontVariantNumeric: "tabular-nums",
                  }}
                >
                  {item.v}
                </div>
              </div>
            ))}
          </div>
          <div
            style={{
              marginTop: 12,
              paddingTop: 12,
              borderTop: "1px solid var(--crm-table-border)",
              color: "var(--crm-text-muted)",
              fontSize: 12,
            }}
          >
            Margem: <span style={{ color: "var(--crm-accent)", fontWeight: 600 }}>{margin}%</span>
          </div>
        </div>

        {order.trackingCode && (
          <div style={{ color: "var(--crm-text-muted)", fontSize: 13, marginBottom: 16 }}>
            🚚 Rastreio:{" "}
            <span style={{ color: "var(--crm-accent)", fontWeight: 600 }}>{order.trackingCode}</span>
          </div>
        )}

        {order.notes && (
          <div
            style={{
              background: "var(--crm-input-bg)",
              borderRadius: 12,
              padding: 12,
              color: "var(--crm-text-muted)",
              fontSize: 13,
              marginBottom: 16,
            }}
          >
            📝 {order.notes}
          </div>
        )}

        <div style={{ display: "flex", gap: 8, justifyContent: "flex-end" }}>
          <Btn onClick={onClose} variant="secondary">
            Fechar
          </Btn>
        </div>
      </div>
    </div>
  );
};

export default OrderDetail;
