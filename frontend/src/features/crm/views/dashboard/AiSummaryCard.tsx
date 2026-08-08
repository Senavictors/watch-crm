"use client";

import { AiSummaryResponse } from "../../types";
import { Btn, Card } from "../../ui/Primitives";
import styles from "./AiSummaryCard.module.css";

type Props = {
  summary: AiSummaryResponse | null;
  loading: boolean;
  error: string | null;
  onGenerate: () => void;
};

function formatGeneratedAt(value: string) {
  return new Intl.DateTimeFormat("pt-BR", {
    dateStyle: "short",
    timeStyle: "short",
    timeZone: "America/Sao_Paulo",
  }).format(new Date(value));
}

export default function AiSummaryCard({ summary, loading, error, onGenerate }: Props) {
  return (
    <Card className={styles.card}>
      <div className={styles.header}>
        <div>
          <div className={styles.title}>Resumo inteligente</div>
          <div className={styles.subtitle}>Fatos priorizados pela IA; números calculados pelo Watch CRM.</div>
        </div>
        <Btn onClick={onGenerate} variant="primary" small disabled={loading}>
          {loading ? "Gerando..." : summary ? "Atualizar resumo" : "Gerar resumo"}
        </Btn>
      </div>

      {error && <div className={styles.unavailable}>Resumo indisponível.</div>}

      {!error && !summary && (
        <div className={styles.empty}>Nenhum resumo foi gerado para este período.</div>
      )}

      {!error && summary && (
        <>
          <ol className={styles.list}>
            {summary.items.map((item) => (
              <li key={item.id} className={styles.item}>
                <div className={styles.statement}>{item.text}</div>
                <div className={styles.sources} aria-label="Fontes do fato">
                  {item.sources.map((source) => (
                    <span key={`${item.id}-${source.label}`} className={styles.source}>
                      {source.label}: <strong>{source.value}</strong>
                    </span>
                  ))}
                </div>
              </li>
            ))}
          </ol>
          <div className={styles.footer}>
            Período: {summary.period.from.split("-").reverse().join("/")} — {summary.period.to.split("-").reverse().join("/")}
            {" · "}dados de {formatGeneratedAt(summary.generatedAt)}
          </div>
        </>
      )}
    </Card>
  );
}
