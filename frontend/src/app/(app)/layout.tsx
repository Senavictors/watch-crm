"use client";
import React, { useEffect, useState } from "react";
import { useRouter, usePathname } from "next/navigation";
import { useAuth } from "../../features/crm/contexts/AuthContext";
import { useTheme } from "../../features/crm/contexts/ThemeContext";
import { NAV } from "../../features/crm/data/mock";
import { Permission } from "../../features/crm/types";
import AppShell from "../../features/crm/components/AppShell/AppShell";
import Background from "../../features/crm/components/Background/Background";
import Sidebar from "../../features/crm/components/Sidebar/Sidebar";

const ROUTE_PERMISSION: Record<string, Permission> = {
  "/dashboard":     "dashboard.view",
  "/pedidos":       "orders.view",
  "/garantias":     "returns.view",
  "/envios":        "shipping.view",
  "/clientes":      "customers.view",
  "/produtos":      "products.view",
  "/modelos":       "models.view",
  "/metas":         "goals.view",
  "/configuracoes": "settings.view",
  "/usuarios":      "users.manage",
};

export default function AppLayout({ children }: { children: React.ReactNode }) {
  const { sessionStatus, currentUser, hasPermission, logout } = useAuth();
  const { theme, setTheme } = useTheme();
  const router = useRouter();
  const pathname = usePathname();
  const [readyCount, setReadyCount] = useState(0);

  // Auth guard
  useEffect(() => {
    if (sessionStatus === "unauthenticated") {
      router.replace("/login");
    }
  }, [sessionStatus, router]);

  // Permission guard
  useEffect(() => {
    if (sessionStatus !== "authenticated" || !currentUser) return;
    const required = ROUTE_PERMISSION[pathname];
    if (required && !hasPermission(required)) {
      const first = NAV.find((item) => hasPermission(item.permission as Permission));
      router.replace(first?.path ?? "/login");
    }
  }, [pathname, sessionStatus, currentUser, hasPermission, router]);

  if (sessionStatus === "unknown") {
    return (
      <>
        <Background />
        <div style={{ display: "flex", alignItems: "center", justifyContent: "center", height: "100vh", color: "var(--crm-text-muted)", fontSize: 14 }}>
          Carregando sessão...
        </div>
      </>
    );
  }

  if (sessionStatus === "unauthenticated" || !currentUser) {
    return null;
  }

  const filteredNav = NAV.filter((item) => hasPermission(item.permission as Permission));

  return (
    <>
      <Background />
      <AppShell
        sidebar={
          <Sidebar
            nav={filteredNav}
            readyCount={readyCount}
            onReadyCount={setReadyCount}
            theme={theme}
            onChangeTheme={setTheme}
            user={currentUser}
            onLogout={logout}
          />
        }
      >
        {children}
      </AppShell>
    </>
  );
}
