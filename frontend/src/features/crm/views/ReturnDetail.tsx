import React from "react";
import { Btn } from "../ui/Primitives";
import { ProductReturn, ReturnType } from "../types";
import { fmtBRL, fmtDate, fmtDateTime } from "../helpers";
import { RETURN_STATUS_COLORS, RETURN_TYPE_COLORS } from "../data/mock";
import ModalBackdrop from "../components/Modal/ModalBackdrop";
import modalStyles from "../components/Modal/Modal.module.css";
import styles from "./ReturnDetail.module.css";

const TYPE_LABELS: Record<ReturnType, string> = {
  garantia: "Garantia",
  troca: "Troca",
  devolucao: "Devolução",
};

type Props = {
  productReturn: ProductReturn;
  canUpdate: boolean;
  // TASK-025: estorno reaproveita quem podia excluir a devolução.
  canVoid: boolean;
  // TASK-027 (ADR-008): aprovar reembolso é ação financeira própria.
  canApproveRefund: boolean;
  onClose: () => void;
  onEdit: (r: ProductReturn) => void;
  onVoid: (r: ProductReturn) => void;
  onApproveRefund: (r: ProductReturn) => void;
};

const ReturnDetail: React.FC<Props> = ({
  productReturn: r,
  canUpdate,
  canVoid,
  canApproveRefund,
  onClose,
  onEdit,
  onVoid,
  onApproveRefund,
}) => {
  const typeColor = RETURN_TYPE_COLORS[r.type] ?? "var(--crm-text-soft)";
  const statusColor = RETURN_STATUS_COLORS[r.status] ?? "var(--crm-text-soft)";

  return (
    <ModalBackdrop onClose={onClose}>
      <div className={`${modalStyles.modal} ${styles.modal}`}>
        <div className={modalStyles.header}>
          <h3 className={modalStyles.title}>Garantia/Troca #{r.id}</h3>
          <button onClick={onClose} className={modalStyles.close}>×</button>
        </div>

        <div className={styles.badges}>
          <span
            className={styles.typePill}
            style={{ background: `${typeColor}22`, color: typeColor, borderColor: `${typeColor}55` }}
          >
            {TYPE_LABELS[r.type] ?? r.type}
          </span>
          <span
            className={styles.statusPill}
            style={{ background: `${statusColor}22`, color: statusColor, borderColor: `${statusColor}55` }}
          >
            {r.status}
          </span>
          {r.voidedAt && (
            <span className={styles.voidedPill} title={r.voidReason ?? undefined}>
              Estornada
            </span>
          )}
          {r.orderId && (
            <span className={styles.pill}>Pedido #{r.orderId}</span>
          )}
          {r.withinWarrantyWindow === true && (
            <span
              className={styles.statusPill}
              style={{ background: "#34D39922", color: "#34D399", borderColor: "#34D39955" }}
            >
              Dentro da garantia
            </span>
          )}
          {r.withinWarrantyWindow === false && (
            <span
              className={styles.statusPill}
              style={{ background: "#F9731622", color: "#F97316", borderColor: "#F9731655" }}
            >
              Fora da garantia
            </span>
          )}
        </div>

        <div className={styles.infoGrid}>
          <div>
            <div className={styles.infoLabel}>Cliente</div>
            <div className={styles.infoValue}>{r.customerName}</div>
            <div className={styles.infoMuted}>{r.customerPhone}</div>
          </div>
          <div>
            <div className={styles.infoLabel}>Responsável</div>
            <div className={styles.infoValue}>{r.assignedUserName ?? "—"}</div>
            <div className={styles.infoMuted}>Criado em {fmtDate(r.createdAt)}</div>
          </div>
        </div>

        <div className={styles.itemsSection}>
          <div className={styles.infoLabel}>Itens Retornados</div>
          <div className={styles.itemsList}>
            {r.items.map((item, index) => (
              <div key={`${item.productId ?? "x"}-${index}`} className={styles.itemCard}>
                <div>
                  <div className={styles.itemTitle}>{item.productName}</div>
                  <div className={styles.infoMuted}>
                    {item.productType}
                    {item.qualityName ? ` · ${item.qualityName}` : ""}
                  </div>
                </div>
                <div className={styles.itemNumbers}>
                  <span>{item.quantity} un.</span>
                  <span>{fmtBRL(item.unitPrice)}</span>
                </div>
              </div>
            ))}
          </div>
        </div>

        <div className={styles.costsSection}>
          <div className={styles.infoLabel}>Custos</div>
          <div className={styles.costsGrid}>
            {[
              { l: "Frete Entrada", v: fmtBRL(r.freightCostIn) },
              { l: "Relojoeiro", v: fmtBRL(r.watchmakerCost) },
              { l: "Frete Saída", v: fmtBRL(r.freightCostOut) },
              { l: "Outros", v: fmtBRL(r.otherCosts) },
            ].map((item) => (
              <div key={item.l}>
                <div className={styles.costLabel}>{item.l}</div>
                <div className={styles.costValue}>{item.v}</div>
              </div>
            ))}
          </div>
          <div className={styles.costTotal}>
            Custo Total: <span className={styles.accent}>{fmtBRL(r.totalCost)}</span>
          </div>
          {r.type === "devolucao" && r.refundAmount !== null && (
            <div className={styles.refund}>
              Reembolso: <span className={styles.danger}>{fmtBRL(r.refundAmount)}</span>
            </div>
          )}
        </div>

        <div className={styles.infoGrid}>
          <div>
            <div className={styles.infoLabel}>Datas</div>
            <div className={styles.infoMuted}>Recebido: {r.receivedDate ? fmtDate(r.receivedDate) : "—"}</div>
            <div className={styles.infoMuted}>Resolvido: {r.resolvedDate ? fmtDate(r.resolvedDate) : "—"}</div>
          </div>
          {r.returnTrackingCode && (
            <div>
              <div className={styles.infoLabel}>Reenvio</div>
              <div className={styles.infoMuted}>Rastreio: <span className={styles.accent}>{r.returnTrackingCode}</span></div>
              {r.shippedBackDate && <div className={styles.infoMuted}>Enviado: {fmtDate(r.shippedBackDate)}</div>}
            </div>
          )}
        </div>

        {r.reason && (
          <div className={styles.notesBlock}>
            <div className={styles.infoLabel}>Motivo</div>
            <div className={styles.noteText}>{r.reason}</div>
          </div>
        )}

        {r.internalNotes && (
          <div className={styles.notesBlock}>
            <div className={styles.infoLabel}>Obs. Internas</div>
            <div className={styles.noteText}>{r.internalNotes}</div>
          </div>
        )}

        {r.resolutionNotes && (
          <div className={styles.notesBlock}>
            <div className={styles.infoLabel}>Notas de Resolução</div>
            <div className={styles.noteText}>{r.resolutionNotes}</div>
          </div>
        )}

        {/* TASK-017 — timeline de transições (CA-01: etapa atual com
            proveniência); já vem ordenada cronologicamente da API. */}
        {r.statusHistory.length > 0 && (
          <div className={styles.itemsSection}>
            <div className={styles.infoLabel}>Histórico</div>
            <div className={styles.itemsList}>
              {r.statusHistory.map((entry, index) => (
                <div key={`${entry.createdAt}-${index}`} className={styles.itemCard}>
                  <div>
                    <div className={styles.itemTitle}>
                      {entry.fromStatus ?? "Criação"} → {entry.toStatus}
                    </div>
                    <div className={styles.infoMuted}>por {entry.actorUserName ?? "—"}</div>
                    {entry.notes && <div className={styles.infoMuted}>{entry.notes}</div>}
                  </div>
                  <div className={styles.infoMuted}>{fmtDateTime(entry.createdAt)}</div>
                </div>
              ))}
            </div>
          </div>
        )}

        <div className={styles.actions}>
          <Btn onClick={onClose} variant="secondary">Fechar</Btn>
          {/* TASK-025 (ADR-007): registro estornado é histórico fechado —
              não volta a andar na máquina de estados. */}
          {canUpdate && !r.voidedAt && (
            <Btn onClick={() => onEdit(r)} variant="primary">Editar</Btn>
          )}
          {/* TASK-027: só aparece para quem realmente pode aprovar, e só
              enquanto o reembolso não foi efetuado. */}
          {canApproveRefund && !r.voidedAt && r.status !== "Reembolso Efetuado" && r.type === "devolucao" && (
            <Btn onClick={() => onApproveRefund(r)} variant="primary">Aprovar reembolso</Btn>
          )}
          {canVoid && !r.voidedAt && (
            <Btn onClick={() => onVoid(r)} variant="danger">Estornar</Btn>
          )}
        </div>
      </div>
    </ModalBackdrop>
  );
};

export default ReturnDetail;
