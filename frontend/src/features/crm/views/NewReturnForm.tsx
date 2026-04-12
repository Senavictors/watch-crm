"use client";
import React, { useEffect, useMemo, useState } from "react";
import { Btn, Input, Select } from "../ui/Primitives";
import {
  Customer,
  Order,
  OrderItem,
  ProductReturn,
  ReturnInput,
  ReturnItemInput,
  ReturnMetadata,
  ReturnType,
} from "../types";
import { fmtBRL } from "../helpers";
import modalStyles from "../components/Modal/Modal.module.css";
import styles from "./NewReturnForm.module.css";

const REENVIO_STATUSES = [
  "Pronto p/ Reenvio",
  "Enviado",
  "Entregue",
  "Concluído",
];

type Props = {
  customers: Customer[];
  orders: Order[];
  metadata: ReturnMetadata;
  returnToEdit?: ProductReturn;
  prefilledOrderId?: number;
  onSave: (data: ReturnInput) => void;
  onClose: () => void;
  onToast: (message: string, variant?: "success" | "error") => void;
};

const itemFromOrderItem = (oi: OrderItem): ReturnItemInput => ({
  orderItemId: oi.id ?? null,
  productId: oi.productId,
  productName: oi.productName,
  productType: oi.productType,
  brandName: oi.brandName ?? null,
  modelName: oi.modelName ?? null,
  qualityName: oi.qualityName ?? null,
  quantity: oi.quantity,
  unitPrice: oi.unitPrice,
});

const emptyForm = (metadata: ReturnMetadata, prefilledOrderId?: number) => ({
  customerId: "",
  orderId: prefilledOrderId ? String(prefilledOrderId) : "",
  selectedOrderItemIds: [] as number[],
  assignedUserId: "",
  type: "garantia" as ReturnType,
  status: "Aguardando Recebimento",
  reason: "",
  internalNotes: "",
  resolutionNotes: "",
  receivedDate: "",
  resolvedDate: "",
  freightCostIn: "0",
  watchmakerCost: "0",
  freightCostOut: "0",
  otherCosts: "0",
  refundAmount: "",
  returnTrackingCode: "",
  shippedBackDate: "",
});

