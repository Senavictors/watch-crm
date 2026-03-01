import React from "react";
import { fmtBRL, nextShippingDay } from "../helpers";
import { Customer, Order } from "../types";
import { Card } from "../ui/Primitives";

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
      <h2
        style={{
          color: "#F8FAFC",
          fontFamily: "'Inter', sans-serif",
          fontSize: 26,
          marginBottom: 8,
          fontWeight: 600,
        }}
      >
        Fila de Envios
      </h2>
      <div style={{ color: "#94A3B8", fontSize: 13, marginBottom: 20 }}>
        Hoje é {dayName} —{" "}
        {isShippingDay ? (
          <span style={{ color: "#60A5FA", fontWeight: 600 }}>✅ Dia de postagem!</span>
        ) : (
          <span style={{ color: "#64748B" }}>
            ⚠️ Próxima postagem: {nextShippingDay(new Date().toISOString().slice(0, 10))}
          </span>
        )}
      </div>

      {readyOrders.length === 0 ? (
        <Card>
          <div style={{ textAlign: "center", color: "#64748B", padding: 30 }}>
            Nenhum pedido pronto para envio
          </div>
        </Card>
      ) : (
        <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
          {readyOrders.map((o) => {
            const customer = customers.find((c) => c.id === o.customerId);
            return (
              <Card
                key={o.id}
                style={{ display: "flex", alignItems: "center", gap: 16, padding: 16 }}
              >
                <div
                  style={{
                    width: 40,
                    height: 40,
                    borderRadius: 8,
                    background: "rgba(96, 165, 250, 0.16)",
                    border: "1px solid rgba(96, 165, 250, 0.35)",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    color: "#93C5FD",
                    fontSize: 18,
                    flexShrink: 0,
                  }}
                >
                  📦
                </div>
                <div style={{ flex: 1 }}>
                  <div style={{ color: "#F8FAFC", fontWeight: 600 }}>
                    #{o.id} — {o.productName}
                  </div>
                  <div style={{ color: "#94A3B8", fontSize: 12 }}>
                    {customer?.name} · {o.shippingMethod} · {o.channel}
                  </div>
                </div>
                <div style={{ textAlign: "right" }}>
                  <div style={{ color: "#60A5FA", fontSize: 12, fontWeight: 600 }}>
                    Postar em: {nextShippingDay(new Date().toISOString().slice(0, 10))}
                  </div>
                  <div style={{ color: "#64748B", fontSize: 11 }}>{fmtBRL(o.freight)} frete</div>
                </div>
                <div
                  style={{
                    width: 20,
                    height: 20,
                    borderRadius: 4,
                    border: "2px solid #60A5FA",
                    cursor: "pointer",
                    flexShrink: 0,
                  }}
                  title="Marcar como postado"
                />
              </Card>
            );
          })}
        </div>
      )}
    </div>
  );
};

export default ShippingQueue;
