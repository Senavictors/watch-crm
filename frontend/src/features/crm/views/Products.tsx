"use client";
import React from "react";
import { Product } from "../types";
import { Btn, Card } from "../ui/Primitives";
import { fmtBRL } from "../helpers";

type Props = {
  products: Product[];
  onNew: () => void;
};

const Products: React.FC<Props> = ({ products, onNew }) => {
  return (
    <div>
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 20 }}>
        <h2 style={{ color: "#F8FAFC", fontFamily: "'Inter', sans-serif", fontSize: 26, fontWeight: 600 }}>
          Produtos & Estoque
        </h2>
        <Btn onClick={onNew} variant="primary">
          Adicionar Produto
        </Btn>
      </div>
      <Card style={{ padding: 0, overflow: "hidden" }}>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 13 }}>
          <thead>
            <tr
              style={{
                background: "rgba(255, 255, 255, 0.04)",
                borderBottom: "1px solid rgba(255, 255, 255, 0.08)",
              }}
            >
              {["Marca / Modelo", "Custo", "Preço", "Margem", "Origem", "Estoque"].map((h) => (
                <th
                  key={h}
                  style={{
                    color: "#94A3B8",
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
                <tr key={p.id} style={{ borderBottom: "1px solid rgba(255, 255, 255, 0.06)" }}>
                  <td style={{ padding: "10px 14px" }}>
                    <div style={{ color: "#F8FAFC", fontWeight: 600 }}>{p.brand || "—"}</div>
                    <div style={{ color: "#94A3B8", fontSize: 12 }}>{p.model || "—"}</div>
                  </td>
                  <td
                    style={{
                      padding: "10px 14px",
                      color: "#64748B",
                      fontFamily: "'Inter', sans-serif",
                      fontVariantNumeric: "tabular-nums",
                    }}
                  >
                    {fmtBRL(p.cost)}
                  </td>
                  <td
                    style={{
                      padding: "10px 14px",
                      color: "#93C5FD",
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
                      color: "#93C5FD",
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
                          p.stock === "IN_STOCK" ? "rgba(96, 165, 250, 0.18)" : "rgba(148, 163, 184, 0.12)",
                        color: p.stock === "IN_STOCK" ? "#93C5FD" : "#94A3B8",
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
                      color: p.qty > 0 ? "#E2E8F0" : "#64748B",
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
