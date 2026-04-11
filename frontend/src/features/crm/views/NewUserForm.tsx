"use client";
import React, { useState } from "react";
import { Btn, Input, Select } from "../ui/Primitives";
import { CrmUser, CrmUserInput } from "../types";
import modalStyles from "../components/Modal/Modal.module.css";
import styles from "./NewUserForm.module.css";

type Props = {
  user?: CrmUser | null;
  resetPasswordMode?: boolean;
  currentUserRole: "admin" | "gerente" | "vendedor";
  onSave: (data: CrmUserInput) => void;
  onClose: () => void;
  onToast: (message: string, variant?: "success" | "error") => void;
};

const NewUserForm: React.FC<Props> = ({
  user,
  resetPasswordMode = false,
  currentUserRole,
  onSave,
  onClose,
  onToast,
}) => {
  const isEditing = Boolean(user);

  const [form, setForm] = useState({
    name: user?.name ?? "",
    email: user?.email ?? "",
    password: "",
    role: user?.role ?? "vendedor" as "admin" | "gerente" | "vendedor",
  });

  function set<K extends keyof typeof form>(k: K, v: (typeof form)[K]) {
    setForm((f) => ({ ...f, [k]: v }));
  }

  function getTitle(): string {
    if (resetPasswordMode) return "Redefinir Senha";
    if (isEditing) return "Editar Usuário";
    return "Novo Usuário";
  }

  function handleSubmit() {
    if (resetPasswordMode) {
      if (!form.password || form.password.length < 12) {
        onToast("A senha deve ter no mínimo 12 caracteres.", "error");
        return;
      }
      onSave({ name: form.name, email: form.email, password: form.password, role: form.role });
      return;
    }

    if (!form.name.trim()) {
      onToast("Preencha o nome.", "error");
      return;
    }
    if (!form.email.trim() || !form.email.includes("@")) {
      onToast("Informe um e-mail válido.", "error");
      return;
    }
    if (!isEditing && (!form.password || form.password.length < 12)) {
      onToast("A senha deve ter no mínimo 12 caracteres.", "error");
      return;
    }

    const data: CrmUserInput = {
      name: form.name.trim(),
      email: form.email.trim(),
      role: form.role,
    };

    if (!isEditing) {
      data.password = form.password;
    }

    onSave(data);
  }

  return (
    <div className={modalStyles.overlay}>
      <div className={`${modalStyles.modal} ${styles.modal}`}>
        <div className={modalStyles.header}>
          <h3 className={modalStyles.title}>{getTitle()}</h3>
          <button onClick={onClose} className={modalStyles.close}>
            ×
          </button>
        </div>

        {resetPasswordMode ? (
          <div className={modalStyles.formGridOne}>
            <Input
              label="Nova Senha (mín. 12 caracteres)"
              type="password"
              value={form.password}
              onChange={(e) => set("password", e.target.value)}
            />
          </div>
        ) : (
          <div className={modalStyles.formGridTwo}>
            <Input
              label="Nome"
              value={form.name}
              onChange={(e) => set("name", e.target.value)}
            />
            <Input
              label="E-mail"
              type="email"
              value={form.email}
              onChange={(e) => set("email", e.target.value)}
            />
            {!isEditing && (
              <Input
                label="Senha (mín. 12 caracteres)"
                type="password"
                value={form.password}
                onChange={(e) => set("password", e.target.value)}
              />
            )}
            <Select
              label="Função"
              value={form.role}
              onChange={(e) => set("role", e.target.value as typeof form.role)}
            >
              <option value="vendedor">Vendedor</option>
              <option value="gerente">Gerente</option>
              {currentUserRole === "admin" && <option value="admin">Admin</option>}
            </Select>
          </div>
        )}

        <div className={styles.actions}>
          <Btn onClick={onClose} variant="secondary" className={styles.actionButton}>
            Cancelar
          </Btn>
          <Btn onClick={handleSubmit} variant="primary" className={styles.actionButton}>
            {resetPasswordMode ? "Redefinir Senha" : isEditing ? "Salvar Alterações" : "Criar Usuário"}
          </Btn>
        </div>
      </div>
    </div>
  );
};

export default NewUserForm;
