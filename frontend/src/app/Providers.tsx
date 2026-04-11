"use client";
import React from "react";
import { ToastProvider } from "../features/crm/contexts/ToastContext";
import { ThemeProvider } from "../features/crm/contexts/ThemeContext";
import { AuthProvider } from "../features/crm/contexts/AuthContext";

export function Providers({ children }: { children: React.ReactNode }) {
  return (
    <ToastProvider>
      <ThemeProvider>
        <AuthProvider>
          {children}
        </AuthProvider>
      </ThemeProvider>
    </ToastProvider>
  );
}
