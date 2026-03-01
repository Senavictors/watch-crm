"use client";
import React, { useState } from "react";
import { Btn, Input } from "../ui/Primitives";
import { Customer } from "../types";

type Props = {
  onSave: (customer: Omit<Customer, "id">) => void;
  onClose: () => void;
};

const NewCustomerForm: React.FC<Props> = ({ onSave, onClose }) => {
  const [form, setForm] = useState<{
    name: string;
    phone: string;
    email: string;
    instagram: string;
  }>({
    name: "",
    phone: "",
    email: "",
    instagram: "",
  });

  function set<K extends keyof typeof form>(k: K, v: (typeof form)[K]) {
    setForm((f) => ({ ...f, [k]: v }));
  }

  function handleSubmit() {
    if (!form.name || !form.phone) {
      alert("Preencha nome e celular.");
      return;
    }
    onSave({
      name: form.name.trim(),
      phone: form.phone.trim(),
      email: form.email.trim() || undefined,
      instagram: form.instagram.trim(),
    } as Omit<Customer, "id">);
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
            Novo Cliente
          </h3>
          <button
            onClick={onClose}
            style={{ background: "none", border: "none", color: "#94A3B8", fontSize: 22, cursor: "pointer" }}
          >
            ×
          </button>
        </div>

        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10 }}>
          <Input label="Nome" value={form.name} onChange={(e) => set("name", e.target.value)} />
          <Input label="Celular" value={form.phone} onChange={(e) => set("phone", e.target.value)} />
          <Input label="Email" type="email" value={form.email} onChange={(e) => set("email", e.target.value)} />
          <Input
            label="Instagram"
            value={form.instagram}
            onChange={(e) => set("instagram", e.target.value)}
          />
        </div>

        <div style={{ display: "flex", gap: 8, justifyContent: "flex-end", marginTop: 16 }}>
          <Btn onClick={onClose} variant="secondary">
            Cancelar
          </Btn>
          <Btn onClick={handleSubmit} variant="primary">
            Salvar Cliente
          </Btn>
        </div>
      </div>
    </div>
  );
};

export default NewCustomerForm;
