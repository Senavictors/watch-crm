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
  onSave: (product: ProductInput) => void;
  onClose: () => void;
  onToast: (message: string, variant?: "success" | "error") => void;
};

const NewProductForm: React.FC<Props> = ({ product, brands, models, onSave, onClose, onToast }) => {
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

  function handleModelChange(value: string) {
    const model = models.find((m) => m.id === Number(value));
    setForm((f) => ({
      ...f,
      modelId: value,
      brandId: model ? String(model.brandId) : "",
    }));
  }

  function handleSubmit() {
    if (!form.modelId || !form.brandId || !form.cost || !form.price) {
      onToast("Preencha modelo, marca, custo e preço.", "error");
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

  return (
    <div className={modalStyles.overlay}>
      <div className={`${modalStyles.modal} ${styles.modal}`}>
        <div className={modalStyles.header}>
          <h3 className={modalStyles.title}>{isEditing ? "Editar Produto" : "Novo Produto"}</h3>
          <button onClick={onClose} className={modalStyles.close}>
            ×
          </button>
        </div>

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
          <Select label="Origem" value={form.stock} onChange={(e) => set("stock", e.target.value as StockOrigin)}>
            <option value="IN_STOCK">Estoque</option>
            <option value="SUPPLIER">Fornecedor</option>
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
          <Btn onClick={handleSubmit} variant="primary" className={styles.actionButton}>
            {isEditing ? "Salvar Alterações" : "Salvar Produto"}
          </Btn>
        </div>
      </div>
    </div>
  );
};

export default NewProductForm;
