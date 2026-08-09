"use client";

import { useEffect, useMemo, useState } from "react";
import { apiGet } from "../api";
import { useDebouncedValue } from "../hooks/useDebouncedValue";
import { LookupResponse } from "../types";
import styles from "./AsyncLookupSelect.module.css";

type Props<T> = {
  label: string;
  endpoint: string;
  value: string;
  getValue: (option: T) => string;
  getLabel: (option: T) => string;
  onSelect: (option: T | null) => void;
  initialOption?: T | null;
  disabled?: boolean;
  placeholder?: string;
};

export default function AsyncLookupSelect<T>({
  label,
  endpoint,
  value,
  getValue,
  getLabel,
  onSelect,
  initialOption = null,
  disabled = false,
  placeholder = "Digite para pesquisar...",
}: Props<T>) {
  const [search, setSearch] = useState("");
  const [options, setOptions] = useState<T[]>(initialOption ? [initialOption] : []);
  const debouncedSearch = useDebouncedValue(search);

  useEffect(() => {
    let alive = true;
    const separator = endpoint.includes("?") ? "&" : "?";
    apiGet<LookupResponse<T>>(
      `${endpoint}${separator}search=${encodeURIComponent(debouncedSearch)}`,
      `Falha ao pesquisar ${label.toLowerCase()}.`
    )
      .then((response) => {
        if (!alive) return;
        setOptions(response.data);
      })
      .catch(() => undefined);

    return () => { alive = false; };
  }, [debouncedSearch, endpoint, label]);

  const selectedOption = useMemo(
    () => options.find((option) => getValue(option) === value) ?? initialOption,
    [options, value, initialOption, getValue]
  );

  return (
    <label className={styles.field}>
      <span className={styles.label}>{label}</span>
      <input
        className={styles.search}
        value={search}
        onChange={(event) => setSearch(event.target.value)}
        placeholder={placeholder}
        disabled={disabled}
      />
      <select
        className={styles.select}
        value={value}
        disabled={disabled}
        onChange={(event) => {
          const option = options.find((entry) => getValue(entry) === event.target.value) ?? null;
          onSelect(option);
        }}
      >
        <option value="">Selecionar...</option>
        {selectedOption && !options.some((option) => getValue(option) === getValue(selectedOption)) && (
          <option value={getValue(selectedOption)}>{getLabel(selectedOption)}</option>
        )}
        {options.map((option) => (
          <option key={getValue(option)} value={getValue(option)}>{getLabel(option)}</option>
        ))}
      </select>
    </label>
  );
}
