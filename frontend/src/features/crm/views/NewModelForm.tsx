"use client";
import React, { useMemo, useState } from "react";
import { Btn, Input, Select } from "../ui/Primitives";
import { Brand, ProductType, Quality, WatchModel } from "../types";
import { productTypeLabel } from "../helpers";
import modalStyles from "../components/Modal/Modal.module.css";
import styles from "./NewModelForm.module.css";

type Props = {
  brands: Brand[];
  qualities: Quality[];
  onSave: (model: Omit<WatchModel, "id" | "imageUrl"> & { imageFile?: File | null }) => void;
  onClose: () => void;
  onToast: (message: string, variant?: "success" | "error") => void;
};

const NewModelForm: React.FC<Props> = ({ brands, qualities, onSave, onClose, onToast }) => {
  const [name, setName] = useState("");
  const [brandId, setBrandId] = useState("");
  const [qualityId, setQualityId] = useState("");
  const [productType, setProductType] = useState<ProductType>("WATCH");
  const [imageFile, setImageFile] = useState<File | null>(null);
  const brandOptions = useMemo(() => brands, [brands]);
  const qualityOptions = useMemo(() => qualities, [qualities]);
  const showQuality = productType === "WATCH";

  function handleSubmit() {
    if (!name.trim() || !brandId || (showQuality && !qualityId)) {
      onToast(
        showQuality ? "Preencha o modelo, a marca e a qualidade." : "Preencha o modelo e a marca.",
        "error"
      );
      return;
    }
    onSave({
      name: name.trim(),
      brandId: Number(brandId),
      productType,
      qualityId: showQuality ? Number(qualityId) : null,
      imageFile,
    });
  }

  return (
    <div className={modalStyles.overlay}>
      <div className={`${modalStyles.modal} ${styles.modal}`}>
        <div className={modalStyles.header}>
          <h3 className={modalStyles.title}>Novo Modelo</h3>
          <button onClick={onClose} className={modalStyles.close}>
            ×
          </button>
        </div>

        <div className={modalStyles.formGridOne}>
          <Input label="Modelo" value={name} onChange={(e) => setName(e.target.value)} />
          <Select
            label="Tipo de Produto"
            value={productType}
            onChange={(e) => {
              const nextType = e.target.value as ProductType;
              setProductType(nextType);
              if (nextType === "BOX") {
                setQualityId("");
              }
            }}
          >
            {(["WATCH", "BOX"] as ProductType[]).map((type) => (
              <option key={type} value={type}>
                {productTypeLabel(type)}
              </option>
            ))}
          </Select>
          <Select label="Marca" value={brandId} onChange={(e) => setBrandId(e.target.value)}>
            <option value="">Selecionar marca...</option>
            {brandOptions.map((b) => (
              <option key={b.id} value={b.id}>
                {b.name}
              </option>
            ))}
          </Select>
          {showQuality && (
            <Select label="Qualidade" value={qualityId} onChange={(e) => setQualityId(e.target.value)}>
              <option value="">Selecionar qualidade...</option>
              {qualityOptions.map((q) => (
                <option key={q.id} value={q.id}>
                  {q.name}
                </option>
              ))}
            </Select>
          )}
          <div className={styles.fileField}>
            <label className={styles.label}>Imagem do Modelo</label>
            <input
              type="file"
              accept="image/png, image/jpeg"
              onChange={(e) => setImageFile(e.target.files?.[0] ?? null)}
              className={styles.fileInput}
            />
            <div className={styles.hint}>PNG ou JPG. Recomendado 800x800.</div>
          </div>
        </div>
        <div className={styles.actions}>
          <Btn onClick={onClose} variant="secondary" className={styles.actionButton}>
            Cancelar
          </Btn>
          <Btn onClick={handleSubmit} variant="primary" className={styles.actionButton}>
            Salvar Modelo
          </Btn>
        </div>
      </div>
    </div>
  );
};

export default NewModelForm;
