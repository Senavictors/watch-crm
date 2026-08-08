"use client";
import React, { useState } from "react";
import { Customer, Order, ProductReturn } from "../types";
import { Badge, Btn } from "../ui/Primitives";
import { fmtBRL, fmtDate, fmtDateTime } from "../helpers";
import modalStyles from "../components/Modal/Modal.module.css";
import styles from "./CustomerDetailModal.module.css";

type Tab = "orders" | "returns";

type Props = {
  customer: Customer;
  orders: Order[] | null;
  returns: ProductReturn[] | null;
  loadingOrders: boolean;
  loadingReturns: boolean;
  loadingDetail?: boolean;
  canRegisterFrictionNote?: boolean;
  onAddFrictionNote?: (note: string) => void | Promise<void>;
  onClose: () => void;
};

/**
 * TASK-019 (CA-03) — o sinal de recompra nunca é apresentado como certeza:
 * `true` é "possibilidade", `false` é "ausência de sinal" (não "não vai
 * comprar"), `null` é "dados insuficientes" (nunca tratado como `false`).
 * Três estados visualmente distintos, linguagem sempre neutra.
 */
function RepurchaseSignal({ value }: { value: boolean | null }) {
  if (value === true) {
    return (
      <span className={`${styles.signal} ${styles.signalPossible}`}>
        Possível recompra
      </span>
    );
  }
  if (value === false) {
    return (
      <span className={`${styles.signal} ${styles.signalNone}`}>
        Sem sinal de recompra no momento
      </span>
    );
  }
  return (
    <span className={`${styles.signal} ${styles.signalUnknown}`}>
      Histórico insuficiente para avaliar
    </span>
  );
}

function formatAddress(c: Customer): string | null {
  const parts: string[] = [];
  if (c.street) {
    let line = c.street;
    if (c.number) line += `, ${c.number}`;
    if (c.complement) line += ` — ${c.complement}`;
    parts.push(line);
  }
  if (c.city || c.state) {
    parts.push([c.city, c.state].filter(Boolean).join(" — "));
  }
  if (c.zipCode) parts.push(`CEP: ${c.zipCode}`);
  return parts.length > 0 ? parts.join("\n") : null;
}