const NewReturnForm: React.FC<Props> = ({
  customers,
  orders,
  metadata,
  returnToEdit,
  prefilledOrderId,
  onSave,
  onClose,
  onToast,
}) => {
  const [form, setForm] = useState(() => {
    if (returnToEdit) {
      return {
        customerId: String(returnToEdit.customerId),
        orderId: returnToEdit.orderId ? String(returnToEdit.orderId) : "",
        selectedOrderItemIds: returnToEdit.items
          .map((i) => i.orderItemId)
          .filter((id): id is number => id !== null),
        assignedUserId: returnToEdit.assignedUserId ? String(returnToEdit.assignedUserId) : "",
        type: returnToEdit.type,
        status: returnToEdit.status,
        reason: returnToEdit.reason,
        internalNotes: returnToEdit.internalNotes,
        resolutionNotes: returnToEdit.resolutionNotes,
        receivedDate: returnToEdit.receivedDate,
        resolvedDate: returnToEdit.resolvedDate,
        freightCostIn: String(returnToEdit.freightCostIn),
        watchmakerCost: String(returnToEdit.watchmakerCost),
        freightCostOut: String(returnToEdit.freightCostOut),
        otherCosts: String(returnToEdit.otherCosts),
        refundAmount: returnToEdit.refundAmount !== null ? String(returnToEdit.refundAmount) : "",
        returnTrackingCode: returnToEdit.returnTrackingCode,
        shippedBackDate: returnToEdit.shippedBackDate,
      };
    }
    return emptyForm(metadata, prefilledOrderId);
  });

  function set<K extends keyof typeof form>(key: K, value: (typeof form)[K]) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  const customerOrders = useMemo(
    () =>
      form.customerId
        ? orders.filter((o) => o.customerId === Number(form.customerId))
        : [],
    [orders, form.customerId]
  );

  const selectedOrder = useMemo(
    () => (form.orderId ? orders.find((o) => o.id === Number(form.orderId)) : null),
    [orders, form.orderId]
  );

  // Auto-select customer when order is prefilled
  useEffect(() => {
    if (prefilledOrderId && !returnToEdit) {
      const order = orders.find((o) => o.id === prefilledOrderId);
      if (order) {
        setForm((current) => ({
          ...current,
          customerId: String(order.customerId),
          orderId: String(order.id),
        }));
      }
    }
  }, [prefilledOrderId, orders, returnToEdit]);

  function handleOrderItemToggle(orderItemId: number) {
    setForm((current) => {
      const already = current.selectedOrderItemIds.includes(orderItemId);
      return {
        ...current,
        selectedOrderItemIds: already
          ? current.selectedOrderItemIds.filter((id) => id !== orderItemId)
          : [...current.selectedOrderItemIds, orderItemId],
      };
    });
  }

  const showTrackingFields = REENVIO_STATUSES.includes(form.status);

  const totalCost =
    Number(form.freightCostIn || 0) +
    Number(form.watchmakerCost || 0) +
    Number(form.freightCostOut || 0) +
    Number(form.otherCosts || 0);

  function buildItems(): ReturnItemInput[] {
    if (selectedOrder && form.selectedOrderItemIds.length > 0) {
      return selectedOrder.items
        .filter((oi) => oi.id !== undefined && form.selectedOrderItemIds.includes(oi.id as number))
        .map(itemFromOrderItem);
    }
    // No order linked — at least one item required, but form allows saving without items
    return [];
  }

  function handleSubmit() {
    if (!form.customerId) {
      onToast("Selecione um cliente.", "error");
      return;
    }
    if (!form.type) {
      onToast("Selecione o tipo (Garantia / Troca / Devolução).", "error");
      return;
    }
    const items = buildItems();
    if (items.length === 0) {
      onToast("Selecione pelo menos um item do pedido.", "error");
      return;
    }

    onSave({
      orderId: form.orderId ? Number(form.orderId) : null,
      customerId: Number(form.customerId),
      assignedUserId: form.assignedUserId ? Number(form.assignedUserId) : null,
      type: form.type,
      status: form.status,
      reason: form.reason,
      internalNotes: form.internalNotes,
      resolutionNotes: form.resolutionNotes,
      receivedDate: form.receivedDate,
      resolvedDate: form.resolvedDate,
      freightCostIn: Number(form.freightCostIn || 0),
      watchmakerCost: Number(form.watchmakerCost || 0),
      freightCostOut: Number(form.freightCostOut || 0),
      otherCosts: Number(form.otherCosts || 0),
      refundAmount: form.refundAmount ? Number(form.refundAmount) : null,
      returnTrackingCode: form.returnTrackingCode,
      shippedBackDate: form.shippedBackDate,
      items,
    });
  }

  return (
    <div className={modalStyles.overlay}>
      <div className={`${modalStyles.modal} ${styles.modal}`}>
        <div className={modalStyles.header}>
          <h3 className={modalStyles.title}>
            {returnToEdit ? `Editar Garantia/Troca #${returnToEdit.id}` : "Nova Garantia/Troca"}
          </h3>
          <button onClick={onClose} className={modalStyles.close}>×</button>
        </div>

        <div className={modalStyles.formGridTwo}>
          {/* Cliente */}
          <div className={styles.fullSpan}>
            <Select
              label="Cliente"
              value={form.customerId}
              onChange={(e) => {
                set("customerId", e.target.value);
                set("orderId", "");
                set("selectedOrderItemIds", []);
              }}
              disabled={!!returnToEdit}
            >
              <option value="">Selecionar cliente...</option>
              {customers.map((c) => (
                <option key={c.id} value={c.id}>{c.name} — {c.phone}</option>
              ))}
            </Select>
          </div>

          {/* Pedido de origem */}
          <div className={styles.fullSpan}>
            <Select
              label="Pedido de Origem (opcional)"
              value={form.orderId}
              onChange={(e) => {
                set("orderId", e.target.value);
                set("selectedOrderItemIds", []);
              }}
            >
              <option value="">Sem pedido vinculado</option>
              {customerOrders.map((o) => (
                <option key={o.id} value={o.id}>
                  #{o.id} — {o.productName} ({o.status})
                </option>
              ))}
            </Select>
          </div>

          {/* Seleção de itens do pedido */}
          {selectedOrder && selectedOrder.items.length > 0 && (
            <div className={styles.fullSpan}>
              <div className={styles.label}>Itens Retornados</div>
              <div className={styles.orderItemsList}>
                {selectedOrder.items.map((oi, idx) => {
                  const itemId = oi.id ?? idx;
                  const checked = oi.id !== undefined && form.selectedOrderItemIds.includes(oi.id);
                  return (
                    <label key={itemId} className={styles.orderItemRow}>
                      <input
                        type="checkbox"
                        checked={checked}
                        onChange={() => oi.id !== undefined && handleOrderItemToggle(oi.id)}
                        className={styles.checkbox}
                      />
                      <span className={styles.orderItemName}>{oi.productName}</span>
                      <span className={styles.orderItemMeta}>{oi.quantity} un. · {fmtBRL(oi.unitPrice)}</span>
                    </label>
                  );
                })}
              </div>
            </div>
          )}

          {/* Tipo e status */}
          <Select label="Tipo" value={form.type} onChange={(e) => set("type", e.target.value as ReturnType)}>
            <option value="garantia">Garantia</option>
            <option value="troca">Troca</option>
            <option value="devolucao">Devolução</option>
          </Select>

          <Select label="Status" value={form.status} onChange={(e) => set("status", e.target.value)}>
            {metadata.statuses.map((s) => (
              <option key={s}>{s}</option>
            ))}
          </Select>

          {/* Responsável */}
          <div className={styles.fullSpan}>
            <Select label="Responsável" value={form.assignedUserId} onChange={(e) => set("assignedUserId", e.target.value)}>
              <option value="">Nenhum</option>
              {metadata.assignableUsers.map((u) => (
                <option key={u.id} value={u.id}>{u.name}</option>
              ))}
            </Select>
          </div>

          {/* Datas */}
          <Input
            label="Data de Recebimento"
            type="date"
            value={form.receivedDate}
            onChange={(e) => set("receivedDate", e.target.value)}
          />
          <Input
            label="Data de Resolução"
            type="date"
            value={form.resolvedDate}
            onChange={(e) => set("resolvedDate", e.target.value)}
          />

          {/* Custos */}
          <div className={styles.fullSpan}>
            <div className={styles.label}>Custos</div>
            <div className={styles.costsGrid}>
              <Input
                label="Frete Entrada (R$)"
                type="number"
                min={0}
                value={form.freightCostIn}
                onChange={(e) => set("freightCostIn", e.target.value)}
              />
              <Input
                label="Relojoeiro (R$)"
                type="number"
                min={0}
                value={form.watchmakerCost}
                onChange={(e) => set("watchmakerCost", e.target.value)}
              />
              <Input
                label="Frete Saída (R$)"
                type="number"
                min={0}
                value={form.freightCostOut}
                onChange={(e) => set("freightCostOut", e.target.value)}
              />
              <Input
                label="Outros (R$)"
                type="number"
                min={0}
                value={form.otherCosts}
                onChange={(e) => set("otherCosts", e.target.value)}
              />
            </div>
          </div>

          {/* Reembolso (só para devolução) */}
          {form.type === "devolucao" && (
            <Input
              label="Valor do Reembolso (R$)"
              type="number"
              min={0}
              value={form.refundAmount}
              onChange={(e) => set("refundAmount", e.target.value)}
            />
          )}

          {/* Tracking de reenvio */}
          {showTrackingFields && (
            <>
              <Input
                label="Código de Rastreio (Reenvio)"
                type="text"
                value={form.returnTrackingCode}
                onChange={(e) => set("returnTrackingCode", e.target.value)}
              />
              <Input
                label="Data de Reenvio"
                type="date"
                value={form.shippedBackDate}
                onChange={(e) => set("shippedBackDate", e.target.value)}
              />
            </>
          )}

          {/* Motivo */}
          <div className={styles.fullSpan}>
            <label className={styles.label}>Motivo (relatado pelo cliente)</label>
            <textarea
              value={form.reason}
              onChange={(e) => set("reason", e.target.value)}
              className={modalStyles.notes}
              placeholder="Ex: Relógio parou de funcionar após 1 semana..."
            />
          </div>

          {/* Obs internas */}
          <div className={styles.fullSpan}>
            <label className={styles.label}>Observações Internas</label>
            <textarea
              value={form.internalNotes}
              onChange={(e) => set("internalNotes", e.target.value)}
              className={modalStyles.notes}
            />
          </div>

          {/* Notas de resolução */}
          <div className={styles.fullSpan}>
            <label className={styles.label}>Notas de Resolução</label>
            <textarea
              value={form.resolutionNotes}
              onChange={(e) => set("resolutionNotes", e.target.value)}
              className={modalStyles.notes}
            />
          </div>
        </div>

        <div className={modalStyles.summaryStrip}>
          <div>
            <span className={modalStyles.summaryBlockLabel}>TIPO</span>
            <br />
            <span className={styles.summaryValue}>{form.type === "garantia" ? "Garantia" : form.type === "troca" ? "Troca" : "Devolução"}</span>
          </div>
          <div>
            <span className={modalStyles.summaryBlockLabel}>STATUS</span>
            <br />
            <span className={styles.summaryValue}>{form.status}</span>
          </div>
          <div>
            <span className={modalStyles.summaryBlockLabel}>CUSTO TOTAL</span>
            <br />
            <span className={styles.summaryValueAccent}>{fmtBRL(totalCost)}</span>
          </div>
          {form.type === "devolucao" && form.refundAmount && (
            <div>
              <span className={modalStyles.summaryBlockLabel}>REEMBOLSO</span>
              <br />
              <span className={styles.summaryValueDanger}>{fmtBRL(Number(form.refundAmount))}</span>
            </div>
          )}
        </div>

        <div className={styles.actions}>
          <Btn onClick={onClose} variant="secondary" className={styles.actionButton}>Cancelar</Btn>
          <Btn onClick={handleSubmit} variant="primary" className={styles.actionButton}>
            {returnToEdit ? "Salvar Alterações" : "Registrar"}
          </Btn>
        </div>
      </div>
    </div>
  );
};

export default NewReturnForm;
