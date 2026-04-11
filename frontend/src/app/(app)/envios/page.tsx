"use client";
import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiFetch, getApiBaseUrl } from "../../../features/crm/api";
import { Customer, Order } from "../../../features/crm/types";
import ShippingQueue from "../../../features/crm/views/ShippingQueue";

type Props = {
  onReadyCount?: (count: number) => void;
};

export default function EnviosPage({ onReadyCount }: Props) {
  const { handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const [orders, setOrders] = useState<Order[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const apiBaseUrl = getApiBaseUrl();
    let alive = true;

    async function load() {
      try {
        setLoading(true);
        const [ordersRes, customersRes] = await Promise.all([
          apiFetch(`${apiBaseUrl}/orders`),
          apiFetch(`${apiBaseUrl}/customers`),
        ]);
        if (ordersRes.status === 401 || customersRes.status === 401) { handleUnauthorized(); return; }
        if (!ordersRes.ok || !customersRes.ok) throw new Error("Falha ao carregar envios.");
        const [ordersData, customersData] = await Promise.all([ordersRes.json(), customersRes.json()]);
        if (!alive) return;
        setOrders(ordersData);
        setCustomers(customersData);
        onReadyCount?.((ordersData as Order[]).filter((o) => o.status === "Pronto para Envio").length);
      } catch (err) {
        if (alive) pushToast(err instanceof Error ? err.message : "Erro.", "error");
      } finally {
        if (alive) setLoading(false);
      }
    }

    load();
    return () => { alive = false; };
  }, []);

  if (loading) return <div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>;

  return <ShippingQueue orders={orders} customers={customers} />;
}
