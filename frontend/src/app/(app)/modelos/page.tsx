"use client";
import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiFetch, ensureCsrfCookie, getErrorMessage, getApiBaseUrl } from "../../../features/crm/api";
import { Brand, Quality, WatchModel } from "../../../features/crm/types";
import Models from "../../../features/crm/views/Models";
import NewModelForm from "../../../features/crm/views/NewModelForm";

export default function ModelosPage() {
  const { hasPermission, handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const [models, setModels] = useState<WatchModel[]>([]);
  const [brands, setBrands] = useState<Brand[]>([]);
  const [qualities, setQualities] = useState<Quality[]>([]);
  const [loading, setLoading] = useState(true);
  const [showNew, setShowNew] = useState(false);

  useEffect(() => {
    const apiBaseUrl = getApiBaseUrl();
    let alive = true;

    async function load() {
      try {
        setLoading(true);
        const [modelsRes, brandsRes, qualitiesRes] = await Promise.all([
          apiFetch(`${apiBaseUrl}/models`),
          apiFetch(`${apiBaseUrl}/brands`),
          apiFetch(`${apiBaseUrl}/qualities`),
        ]);
        if ([modelsRes, brandsRes, qualitiesRes].some((r) => r.status === 401)) { handleUnauthorized(); return; }
        if (!modelsRes.ok || !brandsRes.ok || !qualitiesRes.ok) throw new Error("Falha ao carregar modelos.");
        const [modelsData, brandsData, qualitiesData] = await Promise.all([modelsRes.json(), brandsRes.json(), qualitiesRes.json()]);
        if (!alive) return;
        setModels(modelsData);
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

  async function handleSave(data: Omit<WatchModel, "id" | "imageUrl"> & { imageFile?: File | null }) {
    try {
      const apiBaseUrl = getApiBaseUrl();
      await ensureCsrfCookie(apiBaseUrl);
      const formData = new FormData();
      formData.append("name", data.name);
      formData.append("brandId", String(data.brandId));
      formData.append("productType", data.productType);
      if (data.qualityId !== null) formData.append("qualityId", String(data.qualityId));
      if (data.imageFile) formData.append("image", data.imageFile);

      const response = await apiFetch(`${apiBaseUrl}/models`, { method: "POST", body: formData }, { csrf: true });
      if (!response.ok) throw new Error(await getErrorMessage(response, "Falha ao cadastrar modelo."));

      const created = (await response.json()) as WatchModel;
      setModels((ms) => [created, ...ms]);
      setShowNew(false);
      pushToast("Modelo cadastrado com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  if (loading) return <div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>;

  return (
    <>
      <Models models={models} canCreate={hasPermission("models.create")} onNew={() => setShowNew(true)} />
      {showNew && (
        <NewModelForm brands={brands} qualities={qualities} onSave={handleSave} onClose={() => setShowNew(false)} onToast={pushToast} />
      )}
    </>
  );
}
