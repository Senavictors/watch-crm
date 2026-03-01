"use client";
import React, { useState } from "react";
import Image from "next/image";
import { Brand, WatchModel } from "../types";
import { Btn, Card } from "../ui/Primitives";

type Props = {
  models: WatchModel[];
  brands: Brand[];
  onNew: () => void;
};

const Models: React.FC<Props> = ({ models, brands, onNew }) => {
  const brandById = new Map(brands.map((brand) => [brand.id, brand.name]));
  const [addHover, setAddHover] = useState(false);

  return (
    <div>
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 20 }}>
        <div>
          <h2 style={{ color: "var(--crm-text)", fontFamily: "'Inter', sans-serif", fontSize: 26, fontWeight: 600 }}>
            Modelos
          </h2>
          <div style={{ color: "var(--crm-text-soft)", fontSize: 13, marginTop: 6 }}>
            Catálogo visual com foco na apresentação dos modelos.
          </div>
        </div>
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
          + Adicionar Modelo
        </Btn>
      </div>
      <div
        style={{
          display: "grid",
          gridTemplateColumns: "repeat(auto-fill, minmax(220px, 1fr))",
          gap: 16,
        }}
      >
        {models.map((m) => {
          const imageSrc = m.imageUrl || "/logo-queiroz.png";
          const shouldOptimize = imageSrc.startsWith("/");
          return (
            <Card
              key={m.id}
              style={{
                padding: 0,
                overflow: "hidden",
                display: "flex",
                flexDirection: "column",
                border: "1px solid var(--crm-card-border)",
              }}
            >
              <div
                style={{
                  padding: 12,
                  background:
                    "linear-gradient(180deg, var(--crm-table-header-bg) 0%, rgba(255,255,255,0) 100%)",
                  borderBottom: "1px solid var(--crm-card-border)",
                }}
              >
                <div
                  style={{
                    height: 180,
                    borderRadius: 12,
                    background: "var(--crm-input-bg)",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    overflow: "hidden",
                    position: "relative",
                  }}
                >
                  <Image
                    src={imageSrc}
                    alt={m.name}
                    fill
                    sizes="(max-width: 768px) 100vw, 220px"
                    unoptimized={!shouldOptimize}
                    style={{ objectFit: "contain" }}
                  />
                </div>
              </div>
              <div style={{ padding: 16, display: "grid", gap: 8 }}>
                <div style={{ color: "var(--crm-text)", fontWeight: 600, fontSize: 15 }}>{m.name}</div>
                <div style={{ color: "var(--crm-text-muted)", fontSize: 12 }}>
                  {brandById.get(m.brandId) || "—"}
                </div>
                <div
                  style={{
                    color: "var(--crm-primary)",
                    fontSize: 11,
                    textTransform: "uppercase",
                    letterSpacing: 1,
                  }}
                >
                  Modelo #{m.id}
                </div>
              </div>
            </Card>
          );
        })}
      </div>
    </div>
  );
};

export default Models;
