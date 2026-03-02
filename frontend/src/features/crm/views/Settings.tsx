"use client";
import React, { useMemo, useState } from "react";
import { Brand, Quality } from "../types";
import { Btn, Card, Input } from "../ui/Primitives";
import styles from "./Settings.module.css";

type Props = {
  brands: Brand[];
  qualities: Quality[];
  onAddBrand: (name: string) => void;
  onAddQuality: (name: string) => void;
  onToast: (message: string, variant?: "success" | "error") => void;
};

const Settings: React.FC<Props> = ({ brands, qualities, onAddBrand, onAddQuality, onToast }) => {
  const [brandName, setBrandName] = useState("");
  const [qualityName, setQualityName] = useState("");

  const brandRows = useMemo(() => brands, [brands]);
  const qualityRows = useMemo(() => qualities, [qualities]);

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
            <div>
              <div className={styles.cardTitle}>Marcas</div>
              <div className={styles.cardSubtitle}>Cadastre e acompanhe as marcas disponíveis.</div>
            </div>
            <Btn onClick={handleAddBrand} variant="primary" className={styles.actionButton}>
              + Adicionar
            </Btn>
          </div>
          <Input label="Nova marca" value={brandName} onChange={(e) => setBrandName(e.target.value)} />
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
            <div>
              <div className={styles.cardTitle}>Qualidades</div>
              <div className={styles.cardSubtitle}>Defina as qualidades disponíveis para modelos.</div>
            </div>
            <Btn onClick={handleAddQuality} variant="primary" className={styles.actionButton}>
              + Adicionar
            </Btn>
          </div>
          <Input label="Nova qualidade" value={qualityName} onChange={(e) => setQualityName(e.target.value)} />
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
      </div>
    </div>
  );
};

export default Settings;
