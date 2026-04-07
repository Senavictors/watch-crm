"use client";
import React, { useState } from "react";
import { Btn, Input, Select } from "../ui/Primitives";
import { Channel, Customer, OrderInput, OrderMetadata, PaymentMethod, Product, ShippingMethod } from "../types";
import { fmtBRL } from "../helpers";
import modalStyles from "../components/Modal/Modal.module.css";
import styles from "./NewOrderForm.module.css";

type Props = {
  products: Product[];
  customers: Customer[];
  metadata: OrderMetadata;
  onSave: (order: OrderInput) => void;
  onClose: () => void;
  onToast: (message: string, variant?: "success" | "error") => void;
};

const NewOrderForm: React.FC<Props> = ({ products, customers, metadata, onSave, onClose, onToast }) => {
  const [form, setForm] = useState<{
    customerId: string;
    sellerUserId: string;
    channel: Channel;
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
    sellerUserId: metadata.assignableSellers[0] ? String(metadata.assignableSellers[0].id) : "",
    channel: metadata.channels[0] ?? "",
    productId: "",
    salePrice: "",
    discount: 0,
    freight: 0,
    channelFee: 0,
    paymentMethod: metadata.paymentMethods[0] ?? "",
    shippingMethod: metadata.shippingMethods[0] ?? "",
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
    if (!form.customerId || !form.sellerUserId || !form.productId || !form.salePrice) {
      onToast("Preencha cliente, vendedor, produto e valor.", "error");
      return;
    }
    const product = products.find((p) => p.id === Number(form.productId))!;
    onSave({
      customerId: Number(form.customerId),
      sellerUserId: Number(form.sellerUserId),
      channel: form.channel,
      productId: product.id,
      salePrice: Number(form.salePrice),
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
    <div className={modalStyles.overlay}>
      <div className={`${modalStyles.modal} ${styles.modal}`}>
        <div className={modalStyles.header}>
          <h3 className={modalStyles.title}>Novo Pedido</h3>
          <button onClick={onClose} className={modalStyles.close}>
            ×
          </button>
        </div>

        <div className={modalStyles.formGridTwo}>
          <div className={styles.fullSpan}>
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
            {metadata.channels.map((c) => (
              <option key={c}>{c}</option>
            ))}
          </Select>
          <Select
            label="Vendedor"
            value={form.sellerUserId}
            onChange={(e) => set("sellerUserId", e.target.value)}
          >
            {metadata.assignableSellers.map((seller) => (
              <option key={seller.id} value={seller.id}>
                {seller.name}
              </option>
            ))}
          </Select>
          <div className={styles.fullSpan}>
            <Select label="Produto" value={form.productId} onChange={(e) => handleProductChange(e.target.value)}>
              <option value="">Selecionar produto...</option>
              {products.map((p) => (
                <option key={p.id} value={p.id}>
                  {(p.brand || "—")} {(p.model || "—")}
                  {p.modelQualityName ? ` · ${p.modelQualityName}` : ""} —{" "}
                  {fmtBRL(p.price)}{" "}
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
            {metadata.paymentMethods.map((p) => (
              <option key={p}>{p}</option>
            ))}
          </Select>
          <Select
            label="Envio"
            value={form.shippingMethod}
            onChange={(e) => set("shippingMethod", e.target.value as ShippingMethod)}
          >
            {metadata.shippingMethods.map((s) => (
              <option key={s}>{s}</option>
            ))}
          </Select>
          <div className={styles.fullSpan}>
            <label className={styles.label}>Observações</label>
            <textarea value={form.notes} onChange={(e) => set("notes", e.target.value)} className={modalStyles.notes} />
          </div>
        </div>

        {selectedProduct && (
          <div className={modalStyles.summaryStrip}>
            <div>
              <span className={modalStyles.summaryBlockLabel}>CUSTO</span>
              <br />
              <span className={styles.summaryValueMuted}>{fmtBRL(selectedProduct.cost)}</span>
            </div>
            <div>
              <span className={modalStyles.summaryBlockLabel}>LUCRO EST.</span>
              <br />
              <span
                style={{
                  color: estProfit > 0 ? "var(--crm-success)" : "var(--crm-danger)",
                }}
                className={styles.summaryValueAccent}
              >
                {fmtBRL(estProfit)}
              </span>
            </div>
            <div>
              <span className={modalStyles.summaryBlockLabel}>ORIGEM</span>
              <br />
              <span
                style={{
                  color:
                    selectedProduct.stock === "IN_STOCK"
                      ? "var(--crm-pill-primary-text)"
                      : "var(--crm-text-muted)",
                }}
                className={styles.summaryValueAccent}
              >
                {selectedProduct.stock === "IN_STOCK" ? "✅ Em estoque" : "⚠️ Fornecedor"}
              </span>
            </div>
          </div>
        )}

        <div className={styles.actions}>
          <Btn onClick={onClose} variant="secondary" className={styles.actionButton}>
            Cancelar
          </Btn>
          <Btn onClick={handleSubmit} variant="primary" className={styles.actionButton}>
            Salvar Pedido
          </Btn>
        </div>
      </div>
    </div>
  );
};

export default NewOrderForm;
