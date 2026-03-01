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
            Nova Marca
          </h3>
          <button
            onClick={onClose}
            style={{ background: "none", border: "none", color: "#94A3B8", fontSize: 22, cursor: "pointer" }}
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
