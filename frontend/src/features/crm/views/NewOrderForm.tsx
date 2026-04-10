"use client";
import React, { useMemo, useState } from "react";
import { Btn, Input, Select } from "../ui/Primitives";
import {
  Channel,
  Customer,
  OrderInput,
  OrderItemInput,
  OrderMetadata,
  PaymentMethod,
  Product,
  ShippingMethod,
} from "../types";
import { fmtBRL, productLabel, productTypeLabel } from "../helpers";
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

type ItemForm = {
  productId: string;
  quantity: string;
  unitPrice: string;
  unitDiscount: string;
};

const emptyItem = (): ItemForm => ({
  productId: "",
  quantity: "1",
  unitPrice: "",
  unitDiscount: "0",
});

const NewOrderForm: React.FC<Props> = ({ products, customers, metadata, onSave, onClose, onToast }) => {
  const [form, setForm] = useState<{
    customerId: string;
    sellerUserId: string;
    channel: Channel;
    items: ItemForm[];
    freight: number;
    channelFee: number;
    paymentMethod: PaymentMethod;
    shippingMethod: ShippingMethod;
    notes: string;
  }>({
    customerId: "",
    sellerUserId: metadata.assignableSellers[0] ? String(metadata.assignableSellers[0].id) : "",
    channel: metadata.channels[0] ?? "",
    items: [emptyItem()],
    freight: 0,
    channelFee: 0,
    paymentMethod: metadata.paymentMethods[0] ?? "",
    shippingMethod: metadata.shippingMethods[0] ?? "",
    notes: "",
  });

  function set<K extends keyof typeof form>(key: K, value: (typeof form)[K]) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  function updateItem(index: number, patch: Partial<ItemForm>) {
    setForm((current) => ({
      ...current,
      items: current.items.map((item, itemIndex) => (itemIndex === index ? { ...item, ...patch } : item)),
    }));
  }

  function addItem() {
    set("items", [...form.items, emptyItem()]);
  }

  function removeItem(index: number) {
    if (form.items.length === 1) {
      updateItem(index, emptyItem());
      return;
    }

    set(
      "items",
      form.items.filter((_, itemIndex) => itemIndex !== index)
    );
  }

  function handleProductChange(index: number, productId: string) {
    const product = products.find((entry) => entry.id === Number(productId));

    updateItem(index, {
      productId,
      unitPrice: product ? String(product.price) : "",
      unitDiscount: "0",
      quantity: form.items[index]?.quantity || "1",
    });
  }

  const selectedLines = useMemo(
    () =>
      form.items.map((item) => ({
        item,
        product: products.find((product) => product.id === Number(item.productId)) ?? null,
      })),
    [form.items, products]
  );

  const grossSale = selectedLines.reduce(
    (sum, line) => sum + Number(line.item.unitPrice || 0) * Number(line.item.quantity || 0),
    0
  );
  const totalDiscount = selectedLines.reduce(
    (sum, line) => sum + Number(line.item.unitDiscount || 0) * Number(line.item.quantity || 0),
    0
  );
  const totalCost = selectedLines.reduce(
    (sum, line) => sum + Number(line.product?.cost || 0) * Number(line.item.quantity || 0),
    0
  );
  const estProfit = grossSale - totalDiscount - totalCost - Number(form.channelFee || 0);
  const totalUnits = selectedLines.reduce((sum, line) => sum + Number(line.item.quantity || 0), 0);
  const hasSupplierItems = selectedLines.some((line) => line.product?.stock === "SUPPLIER");

  function handleSubmit() {
    const parsedItems: OrderItemInput[] = form.items
      .filter((item) => item.productId)
      .map((item) => ({
        productId: Number(item.productId),
        quantity: Math.max(1, Number(item.quantity || 1)),
        unitPrice: Number(item.unitPrice),
        unitDiscount: Number(item.unitDiscount || 0),
      }));

    if (!form.customerId || !form.sellerUserId || parsedItems.length === 0) {
      onToast("Preencha cliente, vendedor e pelo menos um item.", "error");
      return;
    }

    if (parsedItems.some((item) => !item.unitPrice || item.quantity < 1)) {
      onToast("Revise quantidade e preço unitário dos itens.", "error");
      return;
    }

    onSave({
      customerId: Number(form.customerId),
      sellerUserId: Number(form.sellerUserId),
      channel: form.channel,
      items: parsedItems,
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
              {customers.map((customer) => (
                <option key={customer.id} value={customer.id}>
                  {customer.name}
                </option>
              ))}
            </Select>
          </div>
          <Select label="Canal" value={form.channel} onChange={(e) => set("channel", e.target.value as Channel)}>
            {metadata.channels.map((channel) => (
              <option key={channel}>{channel}</option>
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
            <div className={styles.itemsHeader}>
              <div>
                <div className={styles.label}>Itens do Pedido</div>
                <div className={styles.itemsHint}>Misture relógios e caixas no mesmo pedido.</div>
              </div>
              <Btn onClick={addItem} variant="secondary" small>
                + Adicionar Item
              </Btn>
            </div>

            <div className={styles.itemsList}>
              {form.items.map((item, index) => {
                const selectedProduct = products.find((product) => product.id === Number(item.productId));

                return (
                  <div key={`${index}-${item.productId || "new"}`} className={styles.itemCard}>
                    <div className={styles.itemRow}>
                      <div className={styles.itemFieldWide}>
                        <Select
                          label={`Item ${index + 1}`}
                          value={item.productId}
                          onChange={(e) => handleProductChange(index, e.target.value)}
                        >
                          <option value="">Selecionar produto...</option>
                          {products.map((product) => (
                            <option key={product.id} value={product.id}>
                              {productLabel(product)} — {fmtBRL(product.price)}
                            </option>
                          ))}
                        </Select>
                      </div>
                      <div className={styles.itemField}>
                        <Input
                          label="Qtd."
                          type="number"
                          min={1}
                          value={item.quantity}
                          onChange={(e) => updateItem(index, { quantity: e.target.value })}
                        />
                      </div>
                      <div className={styles.itemField}>
                        <Input
                          label="Preço Unit."
                          type="number"
                          value={item.unitPrice}
                          onChange={(e) => updateItem(index, { unitPrice: e.target.value })}
                        />
                      </div>
                      <div className={styles.itemField}>
                        <Input
                          label="Desc. Unit."
                          type="number"
                          value={item.unitDiscount}
                          onChange={(e) => updateItem(index, { unitDiscount: e.target.value })}
                        />
                      </div>
                      <div className={styles.itemRemove}>
                        <Btn onClick={() => removeItem(index)} variant="secondary" small>
                          Remover
                        </Btn>
                      </div>
                    </div>

                    {selectedProduct && (
                      <div className={styles.itemMeta}>
                        <span>{productTypeLabel(selectedProduct.productType)}</span>
                        <span>{selectedProduct.stock === "IN_STOCK" ? "✅ Estoque" : "⚠️ Fornecedor"}</span>
                        <span>Custo: {fmtBRL(selectedProduct.cost)}</span>
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          </div>

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
            {metadata.paymentMethods.map((paymentMethod) => (
              <option key={paymentMethod}>{paymentMethod}</option>
            ))}
          </Select>
          <Select
            label="Envio"
            value={form.shippingMethod}
            onChange={(e) => set("shippingMethod", e.target.value as ShippingMethod)}
          >
            {metadata.shippingMethods.map((shippingMethod) => (
              <option key={shippingMethod}>{shippingMethod}</option>
            ))}
          </Select>
          <div className={styles.fullSpan}>
            <label className={styles.label}>Observações</label>
            <textarea value={form.notes} onChange={(e) => set("notes", e.target.value)} className={modalStyles.notes} />
          </div>
        </div>

        <div className={modalStyles.summaryStrip}>
          <div>
            <span className={modalStyles.summaryBlockLabel}>ITENS</span>
            <br />
            <span className={styles.summaryValueMuted}>{totalUnits || 0} un.</span>
          </div>
          <div>
            <span className={modalStyles.summaryBlockLabel}>VENDA BRUTA</span>
            <br />
            <span className={styles.summaryValueMuted}>{fmtBRL(grossSale)}</span>
          </div>
          <div>
            <span className={modalStyles.summaryBlockLabel}>DESCONTO</span>
            <br />
            <span className={styles.summaryValueMuted}>{fmtBRL(totalDiscount)}</span>
          </div>
          <div>
            <span className={modalStyles.summaryBlockLabel}>LUCRO EST.</span>
            <br />
            <span
              style={{ color: estProfit > 0 ? "var(--crm-success)" : "var(--crm-danger)" }}
              className={styles.summaryValueAccent}
            >
              {fmtBRL(estProfit)}
            </span>
          </div>
          <div>
            <span className={modalStyles.summaryBlockLabel}>ORIGEM</span>
            <br />
            <span className={styles.summaryValueAccent}>
              {hasSupplierItems ? "⚠️ Contém fornecedor" : "✅ Somente estoque"}
            </span>
          </div>
        </div>

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
