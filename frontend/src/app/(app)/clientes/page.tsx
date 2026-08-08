"use client";
import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiFetch, apiCreate, apiUpdate, apiGet, getApiBaseUrl } from "../../../features/crm/api";
import { Customer, CustomerFrictionNote, CustomerInput, Order, ProductReturn } from "../../../features/crm/types";
import Customers from "../../../features/crm/views/Customers";
import NewCustomerForm from "../../../features/crm/views/NewCustomerForm";
import CustomerDetailModal from "../../../features/crm/views/CustomerDetailModal";

export default function ClientesPage() {
  const { hasPermission, handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [loading, setLoading] = useState(true);
  const [showNew, setShowNew] = useState(false);
  const [editing, setEditing] = useState<Customer | null>(null);
  const [viewing, setViewing] = useState<Customer | null>(null);
  const [viewOrders, setViewOrders] = useState<Order[] | null>(null);
  const [viewReturns, setViewReturns] = useState<ProductReturn[] | null>(null);
  const [loadingOrders, setLoadingOrders] = useState(false);
  const [loadingReturns, setLoadingReturns] = useState(false);
  const [loadingDetail, setLoadingDetail] = useState(false);

  useEffect(() => {
    const apiBaseUrl = getApiBaseUrl();
    let alive = true;

    async function load() {
      try {
        setLoading(true);
        const res = await apiFetch(`${apiBaseUrl}/customers`);
        if (res.status === 401) { handleUnauthorized(); return; }
        if (!res.ok) throw new Error("Falha ao carregar clientes.");
        const data = await res.json();
        if (alive) setCustomers(data);
      } catch (err) {
        if (alive) pushToast(err instanceof Error ? err.message : "Erro.", "error");
      } finally {
        if (alive) setLoading(false);
      }
    }

    load();
    return () => { alive = false; };
  }, []);

  async function handleSave(data: CustomerInput) {
    try {
      const created = await apiCreate<Customer>("/customers", data, "Falha ao cadastrar cliente.");
      setCustomers((cs) => [created, ...cs]);
      setShowNew(false);
      pushToast("Cliente cadastrado com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  async function handleView(customer: Customer) {
    setViewing(customer);
    setViewOrders(null);
    setViewReturns(null);
    setLoadingOrders(true);
    setLoadingReturns(true);
    setLoadingDetail(true);

    apiGet<Order[]>(`/orders?customer_id=${customer.id}`, "Falha ao carregar pedidos.")
      .then(setViewOrders)
      .catch((err) => {
        pushToast(err instanceof Error ? err.message : "Erro ao carregar pedidos.", "error");
        setViewOrders([]);
      })
      .finally(() => setLoadingOrders(false));

    apiGet<ProductReturn[]>(`/returns?customer_id=${customer.id}`, "Falha ao carregar garantias.")
      .then(setViewReturns)
      .catch((err) => {
        pushToast(err instanceof Error ? err.message : "Erro ao carregar garantias.", "error");
        setViewReturns([]);
      })
      .finally(() => setLoadingReturns(false));

    // TASK-019: detalhe com insights/histórico de atrito só existe no
    // `show`, não no `index` já carregado na lista.
    apiGet<Customer>(`/customers/${customer.id}`, "Falha ao carregar detalhes do cliente.")
      .then((detail) => {
        setViewing((current) => (current && current.id === detail.id ? detail : current));
        setCustomers((cs) => cs.map((c) => (c.id === detail.id ? { ...c, ...detail } : c)));
      })
      .catch((err) => {
        pushToast(err instanceof Error ? err.message : "Erro ao carregar detalhes do cliente.", "error");
      })
      .finally(() => setLoadingDetail(false));
  }

  async function handleAddFrictionNote(note: string) {
    if (!viewing) return;
    try {
      const created = await apiCreate<CustomerFrictionNote>(
        `/customers/${viewing.id}/friction-notes`,
        { note },
        "Falha ao registrar observação."
      );
      setViewing((v) => (v ? { ...v, frictionNotes: [...(v.frictionNotes ?? []), created], hasFrictionHistory: true } : v));
      pushToast("Observação registrada.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro ao registrar observação.", "error");
    }
  }

  async function handleUpdate(data: CustomerInput) {
    if (!editing) return;
    try {
      const updated = await apiUpdate<Customer>(`/customers/${editing.id}`, data, "Falha ao atualizar cliente.");
      setCustomers((cs) => cs.map((c) => (c.id === updated.id ? updated : c)));
      setEditing(null);
      pushToast("Cliente atualizado com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  if (loading) return <div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>;

  return (
    <>
      <Customers
        customers={customers}
        canCreate={hasPermission("customers.create")}
        canUpdate={hasPermission("customers.update")}
        onNew={() => setShowNew(true)}
        onEdit={setEditing}
        onView={handleView}
      />
      {showNew && (
        <NewCustomerForm customer={null} onSave={handleSave} onClose={() => setShowNew(false)} onToast={pushToast} />
      )}
      {editing && (
        <NewCustomerForm customer={editing} onSave={handleUpdate} onClose={() => setEditing(null)} onToast={pushToast} />
      )}
      {viewing && (
        <CustomerDetailModal
          customer={viewing}
          orders={viewOrders}
          returns={viewReturns}
          loadingOrders={loadingOrders}
          loadingReturns={loadingReturns}
          loadingDetail={loadingDetail}
          canRegisterFrictionNote={hasPermission("customers.update")}
          onAddFrictionNote={handleAddFrictionNote}
          onClose={() => setViewing(null)}
        />
      )}
    </>
  );
}
