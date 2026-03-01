"use client";
import React, { useState } from "react";
import { Customer } from "../types";
import { Btn, Card } from "../ui/Primitives";

type Props = {
  customers: Customer[];
  onNew: () => void;
};

const Customers: React.FC<Props> = ({ customers, onNew }) => {
  const [search, setSearch] = useState("");
  const [addHover, setAddHover] = useState(false);
  const filtered = customers.filter(
    (c) => !search || `${c.name} ${c.phone} ${c.instagram}`.toLowerCase().includes(search.toLowerCase())
  );
  return (
    <div>
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 20 }}>
        <h2 style={{ color: "var(--crm-text)", fontFamily: "'Inter', sans-serif", fontSize: 26, fontWeight: 600 }}>
          Clientes
        </h2>
        <Btn
          onClick={onNew}
          variant="primary"
          onMouseEnter={() => setAddHover(true)}
          onMouseLeave={() => setAddHover(false)}
          style={{
            background: "var(--crm-button-primary-bg)",
            color: "var(--crm-button-primary-text)",
            padding: "9px 18px",
            fontSize: 13,
            fontWeight: 600,
            boxShadow:
              "0 1px 0 0 rgba(255, 255, 255, 0.4) inset, 0 1px 2px rgba(15, 23, 42, 0.2)",
            transform: addHover ? "translateY(-2px)" : "translateY(0)",
            transition: "all 0.2s ease",
          }}
        >
          + Adicionar Cliente
        </Btn>
      </div>
      <input
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        placeholder="Buscar cliente..."
        style={{
          background: "var(--crm-input-bg)",
          border: "1px solid var(--crm-input-border)",
          borderRadius: 12,
          color: "var(--crm-input-text)",
          padding: "8px 14px",
          fontSize: 13,
          outline: "none",
          width: "100%",
          marginBottom: 16,
          boxSizing: "border-box",
        }}
      />
      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(260px, 1fr))", gap: 14 }}>
        {filtered.map((c) => (
          <Card key={c.id}>
            <div style={{ color: "var(--crm-text)", fontWeight: 700, fontSize: 15, marginBottom: 6 }}>
              {c.name}
            </div>
            <div style={{ color: "var(--crm-text-muted)", fontSize: 12, marginBottom: 2 }}>
              📱 {c.phone}
            </div>
            {c.email && (
              <div style={{ color: "var(--crm-text-muted)", fontSize: 12, marginBottom: 2 }}>
                ✉️ {c.email}
              </div>
            )}
            {c.instagram && <div style={{ color: "var(--crm-accent)", fontSize: 12 }}>{c.instagram}</div>}
          </Card>
        ))}
      </div>
    </div>
  );
};

export default Customers;
