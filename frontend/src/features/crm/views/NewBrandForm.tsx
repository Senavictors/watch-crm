"use client";
import React, { useState } from "react";
import { Btn, Input } from "../ui/Primitives";
import { Brand } from "../types";

type Props = {
  onSave: (brand: Omit<Brand, "id">) => void;
  onClose: () => void;
};

const NewBrandForm: React.FC<Props> = ({ onSave, onClose }) => {
  const [name, setName] = useState("");

  function handleSubmit() {
    if (!name.trim()) {
      alert("Preencha a marca.");
      return;
    }
    onSave({ name: name.trim() });
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
            Nova Marca
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

        <div style={{ display: "grid", gridTemplateColumns: "1fr", gap: 10 }}>
          <Input label="Marca" value={name} onChange={(e) => setName(e.target.value)} />
        </div>

        <div style={{ display: "flex", gap: 8, justifyContent: "flex-end", marginTop: 16 }}>
          <Btn onClick={onClose} variant="secondary">
            Cancelar
          </Btn>
          <Btn onClick={handleSubmit} variant="primary">
            Salvar Marca
          </Btn>
        </div>
      </div>
    </div>
  );
};

export default NewBrandForm;
