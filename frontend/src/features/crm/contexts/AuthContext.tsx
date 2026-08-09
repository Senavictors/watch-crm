"use client";
import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";
import { apiFetch, ensureCsrfCookie, getApiBaseUrl } from "../api";
import { AuthUser, Permission } from "../types";
import { useToast } from "./ToastContext";

type SessionStatus = "unknown" | "authenticated" | "unauthenticated";

type AuthContextValue = {
  sessionStatus: SessionStatus;
  currentUser: AuthUser | null;
  authLoading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  hasPermission: (permission: Permission) => boolean;
  handleUnauthorized: () => void;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const { pushToast } = useToast();
  const [sessionStatus, setSessionStatus] = useState<SessionStatus>("unknown");
  const [currentUser, setCurrentUser] = useState<AuthUser | null>(null);
  const [authLoading, setAuthLoading] = useState(false);

  const apiBaseUrl = getApiBaseUrl();

  useEffect(() => {
    let alive = true;

    async function loadSession() {
      try {
        const response = await apiFetch(`${apiBaseUrl}/me`);

        if (response.status === 401) {
          if (alive) {
            setCurrentUser(null);
            setSessionStatus("unauthenticated");
          }
          return;
        }

        if (!response.ok) {
          throw new Error("Falha ao carregar a sessão atual.");
        }

        const payload = (await response.json()) as { user: AuthUser };
        if (!alive) return;
        setCurrentUser(payload.user);
        setSessionStatus("authenticated");
      } catch (err) {
        if (!alive) return;
        setCurrentUser(null);
        setSessionStatus("unauthenticated");
        pushToast(err instanceof Error ? err.message : "Erro ao verificar sessão.", "error");
      }
    }

    loadSession();
    return () => { alive = false; };
  }, [apiBaseUrl, pushToast]);

  const login = useCallback(async (email: string, password: string) => {
    setAuthLoading(true);
    try {
      await ensureCsrfCookie(apiBaseUrl);
      const response = await apiFetch(
        `${apiBaseUrl}/login`,
        {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ email, password }),
        },
        { csrf: true }
      );

      const payload = await response.json().catch(() => ({}));

      if (!response.ok) {
        const message = payload?.errors?.email?.[0] ?? payload?.message ?? "Não foi possível autenticar.";
        throw new Error(message);
      }

      setCurrentUser(payload.user as AuthUser);
      setSessionStatus("authenticated");
      pushToast("Login realizado com sucesso.", "success");
    } finally {
      setAuthLoading(false);
    }
  }, [apiBaseUrl, pushToast]);

  const logout = useCallback(async () => {
    try {
      await ensureCsrfCookie(apiBaseUrl);
      const response = await apiFetch(
        `${apiBaseUrl}/logout`,
        { method: "POST" },
        { csrf: true }
      );

      if (!response.ok) {
        throw new Error("Não foi possível encerrar a sessão.");
      }

      setCurrentUser(null);
      setSessionStatus("unauthenticated");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro ao sair.", "error");
    }
  }, [apiBaseUrl, pushToast]);

  const hasPermission = useCallback((permission: Permission) => {
    return currentUser?.permissions.includes(permission) ?? false;
  }, [currentUser]);

  const handleUnauthorized = useCallback(() => {
    setCurrentUser(null);
    setSessionStatus("unauthenticated");
  }, []);

  const value = useMemo(() => ({
    sessionStatus,
    currentUser,
    authLoading,
    login,
    logout,
    hasPermission,
    handleUnauthorized,
  }), [authLoading, currentUser, handleUnauthorized, hasPermission, login, logout, sessionStatus]);

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used within AuthProvider");
  return ctx;
}