const CustomerDetailModal: React.FC<Props> = ({
  customer,
  orders,
  returns,
  loadingOrders,
  loadingReturns,
  loadingDetail,
  canRegisterFrictionNote,
  onAddFrictionNote,
  onClose,
}) => {
  const [tab, setTab] = useState<Tab>("orders");
  const [noteDraft, setNoteDraft] = useState("");
  const [submittingNote, setSubmittingNote] = useState(false);
  const address = formatAddress(customer);
  const insights = customer.insights;
  const frictionNotes = customer.frictionNotes ?? [];

  async function handleSubmitNote() {
    const trimmed = noteDraft.trim();
    if (trimmed.length < 3) return;
    if (!onAddFrictionNote) return;
    try {
      setSubmittingNote(true);
      await onAddFrictionNote(trimmed);
      setNoteDraft("");
    } finally {
      setSubmittingNote(false);
    }
  }

  return (
    <div className={modalStyles.overlay} onClick={onClose}>
      <div
        className={`${modalStyles.modal} ${styles.modal}`}
        onClick={(e) => e.stopPropagation()}
      >
        <div className={modalStyles.header}>
          <h3 className={modalStyles.title}>{customer.name}</h3>
          <button onClick={onClose} className={modalStyles.close}>
            &times;
          </button>
        </div>

        <div className={styles.section}>
          <div className={styles.sectionTitle}>Insights de Compra</div>
          {loadingDetail && !insights && (
            <div className={styles.loading}>Carregando insights...</div>
          )}
          {insights && (
            <>
              <div className={styles.infoGrid}>
                <div className={styles.infoItem}>
                  <span className={styles.infoLabel}>Total Gasto</span>
                  <span className={styles.infoValue}>{fmtBRL(insights.totalSpent)}</span>
                </div>
                <div className={styles.infoItem}>
                  <span className={styles.infoLabel}>Compras</span>
                  <span className={styles.infoValue}>{insights.orderCount}</span>
                </div>
                <div className={styles.infoItem}>
                  <span className={styles.infoLabel}>Ticket Médio</span>
                  <span className={styles.infoValue}>
                    {insights.averageTicket != null ? fmtBRL(insights.averageTicket) : "—"}
                  </span>
                </div>
                <div className={styles.infoItem}>
                  <span className={styles.infoLabel}>Última Compra</span>
                  <span className={styles.infoValue}>
                    {insights.lastOrderAt ? fmtDateTime(insights.lastOrderAt) : "—"}
                  </span>
                </div>
              </div>
              <div className={styles.signalRow}>
                <span className={styles.infoLabel}>Sinal de Recompra</span>
                <RepurchaseSignal value={insights.possibleRepurchase} />
              </div>
            </>
          )}
        </div>

        <hr className={styles.divider} />

        <div className={styles.section}>
          <div className={styles.sectionTitle}>Informações</div>
          <div className={styles.infoGrid}>
            <div className={styles.infoItem}>
              <span className={styles.infoLabel}>Celular</span>
              <span className={styles.infoValue}>{customer.phone}</span>
            </div>
            <div className={styles.infoItem}>
              <span className={styles.infoLabel}>Email</span>
              <span className={styles.infoValue}>{customer.email || "—"}</span>
            </div>
            <div className={styles.infoItem}>
              <span className={styles.infoLabel}>Instagram</span>
              <span className={styles.infoValue}>{customer.instagram || "—"}</span>
            </div>
          </div>
        </div>

        {address && (
          <>
            <hr className={styles.divider} />
            <div className={styles.section}>
              <div className={styles.sectionTitle}>Endereço</div>
              <div className={styles.addressBlock}>
                {address.split("\n").map((line, i) => (
                  <div key={i}>{line}</div>
                ))}
              </div>
            </div>
          </>
        )}

        <hr className={styles.divider} />

        <div className={styles.tabs}>
          <button
            className={`${styles.tab} ${tab === "orders" ? styles.tabActive : ""}`}
            onClick={() => setTab("orders")}
          >
            Pedidos {orders && `(${orders.length})`}
          </button>
          <button
            className={`${styles.tab} ${tab === "returns" ? styles.tabActive : ""}`}
            onClick={() => setTab("returns")}
          >
            Garantias {returns && `(${returns.length})`}
          </button>
        </div>

        {tab === "orders" && (
          <div className={styles.section}>
            {loadingOrders && (
              <div className={styles.loading}>Carregando pedidos...</div>
            )}

            {!loadingOrders && orders && orders.length === 0 && (
              <div className={styles.emptyOrders}>Nenhum pedido encontrado.</div>
            )}

            {!loadingOrders && orders && orders.length > 0 && (
              <table className={styles.ordersTable}>
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Data</th>
                    <th>Produto</th>
                    <th>Itens</th>
                    <th>Total</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  {orders.map((o) => (
                    <tr key={o.id}>
                      <td>{o.id}</td>
                      <td>{fmtDate(o.saleDate)}</td>
                      <td>{o.productName}</td>
                      <td>{o.itemsCount}</td>
                      <td>{fmtBRL(o.salePrice - o.discount)}</td>
                      <td><Badge status={o.status} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        )}

        {tab === "returns" && (
          <div className={styles.section}>
            {loadingReturns && (
              <div className={styles.loading}>Carregando garantias...</div>
            )}

            {!loadingReturns && returns && returns.length === 0 && (
              <div className={styles.emptyOrders}>Nenhuma garantia encontrada.</div>
            )}

            {!loadingReturns && returns && returns.length > 0 && (
              <table className={styles.ordersTable}>
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Produto</th>
                    <th>Custo Total</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  {returns.map((r) => (
                    <tr key={r.id}>
                      <td>{r.id}</td>
                      <td>{fmtDate(r.receivedDate || r.createdAt)}</td>
                      <td>{r.typeLabel}</td>
                      <td>{r.items.length > 0 ? r.items[0].productName : "—"}</td>
                      <td>{fmtBRL(r.totalCost)}</td>
                      <td><Badge status={r.status} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        )}

        <hr className={styles.divider} />

        <div className={styles.section}>
          <div className={styles.sectionTitle}>Histórico de Atrito</div>
          {frictionNotes.length === 0 && (
            <div className={styles.emptyOrders}>Nenhum registro de atrito.</div>
          )}
          {frictionNotes.length > 0 && (
            <div className={styles.frictionList}>
              {frictionNotes.map((n) => (
                <div key={n.id} className={styles.frictionItem}>
                  <div className={styles.frictionMeta}>
                    <span>{n.createdByUserName ?? "—"}</span>
                    <span>{fmtDateTime(n.createdAt)}</span>
                  </div>
                  <div className={styles.frictionText}>{n.note}</div>
                </div>
              ))}
            </div>
          )}

          {canRegisterFrictionNote && (
            <div className={styles.frictionForm}>
              <textarea
                className={styles.frictionTextarea}
                placeholder="Registrar observação (ex.: motivo de atraso, comportamento na negociação)..."
                value={noteDraft}
                onChange={(e) => setNoteDraft(e.target.value)}
              />
              <Btn
                small
                variant="secondary"
                onClick={handleSubmitNote}
                disabled={submittingNote || noteDraft.trim().length < 3}
              >
                {submittingNote ? "Registrando..." : "Registrar"}
              </Btn>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default CustomerDetailModal;
