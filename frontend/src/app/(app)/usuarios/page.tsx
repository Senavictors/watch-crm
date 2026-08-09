"use client";

import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiCreate, apiFetch, apiUpdate, getApiBaseUrl } from "../../../features/crm/api";
import { CrmUser, CrmUserInput, PaginatedResponse, PaginationMeta } from "../../../features/crm/types";
import { appendPagination, EMPTY_PAGINATION } from "../../../features/crm/pagination";
import { useDebouncedValue } from "../../../features/crm/hooks/useDebouncedValue";
import PaginationBar from "../../../features/crm/ui/PaginationBar";
import Users from "../../../features/crm/views/Users";
import NewUserForm from "../../../features/crm/views/NewUserForm";

export default function UsuariosPage() {
  const { currentUser, hasPermission, handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const [users, setUsers] = useState<CrmUser[]>([]);
  const [loading, setLoading] = useState(true);
  const [showNew, setShowNew] = useState(false);
  const [editing, setEditing] = useState<CrmUser | null>(null);
  const [resetPassword, setResetPassword] = useState<CrmUser | null>(null);
  const [search, setSearch] = useState("");
  const [role, setRole] = useState("");
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState<PaginationMeta>(EMPTY_PAGINATION);
  const [reloadKey, setReloadKey] = useState(0);
  const debouncedSearch = useDebouncedValue(search);

  useEffect(() => {
    const params = appendPagination(new URLSearchParams(), page);
    if (debouncedSearch) params.set("search", debouncedSearch);
    if (role) params.set("role", role);
    if (status) params.set("active", status === "active" ? "1" : "0");
    let alive = true;
    async function load() {
      try {
        setLoading(true);
        const response = await apiFetch(`${getApiBaseUrl()}/users?${params}`);
        if (response.status === 401) { handleUnauthorized(); return; }
        if (!response.ok) throw new Error("Falha ao carregar usuários.");
        const payload = await response.json() as PaginatedResponse<CrmUser>;
        if (!alive) return;
        setUsers(payload.data); setMeta(payload.meta);
      } catch (error) { if (alive) pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
      finally { if (alive) setLoading(false); }
    }
    load();
    return () => { alive = false; };
  }, [debouncedSearch, handleUnauthorized, page, pushToast, reloadKey, role, status]);

  useEffect(() => { setPage(1); }, [debouncedSearch]);
  function filter(setter: (value: string) => void, value: string) { setPage(1); setter(value); }
  function reload() { setReloadKey((key) => key + 1); }

  async function handleSave(data: CrmUserInput) {
    try { await apiCreate<CrmUser>("/users", data, "Falha ao cadastrar usuário."); setShowNew(false); setPage(1); reload(); pushToast("Usuário cadastrado com sucesso.", "success"); }
    catch (error) { pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
  }

  async function handleUpdate(data: CrmUserInput) {
    if (!editing) return;
    try { await apiUpdate<CrmUser>(`/users/${editing.id}`, data, "Falha ao atualizar usuário."); setEditing(null); reload(); pushToast("Usuário atualizado com sucesso.", "success"); }
    catch (error) { pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
  }

  async function handleToggleActive(user: CrmUser) {
    try { const updated = await apiUpdate<CrmUser>(`/users/${user.id}/active`, {}, "Falha ao alterar status."); reload(); pushToast(updated.isActive ? "Usuário ativado." : "Usuário bloqueado.", "success"); }
    catch (error) { pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
  }

  async function handleResetPassword(data: CrmUserInput) {
    if (!resetPassword) return;
    try { await apiUpdate<{ ok: boolean }>(`/users/${resetPassword.id}/password`, { password: data.password }, "Falha ao redefinir senha."); setResetPassword(null); pushToast("Senha redefinida com sucesso.", "success"); }
    catch (error) { pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
  }

  if (!currentUser) return null;

  return (
    <>
      <Users
        users={users}
        search={search}
        role={role}
        status={status}
        onSearchChange={setSearch}
        onRoleChange={(value) => filter(setRole, value)}
        onStatusChange={(value) => filter(setStatus, value)}
        currentUserRole={currentUser.role}
        canCreate={hasPermission("users.manage")}
        onNew={() => setShowNew(true)}
        onEdit={setEditing}
        onToggleActive={handleToggleActive}
        onResetPassword={setResetPassword}
      />
      <PaginationBar meta={meta} onPageChange={setPage} disabled={loading} />
      {showNew && <NewUserForm user={null} currentUserRole={currentUser.role} onSave={handleSave} onClose={() => setShowNew(false)} onToast={pushToast} />}
      {editing && <NewUserForm user={editing} currentUserRole={currentUser.role} onSave={handleUpdate} onClose={() => setEditing(null)} onToast={pushToast} />}
      {resetPassword && <NewUserForm user={resetPassword} resetPasswordMode currentUserRole={currentUser.role} onSave={handleResetPassword} onClose={() => setResetPassword(null)} onToast={pushToast} />}
    </>
  );
}
