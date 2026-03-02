"use client";
import React, { useState } from "react";
import { Product } from "../types";
import { Btn, Card } from "../ui/Primitives";
import { fmtBRL } from "../helpers";

type Props = {
  products: Product[];
  onNew: () => void;
};

const Products: React.FC<Props> = ({ products, onNew }) => {
  const [addHover, setAddHover] = useState(false);
  return (
    <div>
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 20 }}>
        <h2 style={{ color: "var(--crm-text)", fontFamily: "'Inter', sans-serif", fontSize: 26, fontWeight: 600 }}>
          Produtos & Estoque
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
          + Adicionar Produto
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
              {["Marca / Modelo", "Custo", "Preço", "Margem", "Origem", "Estoque"].map((h) => (
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
            {products.map((p) => {
              const margin = (((p.price - p.cost) / p.price) * 100).toFixed(0);
              return (
                <tr key={p.id} style={{ borderBottom: "1px solid var(--crm-table-border)" }}>
                  <td style={{ padding: "10px 14px" }}>
                    <div style={{ color: "var(--crm-text)", fontWeight: 600 }}>{p.brand || "—"}</div>
                    <div style={{ color: "var(--crm-text-muted)", fontSize: 12 }}>
                      {p.model || "—"}
                      {p.modelQualityName ? ` · ${p.modelQualityName}` : ""}
                    </div>
                  </td>
                  <td
                    style={{
                      padding: "10px 14px",
                      color: "var(--crm-text-soft)",
                      fontFamily: "'Inter', sans-serif",
                      fontVariantNumeric: "tabular-nums",
                    }}
                  >
                    {fmtBRL(p.cost)}
                  </td>
                  <td
                    style={{
                      padding: "10px 14px",
                      color: "var(--crm-accent)",
                      fontFamily: "'Inter', sans-serif",
                      fontWeight: 600,
                      fontVariantNumeric: "tabular-nums",
                    }}
                  >
                    {fmtBRL(p.price)}
                  </td>
                  <td
                    style={{
                      padding: "10px 14px",
                      color: "var(--crm-accent)",
                      fontFamily: "'Inter', sans-serif",
                      fontVariantNumeric: "tabular-nums",
                    }}
                  >
                    {margin}%
                  </td>
                  <td style={{ padding: "10px 14px" }}>
                    <span
                      style={{
                        background:
                          p.stock === "IN_STOCK" ? "var(--crm-pill-primary-bg)" : "var(--crm-pill-muted-bg)",
                        color: p.stock === "IN_STOCK" ? "var(--crm-pill-primary-text)" : "var(--crm-pill-muted-text)",
                        borderRadius: 999,
                        padding: "4px 10px",
                        fontSize: 10,
                        fontWeight: 600,
                      }}
                    >
                      {p.stock === "IN_STOCK" ? "Estoque" : "Fornecedor"}
                    </span>
                  </td>
                  <td
                    style={{
                      padding: "10px 14px",
                      color: p.qty > 0 ? "var(--crm-text)" : "var(--crm-text-soft)",
                      fontFamily: "'Inter', sans-serif",
                      fontVariantNumeric: "tabular-nums",
                    }}
                  >
                    {p.qty > 0 ? `${p.qty} un.` : "—"}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </Card>
    </div>
  );
};

export default Products;
