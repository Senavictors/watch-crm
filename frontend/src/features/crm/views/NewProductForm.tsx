"use client";
import React, { useState } from "react";
import { Btn, Input, Select } from "../ui/Primitives";
import { Brand, ProductInput, StockOrigin, WatchModel } from "../types";

type Props = {
  brands: Brand[];
  models: WatchModel[];
  onSave: (product: ProductInput) => void;
  onClose: () => void;
};

const NewProductForm: React.FC<Props> = ({ brands, models, onSave, onClose }) => {
  const [form, setForm] = useState<{
    brandId: string;
    modelId: string;
    cost: string;
    price: string;
    stock: StockOrigin;
    qty: string;
  }>({
    brandId: "",
    modelId: "",
    cost: "",
    price: "",
    stock: "IN_STOCK",
    qty: "0",
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
      alert("Preencha modelo, marca, custo e preço.");
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
    <div
      style={{
        position: "fixed",
        inset: 0,
        background: "var(--crm-overlay)",
        zIndex: 100,
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
      }}
    >
      <div
        style={{
          background: "var(--crm-modal-bg)",
          border: "1px solid var(--crm-modal-border)",
          borderRadius: 20,
          padding: 32,
          width: 520,
          maxHeight: "90vh",
          overflowY: "auto",
          boxShadow: "0 24px 60px rgba(15, 23, 42, 0.35)",
          backdropFilter: "blur(10px)",
        }}
      >
        <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 20 }}>
          <h3 style={{ color: "var(--crm-text)", fontFamily: "'Inter', sans-serif", fontSize: 22, fontWeight: 600 }}>
            Novo Produto
          </h3>
          <button
            onClick={onClose}
            style={{
              background: "none",
              border: "none",
              color: "var(--crm-text-muted)",
              fontSize: 22,
              cursor: "pointer",
            }}
          >
            ×
          </button>
        </div>

        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10 }}>
          <Select label="Modelo" value={form.modelId} onChange={(e) => handleModelChange(e.target.value)}>
            <option value="">Selecionar modelo...</option>
            {models.map((m) => (
              <option key={m.id} value={m.id}>
                {m.name}
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

        <div style={{ display: "flex", gap: 8, justifyContent: "flex-end", marginTop: 16 }}>
          <Btn onClick={onClose} variant="secondary">
            Cancelar
          </Btn>
          <Btn onClick={handleSubmit} variant="primary">
            Salvar Produto
          </Btn>
        </div>
      </div>
    </div>
  );
};

export default NewProductForm;
