"use client";

import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiCreate, apiFetch, apiGet, apiUpdate, getApiBaseUrl } from "../../../features/crm/api";
import { PaginatedResponse, PaginationMeta, ProductReturn, ReturnInput, ReturnMetadata, ReturnStatus, ReturnType } from "../../../features/crm/types";
import { appendPagination, EMPTY_PAGINATION } from "../../../features/crm/pagination";
import { useDebouncedValue } from "../../../features/crm/hooks/useDebouncedValue";
import PaginationBar from "../../../features/crm/ui/PaginationBar";
import ReturnList from "../../../features/crm/views/ReturnList";
import ReturnDetail from "../../../features/crm/views/ReturnDetail";
import NewReturnForm from "../../../features/crm/views/NewReturnForm";

const EMPTY_METADATA: ReturnMetadata = {
  types: [],
  typeLabels: { garantia: "Garantia", troca: "Troca", devolucao: "Devolução" },
  statuses: [],
  transitions: {},
  assignableUsers: [],
};

export default function GarantiasPage() {
  const { hasPermission, handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const [returns, setReturns] = useState<ProductReturn[]>([]);
  const [metadata, setMetadata] = useState<ReturnMetadata>(EMPTY_METADATA);
  const [loading, setLoading] = useState(true);
  const [viewReturn, setViewReturn] = useState<ProductReturn | null>(null);
  const [editReturn, setEditReturn] = useState<ProductReturn | null>(null);
  const [showNew, setShowNew] = useState(false);
  const [search, setSearch] = useState("");
  const [type, setType] = useState<ReturnType | "">("");
  const [status, setStatus] = useState<ReturnStatus | "">("");
  const [assignedUserId, setAssignedUserId] = useState("");
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState<PaginationMeta>(EMPTY_PAGINATION);
  const [reloadKey, setReloadKey] = useState(0);
  const debouncedSearch = useDebouncedValue(search);

  useEffect(() => {
    let alive = true;
    async function loadMetadata() {
      try {
        const response = await apiFetch(`${getApiBaseUrl()}/returns/metadata`);
        if (response.status === 401) { handleUnauthorized(); return; }
        if (!response.ok) throw new Error("Falha ao carregar dados de garantias.");
        const payload = await response.json() as ReturnMetadata;
        if (alive) setMetadata(payload);
      } catch (error) {
        if (alive) pushToast(error instanceof Error ? error.message : "Erro.", "error");
      }
    }
    loadMetadata();
    return () => { alive = false; };
  }, [handleUnauthorized, pushToast]);

  useEffect(() => {
    const params = appendPagination(new URLSearchParams(), page);
    if (debouncedSearch) params.set("search", debouncedSearch);
    if (type) params.set("type", type);
    if (status) params.set("status", status);
    if (assignedUserId) params.set("assignedUserId", assignedUserId);
    let alive = true;
    async function loadReturns() {
      try {
        setLoading(true);
        const response = await apiFetch(`${getApiBaseUrl()}/returns?${params}`);
        if (response.status === 401) { handleUnauthorized(); return; }
        if (!response.ok) throw new Error("Falha ao carregar garantias.");
        const payload = await response.json() as PaginatedResponse<ProductReturn>;
        if (!alive) return;
        setReturns(payload.data);
        setMeta(payload.meta);
      } catch (error) {
        if (alive) pushToast(error instanceof Error ? error.message : "Erro.", "error");
      } finally {
        if (alive) setLoading(false);
      }
    }
    loadReturns();
    return () => { alive = false; };
  }, [assignedUserId, debouncedSearch, handleUnauthorized, page, pushToast, reloadKey, status, type]);

  useEffect(() => { setPage(1); }, [debouncedSearch]);
  function filter<T>(setter: (value: T) => void, value: T) { setPage(1); setter(value); }
  function reload() { setReloadKey((key) => key + 1); }

  async function handleView(item: ProductReturn) {
    try {
      setViewReturn(await apiGet<ProductReturn>(`/returns/${item.id}`, "Falha ao carregar o registro."));
      setEditReturn(null);
    } catch (error) { pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
  }

  async function handleUpdateStatus(id: number, nextStatus: ReturnStatus) {
    try {
      const updated = await apiUpdate<ProductReturn>(`/returns/${id}`, { status: nextStatus }, "Falha ao atualizar status.");
      setReturns((current) => current.map((item) => item.id === id ? { ...item, status: updated.status } : item));
      setViewReturn((current) => current?.id === id ? updated : current);
      pushToast("Status atualizado.", "success");
    } catch (error) { pushToast(error instanceof Error ? error.message : "Erro.", "error"); throw error; }
  }

  async function handleSaveReturn(data: ReturnInput) {
    try {
      await apiCreate<ProductReturn>("/returns", data, "Falha ao registrar.");
      setShowNew(false); setPage(1); reload();
      pushToast("Garantia/Troca registrada com sucesso.", "success");
    } catch (error) { pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
  }

  async function handleUpdateReturn(data: ReturnInput) {
    if (!editReturn) return;
    try {
      await apiUpdate<ProductReturn>(`/returns/${editReturn.id}`, data, "Falha ao atualizar.");
      setEditReturn(null); setViewReturn(null); reload();
      pushToast("Atualizado com sucesso.", "success");
    } catch (error) { pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
  }

  // TASK-025 (ADR-007): devolução com impacto financeiro não é apagada — é
  // estornada, com motivo obrigatório, saindo de faturamento, comissões,
  // metas e dashboard sem sumir do histórico.
  async function handleVoidReturn(item: ProductReturn) {
    const reason = prompt(`Motivo do estorno da garantia/troca #${item.id}:`)?.trim();
    if (!reason) return;
    if (reason.length < 3) { pushToast("Descreva o motivo do estorno.", "error"); return; }
    try {
      await apiUpdate<ProductReturn>(`/returns/${item.id}/void`, { reason }, "Falha ao estornar.");
      setViewReturn(null); reload();
      pushToast("Garantia/Troca estornada com sucesso.", "success");
    } catch (error) { pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
  }

  return (
    <>
      <ReturnList
        returns={returns}
        metadata={metadata}
        search={search}
        type={type}
        status={status}
        assignedUserId={assignedUserId}
        onSearchChange={setSearch}
        onTypeChange={(value) => filter(setType, value)}
        onStatusFilterChange={(value) => filter(setStatus, value)}
        onAssignedUserChange={(value) => filter(setAssignedUserId, value)}
        canCreate={hasPermission("returns.create")}
        canUpdateStatus={hasPermission("returns.update")}
        onView={handleView}
        onNew={() => setShowNew(true)}
        onUpdateStatus={handleUpdateStatus}
      />
      <PaginationBar meta={meta} onPageChange={setPage} disabled={loading} />
      {viewReturn && !editReturn && <ReturnDetail productReturn={viewReturn} canUpdate={hasPermission("returns.update")} canVoid={hasPermission("returns.delete")} onClose={() => setViewReturn(null)} onEdit={(item) => { setEditReturn(item); setViewReturn(null); }} onVoid={handleVoidReturn} />}
      {showNew && <NewReturnForm metadata={metadata} onSave={handleSaveReturn} onClose={() => setShowNew(false)} onToast={pushToast} />}
      {editReturn && <NewReturnForm metadata={metadata} returnToEdit={editReturn} onSave={handleUpdateReturn} onClose={() => setEditReturn(null)} onToast={pushToast} />}
    </>
  );
}
