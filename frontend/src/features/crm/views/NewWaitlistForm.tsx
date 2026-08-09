"use client";
import React, { useState } from "react";
import AsyncLookupSelect from "../components/AsyncLookupSelect";
import { Btn, Select } from "../ui/Primitives";
import {
  Customer,
  Order,
  Product,
  WaitlistEntry,
  WaitlistInput,
  WaitlistMetadata,
  WaitlistStatus,
} from "../types";
import { productLabel } from "../helpers";
import ModalBackdrop from "../components/Modal/ModalBackdrop";
import modalStyles from "../components/Modal/Modal.module.css";
import styles from "./NewWaitlistForm.module.css";

type Props = {
  // TASK-018 — decisão de UI: o contrato aceita `orderId` como número simples,
  // mas como a página já carrega `/orders` (mesmo padrão de `NewReturnForm`),
  // oferecemos um select com os pedidos do cliente em vez de um campo
  // numérico cru — só aparece editando uma entrada com status "Convertido".
  metadata: WaitlistMetadata;
  entryToEdit?: WaitlistEntry;
  onSave: (data: WaitlistInput) => void;
  onClose: () => void;
  onToast: (message: string, variant?: "success" | "error") => void;
};

type FormState = {
  customerId: string;
  productId: string;
  productName: string;
  brandName: string | null;
  modelName: string | null;
  qualityName: string | null;
  sellerUserId: string;
  status: WaitlistStatus;
  notes: string;
  notifiedAt: string;
  orderId: string;
};

function emptyForm(): FormState {
  return {
    customerId: "",
    productId: "",
    productName: "",
    brandName: null,
    modelName: null,
    qualityName: null,
    sellerUserId: "",
    status: "Pendente",
    notes: "",
    notifiedAt: "",
    orderId: "",
  };
}

