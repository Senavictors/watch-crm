"use client";
import React, { useState } from "react";
import { Btn, Input, Select } from "../ui/Primitives";
import { Brand, Product, ProductInput, StockOrigin, WatchModel } from "../types";
import { modelLabel } from "../helpers";
import modalStyles from "../components/Modal/Modal.module.css";
import styles from "./NewProductForm.module.css";

type Props = {
  product?: Product | null;
  brands: Brand[];
  models: WatchModel[];
  existingProducts?: Product[];
  onSave: (product: ProductInput) => void;
  onClose: () => void;
  onToast: (message: string, variant?: "success" | "error") => void;
};

const NewProductForm: React.FC<Props> = ({ product, brands, models, existingProducts = [], onSave, onClose, onToast }) => {
  const isEditing = Boolean(product);

  const [form, setForm] = useState<{
    brandId: string;
    modelId: string;
    cost: string;
    price: string;
    stock: StockOrigin;
    qty: string;
  }>({
    brandId: product ? String(product.brandId) : "",
    modelId: product ? String(product.modelId) : "",
    cost: product ? String(product.cost) : "",
    price: product ? String(product.price) : "",
    stock: product?.stock ?? "IN_STOCK",
    qty: product ? String(product.qty) : "0",
  });

  function set<K extends keyof typeof form>(k: K, v: (typeof form)[K]) {
    setForm((f) => ({ ...f, [k]: v }));
  }

  const selectedModel = models.find((m) => m.id === Number(form.modelId));
  const modelBrand = selectedModel ? brands.find((b) => b.id === selectedModel.brandId) : null;

  // origins already taken for the selected model (excluding the current product being edited)
  const takenOrigins = new Set(
    existingProducts
      .filter((p) => p.modelId === Number(form.modelId) && p.id !== product?.id)
      .map((p) => p.stock)
  );

  function handleModelChange(value: string) {
    const model = models.find((m) => m.id === Number(value));
    const newTaken = new Set(
      existingProducts
        .filter((p) => p.modelId === Number(value) && p.id !== product?.id)
        .map((p) => p.stock)
    );
    // pick first available origin
    const defaultStock: StockOrigin = !newTaken.has("IN_STOCK") ? "IN_STOCK" : "SUPPLIER";
    setForm((f) => ({
      ...f,
      modelId: value,
      brandId: model ? String(model.brandId) : "",
      stock: newTaken.has(f.stock) ? defaultStock : f.stock,
    }));
  }

  function handleSubmit() {
    if (!form.modelId || !form.brandId || !form.cost || !form.price) {
      onToast("Preencha modelo, marca, custo e preço.", "error");
      return;
    }
    if (!isEditing && takenOrigins.has(form.stock)) {
      const label = form.stock === "IN_STOCK" ? "Estoque" : "Fornecedor";
      onToast(`Já existe uma entrada "${label}" para este modelo.`, "error");
      return;
    }
    const qty = Math.max(0, Number(form.qty || 0));
    onSave({
      modelId: Number(form.modelId),
      brandId: Number(form.brandId),
      cost: Number(form.cost),
      price: Number(form.price),
      stock: form.stock,
      qty,
    });
  }

  const allOriginsTaken = !isEditing && takenOrigins.has("IN_STOCK") && takenOrigins.has("SUPPLIER");

  return (
    <div className={modalStyles.overlay}>
      <div className={`${modalStyles.modal} ${styles.modal}`}>
        <div className={modalStyles.header}>
          <h3 className={modalStyles.title}>{isEditing ? "Editar Produto" : "Novo Produto"}</h3>
          <button onClick={onClose} className={modalStyles.close}>
            ×
          </button>
        </div>

        {allOriginsTaken && (
          <div className={styles.alert}>
            Este modelo já possui entradas de Estoque e Fornecedor. Use a opção &quot;+ Qtd&quot; para adicionar unidades.
          </div>
        )}

        <div className={modalStyles.formGridTwo}>
          <Select label="Modelo" value={form.modelId} onChange={(e) => handleModelChange(e.target.value)}>
            <option value="">Selecionar modelo...</option>
            {models.map((m) => (
              <option key={m.id} value={m.id}>
                {modelLabel(m)}
              </option>
            ))}
          </Select>
          <Select
            label="Marca"
            value={form.brandId}
            onChange={(e) => set("brandId", e.target.value)}
            disabled={!modelBrand}
          >
            <option value="">Selecionar marca...</option>
            {modelBrand && (
              <option value={modelBrand.id}>
                {modelBrand.name}
              </option>
            )}
          </Select>
          <Input
            label="Custo (R$)"
            type="number"
            value={form.cost}
            onChange={(e) => set("cost", e.target.value)}
          />
          <Input
            label="Preço (R$)"
            type="number"
            value={form.price}
            onChange={(e) => set("price", e.target.value)}
          />
          <Select
            label="Origem"
            value={form.stock}
            onChange={(e) => set("stock", e.target.value as StockOrigin)}
            disabled={isEditing}
          >
            <option value="IN_STOCK" disabled={!isEditing && takenOrigins.has("IN_STOCK")}>
              {takenOrigins.has("IN_STOCK") && !isEditing ? "Estoque (já cadastrado)" : "Estoque"}
            </option>
            <option value="SUPPLIER" disabled={!isEditing && takenOrigins.has("SUPPLIER")}>
              {takenOrigins.has("SUPPLIER") && !isEditing ? "Fornecedor (já cadastrado)" : "Fornecedor"}
            </option>
          </Select>
          <Input
            label="Estoque (unidades)"
            type="number"
            value={form.qty}
            onChange={(e) => set("qty", e.target.value)}
          />
        </div>

        <div className={styles.actions}>
          <Btn onClick={onClose} variant="secondary" className={styles.actionButton}>
            Cancelar
          </Btn>
          <Btn onClick={handleSubmit} variant="primary" className={styles.actionButton} disabled={allOriginsTaken}>
            {isEditing ? "Salvar Alterações" : "Salvar Produto"}
          </Btn>
        </div>
      </div>
    </div>
  );
};

export default NewProductForm;
