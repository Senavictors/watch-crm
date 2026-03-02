"use client";
import React from "react";
import styles from "./Toasts.module.css";

type Toast = {
  id: number;
  message: string;
  variant: "success" | "error";
};

type Props = {
  toasts: Toast[];
  onDismiss: (id: number) => void;
};

const Toasts: React.FC<Props> = ({ toasts, onDismiss }) => {
  if (!toasts.length) return null;
  return (
    <div className={styles.container}>
      {toasts.map((toast) => {
        const isSuccess = toast.variant === "success";
        return (
          <div
            key={toast.id}
            onClick={() => onDismiss(toast.id)}
            role="status"
            className={`${styles.toast} ${isSuccess ? styles.toastSuccess : styles.toastError}`}
          >
            <div className={`${styles.toastLabel} ${isSuccess ? styles.toastLabelSuccess : styles.toastLabelError}`}>
              {isSuccess ? "Sucesso" : "Erro"}
            </div>
            <div className={styles.toastMessage}>{toast.message}</div>
          </div>
        );
      })}
    </div>
  );
};

export default Toasts;
