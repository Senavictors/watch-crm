"use client";
import React from "react";
import styles from "./ThemeToggle.module.css";

type Theme = "light" | "dark" | "system";

type Props = {
  theme: Theme;
  onChangeTheme: (t: Theme) => void;
};

const ThemeToggle: React.FC<Props> = ({ theme, onChangeTheme }) => (
  <div className={styles.toggle}>
    <div
      className={styles.thumb}
      style={{
        transform:
          theme === "light" ? "translateX(0px)" : theme === "system" ? "translateX(22px)" : "translateX(44px)",
      }}
    />
    <button
      onClick={() => onChangeTheme("light")}
      aria-label="Light mode"
      type="button"
      className={`${styles.toggleButton} ${theme === "light" ? styles.toggleButtonActive : styles.toggleButtonInactive}`}
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="12"
        height="12"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      >
        <circle cx="12" cy="12" r="4" />
        <path d="M12 2v2" />
        <path d="M12 20v2" />
        <path d="m4.93 4.93 1.41 1.41" />
        <path d="m17.66 17.66 1.41 1.41" />
        <path d="M2 12h2" />
        <path d="M20 12h2" />
        <path d="m6.34 17.66-1.41 1.41" />
        <path d="m19.07 4.93-1.41 1.41" />
      </svg>
    </button>
    <button
      onClick={() => onChangeTheme("system")}
      aria-label="System theme"
      type="button"
      className={`${styles.toggleButton} ${theme === "system" ? styles.toggleButtonActive : styles.toggleButtonInactive}`}
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="12"
        height="12"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      >
        <rect width="20" height="14" x="2" y="3" rx="2" />
        <line x1="8" x2="16" y1="21" y2="21" />
        <line x1="12" x2="12" y1="17" y2="21" />
      </svg>
    </button>
    <button
      onClick={() => onChangeTheme("dark")}
      aria-label="Dark mode"
      type="button"
      className={`${styles.toggleButton} ${theme === "dark" ? styles.toggleButtonActive : styles.toggleButtonInactive}`}
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="12"
        height="12"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      >
        <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z" />
      </svg>
    </button>
  </div>
);

export default ThemeToggle;
