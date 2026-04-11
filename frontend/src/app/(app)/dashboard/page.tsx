"use client";
import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiFetch, getApiBaseUrl } from "../../../features/crm/api";
import { Order } from "../../../features/crm/types";
import Dashboard from "../../../features/crm/views/Dashboard";

export default function DashboardPage() {
  const { handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const [orders, setOrders] = useState<Order[]>([]);
  const [channels, setChannels] = useState<string[]>([]);
  const [statuses, setStatuses] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const apiBaseUrl = getApiBaseUrl();
    let alive = true;

    async function load() {
      try {
        setLoading(true);
        const [ordersRes, metaRes] = await Promise.all([
          apiFetch(`${apiBaseUrl}/orders`),
          apiFetch(`${apiBaseUrl}/orders/metadata`),
        ]);

        if (ordersRes.status === 401 || metaRes.status === 401) {
          handleUnauthorized();
          return;
        }
        if (!ordersRes.ok || !metaRes.ok) throw new Error("Falha ao carregar dados.");

        const [ordersData, metaData] = await Promise.all([ordersRes.json(), metaRes.json()]);
        if (!alive) return;

        setOrders(ordersData);
        setChannels(metaData.channels?.length ? metaData.channels : Array.from(new Set((ordersData as Order[]).map((o) => o.channel))));
        setStatuses(metaData.statuses?.length ? metaData.statuses : Array.from(new Set((ordersData as Order[]).map((o) => o.status))));
      } catch (err) {
        if (!alive) return;
        pushToast(err instanceof Error ? err.message : "Erro ao carregar dashboard.", "error");
      } finally {
        if (alive) setLoading(false);
      }
    }

    load();
    return () => { alive = false; };
  }, []);

  if (loading) return <div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>;

  return <Dashboard orders={orders} channels={channels} statuses={statuses} />;
}
