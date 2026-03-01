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
        background: "rgba(2, 6, 23, 0.75)",
        zIndex: 100,
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
      }}
    >
      <div
        style={{
          background: "rgba(255, 255, 255, 0.04)",
          border: "1px solid rgba(255, 255, 255, 0.12)",
          borderRadius: 20,
          padding: 32,
          width: 580,
          maxHeight: "85vh",
          overflowY: "auto",
          boxShadow: "0 24px 60px rgba(0, 0, 0, 0.5)",
          backdropFilter: "blur(10px)",
        }}
      >
        <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 20 }}>
          <h3
            style={{
              color: "#F8FAFC",
              fontFamily: "'Inter', sans-serif",
              fontSize: 22,
              fontWeight: 600,
            }}
          >
            Pedido #{order.id}
          </h3>
          <button
            onClick={onClose}
            style={{ background: "none", border: "none", color: "#94A3B8", fontSize: 22, cursor: "pointer" }}
          >
            ×
          </button>
        </div>

        <div style={{ display: "flex", gap: 8, marginBottom: 20, flexWrap: "wrap" }}>
          <Badge status={order.status} />
          <span
            style={{
              background: "rgba(255, 255, 255, 0.06)",
              color: "#CBD5E1",
              borderRadius: 999,
              padding: "4px 10px",
              fontSize: 11,
            }}
          >
            {order.channel}
          </span>
          <span
            style={{
              background: "rgba(255, 255, 255, 0.06)",
              color: "#CBD5E1",
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
              background: "rgba(96, 165, 250, 0.16)",
              border: "1px solid rgba(96, 165, 250, 0.35)",
              borderRadius: 12,
              padding: "10px 14px",
              marginBottom: 16,
              color: "#93C5FD",
              fontSize: 13,
            }}
          >
            📦 Próxima postagem sugerida: <strong>{nextShip}</strong>
          </div>
        )}

        {order.status === "Separação/Fornecedor" && (
          <div
            style={{
              background: "rgba(248, 113, 113, 0.12)",
              border: "1px solid rgba(248, 113, 113, 0.35)",
              borderRadius: 12,
              padding: "10px 14px",
              marginBottom: 16,
              color: "#FCA5A5",
              fontSize: 13,
            }}
          >
            ⚠️ Produto com fornecedor — tarefa: buscar/comprar antes do envio
          </div>
        )}

        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 14, marginBottom: 20 }}>
          <div>
            <div style={{ color: "#64748B", fontSize: 11, marginBottom: 4, textTransform: "uppercase" }}>
              Cliente
            </div>
            <div style={{ color: "#F8FAFC", fontWeight: 600 }}>{customer?.name}</div>
            <div style={{ color: "#94A3B8", fontSize: 12 }}>{customer?.phone}</div>
            <div style={{ color: "#94A3B8", fontSize: 12 }}>{customer?.instagram}</div>
          </div>
          <div>
            <div style={{ color: "#64748B", fontSize: 11, marginBottom: 4, textTransform: "uppercase" }}>
              Produto
            </div>
            <div style={{ color: "#F8FAFC", fontWeight: 600 }}>{order.productName}</div>
            <div style={{ color: "#94A3B8", fontSize: 12 }}>Pagamento: {order.paymentMethod}</div>
            <div style={{ color: "#94A3B8", fontSize: 12 }}>Envio: {order.shippingMethod}</div>
          </div>
        </div>

        <div
          style={{
            background: "rgba(255, 255, 255, 0.03)",
            borderRadius: 14,
            padding: 16,
            marginBottom: 20,
            border: "1px solid rgba(255, 255, 255, 0.06)",
          }}
        >
          <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 10 }}>
            {[
              { l: "Venda", v: fmtBRL(order.salePrice), c: "#F8FAFC" },
              { l: "Desconto", v: fmtBRL(order.discount), c: "#94A3B8" },
              { l: "Frete", v: fmtBRL(order.freight), c: "#94A3B8" },
              { l: "Taxa Canal", v: fmtBRL(order.channelFee), c: "#94A3B8" },
              { l: "Custo Produto", v: fmtBRL(order.cost), c: "#94A3B8" },
              { l: "Lucro", v: fmtBRL(profit), c: profit > 0 ? "#38BDF8" : "#F87171" },
            ].map((item) => (
              <div key={item.l}>
                <div style={{ color: "#64748B", fontSize: 10, textTransform: "uppercase" }}>{item.l}</div>
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
              borderTop: "1px solid rgba(255, 255, 255, 0.08)",
              color: "#94A3B8",
              fontSize: 12,
            }}
          >
            Margem: <span style={{ color: "#93C5FD", fontWeight: 600 }}>{margin}%</span>
          </div>
        </div>

        {order.trackingCode && (
          <div style={{ color: "#94A3B8", fontSize: 13, marginBottom: 16 }}>
            🚚 Rastreio:{" "}
            <span style={{ color: "#93C5FD", fontWeight: 600 }}>{order.trackingCode}</span>
          </div>
        )}

        {order.notes && (
          <div
            style={{
              background: "rgba(255, 255, 255, 0.03)",
              borderRadius: 12,
              padding: 12,
              color: "#94A3B8",
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
