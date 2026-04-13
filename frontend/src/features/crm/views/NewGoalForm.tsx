"use client";
import React, { useEffect, useMemo, useState } from "react";
import { Btn, Input, Select } from "../ui/Primitives";
import {
  Goal,
  GoalInput,
  GoalIntervalInput,
  GoalMetadata,
  GoalPeriodCycle,
  GoalScope,
  GoalCalculationType,
} from "../types";
import modalStyles from "../components/Modal/Modal.module.css";
import styles from "./NewGoalForm.module.css";

type Props = {
  goal?: Goal | null;
  metadata: GoalMetadata;
  onSave: (data: GoalInput) => void;
  onClose: () => void;
  onToast: (message: string, variant?: "success" | "error") => void;
};

function generateIntervals(startDate: string, endDate: string, cycle: GoalPeriodCycle): GoalIntervalInput[] {
  if (!startDate || !endDate) return [];
  const intervals: GoalIntervalInput[] = [];
  const end = new Date(endDate + "T00:00:00");
  let cursor = new Date(startDate + "T00:00:00");

  while (cursor <= end) {
    let intervalEnd: Date;

    switch (cycle) {
      case "monthly": {
        intervalEnd = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0);
        break;
      }
      case "quarterly": {
        const qMonth = cursor.getMonth();
        const qEnd = qMonth + (3 - (qMonth % 3));
        intervalEnd = new Date(cursor.getFullYear(), qEnd, 0);
        break;
      }
      case "semiannual": {
        const sMonth = cursor.getMonth();
        const sEnd = sMonth + (6 - (sMonth % 6));
        intervalEnd = new Date(cursor.getFullYear(), sEnd, 0);
        break;
      }
      case "annual": {
        intervalEnd = new Date(cursor.getFullYear(), 11, 31);
        break;
      }
    }

    if (intervalEnd > end) intervalEnd = new Date(end);

    intervals.push({
      startDate: cursor.toISOString().slice(0, 10),
      endDate: intervalEnd.toISOString().slice(0, 10),
      targetValue: 0,
    });

    cursor = new Date(intervalEnd);
    cursor.setDate(cursor.getDate() + 1);
  }

  return intervals;
}

function fmtDate(d: string): string {
  try {
    const [y, m, day] = d.split("-");
    return `${day}/${m}/${y}`;
  } catch {
    return d;
  }
}

