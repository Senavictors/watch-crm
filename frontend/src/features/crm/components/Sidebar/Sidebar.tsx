"use client";
import React from "react";
import { usePathname, useRouter } from "next/navigation";
import { AuthUser } from "../../types";
import { Btn } from "../../ui/Primitives";
import ThemeToggle from "../ThemeToggle/ThemeToggle";
import styles from "./Sidebar.module.css";

type NavItem = {
  id: string;
  label: string;
  path: string;
};

type Props = {
  nav: NavItem[];
  readyCount: number;
  onReadyCount?: (count: number) => void;
  theme: "light" | "dark" | "system";
  onChangeTheme: (t: "light" | "dark" | "system") => void;
  user: AuthUser;
  onLogout: () => void;
};

const Sidebar: React.FC<Props> = ({ nav, readyCount, theme, onChangeTheme, user, onLogout }) => {
  const pathname = usePathname();
  const router = useRouter();

  return (
    <div className={styles.wrapper}>
      <div className={styles.header}>
        <div className={styles.headerOverline}>Relojoaria</div>
        <div className={styles.headerTitle}>Watch CRM</div>
      </div>

      {nav.map((item) => {
        const isActive = pathname === item.path || pathname.startsWith(item.path + "/");
        return (
          <button
            key={item.id}
            onClick={() => router.push(item.path)}
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
        <div className={styles.userCard}>
          <div className={styles.userName}>{user.name}</div>
          <div className={styles.userMeta}>
            {user.role} · {user.email}
          </div>
        </div>
        <div className={styles.footerLabel}>Tema</div>
        <ThemeToggle theme={theme} onChangeTheme={onChangeTheme} />
        <Btn onClick={onLogout} variant="secondary" className={styles.logoutButton}>
          Sair
        </Btn>
        <div className={styles.footerVersion}>MVP v1.0</div>
      </div>
    </div>
  );
};

export default Sidebar;