const NewWaitlistForm: React.FC<Props> = ({
  metadata,
  entryToEdit,
  onSave,
  onClose,
  onToast,
}) => {
  const initialCustomer: Customer | null = entryToEdit
    ? { id: entryToEdit.customerId, name: entryToEdit.customerName, phone: entryToEdit.customerPhone }
    : null;
  const initialProduct: Product | null = entryToEdit?.productId
    ? {
        id: entryToEdit.productId,
        brandId: 0,
        modelId: 0,
        brand: entryToEdit.brandName ?? undefined,
        model: entryToEdit.modelName ?? entryToEdit.productName,
        modelQualityName: entryToEdit.qualityName,
        categoryHasQuality: Boolean(entryToEdit.qualityName),
        price: 0,
        stock: "IN_STOCK",
        qty: entryToEdit.productCurrentQty ?? 0,
      }
    : null;
  const initialOrder: Order | null = entryToEdit?.orderId
    ? {
        id: entryToEdit.orderId,
        customerId: entryToEdit.customerId,
        customerName: entryToEdit.customerName,
        channel: "",
        seller: "",
        status: "",
        productName: entryToEdit.productName,
        itemsCount: 0,
        items: [],
        salePrice: 0,
        discount: 0,
        freight: 0,
        channelFee: 0,
        paymentMethod: "",
        shippingMethod: "",
        trackingCode: "",
        saleDate: "",
        shippedDate: "",
        notes: "",
        nextPostingDate: null,
        isLate: false,
      }
    : null;
  const [form, setForm] = useState<FormState>(() => {
    if (entryToEdit) {
      return {
        customerId: String(entryToEdit.customerId),
        productId: entryToEdit.productId ? String(entryToEdit.productId) : "",
        productName: entryToEdit.productName,
        brandName: entryToEdit.brandName,
        modelName: entryToEdit.modelName,
        qualityName: entryToEdit.qualityName,
        sellerUserId: entryToEdit.sellerUserId ? String(entryToEdit.sellerUserId) : "",
        status: entryToEdit.status,
        notes: entryToEdit.notes,
        notifiedAt: entryToEdit.notifiedAt ?? "",
        orderId: entryToEdit.orderId ? String(entryToEdit.orderId) : "",
      };
    }
    return emptyForm();
  });

  function set<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  function handleProductChange(product: Product | null) {
    setForm((current) => ({
      ...current,
      productId: product ? String(product.id) : "",
      productName: product ? productLabel(product) : "",
      brandName: product?.brand ?? null,
      modelName: product?.model ?? null,
      qualityName: product?.modelQualityName ?? null,
    }));
  }

  const showOrderField = !!entryToEdit && form.status === "Convertido";

  function handleSubmit() {
    if (!form.customerId) {
      onToast("Selecione um cliente.", "error");
      return;
    }
    if (!form.productId || !form.productName) {
      onToast("Selecione um produto.", "error");
      return;
    }
    if (showOrderField && !form.orderId) {
      onToast("Selecione o pedido vinculado para converter esta entrada.", "error");
      return;
    }

    onSave({
      customerId: Number(form.customerId),
      productId: Number(form.productId),
      productName: form.productName,
      brandName: form.brandName,
      modelName: form.modelName,
      qualityName: form.qualityName,
      sellerUserId: form.sellerUserId ? Number(form.sellerUserId) : null,
      notes: form.notes,
      notifiedAt: form.notifiedAt || null,
      // Nasce sempre em "Pendente" na criação (default do backend); só
      // enviamos `status`/`orderId` editáveis quando já existe a entrada.
      ...(entryToEdit
        ? { status: form.status, orderId: form.orderId ? Number(form.orderId) : null }
        : {}),
    });
  }

  return (
    <ModalBackdrop onClose={onClose}>
      <div className={`${modalStyles.modal} ${styles.modal}`}>
        <div className={modalStyles.header}>
          <h3 className={modalStyles.title}>
            {entryToEdit ? `Editar Entrada #${entryToEdit.id}` : "Nova Entrada na Lista de Espera"}
          </h3>
          <button onClick={onClose} className={modalStyles.close}>×</button>
        </div>

        <div className={modalStyles.formGridTwo}>
          <div className={styles.fullSpan}>
            <AsyncLookupSelect<Customer>
              label="Cliente"
              endpoint="/customers/lookup"
              value={form.customerId}
              getValue={(customer) => String(customer.id)}
              getLabel={(customer) => `${customer.name} — ${customer.phone}`}
              initialOption={initialCustomer}
              onSelect={(customer) => set("customerId", customer ? String(customer.id) : "")}
            />
          </div>

          <div className={styles.fullSpan}>
            <AsyncLookupSelect<Product>
              label="Produto"
              endpoint="/products/lookup"
              value={form.productId}
              getValue={(product) => String(product.id)}
              getLabel={productLabel}
              initialOption={initialProduct}
              onSelect={handleProductChange}
            />
          </div>

          <div className={styles.fullSpan}>
            <Select
              label="Vendedor Responsável"
              value={form.sellerUserId}
              onChange={(e) => set("sellerUserId", e.target.value)}
            >
              <option value="">Eu mesmo (padrão)</option>
              {metadata.assignableUsers.map((u) => (
                <option key={u.id} value={u.id}>{u.name}</option>
              ))}
            </Select>
          </div>

          {entryToEdit ? (
            <Select label="Status" value={form.status} onChange={(e) => set("status", e.target.value as WaitlistStatus)}>
              {metadata.statuses.map((s) => (
                <option key={s}>{s}</option>
              ))}
            </Select>
          ) : (
            <div>
              <span className={styles.label}>Status</span>
              <div className={styles.statusInfo}>Nasce em: Pendente</div>
            </div>
          )}

          {showOrderField && (
            <div className={styles.fullSpan}>
              <AsyncLookupSelect<Order>
                label="Pedido Vinculado"
                endpoint={`/orders/lookup?customerId=${form.customerId}`}
                value={form.orderId}
                getValue={(order) => String(order.id)}
                getLabel={(order) => `#${order.id} — ${order.productName} (${order.status})`}
                initialOption={initialOrder}
                onSelect={(order) => set("orderId", order ? String(order.id) : "")}
              />
            </div>
          )}

          <div className={styles.fullSpan}>
            <label className={styles.label}>Observação</label>
            <textarea
              value={form.notes}
              onChange={(e) => set("notes", e.target.value)}
              className={modalStyles.notes}
              placeholder="Ex: Quer assim que chegar..."
            />
          </div>
        </div>

        <div className={styles.actions}>
          <Btn onClick={onClose} variant="secondary" className={styles.actionButton}>Cancelar</Btn>
          <Btn onClick={handleSubmit} variant="primary" className={styles.actionButton}>
            {entryToEdit ? "Salvar Alterações" : "Registrar"}
          </Btn>
        </div>
      </div>
    </ModalBackdrop>
  );
};

export default NewWaitlistForm;
