"use client";
import React from "react";
import ThemeToggle from "../ThemeToggle/ThemeToggle";
import styles from "./Sidebar.module.css";

type NavItem = {
  id: string;
  label: string;
};

type Props = {
  page: string;
  onNavigate: (id: string) => void;
  nav: NavItem[];
  readyCount: number;
  theme: "light" | "dark" | "system";
  onChangeTheme: (t: "light" | "dark" | "system") => void;
};

const Sidebar: React.FC<Props> = ({ page, onNavigate, nav, readyCount, theme, onChangeTheme }) => (
  <div className={styles.wrapper}>
    <div className={styles.header}>
      <div className={styles.headerOverline}>Relojoaria</div>
      <div className={styles.headerTitle}>QueirozPrimeCRM</div>
    </div>

    {nav.map((item) => {
      const isActive = page === item.id;
      return (
        <button
          key={item.id}
          onClick={() => onNavigate(item.id)}
          className={`${styles.navButton} ${isActive ? styles.navButtonActive : ""}`}
          type="button"
        >
          <span className={styles.navLabel}>{item.label}</span>
          {item.id === "shipping" && readyCount > 0 && (
            <span className={styles.badge}>{readyCount}</span>
          )}
        </button>
      );
    })}

    <div className={styles.footer}>
      <div className={styles.footerLabel}>Tema</div>
      <ThemeToggle theme={theme} onChangeTheme={onChangeTheme} />
      <div className={styles.footerVersion}>MVP v1.0</div>
    </div>
  </div>
);

export default Sidebar;
