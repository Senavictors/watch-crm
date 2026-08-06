"use client";
import React, { useMemo, useState } from "react";
import { Brand, Category, Quality } from "../types";
import { Btn, Card } from "../ui/Primitives";
import styles from "./Settings.module.css";

type Props = {
  brands: Brand[];
  qualities: Quality[];
  categories: Category[];
  onAddBrand: (name: string) => void;
  onAddQuality: (name: string) => void;
  onAddCategory: (name: string, hasQuality: boolean) => void;
  onToast: (message: string, variant?: "success" | "error") => void;
};

const Settings: React.FC<Props> = ({ brands, qualities, categories, onAddBrand, onAddQuality, onAddCategory, onToast }) => {
  const [brandName, setBrandName] = useState("");
  const [qualityName, setQualityName] = useState("");
  const [categoryName, setCategoryName] = useState("");
  const [categoryHasQuality, setCategoryHasQuality] = useState(false);

  const brandRows = useMemo(() => brands, [brands]);
  const qualityRows = useMemo(() => qualities, [qualities]);
  const categoryRows = useMemo(() => categories, [categories]);

  function handleAddBrand() {
    if (!brandName.trim()) {
      onToast("Preencha o nome da marca.", "error");
      return;
    }
    onAddBrand(brandName.trim());
    setBrandName("");
  }

  function handleAddQuality() {
    if (!qualityName.trim()) {
      onToast("Preencha o nome da qualidade.", "error");
      return;
    }
    onAddQuality(qualityName.trim());
    setQualityName("");
  }

  function handleAddCategory() {
    if (!categoryName.trim()) {
      onToast("Preencha o nome da categoria.", "error");
      return;
    }
    onAddCategory(categoryName.trim(), categoryHasQuality);
    setCategoryName("");
    setCategoryHasQuality(false);
  }

  return (
    <div>
      <div className={styles.headerRow}>
        <div>
          <h2 className={styles.title}>Configurações</h2>
          <div className={styles.subtitle}>Centralize cadastros essenciais para o catálogo.</div>
        </div>
      </div>

      <div className={styles.grid}>
        <Card>
          <div className={styles.cardHeader}>
            <div className={styles.cardTitle}>Marcas</div>
            <div className={styles.cardSubtitle}>Cadastre e acompanhe as marcas disponíveis.</div>
          </div>
          <div className={styles.inputRow}>
            <input
              className={styles.inputControl}
              placeholder="Nova marca"
              value={brandName}
              onChange={(e) => setBrandName(e.target.value)}
              onKeyDown={(e) => e.key === "Enter" && handleAddBrand()}
            />
            <Btn onClick={handleAddBrand} variant="primary" className={styles.actionButton}>
              + Adicionar
            </Btn>
          </div>
          <div className={styles.tableWrap}>
            <div className={styles.tableHeader}>
              <div>Marca</div>
              <div>ID</div>
            </div>
            {brandRows.map((brand) => (
              <div key={brand.id} className={styles.tableRow}>
                <div className={styles.rowName}>{brand.name}</div>
                <div className={styles.rowId}>#{brand.id}</div>
              </div>
            ))}
          </div>
        </Card>

        <Card>
          <div className={styles.cardHeader}>
            <div className={styles.cardTitle}>Qualidades</div>
            <div className={styles.cardSubtitle}>Defina as qualidades disponíveis para modelos.</div>
          </div>
          <div className={styles.inputRow}>
            <input
              className={styles.inputControl}
              placeholder="Nova qualidade"
              value={qualityName}
              onChange={(e) => setQualityName(e.target.value)}
              onKeyDown={(e) => e.key === "Enter" && handleAddQuality()}
            />
            <Btn onClick={handleAddQuality} variant="primary" className={styles.actionButton}>
              + Adicionar
            </Btn>
          </div>
          <div className={styles.tableWrap}>
            <div className={styles.tableHeader}>
              <div>Qualidade</div>
              <div>ID</div>
            </div>
            {qualityRows.map((quality) => (
              <div key={quality.id} className={styles.tableRow}>
                <div className={styles.rowName}>{quality.name}</div>
                <div className={styles.rowId}>#{quality.id}</div>
              </div>
            ))}
          </div>
        </Card>

        <Card>
          <div className={styles.cardHeader}>
            <div className={styles.cardTitle}>Categorias</div>
            <div className={styles.cardSubtitle}>Classifique produtos livremente (Relógios, Caixas, Pulseiras...).</div>
          </div>
          <div className={styles.inputRow}>
            <input
              className={styles.inputControl}
              placeholder="Nova categoria"
              value={categoryName}
              onChange={(e) => setCategoryName(e.target.value)}
              onKeyDown={(e) => e.key === "Enter" && handleAddCategory()}
            />
            <Btn onClick={handleAddCategory} variant="primary" className={styles.actionButton}>
              + Adicionar
            </Btn>
          </div>
          <label className={styles.rowName} style={{ display: "flex", alignItems: "center", gap: 8, marginTop: 8 }}>
            <input
              type="checkbox"
              checked={categoryHasQuality}
              onChange={(e) => setCategoryHasQuality(e.target.checked)}
            />
            Usa qualidade (Prime/Base ETA)
          </label>
          <div className={styles.tableWrap}>
            <div className={styles.tableHeader}>
              <div>Categoria</div>
              <div>ID</div>
            </div>
            {categoryRows.map((category) => (
              <div key={category.id} className={styles.tableRow}>
                <div className={styles.rowName}>
                  {category.name}
                  {category.hasQuality ? " · usa qualidade" : ""}
                </div>
                <div className={styles.rowId}>#{category.id}</div>
              </div>
            ))}
          </div>
        </Card>
      </div>
    </div>
  );
};

export default Settings;
