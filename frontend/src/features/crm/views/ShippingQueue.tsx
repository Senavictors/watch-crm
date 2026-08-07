import React from "react";
import { fmtBRL, fmtDate } from "../helpers";
import { PostingDaySchedule, ProductReturn, ShippingQueueItem } from "../types";
import { RETURN_TYPE_COLORS } from "../data/mock";
import { Card } from "../ui/Primitives";
import styles from "./ShippingQueue.module.css";

const TYPE_LABELS: Record<string, string> = {
  garantia: "Garantia",
  troca: "Troca",
  devolucao: "Devolução",
};

type Props = {
  queue: ShippingQueueItem[];
  schedule: PostingDaySchedule[];
  pendingReturns?: ProductReturn[];
};

/**
 * Próximo dia habilitado a partir de `today` (inclusive) — só percorre a
 * agenda já carregada (`schedule`), não recalcula nada que o backend já não
 * tenha decidido (quais dias estão habilitados); é o mesmo tipo de
 * formatação de exibição que `fmtDate` já faz, não uma regra de negócio
 * nova. Usado só para o aviso do cabeçalho ("hoje não é dia de postagem, a
 * próxima é X") — diferente do `nextPostingDate` de cada pedido, que é
 * calculado no backend a partir do `paid_at` daquele pedido específico.
 */
function nextEnabledDateFrom(today: Date, schedule: PostingDaySchedule[]): string | null {
  const enabledWeekdays = new Set(schedule.filter((s) => s.enabled).map((s) => s.weekday));
  if (enabledWeekdays.size === 0) return null;

  const cursor = new Date(today);
  cursor.setHours(0, 0, 0, 0);
  for (let i = 0; i < 7; i++) {
    if (enabledWeekdays.has(cursor.getDay())) {
      return cursor.toISOString().slice(0, 10);
    }
    cursor.setDate(cursor.getDate() + 1);
  }
  return null;
}

const ShippingQueue: React.FC<Props> = ({ queue, schedule, pendingReturns = [] }) => {
  const today = new Date();
  const todaySchedule = schedule.find((s) => s.weekday === today.getDay());
  const isShippingDay = todaySchedule?.enabled ?? false;

  // Próxima data de postagem a partir de HOJE (calendário), não a data do
  // pedido mais atrasado da fila — evita mostrar uma data passada quando há
  // pedidos atrasados (ver TASK-016, achado da validação manual).
  const nextPostingDate = isShippingDay ? null : nextEnabledDateFrom(today, schedule);

  return (
    <div>
      <h2 className={styles.title}>Fila de Envios</h2>
      <div className={styles.subtitle}>
        Hoje é {todaySchedule?.label ?? "—"} —{" "}
        {isShippingDay ? (
          <span className={styles.subtitleHighlight}>✅ Dia de postagem!</span>
        ) : (
          <span className={styles.subtitleMuted}>
            ⚠️ Próxima postagem: {nextPostingDate ? fmtDate(nextPostingDate) : "—"}
          </span>
        )}
      </div>

      {queue.length === 0 ? (
        <Card>
          <div className={styles.empty}>Nenhum pedido pronto para envio</div>
        </Card>
      ) : (
        <div className={styles.list}>
          {queue.map((item) => (
            <Card key={item.id} className={styles.item}>
              <div className={styles.iconBox}>📦</div>
              <div className={styles.info}>
                <div className={styles.infoTitle}>
                  #{item.id} — {item.productName}
                </div>
                <div className={styles.infoMeta}>
                  {item.customerName} · {item.itemsCount} item(ns) · {item.shippingMethod} · {item.channel}
                </div>
              </div>
              <div className={styles.right}>
                <div className={styles.rightTitle}>
                  Postar em: {fmtDate(item.nextPostingDate ?? undefined)}
                  {item.isLate && <span className={styles.lateBadge}>Atrasado</span>}
                </div>
                <div className={styles.rightSub}>{fmtBRL(item.freight)} frete</div>
              </div>
              <div className={styles.check} title="Marcar como postado" />
            </Card>
          ))}
        </div>
      )}

      {pendingReturns.length > 0 && (
        <div className={styles.returnsSection}>
          <h3 className={styles.returnsTitle}>Reenvios — Garantias/Trocas</h3>
          <div className={styles.list}>
            {pendingReturns.map((r) => {
              const typeColor = RETURN_TYPE_COLORS[r.type] ?? "var(--crm-text-soft)";
              const firstItem = r.items[0];
              return (
                <Card key={`return-${r.id}`} className={styles.item}>
                  <div className={styles.iconBox}>🔄</div>
                  <div className={styles.info}>
                    <div className={styles.infoTitle}>
                      Garantia #{r.id} — {firstItem?.productName ?? "—"}
                      {r.items.length > 1 ? ` +${r.items.length - 1}` : ""}
                    </div>
                    <div className={styles.infoMeta}>
                      {r.customerName} · {r.customerPhone}
                      <span
                        className={styles.returnTypeBadge}
                        style={{ background: `${typeColor}22`, color: typeColor, borderColor: `${typeColor}44` }}
                      >
                        {TYPE_LABELS[r.type] ?? r.type}
                      </span>
                    </div>
                  </div>
                  <div className={styles.right}>
                    <div className={styles.rightTitle}>
                      {r.returnTrackingCode ? `Rastreio: ${r.returnTrackingCode}` : "Sem rastreio"}
                    </div>
                    <div className={styles.rightSub}>Custo frete: {fmtBRL(r.freightCostOut)}</div>
                  </div>
                </Card>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
};

export default ShippingQueue;
