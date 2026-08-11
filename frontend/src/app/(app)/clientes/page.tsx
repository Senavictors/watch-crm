"use client";
import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiFetch, apiCreate, apiUpdate, apiGet, getApiBaseUrl } from "../../../features/crm/api";
import { Customer, CustomerFrictionNote, CustomerInput, Order, PaginatedResponse, PaginationMeta, ProductReturn } from "../../../features/crm/types";
import { useDebouncedValue } from "../../../features/crm/hooks/useDebouncedValue";
import { EMPTY_PAGINATION } from "../../../features/crm/pagination";
import PaginationBar from "../../../features/crm/ui/PaginationBar";
import Customers from "../../../features/crm/views/Customers";
import NewCustomerForm from "../../../features/crm/views/NewCustomerForm";
import CustomerDetailModal from "../../../features/crm/views/CustomerDetailModal";

export default function ClientesPage() {
  const { hasPermission, handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [pagination, setPagination] = useState<PaginationMeta>(EMPTY_PAGINATION);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search);
  const [showArchived, setShowArchived] = useState(false);
  const [reloadKey, setReloadKey] = useState(0);
  const [loading, setLoading] = useState(true);
  const [showNew, setShowNew] = useState(false);
  const [editing, setEditing] = useState<Customer | null>(null);
  const [viewing, setViewing] = useState<Customer | null>(null);
  const [viewOrders, setViewOrders] = useState<Order[] | null>(null);
  const [viewReturns, setViewReturns] = useState<ProductReturn[] | null>(null);
  const [viewOrdersMeta, setViewOrdersMeta] = useState<PaginationMeta>(EMPTY_PAGINATION);
  const [viewReturnsMeta, setViewReturnsMeta] = useState<PaginationMeta>(EMPTY_PAGINATION);
  const [loadingOrders, setLoadingOrders] = useState(false);
  const [loadingReturns, setLoadingReturns] = useState(false);
  const [loadingDetail, setLoadingDetail] = useState(false);

  useEffect(() => {
    const apiBaseUrl = getApiBaseUrl();
    let alive = true;

    async function load() {
      try {
        setLoading(true);
        const params = new URLSearchParams({ page: String(page), perPage: "20" });
        if (debouncedSearch) params.set("search", debouncedSearch);
        // TASK-025: arquivados só aparecem quando pedidos explicitamente.
        if (showArchived) params.set("archived", "1");
        const res = await apiFetch(`${apiBaseUrl}/customers?${params.toString()}`);
        if (res.status === 401) { handleUnauthorized(); return; }
        if (!res.ok) throw new Error("Falha ao carregar clientes.");
        const response = (await res.json()) as PaginatedResponse<Customer>;
        if (alive) {
          setCustomers(response.data);
          setPagination(response.meta);
        }
      } catch (err) {
        if (alive) pushToast(err instanceof Error ? err.message : "Erro.", "error");
      } finally {
        if (alive) setLoading(false);
      }
    }

    load();
    return () => { alive = false; };
  }, [debouncedSearch, handleUnauthorized, page, pushToast, reloadKey, showArchived]);

  async function handleSave(data: CustomerInput) {
    try {
      await apiCreate<Customer>("/customers", data, "Falha ao cadastrar cliente.");
      setShowNew(false);
      setPage(1);
      setReloadKey((value) => value + 1);
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

    apiGet<PaginatedResponse<Order>>(`/orders?customer_id=${customer.id}&page=1&perPage=20`, "Falha ao carregar pedidos.")
      .then((response) => { setViewOrders(response.data); setViewOrdersMeta(response.meta); })
      .catch((err) => {
        pushToast(err instanceof Error ? err.message : "Erro ao carregar pedidos.", "error");
        setViewOrders([]);
      })
      .finally(() => setLoadingOrders(false));

    apiGet<PaginatedResponse<ProductReturn>>(`/returns?customer_id=${customer.id}&page=1&perPage=20`, "Falha ao carregar garantias.")
      .then((response) => { setViewReturns(response.data); setViewReturnsMeta(response.meta); })
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

  function loadCustomerOrders(customerId: number, targetPage: number) {
    setLoadingOrders(true);
    apiGet<PaginatedResponse<Order>>(`/orders?customer_id=${customerId}&page=${targetPage}&perPage=20`, "Falha ao carregar pedidos.")
      .then((response) => { setViewOrders(response.data); setViewOrdersMeta(response.meta); })
      .catch((error) => {
        pushToast(error instanceof Error ? error.message : "Erro ao carregar pedidos.", "error");
        setViewOrders([]);
      })
      .finally(() => setLoadingOrders(false));
  }

  function loadCustomerReturns(customerId: number, targetPage: number) {
    setLoadingReturns(true);
    apiGet<PaginatedResponse<ProductReturn>>(`/returns?customer_id=${customerId}&page=${targetPage}&perPage=20`, "Falha ao carregar garantias.")
      .then((response) => { setViewReturns(response.data); setViewReturnsMeta(response.meta); })
      .catch((error) => {
        pushToast(error instanceof Error ? error.message : "Erro ao carregar garantias.", "error");
        setViewReturns([]);
      })
      .finally(() => setLoadingReturns(false));
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
      setReloadKey((value) => value + 1);
      pushToast("Cliente atualizado com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  // TASK-025 (ADR-007): cliente com histórico não é excluído — é arquivado,
  // o que só muda a visibilidade, nunca os números do passado dele.
  async function handleArchive(customer: Customer, archive: boolean) {
    const action = archive ? "arquivar" : "desarquivar";
    if (!confirm(`Deseja ${action} o cliente "${customer.name}"?`)) return;
    try {
      await apiUpdate<Customer>(
        `/customers/${customer.id}/${archive ? "archive" : "unarchive"}`,
        {},
        `Falha ao ${action} cliente.`
      );
      setReloadKey((value) => value + 1);
      pushToast(`Cliente ${archive ? "arquivado" : "desarquivado"} com sucesso.`, "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  if (loading) return <div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>;

  return (
    <>
      <Customers
        customers={customers}
        search={search}
        onSearchChange={(value) => { setSearch(value); setPage(1); }}
        canCreate={hasPermission("customers.create")}
        canUpdate={hasPermission("customers.update")}
        canArchive={hasPermission("customers.delete")}
        showArchived={showArchived}
        onToggleArchived={(value) => { setShowArchived(value); setPage(1); }}
        onArchive={handleArchive}
        onNew={() => setShowNew(true)}
        onEdit={setEditing}
        onView={handleView}
      />
      <PaginationBar meta={pagination} onPageChange={setPage} disabled={loading} />
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
          ordersMeta={viewOrdersMeta}
          returnsMeta={viewReturnsMeta}
          loadingOrders={loadingOrders}
          loadingReturns={loadingReturns}
          loadingDetail={loadingDetail}
          canRegisterFrictionNote={hasPermission("customers.update")}
          onAddFrictionNote={handleAddFrictionNote}
          onOrdersPageChange={(targetPage) => loadCustomerOrders(viewing.id, targetPage)}
          onReturnsPageChange={(targetPage) => loadCustomerReturns(viewing.id, targetPage)}
          onClose={() => setViewing(null)}
        />
      )}
    </>
  );
}
