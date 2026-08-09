"use client";
import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiFetch, ensureCsrfCookie, getErrorMessage, getApiBaseUrl } from "../../../features/crm/api";
import { Brand, Category, PaginatedResponse, PaginationMeta, Quality, WatchModel } from "../../../features/crm/types";
import Models from "../../../features/crm/views/Models";
import NewModelForm from "../../../features/crm/views/NewModelForm";
import PaginationBar from "../../../features/crm/ui/PaginationBar";
import { EMPTY_PAGINATION, appendPagination } from "../../../features/crm/pagination";
import { useDebouncedValue } from "../../../features/crm/hooks/useDebouncedValue";

export default function ModelosPage() {
  const { hasPermission, handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const [models, setModels] = useState<WatchModel[]>([]);
  const [brands, setBrands] = useState<Brand[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [qualities, setQualities] = useState<Quality[]>([]);
  const [loading, setLoading] = useState(true);
  const [showNew, setShowNew] = useState(false);
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState<PaginationMeta>(EMPTY_PAGINATION);
  const [reloadKey, setReloadKey] = useState(0);
  const debouncedSearch = useDebouncedValue(search);

  const canCreate = hasPermission("models.create");

  useEffect(() => {
    const apiBaseUrl = getApiBaseUrl();
    let alive = true;

    async function load() {
      try {
        setLoading(true);
        const params = appendPagination(new URLSearchParams(), page);
        if (debouncedSearch) params.set("search", debouncedSearch);
        const requests: Promise<Response>[] = [apiFetch(`${apiBaseUrl}/models?${params}`)];
        if (canCreate) {
          requests.push(
            apiFetch(`${apiBaseUrl}/brands`),
            apiFetch(`${apiBaseUrl}/qualities`),
            apiFetch(`${apiBaseUrl}/categories`)
          );
        }
        const responses = await Promise.all(requests);
        if (responses.some((r) => r.status === 401)) { handleUnauthorized(); return; }
        if (responses.some((r) => !r.ok)) throw new Error("Falha ao carregar modelos.");
        const [modelsData, brandsData, qualitiesData, categoriesData] = await Promise.all(responses.map((r) => r.json()));
        if (!alive) return;
        const paginated = modelsData as PaginatedResponse<WatchModel>;
        setModels(paginated.data);
        setMeta(paginated.meta);
        if (brandsData) setBrands(brandsData);
        if (qualitiesData) setQualities(qualitiesData);
        if (categoriesData) setCategories(categoriesData);
      } catch (err) {
        if (alive) pushToast(err instanceof Error ? err.message : "Erro.", "error");
      } finally {
        if (alive) setLoading(false);
      }
    }

    load();
    return () => { alive = false; };
  }, [canCreate, debouncedSearch, handleUnauthorized, page, pushToast, reloadKey]);

  useEffect(() => { setPage(1); }, [debouncedSearch]);

  async function handleSave(data: Omit<WatchModel, "id" | "imageUrl"> & { imageFile?: File | null }) {
    try {
      const apiBaseUrl = getApiBaseUrl();
      await ensureCsrfCookie(apiBaseUrl);
      const formData = new FormData();
      formData.append("name", data.name);
      formData.append("brandId", String(data.brandId));
      formData.append("categoryId", String(data.categoryId));
      if (data.qualityId !== null) formData.append("qualityId", String(data.qualityId));
      if (data.imageFile) formData.append("image", data.imageFile);

      const response = await apiFetch(`${apiBaseUrl}/models`, { method: "POST", body: formData }, { csrf: true });
      if (!response.ok) throw new Error(await getErrorMessage(response, "Falha ao cadastrar modelo."));

      await response.json();
      setShowNew(false);
      setPage(1);
      setReloadKey((key) => key + 1);
      pushToast("Modelo cadastrado com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  if (loading) return <div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>;

  return (
    <>
      <Models
        models={models}
        search={search}
        onSearchChange={setSearch}
        canCreate={canCreate}
        onNew={() => setShowNew(true)}
      />
      <PaginationBar meta={meta} onPageChange={setPage} disabled={loading} />
      {showNew && (
        <NewModelForm
          brands={brands}
          categories={categories}
          qualities={qualities}
          onSave={handleSave}
          onClose={() => setShowNew(false)}
          onToast={pushToast}
        />
      )}
    </>
  );
}
