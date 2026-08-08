"use client";
import React, { useEffect, useMemo, useState } from "react";
import { AiSettingsResponse, Brand, Category, PostingDaySchedule, Quality } from "../types";
import { Btn, Card } from "../ui/Primitives";
import styles from "./Settings.module.css";

type Props = {
  brands: Brand[];
  qualities: Quality[];
  categories: Category[];
  schedule: PostingDaySchedule[];
  canEditSchedule: boolean;
  aiSettings: AiSettingsResponse | null;
  canUpdateAiSettings: boolean;
  onAddBrand: (name: string) => void;
  onAddQuality: (name: string) => void;
  onAddCategory: (name: string, hasQuality: boolean) => void;
  onSaveSchedule: (days: { weekday: number; enabled: boolean }[]) => Promise<void>;
  onSaveAiSettings: (settings: { apiKey: string | null; projectId: string | null; model: string; enabled: boolean }) => Promise<void>;
  onRemoveAiKey: () => Promise<void>;
  onToast: (message: string, variant?: "success" | "error") => void;
};

const Settings: React.FC<Props> = ({
  brands,
  qualities,
  categories,
  schedule,
  canEditSchedule,
  aiSettings,
  canUpdateAiSettings,
  onAddBrand,
  onAddQuality,
  onAddCategory,
  onSaveSchedule,
  onSaveAiSettings,
  onRemoveAiKey,
  onToast,
}) => {
  const [brandName, setBrandName] = useState("");
  const [qualityName, setQualityName] = useState("");
  const [categoryName, setCategoryName] = useState("");
  const [categoryHasQuality, setCategoryHasQuality] = useState(false);
  const [scheduleDraft, setScheduleDraft] = useState<PostingDaySchedule[]>(schedule);
  const [savingSchedule, setSavingSchedule] = useState(false);
  const [aiApiKey, setAiApiKey] = useState("");
  const [aiProjectId, setAiProjectId] = useState(aiSettings?.projectId ?? "");
  const [aiModel, setAiModel] = useState(aiSettings?.model ?? "gpt-5.6-luna");
  const [aiEnabled, setAiEnabled] = useState(aiSettings?.enabled ?? true);
  const [savingAi, setSavingAi] = useState(false);

  useEffect(() => {
    setScheduleDraft(schedule);
  }, [schedule]);

  useEffect(() => {
    setAiApiKey("");
    setAiProjectId(aiSettings?.projectId ?? "");
    setAiModel(aiSettings?.model ?? "gpt-5.6-luna");
    setAiEnabled(aiSettings?.enabled ?? true);
  }, [aiSettings]);

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

  function handleToggleDay(weekday: number) {
    if (!canEditSchedule) return;
    setScheduleDraft((days) =>
      days.map((d) => (d.weekday === weekday ? { ...d, enabled: !d.enabled } : d))
    );
  }

  async function handleSaveSchedule() {
    setSavingSchedule(true);
    try {
      await onSaveSchedule(scheduleDraft.map((d) => ({ weekday: d.weekday, enabled: d.enabled })));
    } catch {
      // reverte a UI pro estado confirmado pela API em caso de falha (ex.:
      // 422 de "zero dias habilitados") — o toast de erro é disparado pelo
      // handler no page.
      setScheduleDraft(schedule);
    } finally {
      setSavingSchedule(false);
    }
  }

  async function handleSaveAi() {
    setSavingAi(true);
    try {
      await onSaveAiSettings({
        apiKey: aiApiKey.trim() || null,
        projectId: aiProjectId.trim() || null,
        model: aiModel.trim(),
        enabled: aiEnabled,
      });
      setAiApiKey("");
    } finally {
      setSavingAi(false);
    }
  }

  async function handleRemoveAiKey() {
    setSavingAi(true);
    try {
      await onRemoveAiKey();
      setAiApiKey("");
    } finally {
      setSavingAi(false);
    }
  }

  return (
    <div>
      <div className={styles.headerRow}>
        <div>
          <h2 className={styles.title}>Configurações</h2>
          <div className={styles.subtitle}>Centralize cadastros, operação e integrações do CRM.</div>
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

        <Card>
          <div className={styles.cardHeader}>
            <div className={styles.cardTitle}>Dias de Postagem</div>
            <div className={styles.cardSubtitle}>
              Defina em quais dias da semana os pedidos são postados — usado pela fila de envios.
            </div>
          </div>
          <div className={styles.scheduleList}>
            {scheduleDraft.map((day) => (
              <label key={day.weekday} className={styles.scheduleRow}>
                <input
                  type="checkbox"
                  checked={day.enabled}
                  disabled={!canEditSchedule}
                  onChange={() => handleToggleDay(day.weekday)}
                />
                {day.label}
              </label>
            ))}
          </div>
          {canEditSchedule && (
            <Btn onClick={handleSaveSchedule} variant="primary" className={styles.actionButton} disabled={savingSchedule}>
              Salvar
            </Btn>
          )}
        </Card>

        {aiSettings && (
          <Card>
            <div className={styles.cardHeader}>
              <div className={styles.cardTitle}>Integração OpenAI</div>
              <div className={styles.cardSubtitle}>
                Configure o resumo inteligente. A chave é enviada uma vez, criptografada no backend e nunca exibida novamente.
              </div>
            </div>

            <div className={styles.integrationStatus}>
              <span className={aiSettings.configured ? styles.statusOk : styles.statusPending}>
                {aiSettings.configured ? "Chave configurada" : "Chave não configurada"}
              </span>
              <span className={styles.statusDetail}>
                Origem: {aiSettings.apiKeySource === "database" ? "painel do CRM" : aiSettings.apiKeySource === "environment" ? "ambiente do servidor" : "nenhuma"}
              </span>
            </div>

            {!aiSettings.featureEnabled && (
              <div className={styles.warning}>A feature flag AI_SUMMARY_ENABLED está desabilitada no servidor.</div>
            )}

            <label className={styles.fieldLabel}>
              Chave de API
              <input
                type="password"
                autoComplete="new-password"
                className={styles.inputControl}
                placeholder={aiSettings.configured ? "Deixe vazio para manter a chave atual" : "sk-proj-..."}
                value={aiApiKey}
                disabled={!canUpdateAiSettings}
                onChange={(event) => setAiApiKey(event.target.value)}
              />
            </label>

            <label className={styles.fieldLabel}>
              ID do projeto (opcional)
              <input
                className={styles.inputControl}
                placeholder="proj_..."
                value={aiProjectId}
                disabled={!canUpdateAiSettings}
                onChange={(event) => setAiProjectId(event.target.value)}
              />
            </label>

            <label className={styles.fieldLabel}>
              Modelo
              <input
                className={styles.inputControl}
                value={aiModel}
                disabled={!canUpdateAiSettings}
                onChange={(event) => setAiModel(event.target.value)}
              />
            </label>

            <label className={styles.scheduleRow}>
              <input
                type="checkbox"
                checked={aiEnabled}
                disabled={!canUpdateAiSettings}
                onChange={(event) => setAiEnabled(event.target.checked)}
              />
              Habilitar geração de resumos
            </label>

            <div className={styles.integrationActions}>
              <Btn onClick={handleSaveAi} variant="primary" disabled={!canUpdateAiSettings || savingAi || !aiModel.trim()}>
                {savingAi ? "Salvando..." : "Salvar integração"}
              </Btn>
              {aiSettings.apiKeySource === "database" && (
                <Btn onClick={handleRemoveAiKey} variant="danger" disabled={!canUpdateAiSettings || savingAi}>
                  Remover chave
                </Btn>
              )}
            </div>

            <a
              className={styles.helpLink}
              href="https://platform.openai.com/settings/organization/api-keys"
              target="_blank"
              rel="noreferrer"
            >
              Abrir gerenciamento de chaves da OpenAI ↗
            </a>
          </Card>
        )}
      </div>
    </div>
  );
};

export default Settings;
