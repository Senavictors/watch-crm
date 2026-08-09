"use client";
import React, { useMemo, useState } from "react";
import AsyncLookupSelect from "../components/AsyncLookupSelect";
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
import { fmtBRL, productLabel, suggestedUnitPrice } from "../helpers";
import ModalBackdrop from "../components/Modal/ModalBackdrop";
import modalStyles from "../components/Modal/Modal.module.css";
import styles from "./NewOrderForm.module.css";

type Props = {
  metadata: OrderMetadata;
  // TASK-013: quem não tem dashboard.financial.view não recebe `cost` na API
  // (gerente inclusive) — sem essa flag o custo/lucro estimado ficaria
  // zerado ao invés de simplesmente escondido, o que mentiria pro usuário.
  canViewFinancials: boolean;
  onSave: (order: OrderInput) => void;
  onClose: () => void;
  onToast: (message: string, variant?: "success" | "error") => void;
};

type ItemForm = {
  productId: string;
  quantity: string;
  unitPrice: string;
  unitDiscount: string;
  // TASK-004 (CA-02): true depois que o vendedor edita o preço unitário à
  // mão — a partir daí a sugestão por forma de pagamento para de sobrescrever
  // esse item, mesmo que o pagamento mude de novo.
  priceTouched: boolean;
};

const emptyItem = (): ItemForm => ({
  productId: "",
  quantity: "1",
  unitPrice: "",
  unitDiscount: "0",
  priceTouched: false,
});

const NewOrderForm: React.FC<Props> = ({ metadata, canViewFinancials, onSave, onClose, onToast }) => {
  const [selectedProducts, setSelectedProducts] = useState<Record<number, Product>>({});
  const [form, setForm] = useState<{
    customerId: string;
    sellerUserId: string;
    channel: Channel;
    items: ItemForm[];
    freight: number;
    channelFee: number;
    paymentMethod: PaymentMethod;
    paymentExpiresAt: string;
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
    paymentExpiresAt: "",
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

  function handleProductChange(index: number, product: Product | null) {
    const productId = product ? String(product.id) : "";
    if (product) setSelectedProducts((current) => ({ ...current, [product.id]: product }));

    updateItem(index, {
      productId,
      unitPrice: product ? String(suggestedUnitPrice(product, form.paymentMethod)) : "",
      unitDiscount: "0",
      priceTouched: false,
      quantity: form.items[index]?.quantity || "1",
    });
  }

  function handleUnitPriceChange(index: number, unitPrice: string) {
    updateItem(index, { unitPrice, priceTouched: true });
  }

  function handlePaymentMethodChange(paymentMethod: PaymentMethod) {
    setForm((current) => ({
      ...current,
      paymentMethod,
      paymentExpiresAt: paymentMethod === "PIX" || paymentMethod === "Boleto" ? current.paymentExpiresAt : "",
      items: current.items.map((item) => {
        if (item.priceTouched || !item.productId) return item;
        const product = selectedProducts[Number(item.productId)];
        if (!product) return item;
        return { ...item, unitPrice: String(suggestedUnitPrice(product, paymentMethod)) };
      }),
    }));
  }

  const selectedLines = useMemo(
    () =>
      form.items.map((item) => ({
        item,
        product: selectedProducts[Number(item.productId)] ?? null,
      })),
    [form.items, selectedProducts]
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
      paymentExpiresAt: form.paymentExpiresAt ? new Date(form.paymentExpiresAt).toISOString() : null,
      shippingMethod: form.shippingMethod,
      trackingCode: "",
      saleDate: new Date().toISOString().slice(0, 10),
      shippedDate: "",
      notes: form.notes,
      status: "Novo",
    });
  }

  return (
    <ModalBackdrop onClose={onClose}>
      <div className={`${modalStyles.modal} ${styles.modal}`}>
        <div className={modalStyles.header}>
          <h3 className={modalStyles.title}>Novo Pedido</h3>
          <button onClick={onClose} className={modalStyles.close}>
            ×
          </button>
        </div>

        <div className={modalStyles.formGridTwo}>
          <div className={styles.fullSpan}>
            <AsyncLookupSelect<Customer>
              label="Cliente"
              endpoint="/customers/lookup"
              value={form.customerId}
              getValue={(customer) => String(customer.id)}
              getLabel={(customer) => `${customer.name} — ${customer.phone}`}
              onSelect={(customer) => set("customerId", customer ? String(customer.id) : "")}
            />
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
                const selectedProduct = selectedProducts[Number(item.productId)];

                return (
                  <div key={`${index}-${item.productId || "new"}`} className={styles.itemCard}>
                    <div className={styles.itemRow}>
                      <div className={styles.itemFieldWide}>
                        <AsyncLookupSelect<Product>
                          label={`Item ${index + 1}`}
                          endpoint="/products/lookup"
                          value={item.productId}
                          getValue={(product) => String(product.id)}
                          getLabel={(product) => `${productLabel(product)} — ${fmtBRL(suggestedUnitPrice(product, form.paymentMethod))}`}
                          onSelect={(product) => handleProductChange(index, product)}
                          initialOption={selectedProduct}
                        />
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
                          onChange={(e) => handleUnitPriceChange(index, e.target.value)}
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
                        <span>{selectedProduct.categoryName}</span>
                        <span>{selectedProduct.stock === "IN_STOCK" ? "✅ Estoque" : "⚠️ Fornecedor"}</span>
                        {canViewFinancials && <span>Custo: {fmtBRL(selectedProduct.cost)}</span>}
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
            onChange={(e) => handlePaymentMethodChange(e.target.value as PaymentMethod)}
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
          {(form.paymentMethod === "PIX" || form.paymentMethod === "Boleto") && (
            <Input
              label="Vencimento do pagamento (opcional)"
              type="datetime-local"
              value={form.paymentExpiresAt}
              onChange={(e) => set("paymentExpiresAt", e.target.value)}
            />
          )}
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
          {canViewFinancials && (
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
          )}
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
    </ModalBackdrop>
  );
};

export default NewOrderForm;
