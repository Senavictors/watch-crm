"use client";
import React, { useMemo, useState } from "react";
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
import modalStyles from "../components/Modal/Modal.module.css";
import styles from "./NewWaitlistForm.module.css";

type Props = {
  customers: Customer[];
  products: Product[];
  // TASK-018 — decisão de UI: o contrato aceita `orderId` como número simples,
  // mas como a página já carrega `/orders` (mesmo padrão de `NewReturnForm`),
  // oferecemos um select com os pedidos do cliente em vez de um campo
  // numérico cru — só aparece editando uma entrada com status "Convertido".
  orders: Order[];
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
  customers,
  products,
  orders,
  metadata,
  entryToEdit,
  onSave,
  onClose,
  onToast,
}) => {
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

  function handleProductChange(value: string) {
    const product = products.find((p) => p.id === Number(value));
    setForm((current) => ({
      ...current,
      productId: value,
      productName: product ? productLabel(product) : "",
      brandName: product?.brand ?? null,
      modelName: product?.model ?? null,
      qualityName: product?.modelQualityName ?? null,
    }));
  }

  const customerOrders = useMemo(
    () => (form.customerId ? orders.filter((o) => o.customerId === Number(form.customerId)) : []),
    [orders, form.customerId]
  );

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
    <div className={modalStyles.overlay}>
      <div className={`${modalStyles.modal} ${styles.modal}`}>
        <div className={modalStyles.header}>
          <h3 className={modalStyles.title}>
            {entryToEdit ? `Editar Entrada #${entryToEdit.id}` : "Nova Entrada na Lista de Espera"}
          </h3>
          <button onClick={onClose} className={modalStyles.close}>×</button>
        </div>

        <div className={modalStyles.formGridTwo}>
          <div className={styles.fullSpan}>
            <Select
              label="Cliente"
              value={form.customerId}
              onChange={(e) => set("customerId", e.target.value)}
            >
              <option value="">Selecionar cliente...</option>
              {customers.map((c) => (
                <option key={c.id} value={c.id}>{c.name} — {c.phone}</option>
              ))}
            </Select>
          </div>

          <div className={styles.fullSpan}>
            <Select
              label="Produto"
              value={form.productId}
              onChange={(e) => handleProductChange(e.target.value)}
            >
              <option value="">Selecionar produto...</option>
              {products.map((p) => (
                <option key={p.id} value={p.id}>{productLabel(p)}</option>
              ))}
            </Select>
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
              <Select
                label="Pedido Vinculado"
                value={form.orderId}
                onChange={(e) => set("orderId", e.target.value)}
              >
                <option value="">Selecionar pedido...</option>
                {customerOrders.map((o) => (
                  <option key={o.id} value={o.id}>
                    #{o.id} — {o.productName} ({o.status})
                  </option>
                ))}
              </Select>
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
    </div>
  );
};

export default NewWaitlistForm;
