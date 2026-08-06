"use client";
import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiFetch, apiCreate, getApiBaseUrl } from "../../../features/crm/api";
import { Brand, Category, Quality } from "../../../features/crm/types";
import Settings from "../../../features/crm/views/Settings";

export default function ConfiguracoesPage() {
  const { handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const [brands, setBrands] = useState<Brand[]>([]);
  const [qualities, setQualities] = useState<Quality[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const apiBaseUrl = getApiBaseUrl();
    let alive = true;

    async function load() {
      try {
        setLoading(true);
        const [brandsRes, qualitiesRes, categoriesRes] = await Promise.all([
          apiFetch(`${apiBaseUrl}/brands`),
          apiFetch(`${apiBaseUrl}/qualities`),
          apiFetch(`${apiBaseUrl}/categories`),
        ]);
        if (brandsRes.status === 401 || qualitiesRes.status === 401 || categoriesRes.status === 401) { handleUnauthorized(); return; }
        if (!brandsRes.ok || !qualitiesRes.ok || !categoriesRes.ok) throw new Error("Falha ao carregar configurações.");
        const [brandsData, qualitiesData, categoriesData] = await Promise.all([
          brandsRes.json(),
          qualitiesRes.json(),
          categoriesRes.json(),
        ]);
        if (!alive) return;
        setBrands(brandsData);
        setQualities(qualitiesData);
        setCategories(categoriesData);
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

  async function handleAddCategory(name: string, hasQuality: boolean) {
    try {
      const created = await apiCreate<Category>("/categories", { name, hasQuality }, "Falha ao cadastrar categoria.");
      setCategories((cs) => [created, ...cs]);
      pushToast("Categoria cadastrada com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  if (loading) return <div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>;

  return (
    <Settings
      brands={brands}
      qualities={qualities}
      categories={categories}
      onAddBrand={handleAddBrand}
      onAddQuality={handleAddQuality}
      onAddCategory={handleAddCategory}
      onToast={pushToast}
    />
  );
}
