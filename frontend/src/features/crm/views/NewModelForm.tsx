"use client";
import React, { useMemo, useState } from "react";
import { Btn, Input, Select } from "../ui/Primitives";
import { Brand, WatchModel } from "../types";

type Props = {
  brands: Brand[];
  onSave: (model: Omit<WatchModel, "id" | "imageUrl"> & { imageFile?: File | null }) => void;
  onClose: () => void;
};

const NewModelForm: React.FC<Props> = ({ brands, onSave, onClose }) => {
  const [name, setName] = useState("");
  const [brandId, setBrandId] = useState("");
  const [imageFile, setImageFile] = useState<File | null>(null);

  const brandOptions = useMemo(() => brands, [brands]);

  function handleSubmit() {
    if (!name.trim() || !brandId) {
      alert("Preencha o modelo e a marca.");
      return;
    }
    onSave({ name: name.trim(), brandId: Number(brandId), imageFile });
  }

  return (
    <div
      style={{
        position: "fixed",
        inset: 0,
        background: "rgba(2, 6, 23, 0.75)",
        zIndex: 100,
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
      }}
    >
      <div
        style={{
          background: "rgba(255, 255, 255, 0.04)",
          border: "1px solid rgba(255, 255, 255, 0.12)",
          borderRadius: 20,
          padding: 32,
          width: 520,
          maxHeight: "90vh",
          overflowY: "auto",
          boxShadow: "0 24px 60px rgba(0, 0, 0, 0.5)",
          backdropFilter: "blur(10px)",
        }}
      >
        <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 20 }}>
          <h3 style={{ color: "#F8FAFC", fontFamily: "'Inter', sans-serif", fontSize: 22, fontWeight: 600 }}>
            Novo Modelo
          </h3>
          <button
            onClick={onClose}
            style={{ background: "none", border: "none", color: "#94A3B8", fontSize: 22, cursor: "pointer" }}
          >
            ×
          </button>
        </div>

        <div style={{ display: "grid", gridTemplateColumns: "1fr", gap: 10 }}>
          <Input label="Modelo" value={name} onChange={(e) => setName(e.target.value)} />
          <Select label="Marca" value={brandId} onChange={(e) => setBrandId(e.target.value)}>
            <option value="">Selecionar marca...</option>
            {brandOptions.map((b) => (
              <option key={b.id} value={b.id}>
                {b.name}
              </option>
            ))}
          </Select>
          <div style={{ marginBottom: 14 }}>
            <label
              style={{
                display: "block",
                color: "#94A3B8",
                fontSize: 11,
                marginBottom: 5,
                textTransform: "uppercase",
                letterSpacing: 0.5,
              }}
            >
              Imagem do Modelo
            </label>
            <input
              type="file"
              accept="image/png, image/jpeg"
              onChange={(e) => setImageFile(e.target.files?.[0] ?? null)}
              style={{
                width: "100%",
                background: "rgba(255, 255, 255, 0.03)",
                border: "1px solid rgba(255, 255, 255, 0.08)",
                borderRadius: 12,
                color: "#E2E8F0",
                padding: "8px 12px",
                fontSize: 13,
                outline: "none",
                boxSizing: "border-box",
              }}
            />
            <div style={{ color: "#64748B", fontSize: 11, marginTop: 6 }}>
              PNG ou JPG. Recomendado 800x800.
            </div>
          </div>
        </div>
        <div style={{ display: "flex", gap: 8, justifyContent: "flex-end", marginTop: 16 }}>
          <Btn onClick={onClose} variant="secondary">
            Cancelar
          </Btn>
          <Btn onClick={handleSubmit} variant="primary">
            Salvar Modelo
          </Btn>
        </div>
      </div>
    </div>
  );
};

export default NewModelForm;
