"use client";
import React, { useState } from "react";
import { Btn, Input } from "../ui/Primitives";
import { Brand } from "../types";
import modalStyles from "../components/Modal/Modal.module.css";
import styles from "./NewBrandForm.module.css";

type Props = {
  onSave: (brand: Omit<Brand, "id">) => void;
  onClose: () => void;
  onToast: (message: string, variant?: "success" | "error") => void;
};

const NewBrandForm: React.FC<Props> = ({ onSave, onClose, onToast }) => {
  const [name, setName] = useState("");
  function handleSubmit() {
    if (!name.trim()) {
      onToast("Preencha a marca.", "error");
      return;
    }
    onSave({ name: name.trim() });
  }

  return (
    <div className={modalStyles.overlay}>
      <div className={`${modalStyles.modal} ${styles.modal}`}>
        <div className={modalStyles.header}>
          <h3 className={modalStyles.title}>Nova Marca</h3>
          <button onClick={onClose} className={modalStyles.close}>
            ×
          </button>
        </div>

        <div className={modalStyles.formGridOne}>
          <Input label="Marca" value={name} onChange={(e) => setName(e.target.value)} />
        </div>

        <div className={styles.actions}>
          <Btn onClick={onClose} variant="secondary" className={styles.actionButton}>
            Cancelar
          </Btn>
          <Btn onClick={handleSubmit} variant="primary" className={styles.actionButton}>
            Salvar Marca
          </Btn>
        </div>
      </div>
    </div>
  );
};

export default NewBrandForm;
