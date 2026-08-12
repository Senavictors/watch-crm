"use client";
import React, { useMemo, useState } from "react";
import AsyncLookupSelect from "../components/AsyncLookupSelect";
import { Btn, Input, Select } from "../ui/Primitives";
import {
  Customer,
  Order,
  OrderItem,
  Product,
  ProductReturn,
  ReturnInput,
  ReturnItemInput,
  ReturnMetadata,
  ReturnType,
} from "../types";
import { fmtBRL, productLabel } from "../helpers";
import { useAuth } from "../contexts/AuthContext";
import ModalBackdrop from "../components/Modal/ModalBackdrop";
import modalStyles from "../components/Modal/Modal.module.css";
import styles from "./NewReturnForm.module.css";

const REENVIO_STATUSES = ["Pronto para Reenvio", "Reenviado", "Concluído"];

// TASK-017 — toda devolução nasce em "Aguardando Recebimento" no backend
// (o campo `status` enviado na criação é ignorado); mantido como constante
// pra não hardcodear a string em mais de um lugar deste arquivo.
const INITIAL_RETURN_STATUS = "Aguardando Recebimento";

type Props = {
  metadata: ReturnMetadata;
  returnToEdit?: ProductReturn;
  prefilledOrder?: Order;
  onSave: (data: ReturnInput) => void;
  onClose: () => void;
  onToast: (message: string, variant?: "success" | "error") => void;
};

// TASK-028: só ID e quantidade — o backend deriva o resto da venda real.
const itemFromOrderItem = (oi: OrderItem): ReturnItemInput => ({
  orderItemId: oi.id ?? null,
  quantity: oi.quantity,
});

