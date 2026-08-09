"use client";

import { useState } from "react";
import { ChevronDown, ChevronUp } from "lucide-react";
import { AiSummaryResponse } from "../../types";
import { Btn, Card } from "../../ui/Primitives";
import styles from "./AiSummaryCard.module.css";

type Props = {
  summary: AiSummaryResponse | null;
  loading: boolean;
  error: string | null;
  onGenerate: () => void;
};

const TYPE_LABELS: Record<AiSummaryResponse["items"][number]["type"], string> = {
  currency: "Financeiro",
  quantity: "Volume",
  percentage: "Taxa",
  count: "Operação",
};

function formatGeneratedAt(value: string) {
  return new Intl.DateTimeFormat("pt-BR", {
    dateStyle: "short",
    timeStyle: "short",
    timeZone: "America/Sao_Paulo",
  }).format(new Date(value));
}

export default function AiSummaryCard({ summary, loading, error, onGenerate }: Props) {
  const [expanded, setExpanded] = useState(true);

  return (
    <Card className={styles.card}>
      <div className={styles.header}>
        <div>
          <div className={styles.eyebrow}>CURADORIA POR IA</div>
          <div className={styles.title}>Resumo inteligente</div>
          <div className={styles.subtitle}>Leitura gerencial priorizada pela IA, com números calculados pelo Watch CRM.</div>
        </div>
        <div className={styles.actions}>
          <button
            type="button"
            className={styles.toggleButton}
            onClick={() => setExpanded((current) => !current)}
            aria-expanded={expanded}
            aria-controls="ai-summary-content"
          >
            {expanded ? <ChevronUp size={15} aria-hidden="true" /> : <ChevronDown size={15} aria-hidden="true" />}
            {expanded ? "Recolher" : "Expandir"}
          </button>
          <Btn onClick={onGenerate} variant="primary" small disabled={loading}>
            {loading ? "Gerando..." : summary ? "Atualizar resumo" : "Gerar resumo"}
          </Btn>
        </div>
      </div>

      <div id="ai-summary-content" hidden={!expanded}>
        {error && <div className={styles.unavailable}>Resumo indisponível.</div>}

        {!error && !summary && (
          <div className={styles.empty}>Nenhum resumo foi gerado para este período.</div>
        )}

        {!error && summary && (
          <>
            <div className={styles.list}>
              {summary.items.map((item, index) => (
                <article key={item.id} className={styles.item}>
                  <div className={styles.index}>{String(index + 1).padStart(2, "0")}</div>
                  <div className={styles.itemBody}>
                    <span className={styles.type}>{TYPE_LABELS[item.type]}</span>
                    <div className={styles.statement}>{item.text}</div>
                    <div className={styles.sources} aria-label="Fontes do fato">
                      {item.sources.map((source) => (
                        <span key={`${item.id}-${source.label}`} className={styles.source}>
                          {source.label}: <strong>{source.value}</strong>
                        </span>
                      ))}
                    </div>
                  </div>
                </article>
              ))}
            </div>
            <div className={styles.footer}>
              Período: {summary.period.from.split("-").reverse().join("/")} — {summary.period.to.split("-").reverse().join("/")}
              {" · "}dados de {formatGeneratedAt(summary.generatedAt)}
            </div>
          </>
        )}
      </div>
    </Card>
  );
}
