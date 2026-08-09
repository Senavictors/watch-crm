"use client";
import React from "react";
import { Pencil, Eye } from "lucide-react";
import { Customer } from "../types";
import { Btn } from "../ui/Primitives";
import styles from "./Customers.module.css";

type Props = {
  customers: Customer[];
  search: string;
  onSearchChange: (value: string) => void;
  canCreate: boolean;
  canUpdate: boolean;
  onNew: () => void;
  onEdit: (customer: Customer) => void;
  onView: (customer: Customer) => void;
};

const Customers: React.FC<Props> = ({ customers, search, onSearchChange, canCreate, canUpdate, onNew, onEdit, onView }) => {
  return (
    <div>
      <div className={styles.headerRow}>
        <h2 className={styles.title}>Clientes</h2>
        {canCreate && (
          <Btn onClick={onNew} variant="primary" className={styles.actionButton}>
            + Adicionar Cliente
          </Btn>
        )}
      </div>
      <input
        value={search}
        onChange={(e) => onSearchChange(e.target.value)}
        placeholder="Buscar cliente..."
        className={styles.search}
      />
      <div className={styles.list}>
        <div className={styles.listHeader} aria-hidden="true">
          <span>Cliente</span>
          <span>Telefone</span>
          <span>E-mail</span>
          <span>Instagram</span>
          <span className={styles.actionsLabel}>Ações</span>
        </div>

        <div role="list" aria-label="Lista de clientes">
          {customers.map((c) => (
            <article key={c.id} className={styles.customerRow} role="listitem">
              <div className={styles.customerIdentity}>
                <div className={styles.avatar} aria-hidden="true">
                  {c.name.trim().charAt(0).toUpperCase()}
                </div>
                <div className={styles.customerName}>{c.name}</div>
              </div>

              <div className={styles.field}>
                <span className={styles.mobileLabel}>Telefone</span>
                <span className={styles.fieldValue}>{c.phone}</span>
              </div>

              <div className={styles.field}>
                <span className={styles.mobileLabel}>E-mail</span>
                <span className={styles.fieldValue}>{c.email || "Não informado"}</span>
              </div>

              <div className={styles.field}>
                <span className={styles.mobileLabel}>Instagram</span>
                <span className={c.instagram ? styles.instagram : styles.fieldValue}>
                  {c.instagram || "Não informado"}
                </span>
              </div>

              <div className={styles.rowActions}>
                <Btn onClick={() => onView(c)} variant="secondary" small className={styles.rowAction}>
                  <Eye size={16} aria-hidden="true" />
                  <span className={styles.srOnly}>Visualizar {c.name}</span>
                </Btn>
                {canUpdate && (
                  <Btn onClick={() => onEdit(c)} variant="secondary" small className={styles.rowAction}>
                    <Pencil size={16} aria-hidden="true" />
                    <span className={styles.srOnly}>Editar {c.name}</span>
                  </Btn>
                )}
              </div>
            </article>
          ))}
        </div>

        {customers.length === 0 && (
          <div className={styles.emptyState}>Nenhum cliente encontrado.</div>
        )}
      </div>
    </div>
  );
};

export default Customers;
