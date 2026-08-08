"use client";
import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiFetch, apiCreate, apiUpdate, apiDelete, getApiBaseUrl } from "../../../features/crm/api";
import {
  Customer,
  Order,
  Product,
  WaitlistEntry,
  WaitlistInput,
  WaitlistMetadata,
  WaitlistStatus,
} from "../../../features/crm/types";
import WaitlistList from "../../../features/crm/views/WaitlistList";
import NewWaitlistForm from "../../../features/crm/views/NewWaitlistForm";

const EMPTY_METADATA: WaitlistMetadata = {
  statuses: [],
  assignableUsers: [],
};

export default function ListaEsperaPage() {
  const { hasPermission, handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const [entries, setEntries] = useState<WaitlistEntry[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [orders, setOrders] = useState<Order[]>([]);
  const [metadata, setMetadata] = useState<WaitlistMetadata>(EMPTY_METADATA);
  const [loading, setLoading] = useState(true);
  const [editEntry, setEditEntry] = useState<WaitlistEntry | null>(null);
  const [showNew, setShowNew] = useState(false);

  const canCreate = hasPermission("waitlist.create");
  const canUpdate = hasPermission("waitlist.update");
  const canDelete = hasPermission("waitlist.delete");

  useEffect(() => {
    const apiBaseUrl = getApiBaseUrl();
    let alive = true;

    async function load() {
      try {
        setLoading(true);
        const [entriesRes, metaRes, customersRes, productsRes, ordersRes] = await Promise.all([
          apiFetch(`${apiBaseUrl}/waitlist`),
          apiFetch(`${apiBaseUrl}/waitlist/metadata`),
          apiFetch(`${apiBaseUrl}/customers`),
          apiFetch(`${apiBaseUrl}/products`),
          apiFetch(`${apiBaseUrl}/orders`),
        ]);
        if ([entriesRes, metaRes, customersRes, productsRes, ordersRes].some((r) => r.status === 401)) {
          handleUnauthorized();
          return;
        }
        if (!entriesRes.ok || !metaRes.ok || !customersRes.ok || !productsRes.ok || !ordersRes.ok) {
          throw new Error("Falha ao carregar lista de espera.");
        }
        const [entriesData, metaData, customersData, productsData, ordersData] = await Promise.all([
          entriesRes.json(),
          metaRes.json(),
          customersRes.json(),
          productsRes.json(),
          ordersRes.json(),
        ]);
        if (!alive) return;
        setEntries(entriesData);
        setMetadata(metaData);
        setCustomers(customersData);
        setProducts(productsData);
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

  async function handleUpdateStatus(id: number, status: WaitlistStatus) {
    try {
      // apiUpdate já lança com a mensagem exata do backend quando presente
      // (`getErrorMessage`) — inclui o 422 de duplicidade/conversão sem
      // pedido/reabertura de status terminal.
      const updated = await apiUpdate<WaitlistEntry>(`/waitlist/${id}`, { status }, "Falha ao atualizar status.");
      setEntries((prev) => prev.map((e) => (e.id === id ? updated : e)));
      pushToast("Status atualizado.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
      // Rethrow pra WaitlistList reverter a seleção visual do select da linha
      // (ela não foi confirmada — `entries` não mudou).
      throw err;
    }
  }

  async function handleSaveEntry(data: WaitlistInput) {
    try {
      const created = await apiCreate<WaitlistEntry>("/waitlist", data, "Falha ao registrar.");
      setEntries((prev) => [created, ...prev]);
      setShowNew(false);
      pushToast("Entrada registrada com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  async function handleUpdateEntry(data: WaitlistInput) {
    if (!editEntry) return;
    try {
      const updated = await apiUpdate<WaitlistEntry>(`/waitlist/${editEntry.id}`, data, "Falha ao atualizar.");
      setEntries((prev) => prev.map((e) => (e.id === editEntry.id ? updated : e)));
      setEditEntry(null);
      pushToast("Atualizado com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  async function handleDeleteEntry(entry: WaitlistEntry) {
    if (!confirm(`Remover a entrada de ${entry.customerName} para "${entry.productName}"?`)) return;
    try {
      await apiDelete(`/waitlist/${entry.id}`, "Falha ao excluir.");
      setEntries((prev) => prev.filter((e) => e.id !== entry.id));
      pushToast("Entrada removida com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  if (loading) return <div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>;

  return (
    <>
      <WaitlistList
        entries={entries}
        metadata={metadata}
        canCreate={canCreate}
        canUpdate={canUpdate}
        canDelete={canDelete}
        onNew={() => setShowNew(true)}
        onEdit={setEditEntry}
        onDelete={handleDeleteEntry}
        onUpdateStatus={handleUpdateStatus}
      />
      {showNew && (
        <NewWaitlistForm
          customers={customers}
          products={products}
          orders={orders}
          metadata={metadata}
          onSave={handleSaveEntry}
          onClose={() => setShowNew(false)}
          onToast={pushToast}
        />
      )}
      {editEntry && (
        <NewWaitlistForm
          customers={customers}
          products={products}
          orders={orders}
          metadata={metadata}
          entryToEdit={editEntry}
          onSave={handleUpdateEntry}
          onClose={() => setEditEntry(null)}
          onToast={pushToast}
        />
      )}
    </>
  );
}
