"use client";
import React from "react";
import { Brand } from "../types";
import { Btn, Card } from "../ui/Primitives";
import styles from "./Brands.module.css";

type Props = {
  brands: Brand[];
  onNew: () => void;
};

const Brands: React.FC<Props> = ({ brands, onNew }) => {
  return (
    <div>
      <div className={styles.headerRow}>
        <h2 className={styles.title}>Marcas</h2>
        <Btn onClick={onNew} variant="primary" className={styles.actionButton}>
          + Adicionar Marca
        </Btn>
      </div>
      <Card className={styles.tableCard}>
        <table className={styles.table}>
          <thead>
            <tr className={styles.theadRow}>
              {["Marca", "ID"].map((h) => (
                <th key={h} className={styles.theadCell}>
                  {h}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {brands.map((b) => (
              <tr key={b.id} className={styles.row}>
                <td className={styles.cellName}>{b.name}</td>
                <td className={styles.cellMuted}>#{b.id}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </div>
  );
};

export default Brands;
