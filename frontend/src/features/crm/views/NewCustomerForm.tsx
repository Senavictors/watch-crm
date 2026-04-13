"use client";
import React, { useState } from "react";
import { Btn, Input } from "../ui/Primitives";
import { Customer, CustomerInput } from "../types";
import modalStyles from "../components/Modal/Modal.module.css";
import styles from "./NewCustomerForm.module.css";

type Props = {
  customer?: Customer | null;
  onSave: (customer: CustomerInput) => void;
  onClose: () => void;
  onToast: (message: string, variant?: "success" | "error") => void;
};

const NewCustomerForm: React.FC<Props> = ({ customer, onSave, onClose, onToast }) => {
  const [form, setForm] = useState<{
    name: string;
    phone: string;
    email: string;
    instagram: string;
    street: string;
    number: string;
    complement: string;
    zipCode: string;
    city: string;
    state: string;
  }>({
    name: customer?.name ?? "",
    phone: customer?.phone ?? "",
    email: customer?.email ?? "",
    instagram: customer?.instagram ?? "",
    street: customer?.street ?? "",
    number: customer?.number ?? "",
    complement: customer?.complement ?? "",
    zipCode: customer?.zipCode ?? "",
    city: customer?.city ?? "",
    state: customer?.state ?? "",
  });

  const isEditing = Boolean(customer);

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
      email: form.email.trim() || null,
      instagram: form.instagram.trim() || null,
      street: form.street.trim() || null,
      number: form.number.trim() || null,
      complement: form.complement.trim() || null,
      zipCode: form.zipCode.trim() || null,
      city: form.city.trim() || null,
      state: form.state.trim() || null,
    });
  }

  return (
    <div className={modalStyles.overlay}>
      <div className={`${modalStyles.modal} ${styles.modal}`}>
        <div className={modalStyles.header}>
          <h3 className={modalStyles.title}>{isEditing ? "Editar Cliente" : "Novo Cliente"}</h3>
          <button onClick={onClose} className={modalStyles.close}>
            ×
          </button>
        </div>

        <div className={modalStyles.formGridTwo}>
          <Input label="Nome" value={form.name} onChange={(e) => set("name", e.target.value)} />
          <Input label="Celular" value={form.phone} onChange={(e) => set("phone", e.target.value)} />
          <Input label="Email" type="email" value={form.email} onChange={(e) => set("email", e.target.value)} />
          <Input
            label="Instagram"
            value={form.instagram}
            onChange={(e) => set("instagram", e.target.value)}
          />
        </div>

        <div className={styles.sectionLabel}>Endereço (opcional)</div>
        <div className={modalStyles.formGridTwo}>
          <Input label="CEP" value={form.zipCode} onChange={(e) => set("zipCode", e.target.value)} />
          <Input label="Estado (UF)" value={form.state} onChange={(e) => set("state", e.target.value.toUpperCase().slice(0, 2))} />
          <Input label="Cidade" value={form.city} onChange={(e) => set("city", e.target.value)} />
          <Input label="Rua" value={form.street} onChange={(e) => set("street", e.target.value)} />
          <Input label="Número" value={form.number} onChange={(e) => set("number", e.target.value)} />
          <Input label="Complemento" value={form.complement} onChange={(e) => set("complement", e.target.value)} />
        </div>

        <div className={styles.actions}>
          <Btn onClick={onClose} variant="secondary" className={styles.actionButton}>
            Cancelar
          </Btn>
          <Btn onClick={handleSubmit} variant="primary" className={styles.actionButton}>
            {isEditing ? "Salvar Alterações" : "Salvar Cliente"}
          </Btn>
        </div>
      </div>
    </div>
  );
};

export default NewCustomerForm;
