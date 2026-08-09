"use client";

import { PaginationMeta } from "../types";
import { Btn } from "./Primitives";
import styles from "./PaginationBar.module.css";

type Props = {
  meta: PaginationMeta;
  onPageChange: (page: number) => void;
  disabled?: boolean;
};

export default function PaginationBar({ meta, onPageChange, disabled = false }: Props) {
  if (meta.total === 0) return null;

  return (
    <nav className={styles.root} aria-label="Paginação">
      <span className={styles.info}>{meta.from}–{meta.to} de {meta.total}</span>
      <div className={styles.actions}>
        <Btn variant="secondary" small disabled={disabled || meta.currentPage <= 1} onClick={() => onPageChange(meta.currentPage - 1)}>
          Anterior
        </Btn>
        <span className={styles.page}>Página {meta.currentPage} de {meta.lastPage}</span>
        <Btn variant="secondary" small disabled={disabled || meta.currentPage >= meta.lastPage} onClick={() => onPageChange(meta.currentPage + 1)}>
          Próxima
        </Btn>
      </div>
    </nav>
  );
}
