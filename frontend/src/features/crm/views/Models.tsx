"use client";
import React from "react";
import Image from "next/image";
import { Brand, WatchModel } from "../types";
import { Btn, Card } from "../ui/Primitives";
import styles from "./Models.module.css";

type Props = {
  models: WatchModel[];
  brands: Brand[];
  onNew: () => void;
};

const Models: React.FC<Props> = ({ models, brands, onNew }) => {
  const brandById = new Map(brands.map((brand) => [brand.id, brand.name]));
  return (
    <div>
      <div className={styles.headerRow}>
        <div>
          <h2 className={styles.title}>Modelos</h2>
          <div className={styles.subtitle}>Catálogo visual com foco na apresentação dos modelos.</div>
        </div>
        <Btn onClick={onNew} variant="primary" className={styles.actionButton}>
          + Adicionar Modelo
        </Btn>
      </div>
      <div className={styles.grid}>
        {models.map((m) => {
          const imageSrc = m.imageUrl || "/logo-queiroz.png";
          const shouldOptimize = imageSrc.startsWith("/");
          return (
            <Card
              key={m.id}
              className={styles.card}
            >
              <div className={styles.media}>
                <div className={styles.mediaBox}>
                  <Image
                    src={imageSrc}
                    alt={m.name}
                    fill
                    sizes="(max-width: 768px) 100vw, 220px"
                    unoptimized={!shouldOptimize}
                    className={styles.image}
                  />
                </div>
              </div>
              <div className={styles.cardBody}>
                <div className={styles.name}>{m.name}</div>
                <div className={styles.brand}>{brandById.get(m.brandId) || "—"}</div>
                <div className={styles.quality}>{m.qualityName ?? "—"}</div>
                <div className={styles.meta}>Modelo #{m.id}</div>
              </div>
            </Card>
          );
        })}
      </div>
    </div>
  );
};

export default Models;
