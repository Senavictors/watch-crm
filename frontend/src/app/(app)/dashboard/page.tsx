"use client";
import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiFetch, getApiBaseUrl, getErrorMessage } from "../../../features/crm/api";
import { DashboardSummaryResponse } from "../../../features/crm/types";
import Dashboard from "../../../features/crm/views/Dashboard";
import { PeriodPresetId, resolvePresetRange } from "../../../features/crm/views/dashboard/periodPresets";

const AUTO_REFRESH_INTERVAL_MS = 5 * 60 * 1000; // RN-03: atualização automática a cada 5 minutos.

export default function DashboardPage() {
  const { handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const router = useRouter();

  const [preset, setPreset] = useState<PeriodPresetId>("thisMonth");
  // Pré-preenche "Personalizado" com o mês atual, pra não abrir vazio caso o
  // usuário troque de preset sem escolher datas ainda.
  const defaultRange = resolvePresetRange("thisMonth");
  const [customFrom, setCustomFrom] = useState(defaultRange?.from ?? "");
  const [customTo, setCustomTo] = useState(defaultRange?.to ?? "");
  const [summary, setSummary] = useState<DashboardSummaryResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [reloadToken, setReloadToken] = useState(0);

  const range = preset === "custom" ? { from: customFrom, to: customTo } : resolvePresetRange(preset);
  const from = range?.from ?? "";
  const to = range?.to ?? "";

  // Distingue troca de período (mostra o placeholder de carregamento
  // inteiro) de refresh manual/automático no mesmo período (mantém os
  // dados na tela e só sinaliza "Atualizando...", CA-03).
  const prevRangeRef = useRef<{ from: string; to: string } | null>(null);

  useEffect(() => {
    if (!from || !to) return; // "Personalizado" sem as duas datas ainda: não busca.

    const apiBaseUrl = getApiBaseUrl();
    let alive = true;
    const periodChanged = prevRangeRef.current?.from !== from || prevRangeRef.current?.to !== to;
    prevRangeRef.current = { from, to };

    async function loadSummary() {
      if (periodChanged || !summary) {
        setLoading(true);
      } else {
        setRefreshing(true);
      }
      setError(null);

      try {
        const params = new URLSearchParams({ from, to });
        const response = await apiFetch(`${apiBaseUrl}/dashboard/summary?${params.toString()}`);

        if (response.status === 401) {
          handleUnauthorized();
          return;
        }

        if (!response.ok) {
          const fallback = response.status === 422 ? "Período inválido." : "Falha ao carregar o dashboard.";
          throw new Error(await getErrorMessage(response, fallback));
        }

        const data = (await response.json()) as DashboardSummaryResponse;
        if (!alive) return;
        setSummary(data);
      } catch (err) {
        if (!alive) return;
        const message = err instanceof Error ? err.message : "Erro ao carregar o dashboard.";
        setError(message);
        pushToast(message, "error");
      } finally {
        if (alive) {
          setLoading(false);
          setRefreshing(false);
        }
      }
    }

    loadSummary();
    return () => { alive = false; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [from, to, reloadToken]);

  // RN-03: atualização automática a cada 5 minutos.
  useEffect(() => {
    const interval = setInterval(() => setReloadToken((t) => t + 1), AUTO_REFRESH_INTERVAL_MS);
    return () => clearInterval(interval);
  }, []);

  function handleCategoryClick(category: string) {
    // CA-02: usa o período efetivamente resolvido pela API (`period`), não o
    // `from`/`to` local — evita divergência se o backend normalizar as datas.
    const params = new URLSearchParams({ category });
    const periodFrom = summary?.period.from ?? from;
    const periodTo = summary?.period.to ?? to;
    if (periodFrom) params.set("from", periodFrom);
    if (periodTo) params.set("to", periodTo);
    router.push(`/pedidos?${params.toString()}`);
  }

  if (loading) return <div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>;

  return (
    <Dashboard
      summary={summary}
      refreshing={refreshing}
      error={error}
      preset={preset}
      customFrom={customFrom}
      customTo={customTo}
      onPresetChange={setPreset}
      onCustomFromChange={setCustomFrom}
      onCustomToChange={setCustomTo}
      onRefresh={() => setReloadToken((t) => t + 1)}
      onCategoryClick={handleCategoryClick}
    />
  );
}