const NewGoalForm: React.FC<Props> = ({ goal, metadata, onSave, onClose, onToast }) => {
  const isEditing = Boolean(goal);

  const [form, setForm] = useState({
    name: goal?.name ?? "",
    description: goal?.description ?? "",
    scope: (goal?.scope ?? "company") as GoalScope,
    targetUserId: goal?.targetUserId ? String(goal.targetUserId) : "",
    calculationType: (goal?.calculationType ?? "total_value") as GoalCalculationType,
    productTypeFilter: goal?.productTypeFilter ?? "",
    brandId: goal?.brandId ? String(goal.brandId) : "",
    modelId: goal?.modelId ? String(goal.modelId) : "",
    periodCycle: (goal?.periodCycle ?? "monthly") as GoalPeriodCycle,
    startDate: goal?.startDate ?? "",
    endDate: goal?.endDate ?? "",
  });

  const [intervals, setIntervals] = useState<GoalIntervalInput[]>(
    goal?.intervals.map((i) => ({ startDate: i.startDate, endDate: i.endDate, targetValue: i.targetValue })) ?? []
  );
  const [distributeTotal, setDistributeTotal] = useState("");

  function set<K extends keyof typeof form>(k: K, v: (typeof form)[K]) {
    setForm((f) => ({ ...f, [k]: v }));
  }

  // Re-generate intervals when dates or cycle change
  useEffect(() => {
    if (form.startDate && form.endDate && form.periodCycle) {
      const newIntervals = generateIntervals(form.startDate, form.endDate, form.periodCycle);
      setIntervals(newIntervals);
      setDistributeTotal("");
    }
  }, [form.startDate, form.endDate, form.periodCycle]);

  // Filter models by selected brand
  const filteredModels = useMemo(() => {
    if (!form.brandId) return metadata.models;
    return metadata.models.filter((m) => m.brandId === Number(form.brandId));
  }, [form.brandId, metadata.models]);

  function handleDistribute() {
    const total = Number(distributeTotal);
    if (!total || intervals.length === 0) return;
    const perInterval = Math.round((total / intervals.length) * 100) / 100;
    setIntervals((prev) => prev.map((i) => ({ ...i, targetValue: perInterval })));
  }

  function updateIntervalValue(index: number, value: number) {
    setIntervals((prev) => prev.map((i, idx) => (idx === index ? { ...i, targetValue: value } : i)));
  }

  function handleSubmit() {
    if (!form.name.trim()) {
      onToast("Preencha o nome da meta.", "error");
      return;
    }
    if (form.scope === "user" && !form.targetUserId) {
      onToast("Selecione o vendedor para a meta.", "error");
      return;
    }
    if (!form.startDate || !form.endDate) {
      onToast("Preencha as datas de início e fim.", "error");
      return;
    }
    if (intervals.length === 0) {
      onToast("Nenhum intervalo gerado. Verifique as datas.", "error");
      return;
    }
    if (intervals.some((i) => i.targetValue <= 0)) {
      onToast("Preencha o valor alvo de todos os intervalos.", "error");
      return;
    }

    const data: GoalInput = {
      name: form.name.trim(),
      description: form.description.trim() || null,
      scope: form.scope,
      targetUserId: form.scope === "user" ? Number(form.targetUserId) : null,
      calculationType: form.calculationType,
      productTypeFilter: form.productTypeFilter || null,
      brandId: form.brandId ? Number(form.brandId) : null,
      modelId: form.modelId ? Number(form.modelId) : null,
      periodCycle: form.periodCycle,
      startDate: form.startDate,
      endDate: form.endDate,
      intervals,
    };

    onSave(data);
  }

  return (
    <div className={modalStyles.overlay}>
      <div className={`${modalStyles.modal} ${styles.modal}`}>
        <div className={modalStyles.header}>
          <h3 className={modalStyles.title}>{isEditing ? "Editar Meta" : "Nova Meta"}</h3>
          <button onClick={onClose} className={modalStyles.close}>×</button>
        </div>

        <div className={modalStyles.formGridTwo}>
          <div className={styles.fullSpan}>
            <Input label="Nome da Meta" value={form.name} onChange={(e) => set("name", e.target.value)} />
          </div>

          <Select label="Escopo" value={form.scope} onChange={(e) => set("scope", e.target.value as GoalScope)}>
            {metadata.scopes.map((s) => (
              <option key={s.value} value={s.value}>{s.label}</option>
            ))}
          </Select>

          {form.scope === "user" ? (
            <Select label="Vendedor" value={form.targetUserId} onChange={(e) => set("targetUserId", e.target.value)}>
              <option value="">Selecionar vendedor...</option>
              {metadata.sellers.map((s) => (
                <option key={s.id} value={s.id}>{s.name}</option>
              ))}
            </Select>
          ) : (
            <div />
          )}

          <Select
            label="Tipo de Cálculo"
            value={form.calculationType}
            onChange={(e) => set("calculationType", e.target.value as GoalCalculationType)}
          >
            {metadata.calculationTypes.map((t) => (
              <option key={t.value} value={t.value}>{t.label}</option>
            ))}
          </Select>

          <Select
            label="Tipo de Produto"
            value={form.productTypeFilter}
            onChange={(e) => set("productTypeFilter", e.target.value)}
          >
            {metadata.productTypeFilters.map((t) => (
              <option key={t.value} value={t.value}>{t.label}</option>
            ))}
          </Select>

          <Select
            label="Marca"
            value={form.brandId}
            onChange={(e) => {
              set("brandId", e.target.value);
              set("modelId", "");
            }}
          >
            <option value="">Todas as marcas</option>
            {metadata.brands.map((b) => (
              <option key={b.id} value={b.id}>{b.name}</option>
            ))}
          </Select>

          <Select label="Modelo" value={form.modelId} onChange={(e) => set("modelId", e.target.value)}>
            <option value="">Todos os modelos</option>
            {filteredModels.map((m) => (
              <option key={m.id} value={m.id}>{m.name}</option>
            ))}
          </Select>

          <Input label="Data Início" type="date" value={form.startDate} onChange={(e) => set("startDate", e.target.value)} />
          <Input label="Data Fim" type="date" value={form.endDate} onChange={(e) => set("endDate", e.target.value)} />

          <Select
            label="Ciclo do Período"
            value={form.periodCycle}
            onChange={(e) => set("periodCycle", e.target.value as GoalPeriodCycle)}
          >
            {metadata.periodCycles.map((c) => (
              <option key={c.value} value={c.value}>{c.label}</option>
            ))}
          </Select>
          <div />

          {intervals.length > 0 && (
            <div className={styles.intervalsSection}>
              <div className={styles.intervalsHeader}>
                <span className={styles.intervalsTitle}>Metas por Período ({intervals.length})</span>
                <div className={styles.distributeRow}>
                  <input
                    type="number"
                    placeholder="Total..."
                    value={distributeTotal}
                    onChange={(e) => setDistributeTotal(e.target.value)}
                    className={styles.distributeInput}
                  />
                  <Btn onClick={handleDistribute} variant="secondary" small>
                    Distribuir
                  </Btn>
                </div>
              </div>

              <table className={styles.intervalsTable}>
                <thead>
                  <tr>
                    <th>Período</th>
                    <th>Valor Alvo</th>
                  </tr>
                </thead>
                <tbody>
                  {intervals.map((interval, idx) => (
                    <tr key={idx}>
                      <td>{fmtDate(interval.startDate)} — {fmtDate(interval.endDate)}</td>
                      <td>
                        <input
                          type="number"
                          min={0}
                          step="0.01"
                          value={interval.targetValue || ""}
                          onChange={(e) => updateIntervalValue(idx, Number(e.target.value) || 0)}
                          className={styles.intervalValueInput}
                          placeholder="0,00"
                        />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          <div className={styles.fullSpan}>
            <label className={styles.label}>Descrição</label>
            <textarea
              value={form.description}
              onChange={(e) => set("description", e.target.value)}
              className={modalStyles.notes}
              placeholder="Descrição opcional da meta..."
            />
          </div>
        </div>

        <div className={styles.actions}>
          <Btn onClick={onClose} variant="secondary" className={styles.actionButton}>
            Cancelar
          </Btn>
          <Btn onClick={handleSubmit} variant="primary" className={styles.actionButton}>
            {isEditing ? "Salvar" : "Criar Meta"}
          </Btn>
        </div>
      </div>
    </div>
  );
};

export default NewGoalForm;
