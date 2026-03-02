"use client";
import React, { useState } from "react";
import { Btn, Input } from "../ui/Primitives";
import { Customer } from "../types";

type Props = {
  onSave: (customer: Omit<Customer, "id">) => void;
  onClose: () => void;
  onToast: (message: string, variant?: "success" | "error") => void;
};

const NewCustomerForm: React.FC<Props> = ({ onSave, onClose, onToast }) => {
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
  const [cancelHover, setCancelHover] = useState(false);
  const [saveHover, setSaveHover] = useState(false);

  function set<K extends keyof typeof form>(k: K, v: (typeof form)[K]) {
    setForm((f) => ({ ...f, [k]: v }));
  }

  function handleSubmit() {
    if (!form.name || !form.phone) {
      onToast("Preencha nome e celular.", "error");
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
            Novo Cliente
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
          <Btn
            onClick={onClose}
            variant="secondary"
            onMouseEnter={() => setCancelHover(true)}
            onMouseLeave={() => setCancelHover(false)}
            style={{
              background: "var(--crm-button-secondary-bg)",
              color: "var(--crm-button-secondary-text)",
              padding: "9px 18px",
              fontSize: 13,
              fontWeight: 600,
              boxShadow:
                "0 1px 0 0 rgba(255, 255, 255, 0.4) inset, 0 1px 2px rgba(15, 23, 42, 0.2)",
              transform: cancelHover ? "translateY(-2px)" : "translateY(0)",
              transition: "all 0.2s ease",
            }}
          >
            Cancelar
          </Btn>
          <Btn
            onClick={handleSubmit}
            variant="primary"
            onMouseEnter={() => setSaveHover(true)}
            onMouseLeave={() => setSaveHover(false)}
            style={{
              background: "var(--crm-button-primary-bg)",
              color: "var(--crm-button-primary-text)",
              padding: "9px 18px",
              fontSize: 13,
              fontWeight: 600,
              boxShadow:
                "0 1px 0 0 rgba(255, 255, 255, 0.4) inset, 0 1px 2px rgba(15, 23, 42, 0.2)",
              transform: saveHover ? "translateY(-2px)" : "translateY(0)",
              transition: "all 0.2s ease",
            }}
          >
            Salvar Cliente
          </Btn>
        </div>
      </div>
    </div>
  );
};

export default NewCustomerForm;
