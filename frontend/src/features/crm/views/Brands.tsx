"use client";
import React from "react";
import { Brand } from "../types";
import { Btn, Card } from "../ui/Primitives";

type Props = {
  brands: Brand[];
  onNew: () => void;
};

const Brands: React.FC<Props> = ({ brands, onNew }) => {
  return (
    <div>
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 20 }}>
        <h2 style={{ color: "var(--crm-text)", fontFamily: "'Inter', sans-serif", fontSize: 26, fontWeight: 600 }}>
          Marcas
        </h2>
        <Btn onClick={onNew} variant="primary">
          + Adicionar Marca
        </Btn>
      </div>
      <Card style={{ padding: 0, overflow: "hidden" }}>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 13 }}>
          <thead>
            <tr
              style={{
                background: "var(--crm-table-header-bg)",
                borderBottom: "1px solid var(--crm-table-border)",
              }}
            >
              {["Marca", "ID"].map((h) => (
                <th
                  key={h}
                  style={{
                    color: "var(--crm-text-muted)",
                    fontWeight: 600,
                    padding: "10px 14px",
                    textAlign: "left",
                    fontSize: 11,
                    textTransform: "uppercase",
                  }}
                >
                  {h}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {brands.map((b) => (
              <tr key={b.id} style={{ borderBottom: "1px solid var(--crm-table-border)" }}>
                <td style={{ padding: "10px 14px", color: "var(--crm-text)", fontWeight: 600 }}>{b.name}</td>
                <td style={{ padding: "10px 14px", color: "var(--crm-text-muted)" }}>#{b.id}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </div>
  );
};

export default Brands;
