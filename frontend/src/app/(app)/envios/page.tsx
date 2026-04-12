"use client";
import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiFetch, getApiBaseUrl } from "../../../features/crm/api";
import { Customer, Order, ProductReturn } from "../../../features/crm/types";
import ShippingQueue from "../../../features/crm/views/ShippingQueue";

type Props = {
  onReadyCount?: (count: number) => void;
};

export default function EnviosPage({ onReadyCount }: Props) {
  const { hasPermission, handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const [orders, setOrders] = useState<Order[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [pendingReturns, setPendingReturns] = useState<ProductReturn[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const apiBaseUrl = getApiBaseUrl();
    let alive = true;

    async function load() {
      try {
        setLoading(true);
        const fetches: Promise<Response>[] = [
          apiFetch(`${apiBaseUrl}/orders`),
          apiFetch(`${apiBaseUrl}/customers`),
        ];

        const canViewReturns = hasPermission("returns.view");
        if (canViewReturns) {
          fetches.push(apiFetch(`${apiBaseUrl}/returns`));
        }

        const results = await Promise.all(fetches);
        if (results.some((r) => r.status === 401)) { handleUnauthorized(); return; }
        if (results.some((r) => !r.ok)) throw new Error("Falha ao carregar envios.");

        const [ordersData, customersData] = await Promise.all([results[0].json(), results[1].json()]);
        const returnsData: ProductReturn[] = canViewReturns ? await results[2].json() : [];

        if (!alive) return;
        setOrders(ordersData);
        setCustomers(customersData);
        setPendingReturns(returnsData.filter((r) => r.status === "Pronto p/ Reenvio"));
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

  return <ShippingQueue orders={orders} customers={customers} pendingReturns={pendingReturns} />;
}
