"use client";
import React, { useState } from "react";
import { Btn, Input, Select } from "../ui/Primitives";
import { Expense, ExpenseInput, ExpenseMetadata } from "../types";
import modalStyles from "../components/Modal/Modal.module.css";
import styles from "./NewExpenseForm.module.css";

type Props = {
  expense?: Expense | null;
  metadata: ExpenseMetadata;
  onSave: (expense: ExpenseInput) => void;
  onClose: () => void;
  onToast: (message: string, variant?: "success" | "error") => void;
};

const NewExpenseForm: React.FC<Props> = ({ expense, metadata, onSave, onClose, onToast }) => {
  const isEditing = Boolean(expense);

  const [form, setForm] = useState<{
    category: string;
    description: string;
    amount: string;
    expenseDate: string;
  }>({
    category: expense?.category ?? metadata.categories[0] ?? "",
    description: expense?.description ?? "",
    amount: expense ? String(expense.amount) : "",
    expenseDate: expense?.expenseDate ?? new Date().toISOString().slice(0, 10),
  });

  function set<K extends keyof typeof form>(k: K, v: (typeof form)[K]) {
    setForm((f) => ({ ...f, [k]: v }));
  }

  function handleSubmit() {
    if (!form.category || !form.description.trim() || !form.amount || !form.expenseDate) {
      onToast("Preencha categoria, descrição, valor e data.", "error");
      return;
    }
    const amount = Number(form.amount);
    if (!Number.isFinite(amount) || amount <= 0) {
      onToast("Informe um valor válido (maior que zero).", "error");
      return;
    }
    onSave({
      category: form.category,
      description: form.description.trim(),
      amount,
      expenseDate: form.expenseDate,
    });
  }

  return (
    <div className={modalStyles.overlay}>
      <div className={`${modalStyles.modal} ${styles.modal}`}>
        <div className={modalStyles.header}>
          <h3 className={modalStyles.title}>{isEditing ? "Editar Despesa" : "Nova Despesa"}</h3>
          <button onClick={onClose} className={modalStyles.close}>
            ×
          </button>
        </div>

        <div className={modalStyles.formGridTwo}>
          <Select label="Categoria" value={form.category} onChange={(e) => set("category", e.target.value)}>
            {metadata.categories.map((c) => (
              <option key={c} value={c}>
                {c}
              </option>
            ))}
          </Select>
          <Input
            label="Data"
            type="date"
            value={form.expenseDate}
            onChange={(e) => set("expenseDate", e.target.value)}
          />
          <Input
            label="Descrição"
            value={form.description}
            onChange={(e) => set("description", e.target.value)}
          />
          <Input
            label="Valor (R$)"
            type="number"
            value={form.amount}
            onChange={(e) => set("amount", e.target.value)}
          />
        </div>

        <div className={styles.actions}>
          <Btn onClick={onClose} variant="secondary" className={styles.actionButton}>
            Cancelar
          </Btn>
          <Btn onClick={handleSubmit} variant="primary" className={styles.actionButton}>
            {isEditing ? "Salvar Alterações" : "Salvar Despesa"}
          </Btn>
        </div>
      </div>
    </div>
  );
};

export default NewExpenseForm;
