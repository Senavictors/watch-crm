"use client";
import React, { useState } from "react";
import { Customer } from "../types";
import { Btn, Card } from "../ui/Primitives";
import styles from "./Customers.module.css";

type Props = {
  customers: Customer[];
  onNew: () => void;
};

const Customers: React.FC<Props> = ({ customers, onNew }) => {
  const [search, setSearch] = useState("");
  const filtered = customers.filter(
    (c) => !search || `${c.name} ${c.phone} ${c.instagram}`.toLowerCase().includes(search.toLowerCase())
  );
  return (
    <div>
      <div className={styles.headerRow}>
        <h2 className={styles.title}>Clientes</h2>
        <Btn onClick={onNew} variant="primary" className={styles.actionButton}>
          + Adicionar Cliente
        </Btn>
      </div>
      <input
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        placeholder="Buscar cliente..."
        className={styles.search}
      />
      <div className={styles.grid}>
        {filtered.map((c) => (
          <Card key={c.id}>
            <div className={styles.cardName}>{c.name}</div>
            <div className={styles.cardMuted}>📱 {c.phone}</div>
            {c.email && (
              <div className={styles.cardMuted}>✉️ {c.email}</div>
            )}
            {c.instagram && <div className={styles.cardAccent}>{c.instagram}</div>}
          </Card>
        ))}
      </div>
    </div>
  );
};

export default Customers;
