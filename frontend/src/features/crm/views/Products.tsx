"use client";
import React from "react";
import { Product } from "../types";
import { fmtBRL, productTypeLabel } from "../helpers";
import { Btn, Card } from "../ui/Primitives";
import styles from "./Products.module.css";

type Props = {
  products: Product[];
  canCreate: boolean;
  compact: boolean;
  onNew: () => void;
};

const Products: React.FC<Props> = ({ products, canCreate, compact, onNew }) => {
  return (
    <div>
      <div className={styles.headerRow}>
        <h2 className={styles.title}>Produtos & Estoque</h2>
        {canCreate && (
          <Btn onClick={onNew} variant="primary" className={styles.actionButton}>
            + Adicionar Produto
          </Btn>
        )}
      </div>
      <Card className={styles.tableCard}>
        <table className={styles.table}>
          <thead>
            <tr className={styles.theadRow}>
              {(compact
                ? ["Preço", "Origem", "Estoque"]
                : ["Marca / Modelo", "Custo", "Preço", "Margem", "Origem", "Estoque"]).map((h) => (
                <th key={h} className={styles.theadCell}>
                  {h}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {products.map((p) => {
              const margin = (((p.price - p.cost) / p.price) * 100).toFixed(0);
              return (
                <tr key={p.id} className={styles.row}>
                  {!compact && (
                    <td className={styles.cell}>
                      <div className={styles.name}>{p.brand || "—"}</div>
                      <div className={styles.sub}>
                        {p.model || "—"}
                        {p.productType === "BOX"
                          ? ` · ${productTypeLabel(p.productType)}`
                          : p.modelQualityName
                            ? ` · ${p.modelQualityName}`
                            : ""}
                      </div>
                    </td>
                  )}
                  {!compact && <td className={styles.numericSoft}>{fmtBRL(p.cost)}</td>}
                  <td className={styles.numericAccentStrong}>{fmtBRL(p.price)}</td>
                  {!compact && <td className={styles.numericAccent}>{margin}%</td>}
                  <td className={styles.cell}>
                    <span
                      className={`${styles.pill} ${
                        p.stock === "IN_STOCK" ? styles.pillStock : styles.pillSupplier
                      }`}
                    >
                      {p.stock === "IN_STOCK" ? "Estoque" : "Fornecedor"}
                    </span>
                  </td>
                  <td
                    className={`${styles.numericAccent} ${p.qty > 0 ? "" : styles.qtyMuted}`}
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
