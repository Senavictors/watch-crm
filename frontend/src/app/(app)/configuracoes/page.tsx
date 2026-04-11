"use client";
import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiFetch, apiCreate, getApiBaseUrl } from "../../../features/crm/api";
import { Brand, Quality } from "../../../features/crm/types";
import Settings from "../../../features/crm/views/Settings";

export default function ConfiguracoesPage() {
  const { handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const [brands, setBrands] = useState<Brand[]>([]);
  const [qualities, setQualities] = useState<Quality[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const apiBaseUrl = getApiBaseUrl();
    let alive = true;

    async function load() {
      try {
        setLoading(true);
        const [brandsRes, qualitiesRes] = await Promise.all([
          apiFetch(`${apiBaseUrl}/brands`),
          apiFetch(`${apiBaseUrl}/qualities`),
        ]);
        if (brandsRes.status === 401 || qualitiesRes.status === 401) { handleUnauthorized(); return; }
        if (!brandsRes.ok || !qualitiesRes.ok) throw new Error("Falha ao carregar configurações.");
        const [brandsData, qualitiesData] = await Promise.all([brandsRes.json(), qualitiesRes.json()]);
        if (!alive) return;
        setBrands(brandsData);
        setQualities(qualitiesData);
      } catch (err) {
        if (alive) pushToast(err instanceof Error ? err.message : "Erro.", "error");
      } finally {
        if (alive) setLoading(false);
      }
    }

    load();
    return () => { alive = false; };
  }, []);

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

  if (loading) return <div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>;

  return (
    <Settings
      brands={brands}
      qualities={qualities}
      onAddBrand={handleAddBrand}
      onAddQuality={handleAddQuality}
      onToast={pushToast}
    />
  );
}
