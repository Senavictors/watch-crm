"use client";
import React, { useState } from "react";
import { Btn, Input, Select } from "../ui/Primitives";
import { CHANNELS, PAYMENT_METHODS, SHIPPING_METHODS, SELLERS } from "../data/mock";
import { Channel, Customer, Order, PaymentMethod, Product, Seller, ShippingMethod } from "../types";
import { fmtBRL } from "../helpers";

type Props = {
  products: Product[];
  customers: Customer[];
  onSave: (order: Omit<Order, "id">) => void;
  onClose: () => void;
};

const NewOrderForm: React.FC<Props> = ({ products, customers, onSave, onClose }) => {
  const [form, setForm] = useState<{
    customerId: string;
    channel: Channel;
    seller: Seller;
    productId: string;
    salePrice: string;
    discount: number;
    freight: number;
    channelFee: number;
    paymentMethod: PaymentMethod;
    shippingMethod: ShippingMethod;
    notes: string;
  }>({
    customerId: "",
    channel: CHANNELS[0],
    seller: SELLERS[0] as Seller,
    productId: "",
    salePrice: "",
    discount: 0,
    freight: 0,
    channelFee: 0,
    paymentMethod: PAYMENT_METHODS[0],
    shippingMethod: SHIPPING_METHODS[0],
    notes: "",
  });

  const selectedProduct = products.find((p) => p.id === Number(form.productId));

  function set<K extends keyof typeof form>(k: K, v: (typeof form)[K]) {
    setForm((f) => ({ ...f, [k]: v }));
  }

  function handleProductChange(id: string) {
    const p = products.find((pr) => pr.id === Number(id));
    set("productId", id);
    if (p) set("salePrice", String(p.price));
  }

  function handleSubmit() {
    if (!form.customerId || !form.productId || !form.salePrice) {
      alert("Preencha cliente, produto e valor.");
      return;
    }
    const product = products.find((p) => p.id === Number(form.productId))!;
    const brandLabel = product.brand || "—";
    const modelLabel = product.model || "—";
    onSave({
      customerId: Number(form.customerId),
      channel: form.channel,
      seller: form.seller,
      productId: product.id,
      productName: `${brandLabel} ${modelLabel}`,
      salePrice: Number(form.salePrice),
      cost: product.cost,
      discount: Number(form.discount),
      freight: Number(form.freight),
      channelFee: Number(form.channelFee),
      paymentMethod: form.paymentMethod,
      shippingMethod: form.shippingMethod,
      trackingCode: "",
      saleDate: new Date().toISOString().slice(0, 10),
      shippedDate: "",
      notes: form.notes,
      status: "Novo",
    });
  }

  const estProfit = selectedProduct
    ? Number(form.salePrice || 0) -
      Number(form.discount || 0) -
      selectedProduct.cost -
      Number(form.channelFee || 0)
    : 0;

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
          width: 540,
          maxHeight: "90vh",
          overflowY: "auto",
          boxShadow: "0 24px 60px rgba(15, 23, 42, 0.35)",
          backdropFilter: "blur(10px)",
        }}
      >
        <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 20 }}>
          <h3 style={{ color: "var(--crm-text)", fontFamily: "'Inter', sans-serif", fontSize: 22, fontWeight: 600 }}>
            Novo Pedido
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

        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10 }}>
          <div style={{ gridColumn: "1 / -1" }}>
            <Select label="Cliente" value={form.customerId} onChange={(e) => set("customerId", e.target.value)}>
              <option value="">Selecionar cliente...</option>
              {customers.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </Select>
          </div>
          <Select
            label="Canal"
            value={form.channel}
            onChange={(e) => set("channel", e.target.value as Channel)}
          >
            {CHANNELS.map((c) => (
              <option key={c}>{c}</option>
            ))}
          </Select>
          <Select
            label="Vendedor"
            value={form.seller}
            onChange={(e) => set("seller", e.target.value as Seller)}
          >
            {SELLERS.map((s) => (
              <option key={s}>{s}</option>
            ))}
          </Select>
          <div style={{ gridColumn: "1 / -1" }}>
            <Select label="Produto" value={form.productId} onChange={(e) => handleProductChange(e.target.value)}>
              <option value="">Selecionar produto...</option>
              {products.map((p) => (
                <option key={p.id} value={p.id}>
                  {(p.brand || "—")} {(p.model || "—")} — {fmtBRL(p.price)}{" "}
                  {p.stock === "SUPPLIER" ? "⚠️ Fornecedor" : "✅ Estoque"}
                </option>
              ))}
            </Select>
          </div>
          <Input
            label="Preço de Venda (R$)"
            type="number"
            value={form.salePrice}
            onChange={(e) => set("salePrice", e.target.value)}
          />
          <Input
            label="Desconto (R$)"
            type="number"
            value={form.discount}
            onChange={(e) => set("discount", Number(e.target.value))}
          />
          <Input
            label="Frete (R$)"
            type="number"
            value={form.freight}
            onChange={(e) => set("freight", Number(e.target.value))}
          />
          <Input
            label="Taxa do Canal (R$)"
            type="number"
            value={form.channelFee}
            onChange={(e) => set("channelFee", Number(e.target.value))}
          />
          <Select
            label="Pagamento"
            value={form.paymentMethod}
            onChange={(e) => set("paymentMethod", e.target.value as PaymentMethod)}
          >
            {PAYMENT_METHODS.map((p) => (
              <option key={p}>{p}</option>
            ))}
          </Select>
          <Select
            label="Envio"
            value={form.shippingMethod}
            onChange={(e) => set("shippingMethod", e.target.value as ShippingMethod)}
          >
            {SHIPPING_METHODS.map((s) => (
              <option key={s}>{s}</option>
            ))}
          </Select>
          <div style={{ gridColumn: "1 / -1" }}>
            <label
              style={{
                color: "var(--crm-text-muted)",
                fontSize: 11,
                marginBottom: 5,
                display: "block",
                textTransform: "uppercase",
                letterSpacing: 0.5,
              }}
            >
              Observações
            </label>
            <textarea
              value={form.notes}
              onChange={(e) => set("notes", e.target.value)}
              style={{
                width: "100%",
                background: "var(--crm-input-bg)",
                border: "1px solid var(--crm-input-border)",
                borderRadius: 12,
                color: "var(--crm-input-text)",
                padding: "8px 12px",
                fontSize: 14,
                outline: "none",
                resize: "vertical",
                minHeight: 60,
                boxSizing: "border-box",
              }}
            />
          </div>
        </div>

        {selectedProduct && (
          <div
            style={{
              background: "var(--crm-input-bg)",
              borderRadius: 14,
              padding: 12,
              marginBottom: 16,
              display: "flex",
              gap: 20,
              border: "1px solid var(--crm-table-border)",
            }}
          >
            <div>
              <span style={{ color: "var(--crm-text-soft)", fontSize: 11 }}>CUSTO</span>
              <br />
              <span style={{ color: "var(--crm-text-muted)", fontWeight: 700 }}>
                {fmtBRL(selectedProduct.cost)}
              </span>
            </div>
            <div>
              <span style={{ color: "var(--crm-text-soft)", fontSize: 11 }}>LUCRO EST.</span>
              <br />
              <span
                style={{
                  color: estProfit > 0 ? "var(--crm-success)" : "var(--crm-danger)",
                  fontWeight: 700,
                }}
              >
                {fmtBRL(estProfit)}
              </span>
            </div>
            <div>
              <span style={{ color: "var(--crm-text-soft)", fontSize: 11 }}>ORIGEM</span>
              <br />
              <span
                style={{
                  color:
                    selectedProduct.stock === "IN_STOCK"
                      ? "var(--crm-pill-primary-text)"
                      : "var(--crm-text-muted)",
                  fontWeight: 700,
                }}
              >
                {selectedProduct.stock === "IN_STOCK" ? "✅ Em estoque" : "⚠️ Fornecedor"}
              </span>
            </div>
          </div>
        )}

        <div style={{ display: "flex", gap: 8, justifyContent: "flex-end" }}>
          <Btn onClick={onClose} variant="secondary">
            Cancelar
          </Btn>
          <Btn onClick={handleSubmit} variant="primary">
            Salvar Pedido
          </Btn>
        </div>
      </div>
    </div>
  );
};

export default NewOrderForm;
