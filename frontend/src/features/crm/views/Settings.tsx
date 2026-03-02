"use client";
import React, { useMemo, useState } from "react";
import { Brand, Quality } from "../types";
import { Btn, Card, Input } from "../ui/Primitives";

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
  const [brandHover, setBrandHover] = useState(false);
  const [qualityHover, setQualityHover] = useState(false);

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
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 20 }}>
        <div>
          <h2 style={{ color: "var(--crm-text)", fontFamily: "'Inter', sans-serif", fontSize: 26, fontWeight: 600 }}>
            Configurações
          </h2>
          <div style={{ color: "var(--crm-text-soft)", fontSize: 13, marginTop: 6 }}>
            Centralize cadastros essenciais para o catálogo.
          </div>
        </div>
      </div>

      <div style={{ display: "grid", gridTemplateColumns: "repeat(2, minmax(0, 1fr))", gap: 20 }}>
        <Card>
          <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 14 }}>
            <div>
              <div style={{ color: "var(--crm-text)", fontWeight: 600, fontSize: 16 }}>Marcas</div>
              <div style={{ color: "var(--crm-text-soft)", fontSize: 12, marginTop: 4 }}>
                Cadastre e acompanhe as marcas disponíveis.
              </div>
            </div>
            <Btn
              onClick={handleAddBrand}
              variant="primary"
              onMouseEnter={() => setBrandHover(true)}
              onMouseLeave={() => setBrandHover(false)}
              style={{
                background: "var(--crm-button-primary-bg)",
                color: "var(--crm-button-primary-text)",
                padding: "8px 16px",
                fontSize: 12,
                fontWeight: 600,
                boxShadow: "0 1px 0 0 rgba(255, 255, 255, 0.4) inset, 0 1px 2px rgba(15, 23, 42, 0.2)",
                transform: brandHover ? "translateY(-2px)" : "translateY(0)",
                transition: "all 0.2s ease",
              }}
            >
              + Adicionar
            </Btn>
          </div>
          <Input label="Nova marca" value={brandName} onChange={(e) => setBrandName(e.target.value)} />
          <div style={{ marginTop: 16 }}>
            <div
              style={{
                display: "grid",
                gridTemplateColumns: "1fr auto",
                gap: 12,
                padding: "8px 12px",
                color: "var(--crm-text-muted)",
                fontSize: 11,
                borderBottom: "1px solid var(--crm-table-border)",
                textTransform: "uppercase",
                letterSpacing: 0.6,
              }}
            >
              <div>Marca</div>
              <div>ID</div>
            </div>
            {brandRows.map((brand) => (
              <div
                key={brand.id}
                style={{
                  display: "grid",
                  gridTemplateColumns: "1fr auto",
                  gap: 12,
                  padding: "10px 12px",
                  borderBottom: "1px solid var(--crm-table-border)",
                }}
              >
                <div style={{ color: "var(--crm-text)", fontWeight: 600 }}>{brand.name}</div>
                <div style={{ color: "var(--crm-text-muted)" }}>#{brand.id}</div>
              </div>
            ))}
          </div>
        </Card>

        <Card>
          <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 14 }}>
            <div>
              <div style={{ color: "var(--crm-text)", fontWeight: 600, fontSize: 16 }}>Qualidades</div>
              <div style={{ color: "var(--crm-text-soft)", fontSize: 12, marginTop: 4 }}>
                Defina as qualidades disponíveis para modelos.
              </div>
            </div>
            <Btn
              onClick={handleAddQuality}
              variant="primary"
              onMouseEnter={() => setQualityHover(true)}
              onMouseLeave={() => setQualityHover(false)}
              style={{
                background: "var(--crm-button-primary-bg)",
                color: "var(--crm-button-primary-text)",
                padding: "8px 16px",
                fontSize: 12,
                fontWeight: 600,
                boxShadow: "0 1px 0 0 rgba(255, 255, 255, 0.4) inset, 0 1px 2px rgba(15, 23, 42, 0.2)",
                transform: qualityHover ? "translateY(-2px)" : "translateY(0)",
                transition: "all 0.2s ease",
              }}
            >
              + Adicionar
            </Btn>
          </div>
          <Input label="Nova qualidade" value={qualityName} onChange={(e) => setQualityName(e.target.value)} />
          <div style={{ marginTop: 16 }}>
            <div
              style={{
                display: "grid",
                gridTemplateColumns: "1fr auto",
                gap: 12,
                padding: "8px 12px",
                color: "var(--crm-text-muted)",
                fontSize: 11,
                borderBottom: "1px solid var(--crm-table-border)",
                textTransform: "uppercase",
                letterSpacing: 0.6,
              }}
            >
              <div>Qualidade</div>
              <div>ID</div>
            </div>
            {qualityRows.map((quality) => (
              <div
                key={quality.id}
                style={{
                  display: "grid",
                  gridTemplateColumns: "1fr auto",
                  gap: 12,
                  padding: "10px 12px",
                  borderBottom: "1px solid var(--crm-table-border)",
                }}
              >
                <div style={{ color: "var(--crm-text)", fontWeight: 600 }}>{quality.name}</div>
                <div style={{ color: "var(--crm-text-muted)" }}>#{quality.id}</div>
              </div>
            ))}
          </div>
        </Card>
      </div>
    </div>
  );
};

export default Settings;
