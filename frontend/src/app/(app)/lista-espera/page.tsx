"use client";

import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiCreate, apiDelete, apiFetch, apiUpdate, getApiBaseUrl } from "../../../features/crm/api";
import { PaginatedResponse, PaginationMeta, WaitlistEntry, WaitlistInput, WaitlistMetadata, WaitlistStatus } from "../../../features/crm/types";
import { appendPagination, EMPTY_PAGINATION } from "../../../features/crm/pagination";
import { useDebouncedValue } from "../../../features/crm/hooks/useDebouncedValue";
import PaginationBar from "../../../features/crm/ui/PaginationBar";
import WaitlistList from "../../../features/crm/views/WaitlistList";
import NewWaitlistForm from "../../../features/crm/views/NewWaitlistForm";

const EMPTY_METADATA: WaitlistMetadata = { statuses: [], assignableUsers: [] };

export default function ListaEsperaPage() {
  const { hasPermission, handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const [entries, setEntries] = useState<WaitlistEntry[]>([]);
  const [metadata, setMetadata] = useState<WaitlistMetadata>(EMPTY_METADATA);
  const [loading, setLoading] = useState(true);
  const [editEntry, setEditEntry] = useState<WaitlistEntry | null>(null);
  const [showNew, setShowNew] = useState(false);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState<WaitlistStatus | "">("");
  const [sellerUserId, setSellerUserId] = useState("");
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState<PaginationMeta>(EMPTY_PAGINATION);
  const [reloadKey, setReloadKey] = useState(0);
  const debouncedSearch = useDebouncedValue(search);
  const canCreate = hasPermission("waitlist.create");
  const canUpdate = hasPermission("waitlist.update");
  const canDelete = hasPermission("waitlist.delete");

  useEffect(() => {
    let alive = true;
    async function loadMetadata() {
      try {
        const response = await apiFetch(`${getApiBaseUrl()}/waitlist/metadata`);
        if (response.status === 401) { handleUnauthorized(); return; }
        if (!response.ok) throw new Error("Falha ao carregar dados da lista de espera.");
        const payload = await response.json() as WaitlistMetadata;
        if (alive) setMetadata(payload);
      } catch (error) { if (alive) pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
    }
    loadMetadata();
    return () => { alive = false; };
  }, [handleUnauthorized, pushToast]);

  useEffect(() => {
    const params = appendPagination(new URLSearchParams(), page);
    if (debouncedSearch) params.set("search", debouncedSearch);
    if (status) params.set("status", status);
    if (sellerUserId) params.set("sellerUserId", sellerUserId);
    let alive = true;
    async function loadEntries() {
      try {
        setLoading(true);
        const response = await apiFetch(`${getApiBaseUrl()}/waitlist?${params}`);
        if (response.status === 401) { handleUnauthorized(); return; }
        if (!response.ok) throw new Error("Falha ao carregar lista de espera.");
        const payload = await response.json() as PaginatedResponse<WaitlistEntry>;
        if (!alive) return;
        setEntries(payload.data); setMeta(payload.meta);
      } catch (error) { if (alive) pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
      finally { if (alive) setLoading(false); }
    }
    loadEntries();
    return () => { alive = false; };
  }, [debouncedSearch, handleUnauthorized, page, pushToast, reloadKey, sellerUserId, status]);

  useEffect(() => { setPage(1); }, [debouncedSearch]);
  function filter<T>(setter: (value: T) => void, value: T) { setPage(1); setter(value); }
  function reload() { setReloadKey((key) => key + 1); }

  async function handleUpdateStatus(id: number, nextStatus: WaitlistStatus) {
    try {
      const updated = await apiUpdate<WaitlistEntry>(`/waitlist/${id}`, { status: nextStatus }, "Falha ao atualizar status.");
      setEntries((current) => current.map((entry) => entry.id === id ? updated : entry));
      pushToast("Status atualizado.", "success");
    } catch (error) { pushToast(error instanceof Error ? error.message : "Erro.", "error"); throw error; }
  }

  async function handleSaveEntry(data: WaitlistInput) {
    try {
      await apiCreate<WaitlistEntry>("/waitlist", data, "Falha ao registrar.");
      setShowNew(false); setPage(1); reload();
      pushToast("Entrada registrada com sucesso.", "success");
    } catch (error) { pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
  }

  async function handleUpdateEntry(data: WaitlistInput) {
    if (!editEntry) return;
    try {
      await apiUpdate<WaitlistEntry>(`/waitlist/${editEntry.id}`, data, "Falha ao atualizar.");
      setEditEntry(null); reload();
      pushToast("Atualizado com sucesso.", "success");
    } catch (error) { pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
  }

  async function handleDeleteEntry(entry: WaitlistEntry) {
    if (!confirm(`Remover a entrada de ${entry.customerName} para "${entry.productName}"?`)) return;
    try {
      await apiDelete(`/waitlist/${entry.id}`, "Falha ao excluir.");
      reload(); pushToast("Entrada removida com sucesso.", "success");
    } catch (error) { pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
  }

  return (
    <>
      <WaitlistList
        entries={entries}
        metadata={metadata}
        search={search}
        status={status}
        sellerUserId={sellerUserId}
        onSearchChange={setSearch}
        onStatusFilterChange={(value) => filter(setStatus, value)}
        onSellerChange={(value) => filter(setSellerUserId, value)}
        canCreate={canCreate}
        canUpdate={canUpdate}
        canDelete={canDelete}
        onNew={() => setShowNew(true)}
        onEdit={setEditEntry}
        onDelete={handleDeleteEntry}
        onUpdateStatus={handleUpdateStatus}
      />
      <PaginationBar meta={meta} onPageChange={setPage} disabled={loading} />
      {showNew && <NewWaitlistForm metadata={metadata} onSave={handleSaveEntry} onClose={() => setShowNew(false)} onToast={pushToast} />}
      {editEntry && <NewWaitlistForm metadata={metadata} entryToEdit={editEntry} onSave={handleUpdateEntry} onClose={() => setEditEntry(null)} onToast={pushToast} />}
    </>
  );
}
