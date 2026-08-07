"use client";
import React from "react";
import { PERIOD_PRESETS, PeriodPresetId } from "./periodPresets";
import styles from "./PeriodSelector.module.css";

type Props = {
  preset: PeriodPresetId;
  customFrom: string;
  customTo: string;
  refreshing: boolean;
  onPresetChange: (preset: PeriodPresetId) => void;
  onCustomFromChange: (value: string) => void;
  onCustomToChange: (value: string) => void;
  onRefresh: () => void;
};

const PeriodSelector: React.FC<Props> = ({
  preset,
  customFrom,
  customTo,
  refreshing,
  onPresetChange,
  onCustomFromChange,
  onCustomToChange,
  onRefresh,
}) => {
  return (
    <div className={styles.wrapper}>
      <div className={styles.presetRow}>
        {PERIOD_PRESETS.map((option) => (
          <button
            key={option.id}
            type="button"
            onClick={() => onPresetChange(option.id)}
            className={`${styles.presetButton} ${preset === option.id ? styles.presetButtonActive : ""}`}
          >
            {option.label}
          </button>
        ))}
      </div>

      <div className={styles.rightRow}>
        {preset === "custom" && (
          <div className={styles.customRow}>
            <input
              type="date"
              value={customFrom}
              onChange={(e) => onCustomFromChange(e.target.value)}
              className={styles.dateInput}
              aria-label="Data inicial"
              title="Data inicial"
            />
            <span className={styles.customSeparator}>até</span>
            <input
              type="date"
              value={customTo}
              onChange={(e) => onCustomToChange(e.target.value)}
              className={styles.dateInput}
              aria-label="Data final"
              title="Data final"
            />
          </div>
        )}

        <button
          type="button"
          onClick={onRefresh}
          className={styles.refreshButton}
          disabled={refreshing}
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="14"
            height="14"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            className={refreshing ? styles.refreshIconSpinning : styles.refreshIcon}
          >
            <path d="M21 12a9 9 0 1 1-2.64-6.36" />
            <path d="M21 3v6h-6" />
          </svg>
          {refreshing ? "Atualizando..." : "Atualizar"}
        </button>
      </div>
    </div>
  );
};

export default PeriodSelector;
