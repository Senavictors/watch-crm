"use client";
import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiFetch, apiCreate, apiUpdate, getApiBaseUrl } from "../../../features/crm/api";
import {
  Customer,
  Order,
  ProductReturn,
  ReturnInput,
  ReturnMetadata,
  ReturnStatus,
} from "../../../features/crm/types";
import ReturnList from "../../../features/crm/views/ReturnList";
import ReturnDetail from "../../../features/crm/views/ReturnDetail";
import NewReturnForm from "../../../features/crm/views/NewReturnForm";

const EMPTY_METADATA: ReturnMetadata = {
  types: [],
  typeLabels: { garantia: "Garantia", troca: "Troca", devolucao: "Devolução" },
  statuses: [],
  assignableUsers: [],
};

export default function GarantiasPage() {
  const { hasPermission, handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const [returns, setReturns] = useState<ProductReturn[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [orders, setOrders] = useState<Order[]>([]);
  const [metadata, setMetadata] = useState<ReturnMetadata>(EMPTY_METADATA);
  const [loading, setLoading] = useState(true);
  const [viewReturn, setViewReturn] = useState<ProductReturn | null>(null);
  const [editReturn, setEditReturn] = useState<ProductReturn | null>(null);
  const [showNew, setShowNew] = useState(false);

  useEffect(() => {
    const apiBaseUrl = getApiBaseUrl();
    let alive = true;

    async function load() {
      try {
        setLoading(true);
        const [returnsRes, metaRes, customersRes, ordersRes] = await Promise.all([
          apiFetch(`${apiBaseUrl}/returns`),
          apiFetch(`${apiBaseUrl}/returns/metadata`),
          apiFetch(`${apiBaseUrl}/customers`),
          apiFetch(`${apiBaseUrl}/orders`),
        ]);
        if ([returnsRes, metaRes, customersRes, ordersRes].some((r) => r.status === 401)) {
          handleUnauthorized();
          return;
        }
        if (!returnsRes.ok || !metaRes.ok || !customersRes.ok || !ordersRes.ok) {
          throw new Error("Falha ao carregar garantias.");
        }
        const [returnsData, metaData, customersData, ordersData] = await Promise.all([
          returnsRes.json(),
          metaRes.json(),
          customersRes.json(),
          ordersRes.json(),
        ]);
        if (!alive) return;
        setReturns(returnsData);
        setMetadata(metaData);
        setCustomers(customersData);
        setOrders(ordersData);
      } catch (err) {
        if (alive) pushToast(err instanceof Error ? err.message : "Erro.", "error");
      } finally {
        if (alive) setLoading(false);
      }
    }

    load();
    return () => { alive = false; };
  }, []);

  async function handleUpdateStatus(id: number, status: ReturnStatus) {
    try {
      const updated = await apiUpdate<ProductReturn>(`/returns/${id}`, { status }, "Falha ao atualizar status.");
      setReturns((prev) => prev.map((r) => (r.id === id ? updated : r)));
      setViewReturn((current) => (current?.id === id ? updated : current));
      pushToast("Status atualizado.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  async function handleSaveReturn(data: ReturnInput) {
    try {
      const created = await apiCreate<ProductReturn>("/returns", data, "Falha ao registrar.");
      setReturns((prev) => [created, ...prev]);
      setShowNew(false);
      pushToast("Garantia/Troca registrada com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  async function handleUpdateReturn(data: ReturnInput) {
    if (!editReturn) return;
    try {
      const updated = await apiUpdate<ProductReturn>(
        `/returns/${editReturn.id}`,
        data,
        "Falha ao atualizar."
      );
      setReturns((prev) => prev.map((r) => (r.id === editReturn.id ? updated : r)));
      setEditReturn(null);
      setViewReturn(null);
      pushToast("Atualizado com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  if (loading) return <div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>;

  return (
    <>
      <ReturnList
        returns={returns}
        statuses={metadata.statuses}
        canCreate={hasPermission("returns.create")}
        canUpdateStatus={hasPermission("returns.update")}
        onView={(r) => { setViewReturn(r); setEditReturn(null); }}
        onNew={() => setShowNew(true)}
        onUpdateStatus={handleUpdateStatus}
      />
      {viewReturn && !editReturn && (
        <ReturnDetail
          productReturn={viewReturn}
          canUpdate={hasPermission("returns.update")}
          onClose={() => setViewReturn(null)}
          onEdit={(r) => { setEditReturn(r); setViewReturn(null); }}
        />
      )}
      {showNew && (
        <NewReturnForm
          customers={customers}
          orders={orders}
          metadata={metadata}
          onSave={handleSaveReturn}
          onClose={() => setShowNew(false)}
          onToast={pushToast}
        />
      )}
      {editReturn && (
        <NewReturnForm
          customers={customers}
          orders={orders}
          metadata={metadata}
          returnToEdit={editReturn}
          onSave={handleUpdateReturn}
          onClose={() => { setEditReturn(null); }}
          onToast={pushToast}
        />
      )}
    </>
  );
}