const emptyForm = (metadata: ReturnMetadata, prefilledOrder?: Order) => ({
  customerId: prefilledOrder ? String(prefilledOrder.customerId) : "",
  orderId: prefilledOrder ? String(prefilledOrder.id) : "",
  selectedOrderItemIds: [] as number[],
  // TASK-028: quantidade da devolução avulsa (sem pedido vinculado).
  manualQuantity: "1",
  assignedUserId: "",
  type: "garantia" as ReturnType,
  status: INITIAL_RETURN_STATUS,
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
  metadata,
  returnToEdit,
  prefilledOrder,
  onSave,
  onClose,
  onToast,
}) => {
  // TASK-027 (ADR-008): as duas permissões financeiras do pós-venda.
  const { hasPermission } = useAuth();
  const canApproveRefund = hasPermission("returns.refund.approve");
  const canUpdateFinancials = hasPermission("returns.financials.update");
  // TASK-028: produto do catálogo para a devolução avulsa (sem pedido).
  const [manualProduct, setManualProduct] = useState<Product | null>(null);
  const initialCustomer: Customer | null = returnToEdit
    ? { id: returnToEdit.customerId, name: returnToEdit.customerName, phone: returnToEdit.customerPhone }
    : prefilledOrder
      ? { id: prefilledOrder.customerId, name: prefilledOrder.customerName ?? `Cliente #${prefilledOrder.customerId}`, phone: "" }
      : null;
  const initialOrder = prefilledOrder ?? null;
  const [selectedOrder, setSelectedOrder] = useState<Order | null>(initialOrder);
  const [form, setForm] = useState(() => {
    if (returnToEdit) {
      return {
        customerId: String(returnToEdit.customerId),
        orderId: returnToEdit.orderId ? String(returnToEdit.orderId) : "",
        selectedOrderItemIds: returnToEdit.items
          .map((i) => i.orderItemId)
          .filter((id): id is number => id !== null),
        manualQuantity: String(returnToEdit.items[0]?.quantity ?? 1),
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
    return emptyForm(metadata, prefilledOrder);
  });

  function set<K extends keyof typeof form>(key: K, value: (typeof form)[K]) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  // TASK-017 — ao editar, só oferece o status atual (registro já é válido
  // permanecer nele) + os destinos válidos a partir dele
  // (`metadata.transitions[statusAtual]`); o backend rejeita (422) qualquer
  // outra transição.
  // TASK-027 (CA-04 / ADR-008) — "Reembolso Efetuado" sai da lista para quem
  // não pode aprovar reembolso: o backend recusa com 403, e mostrar a opção
  // seria oferecer uma ação que vai falhar. A aprovação tem ação própria no
  // detalhe da devolução.
  const allowedStatusOptions = useMemo(() => {
    if (!returnToEdit) return [];
    const currentStatus = returnToEdit.status;
    const nextStatuses = metadata.transitions?.[currentStatus] ?? [];
    return [currentStatus, ...nextStatuses.filter((s) => s !== currentStatus)]
      .filter((s) => s !== "Reembolso Efetuado" || canApproveRefund || currentStatus === "Reembolso Efetuado");
  }, [returnToEdit, metadata.transitions, canApproveRefund]);

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
    if (returnToEdit) {
      // Reenvia os mesmos vínculos já gravados; o backend revalida e deriva
      // os snapshots de novo (TASK-028).
      return returnToEdit.items.map((item) => (
        item.orderItemId != null
          ? { orderItemId: item.orderItemId, quantity: item.quantity }
          : { productId: item.productId, quantity: item.quantity }
      ));
    }
    // TASK-028 (RN-06): sem pedido vinculado, o item é um produto do
    // catálogo — nome, categoria e preço saem dele.
    if (manualProduct) {
      return [{ productId: manualProduct.id, quantity: Math.max(1, Number(form.manualQuantity || 1)) }];
    }
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
      onToast(
        form.orderId
          ? "Selecione pelo menos um item do pedido."
          : "Selecione o produto do catálogo para a devolução sem pedido vinculado.",
        "error"
      );
      return;
    }

    onSave({
      orderId: form.orderId ? Number(form.orderId) : null,
      customerId: Number(form.customerId),
      assignedUserId: form.assignedUserId ? Number(form.assignedUserId) : null,
      type: form.type,
      // TASK-017 — na criação o backend ignora `status` (nasce sempre em
      // "Aguardando Recebimento"); só enviamos o valor editável na edição.
      ...(returnToEdit ? { status: form.status } : {}),
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
    <ModalBackdrop onClose={onClose}>
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
            <AsyncLookupSelect<Customer>
              label="Cliente"
              endpoint="/customers/lookup"
              value={form.customerId}
              getValue={(customer) => String(customer.id)}
              getLabel={(customer) => `${customer.name} — ${customer.phone}`}
              initialOption={initialCustomer}
              onSelect={(customer) => {
                set("customerId", customer ? String(customer.id) : "");
                set("orderId", "");
                set("selectedOrderItemIds", []);
                setSelectedOrder(null);
              }}
              disabled={!!returnToEdit}
            />
          </div>

          {/* Pedido de origem */}
          <div className={styles.fullSpan}>
            <AsyncLookupSelect<Order>
              label="Pedido de Origem (opcional)"
              endpoint={`/orders/lookup${form.customerId ? `?customerId=${form.customerId}` : ""}`}
              value={form.orderId}
              getValue={(order) => String(order.id)}
              getLabel={(order) => `#${order.id} — ${order.productName} (${order.status})`}
              initialOption={initialOrder}
              disabled={!form.customerId}
              onSelect={(order) => {
                set("orderId", order ? String(order.id) : "");
                set("selectedOrderItemIds", []);
                setSelectedOrder(order);
              }}
            />
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

          {/* TASK-028 (RN-06): devolução sem pedido vinculado — o produto vem
              do catálogo, para não gravar snapshot digitado à mão. */}
          {!selectedOrder && !returnToEdit && (
            <>
              <div className={styles.fullSpan}>
                <AsyncLookupSelect<Product>
                  label="Produto (sem pedido vinculado)"
                  endpoint="/products/lookup"
                  value={manualProduct ? String(manualProduct.id) : ""}
                  getValue={(product) => String(product.id)}
                  getLabel={(product) => productLabel(product)}
                  onSelect={setManualProduct}
                  initialOption={manualProduct ?? undefined}
                />
              </div>
              <Input
                label="Quantidade"
                type="number"
                min={1}
                value={form.manualQuantity}
                onChange={(e) => set("manualQuantity", e.target.value)}
              />
            </>
          )}

          {/* Tipo e status */}
          <Select label="Tipo" value={form.type} onChange={(e) => set("type", e.target.value as ReturnType)}>
            <option value="garantia">Garantia</option>
            <option value="troca">Troca</option>
            <option value="devolucao">Devolução</option>
          </Select>

          {returnToEdit ? (
            <Select label="Status" value={form.status} onChange={(e) => set("status", e.target.value)}>
              {allowedStatusOptions.map((s) => (
                <option key={s}>{s}</option>
              ))}
            </Select>
          ) : (
            <div>
              <span className={styles.label}>Status</span>
              <div className={styles.statusInfo}>Nasce em: {INITIAL_RETURN_STATUS}</div>
            </div>
          )}

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

          {/* Reembolso (só para devolução) — TASK-027: o valor é dinheiro
              voltando ao cliente, diferente dos custos acima, que são
              operacionais. Sem permissão financeira o campo é somente
              leitura; o backend recusa a alteração de qualquer forma. */}
          {form.type === "devolucao" && (
            canUpdateFinancials ? (
              <Input
                label="Valor do Reembolso (R$)"
                type="number"
                min={0}
                value={form.refundAmount}
                onChange={(e) => set("refundAmount", e.target.value)}
              />
            ) : (
              <div>
                <span className={styles.label}>Valor do Reembolso (R$)</span>
                <div className={styles.statusInfo}>
                  {form.refundAmount ? fmtBRL(Number(form.refundAmount)) : "Definido na aprovação do reembolso"}
                </div>
              </div>
            )
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
    </ModalBackdrop>
  );
};

export default NewReturnForm;
