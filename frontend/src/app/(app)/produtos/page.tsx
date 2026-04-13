"use client";
import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiFetch, apiCreate, apiUpdate, apiDelete, getApiBaseUrl, ensureCsrfCookie, getErrorMessage } from "../../../features/crm/api";
import { Brand, Product, ProductInput, WatchModel } from "../../../features/crm/types";
import Products from "../../../features/crm/views/Products";
import NewProductForm from "../../../features/crm/views/NewProductForm";
import modalStyles from "../../../features/crm/components/Modal/Modal.module.css";
import { Btn, Input } from "../../../features/crm/ui/Primitives";

export default function ProdutosPage() {
  const { hasPermission, handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const [products, setProducts] = useState<Product[]>([]);
  const [brands, setBrands] = useState<Brand[]>([]);
  const [models, setModels] = useState<WatchModel[]>([]);
  const [loading, setLoading] = useState(true);
  const [showNew, setShowNew] = useState(false);
  const [editing, setEditing] = useState<Product | null>(null);
  const [addQtyTarget, setAddQtyTarget] = useState<Product | null>(null);
  const [addQtyValue, setAddQtyValue] = useState("1");
  const [addQtyLoading, setAddQtyLoading] = useState(false);

  const canCreate = hasPermission("products.create");
  const canUpdate = hasPermission("products.update");
  const canDelete = hasPermission("products.delete");
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

  async function handleDelete(product: Product) {
    if (!confirm(`Excluir entrada "${product.stock === "IN_STOCK" ? "Estoque" : "Fornecedor"}" de ${product.brand} ${product.model}?`)) return;
    try {
      await apiDelete(`/products/${product.id}`, "Falha ao excluir produto.");
      setProducts((ps) => ps.filter((p) => p.id !== product.id));
      pushToast("Produto excluído com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  async function handleAddQtyConfirm() {
    if (!addQtyTarget) return;
    const qty = parseInt(addQtyValue, 10);
    if (!qty || qty < 1) {
      pushToast("Informe uma quantidade válida (mínimo 1).", "error");
      return;
    }
    setAddQtyLoading(true);
    try {
      const apiBaseUrl = getApiBaseUrl();
      await ensureCsrfCookie(apiBaseUrl);
      const response = await apiFetch(
        `${apiBaseUrl}/products/${addQtyTarget.id}/add-qty`,
        {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ qty }),
        },
        { csrf: true }
      );
      if (!response.ok) throw new Error(await getErrorMessage(response, "Falha ao adicionar unidades."));
      const updated = (await response.json()) as Product;
      setProducts((ps) => ps.map((p) => (p.id === updated.id ? updated : p)));
      setAddQtyTarget(null);
      setAddQtyValue("1");
      pushToast(`${qty} unidade(s) adicionada(s) com sucesso.`, "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    } finally {
      setAddQtyLoading(false);
    }
  }

  if (loading) return <div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>;

  return (
    <>
      <Products
        products={products}
        canCreate={canCreate}
        canUpdate={canUpdate}
        canDelete={canDelete}
        compact={!canManageCatalog}
        onNew={() => setShowNew(true)}
        onEdit={setEditing}
        onDelete={handleDelete}
        onAddQty={(p) => { setAddQtyTarget(p); setAddQtyValue("1"); }}
      />
      {showNew && (
        <NewProductForm
          brands={brands}
          models={models}
          existingProducts={products}
          onSave={handleSave}
          onClose={() => setShowNew(false)}
          onToast={pushToast}
        />
      )}
      {editing && (
        <NewProductForm
          product={editing}
          brands={brands}
          models={models}
          existingProducts={products}
          onSave={handleUpdate}
          onClose={() => setEditing(null)}
          onToast={pushToast}
        />
      )}
      {addQtyTarget && (
        <div className={modalStyles.overlay}>
          <div className={modalStyles.modal} style={{ width: 360 }}>
            <div className={modalStyles.header}>
              <h3 className={modalStyles.title}>Adicionar Unidades</h3>
              <button onClick={() => setAddQtyTarget(null)} className={modalStyles.close}>×</button>
            </div>
            <p style={{ color: "var(--crm-text-soft)", fontSize: 13, marginBottom: 16 }}>
              {addQtyTarget.brand} {addQtyTarget.model} —{" "}
              <strong>{addQtyTarget.stock === "IN_STOCK" ? "Estoque" : "Fornecedor"}</strong>
              {" "}({addQtyTarget.qty} un. atual)
            </p>
            <Input
              label="Quantidade a adicionar"
              type="number"
              value={addQtyValue}
              onChange={(e) => setAddQtyValue(e.target.value)}
            />
            <div style={{ display: "flex", gap: 8, justifyContent: "flex-end", marginTop: 16 }}>
              <Btn onClick={() => setAddQtyTarget(null)} variant="secondary">Cancelar</Btn>
              <Btn onClick={handleAddQtyConfirm} variant="primary" disabled={addQtyLoading}>
                {addQtyLoading ? "Salvando..." : "Confirmar"}
              </Btn>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
