"use client";
import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiFetch, apiCreate, apiUpdate, getApiBaseUrl } from "../../../features/crm/api";
import { Brand, Product, ProductInput, WatchModel } from "../../../features/crm/types";
import Products from "../../../features/crm/views/Products";
import NewProductForm from "../../../features/crm/views/NewProductForm";

export default function ProdutosPage() {
  const { hasPermission, handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const [products, setProducts] = useState<Product[]>([]);
  const [brands, setBrands] = useState<Brand[]>([]);
  const [models, setModels] = useState<WatchModel[]>([]);
  const [loading, setLoading] = useState(true);
  const [showNew, setShowNew] = useState(false);
  const [editing, setEditing] = useState<Product | null>(null);

  const canCreate = hasPermission("products.create");
  const canUpdate = hasPermission("products.update");
  const canManageCatalog = hasPermission("settings.view");

  useEffect(() => {
    const apiBaseUrl = getApiBaseUrl();
    let alive = true;

    async function load() {
      try {
        setLoading(true);
        const requests: Promise<Response>[] = [apiFetch(`${apiBaseUrl}/products`)];
        if (canCreate || canUpdate) {
          requests.push(apiFetch(`${apiBaseUrl}/brands`), apiFetch(`${apiBaseUrl}/models`));
        }
        const responses = await Promise.all(requests);
        if (responses.some((r) => r.status === 401)) { handleUnauthorized(); return; }
        if (responses.some((r) => !r.ok)) throw new Error("Falha ao carregar produtos.");
        const [productsData, brandsData, modelsData] = await Promise.all(responses.map((r) => r.json()));
        if (!alive) return;
        setProducts(productsData);
        if (brandsData) setBrands(brandsData);
        if (modelsData) setModels(modelsData);
      } catch (err) {
        if (alive) pushToast(err instanceof Error ? err.message : "Erro.", "error");
      } finally {
        if (alive) setLoading(false);
      }
    }

    load();
    return () => { alive = false; };
  }, []);

  async function handleSave(data: ProductInput) {
    try {
      const created = await apiCreate<Product>("/products", data, "Falha ao cadastrar produto.");
      setProducts((ps) => [created, ...ps]);
      setShowNew(false);
      pushToast("Produto cadastrado com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  async function handleUpdate(data: ProductInput) {
    if (!editing) return;
    try {
      const updated = await apiUpdate<Product>(`/products/${editing.id}`, data, "Falha ao atualizar produto.");
      setProducts((ps) => ps.map((p) => (p.id === updated.id ? updated : p)));
      setEditing(null);
      pushToast("Produto atualizado com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  if (loading) return <div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>;

  return (
    <>
      <Products
        products={products}
        canCreate={canCreate}
        canUpdate={canUpdate}
        compact={!canManageCatalog}
        onNew={() => setShowNew(true)}
        onEdit={setEditing}
      />
      {showNew && (
        <NewProductForm brands={brands} models={models} onSave={handleSave} onClose={() => setShowNew(false)} onToast={pushToast} />
      )}
      {editing && (
        <NewProductForm product={editing} brands={brands} models={models} onSave={handleUpdate} onClose={() => setEditing(null)} onToast={pushToast} />
      )}
    </>
  );
}
