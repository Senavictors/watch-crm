"use client";
import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiDelete, apiFetch, apiCreate, apiUpdate, getApiBaseUrl } from "../../../features/crm/api";
import { AiSettingsResponse, Brand, Category, PostingDaySchedule, Quality } from "../../../features/crm/types";
import Settings from "../../../features/crm/views/Settings";

export default function ConfiguracoesPage() {
  const { hasPermission, handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const [brands, setBrands] = useState<Brand[]>([]);
  const [qualities, setQualities] = useState<Quality[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [schedule, setSchedule] = useState<PostingDaySchedule[]>([]);
  const [aiSettings, setAiSettings] = useState<AiSettingsResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const canViewAiSettings = hasPermission("ai.settings.view");
  const canUpdateAiSettings = hasPermission("ai.settings.update");

  useEffect(() => {
    const apiBaseUrl = getApiBaseUrl();
    let alive = true;

    async function load() {
      try {
        setLoading(true);
        const [brandsRes, qualitiesRes, categoriesRes, scheduleRes, aiSettingsRes] = await Promise.all([
          apiFetch(`${apiBaseUrl}/brands`),
          apiFetch(`${apiBaseUrl}/qualities`),
          apiFetch(`${apiBaseUrl}/categories`),
          apiFetch(`${apiBaseUrl}/shipping/schedule`),
          canViewAiSettings ? apiFetch(`${apiBaseUrl}/ai/settings`) : Promise.resolve(null),
        ]);
        if (brandsRes.status === 401 || qualitiesRes.status === 401 || categoriesRes.status === 401 || scheduleRes.status === 401 || aiSettingsRes?.status === 401) { handleUnauthorized(); return; }
        if (!brandsRes.ok || !qualitiesRes.ok || !categoriesRes.ok || !scheduleRes.ok || (aiSettingsRes && !aiSettingsRes.ok)) throw new Error("Falha ao carregar configurações.");
        const [brandsData, qualitiesData, categoriesData, scheduleData, aiSettingsData] = await Promise.all([
          brandsRes.json(),
          qualitiesRes.json(),
          categoriesRes.json(),
          scheduleRes.json(),
          aiSettingsRes ? aiSettingsRes.json() : Promise.resolve(null),
        ]);
        if (!alive) return;
        setBrands(brandsData);
        setQualities(qualitiesData);
        setCategories(categoriesData);
        setSchedule(scheduleData);
        setAiSettings(aiSettingsData);
      } catch (err) {
        if (alive) pushToast(err instanceof Error ? err.message : "Erro.", "error");
      } finally {
        if (alive) setLoading(false);
      }
    }

    load();
    return () => { alive = false; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [canViewAiSettings]);

  async function handleAddBrand(name: string) {
    try {
      const created = await apiCreate<Brand>("/brands", { name }, "Falha ao cadastrar marca.");
      setBrands((bs) => [created, ...bs]);
      pushToast("Marca cadastrada com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  async function handleAddQuality(name: string) {
    try {
      const created = await apiCreate<Quality>("/qualities", { name }, "Falha ao cadastrar qualidade.");
      setQualities((qs) => [created, ...qs]);
      pushToast("Qualidade cadastrada com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  async function handleAddCategory(name: string, hasQuality: boolean) {
    try {
      const created = await apiCreate<Category>("/categories", { name, hasQuality }, "Falha ao cadastrar categoria.");
      setCategories((cs) => [created, ...cs]);
      pushToast("Categoria cadastrada com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  async function handleSaveSchedule(days: { weekday: number; enabled: boolean }[]) {
    try {
      const updated = await apiUpdate<PostingDaySchedule[]>(
        "/shipping/schedule",
        { days },
        "Falha ao salvar dias de postagem.",
        "PUT"
      );
      setSchedule(updated);
      pushToast("Dias de postagem atualizados com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
      throw err;
    }
  }

  async function handleSaveAiSettings(settings: { apiKey: string | null; projectId: string | null; model: string; enabled: boolean }) {
    try {
      const updated = await apiUpdate<AiSettingsResponse>(
        "/ai/settings",
        settings,
        "Falha ao salvar a integração OpenAI.",
        "PUT"
      );
      setAiSettings(updated);
      pushToast("Integração OpenAI atualizada com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
      throw err;
    }
  }

  async function handleRemoveAiKey() {
    try {
      await apiDelete("/ai/settings/key", "Falha ao remover a chave da OpenAI.");
      const apiBaseUrl = getApiBaseUrl();
      const response = await apiFetch(`${apiBaseUrl}/ai/settings`);
      if (!response.ok) throw new Error("Falha ao atualizar o estado da integração.");
      setAiSettings((await response.json()) as AiSettingsResponse);
      pushToast("Chave da OpenAI removida.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
      throw err;
    }
  }

  if (loading) return <div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>;

  return (
    <Settings
      brands={brands}
      qualities={qualities}
      categories={categories}
      schedule={schedule}
      canEditSchedule={hasPermission("shipping.update")}
      aiSettings={aiSettings}
      canUpdateAiSettings={canUpdateAiSettings}
      onAddBrand={handleAddBrand}
      onAddQuality={handleAddQuality}
      onAddCategory={handleAddCategory}
      onSaveSchedule={handleSaveSchedule}
      onSaveAiSettings={handleSaveAiSettings}
      onRemoveAiKey={handleRemoveAiKey}
      onToast={pushToast}
    />
  );
}
