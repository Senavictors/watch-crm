"use client";

import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiCreate, apiDelete, apiFetch, apiUpdate, getApiBaseUrl } from "../../../features/crm/api";
import { Goal, GoalInput, GoalMetadata, PaginatedResponse, PaginationMeta } from "../../../features/crm/types";
import { appendPagination, EMPTY_PAGINATION } from "../../../features/crm/pagination";
import { useDebouncedValue } from "../../../features/crm/hooks/useDebouncedValue";
import PaginationBar from "../../../features/crm/ui/PaginationBar";
import GoalList from "../../../features/crm/views/GoalList";
import NewGoalForm from "../../../features/crm/views/NewGoalForm";
import GoalDetail from "../../../features/crm/views/GoalDetail";

export default function MetasPage() {
  const { currentUser, hasPermission, handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const [goals, setGoals] = useState<Goal[]>([]);
  const [metadata, setMetadata] = useState<GoalMetadata | null>(null);
  const [loading, setLoading] = useState(true);
  const [showNew, setShowNew] = useState(false);
  const [editing, setEditing] = useState<Goal | null>(null);
  const [viewing, setViewing] = useState<Goal | null>(null);
  const [search, setSearch] = useState("");
  const [scope, setScope] = useState("");
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState<PaginationMeta>(EMPTY_PAGINATION);
  const [reloadKey, setReloadKey] = useState(0);
  const debouncedSearch = useDebouncedValue(search);

  useEffect(() => {
    let alive = true;
    async function loadMetadata() {
      try {
        const response = await apiFetch(`${getApiBaseUrl()}/goals/metadata`);
        if (response.status === 401) { handleUnauthorized(); return; }
        if (!response.ok) throw new Error("Falha ao carregar metadados.");
        const payload = await response.json() as GoalMetadata;
        if (alive) setMetadata(payload);
      } catch (error) { if (alive) pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
    }
    loadMetadata();
    return () => { alive = false; };
  }, [handleUnauthorized, pushToast]);

  useEffect(() => {
    const params = appendPagination(new URLSearchParams(), page);
    if (debouncedSearch) params.set("search", debouncedSearch);
    if (scope) params.set("scope", scope);
    if (status) params.set("status", status);
    let alive = true;
    async function loadGoals() {
      try {
        setLoading(true);
        const response = await apiFetch(`${getApiBaseUrl()}/goals?${params}`);
        if (response.status === 401) { handleUnauthorized(); return; }
        if (!response.ok) throw new Error("Falha ao carregar metas.");
        const payload = await response.json() as PaginatedResponse<Goal>;
        if (!alive) return;
        setGoals(payload.data); setMeta(payload.meta);
      } catch (error) { if (alive) pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
      finally { if (alive) setLoading(false); }
    }
    loadGoals();
    return () => { alive = false; };
  }, [debouncedSearch, handleUnauthorized, page, pushToast, reloadKey, scope, status]);

  useEffect(() => { setPage(1); }, [debouncedSearch]);
  function filter(setter: (value: string) => void, value: string) { setPage(1); setter(value); }
  function reload() { setReloadKey((key) => key + 1); }

  async function handleSave(data: GoalInput) {
    try { await apiCreate<Goal>("/goals", data, "Falha ao criar meta."); setShowNew(false); setPage(1); reload(); pushToast("Meta criada com sucesso.", "success"); }
    catch (error) { pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
  }

  async function handleUpdate(data: GoalInput) {
    if (!editing) return;
    try { await apiUpdate<Goal>(`/goals/${editing.id}`, data, "Falha ao atualizar meta."); setEditing(null); reload(); pushToast("Meta atualizada com sucesso.", "success"); }
    catch (error) { pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
  }

  async function handleDelete(goal: Goal) {
    if (!confirm(`Tem certeza que deseja excluir "${goal.name}"?`)) return;
    try { await apiDelete(`/goals/${goal.id}`, "Falha ao excluir meta."); reload(); pushToast("Meta excluída com sucesso.", "success"); }
    catch (error) { pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
  }

  if (!currentUser) return null;
  if (!metadata) return <div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>;

  return (
    <>
      <GoalList
        goals={goals}
        search={search}
        scope={scope}
        status={status}
        onSearchChange={setSearch}
        onScopeChange={(value) => filter(setScope, value)}
        onStatusChange={(value) => filter(setStatus, value)}
        canCreate={hasPermission("goals.create")}
        canUpdate={hasPermission("goals.update")}
        canDelete={hasPermission("goals.delete")}
        onNew={() => setShowNew(true)}
        onEdit={setEditing}
        onDelete={handleDelete}
        onSelect={setViewing}
      />
      <PaginationBar meta={meta} onPageChange={setPage} disabled={loading} />
      {showNew && <NewGoalForm metadata={metadata} onSave={handleSave} onClose={() => setShowNew(false)} onToast={pushToast} />}
      {editing && <NewGoalForm goal={editing} metadata={metadata} onSave={handleUpdate} onClose={() => setEditing(null)} onToast={pushToast} />}
      {viewing && <GoalDetail goal={viewing} onClose={() => setViewing(null)} />}
    </>
  );
}
