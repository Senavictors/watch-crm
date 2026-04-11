"use client";
import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiFetch, apiCreate, apiUpdate, getApiBaseUrl } from "../../../features/crm/api";
import { CrmUser, CrmUserInput } from "../../../features/crm/types";
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

  useEffect(() => {
    const apiBaseUrl = getApiBaseUrl();
    let alive = true;

    async function load() {
      try {
        setLoading(true);
        const res = await apiFetch(`${apiBaseUrl}/users`);
        if (res.status === 401) { handleUnauthorized(); return; }
        if (!res.ok) throw new Error("Falha ao carregar usuários.");
        const data = await res.json();
        if (alive) setUsers(data);
      } catch (err) {
        if (alive) pushToast(err instanceof Error ? err.message : "Erro.", "error");
      } finally {
        if (alive) setLoading(false);
      }
    }

    load();
    return () => { alive = false; };
  }, []);

  async function handleSave(data: CrmUserInput) {
    try {
      const created = await apiCreate<CrmUser>("/users", data, "Falha ao cadastrar usuário.");
      setUsers((us) => [created, ...us]);
      setShowNew(false);
      pushToast("Usuário cadastrado com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  async function handleUpdate(data: CrmUserInput) {
    if (!editing) return;
    try {
      const updated = await apiUpdate<CrmUser>(`/users/${editing.id}`, data, "Falha ao atualizar usuário.");
      setUsers((us) => us.map((u) => (u.id === updated.id ? updated : u)));
      setEditing(null);
      pushToast("Usuário atualizado com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  async function handleToggleActive(user: CrmUser) {
    try {
      const updated = await apiUpdate<CrmUser>(`/users/${user.id}/active`, {}, "Falha ao alterar status.");
      setUsers((us) => us.map((u) => (u.id === updated.id ? updated : u)));
      pushToast(updated.isActive ? "Usuário ativado." : "Usuário bloqueado.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  async function handleResetPassword(data: CrmUserInput) {
    if (!resetPassword) return;
    try {
      await apiUpdate<{ ok: boolean }>(`/users/${resetPassword.id}/password`, { password: data.password }, "Falha ao redefinir senha.");
      setResetPassword(null);
      pushToast("Senha redefinida com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  if (!currentUser) return null;
  if (loading) return <div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>;

  return (
    <>
      <Users
        users={users}
        currentUserRole={currentUser.role}
        canCreate={hasPermission("users.manage")}
        onNew={() => setShowNew(true)}
        onEdit={setEditing}
        onToggleActive={handleToggleActive}
        onResetPassword={setResetPassword}
      />
      {showNew && (
        <NewUserForm user={null} currentUserRole={currentUser.role} onSave={handleSave} onClose={() => setShowNew(false)} onToast={pushToast} />
      )}
      {editing && (
        <NewUserForm user={editing} currentUserRole={currentUser.role} onSave={handleUpdate} onClose={() => setEditing(null)} onToast={pushToast} />
      )}
      {resetPassword && (
        <NewUserForm user={resetPassword} resetPasswordMode currentUserRole={currentUser.role} onSave={handleResetPassword} onClose={() => setResetPassword(null)} onToast={pushToast} />
      )}
    </>
  );
}
