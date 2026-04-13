"use client";
import React from "react";
import { usePathname, useRouter } from "next/navigation";
import {
  LayoutDashboard,
  ClipboardList,
  RefreshCw,
  Truck,
  Users,
  Watch,
  Layers,
  Target,
  Settings,
  UserCog,
  LucideIcon,
  ChevronLeft,
  ChevronRight,
  LogOut,
} from "lucide-react";
import { AuthUser } from "../../types";
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
  collapsed?: boolean;
  onToggle?: () => void;
};

const iconMap: Record<string, LucideIcon> = {
  dashboard: LayoutDashboard,
  orders: ClipboardList,
  returns: RefreshCw,
  shipping: Truck,
  customers: Users,
  products: Watch,
  models: Layers,
  goals: Target,
  settings: Settings,
  users: UserCog,
};

const Sidebar: React.FC<Props> = ({
  nav,
  readyCount,
  theme,
  onChangeTheme,
  user,
  onLogout,
  collapsed = false,
  onToggle,
}) => {
  const pathname = usePathname();
  const router = useRouter();

  return (
    <div className={`${styles.wrapper} ${collapsed ? styles.wrapperCollapsed : ""}`}>
      <div className={styles.header}>
        {!collapsed && (
          <>
            <div className={styles.headerOverline}>Relojoaria</div>
            <div className={styles.headerTitle}>Watch CRM</div>
          </>
        )}
        <button
          className={styles.toggleButton}
          onClick={onToggle}
          type="button"
          title={collapsed ? "Expandir menu" : "Recolher menu"}
        >
          {collapsed ? <ChevronRight size={16} /> : <ChevronLeft size={16} />}
        </button>
      </div>

      {nav.map((item) => {
        const isActive = pathname === item.path || pathname.startsWith(item.path + "/");
        const Icon = iconMap[item.id];
        return (
          <button
            key={item.id}
            onClick={() => router.push(item.path)}
            className={`${styles.navButton} ${isActive ? styles.navButtonActive : ""} ${collapsed ? styles.navButtonCollapsed : ""}`}
            type="button"
            title={collapsed ? item.label : undefined}
          >
            {Icon && <Icon size={18} className={styles.navIcon} />}
            {!collapsed && <span className={styles.navLabel}>{item.label}</span>}
            {!collapsed && item.id === "shipping" && readyCount > 0 && (
              <span className={styles.badge}>{readyCount}</span>
            )}
            {collapsed && item.id === "shipping" && readyCount > 0 && (
              <span className={styles.badgeDot} />
            )}
          </button>
        );
      })}

      <div className={`${styles.footer} ${collapsed ? styles.footerCollapsed : ""}`}>
        {!collapsed ? (
          <>
            <div className={styles.userCard}>
              <div className={styles.userName}>{user.name}</div>
              <div className={styles.userMeta}>
                {user.role} · {user.email}
              </div>
            </div>
            <div className={styles.footerLabel}>Tema</div>
            <ThemeToggle theme={theme} onChangeTheme={onChangeTheme} />
            <button
              onClick={onLogout}
              className={styles.logoutButton}
              type="button"
            >
              <LogOut size={16} />
              <span>Sair</span>
            </button>
            <div className={styles.footerVersion}>MVP v1.0</div>
          </>
        ) : (
          <button
            onClick={onLogout}
            className={`${styles.navButton} ${styles.navButtonCollapsed}`}
            type="button"
            title="Sair"
          >
            <LogOut size={18} className={styles.navIcon} />
          </button>
        )}
      </div>
    </div>
  );
};

export default Sidebar;
