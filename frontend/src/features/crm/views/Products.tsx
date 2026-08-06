"use client";
import React from "react";
import { Product } from "../types";
import { fmtBRL } from "../helpers";
import { Btn, Card } from "../ui/Primitives";
import styles from "./Products.module.css";

type Props = {
  products: Product[];
  canCreate: boolean;
  canUpdate: boolean;
  canDelete: boolean;
  // TASK-013 (RN-02): custo/margem só aparecem pra quem tem
  // dashboard.financial.view OU gerencia catálogo (products.create/update) —
  // ver User::canViewCatalogCost() no backend. Não confundir com `compact`
  // antigo, que era uma densidade visual, não um controle de acesso.
  canViewFinancials: boolean;
  onNew: () => void;
  onEdit: (product: Product) => void;
  onDelete: (product: Product) => void;
  onAddQty: (product: Product) => void;
};

const Products: React.FC<Props> = ({ products, canCreate, canUpdate, canDelete, canViewFinancials, onNew, onEdit, onDelete, onAddQty }) => {
  const showActions = canUpdate || canDelete;

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
              {["Marca / Modelo",
                ...(canViewFinancials ? ["Custo"] : []),
                "Preço",
                ...(canViewFinancials ? ["Margem"] : []),
                "Comissão",
                "Origem", "Estoque",
                ...(showActions ? ["Ações"] : []),
              ].map((h) => (
                <th key={h} className={styles.theadCell}>
                  {h}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {products.map((p) => {
              const margin = p.cost !== undefined ? (((p.price - p.cost) / p.price) * 100).toFixed(0) : null;
              return (
                <tr key={p.id} className={styles.row}>
                  <td className={styles.cell}>
                    <div className={styles.name}>{p.brand || "—"}</div>
                    <div className={styles.sub}>
                      {p.model || "—"}
                      {!p.categoryHasQuality
                        ? ` · ${p.categoryName ?? "—"}`
                        : p.modelQualityName
                          ? ` · ${p.modelQualityName}`
                          : ""}
                    </div>
                  </td>
                  {canViewFinancials && <td className={styles.numericSoft}>{fmtBRL(p.cost)}</td>}
                  <td className={styles.numericAccentStrong}>{fmtBRL(p.price)}</td>
                  {canViewFinancials && <td className={styles.numericAccent}>{margin ?? "—"}%</td>}
                  <td className={styles.numericSoft}>{p.commissionAmount != null ? fmtBRL(p.commissionAmount) : "—"}</td>
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
                  {showActions && (
                    <td className={styles.cell}>
                      <div className={styles.rowActions}>
                        {canUpdate && (
                          <>
                            <Btn onClick={() => onAddQty(p)} variant="secondary" small className={styles.rowAction}>
                              Entrada
                            </Btn>
                            <Btn onClick={() => onEdit(p)} variant="secondary" small className={styles.rowAction}>
                              Editar
                            </Btn>
                          </>
                        )}
                        {canDelete && (
                          <Btn onClick={() => onDelete(p)} variant="danger" small className={styles.rowAction}>
                            Excluir
                          </Btn>
                        )}
                      </div>
                    </td>
                  )}
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
