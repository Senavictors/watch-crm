"use client";
import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiFetch, apiCreate, apiUpdate, apiDelete, getApiBaseUrl } from "../../../features/crm/api";
import { Goal, GoalInput, GoalMetadata } from "../../../features/crm/types";
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

  useEffect(() => {
    const apiBaseUrl = getApiBaseUrl();
    let alive = true;

    async function load() {
      try {
        setLoading(true);
        const [goalsRes, metaRes] = await Promise.all([
          apiFetch(`${apiBaseUrl}/goals`),
          apiFetch(`${apiBaseUrl}/goals/metadata`),
        ]);
        if (goalsRes.status === 401 || metaRes.status === 401) {
          handleUnauthorized();
          return;
        }
        if (!goalsRes.ok) throw new Error("Falha ao carregar metas.");
        if (!metaRes.ok) throw new Error("Falha ao carregar metadados.");
        const [goalsData, metaData] = await Promise.all([goalsRes.json(), metaRes.json()]);
        if (alive) {
          setGoals(goalsData);
          setMetadata(metaData);
        }
      } catch (err) {
        if (alive) pushToast(err instanceof Error ? err.message : "Erro.", "error");
      } finally {
        if (alive) setLoading(false);
      }
    }

    load();
    return () => { alive = false; };
  }, []);

  async function handleSave(data: GoalInput) {
    try {
      const created = await apiCreate<Goal>("/goals", data, "Falha ao criar meta.");
      setGoals((gs) => [created, ...gs]);
      setShowNew(false);
      pushToast("Meta criada com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  async function handleUpdate(data: GoalInput) {
    if (!editing) return;
    try {
      const updated = await apiUpdate<Goal>(`/goals/${editing.id}`, data, "Falha ao atualizar meta.");
      setGoals((gs) => gs.map((g) => (g.id === updated.id ? updated : g)));
      setEditing(null);
      pushToast("Meta atualizada com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  async function handleDelete(goal: Goal) {
    if (!confirm(`Tem certeza que deseja excluir "${goal.name}"?`)) return;
    try {
      await apiDelete(`/goals/${goal.id}`, "Falha ao excluir meta.");
      setGoals((gs) => gs.filter((g) => g.id !== goal.id));
      pushToast("Meta excluída com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  if (!currentUser) return null;
  if (loading || !metadata) return <div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>;

  return (
    <>
      <GoalList
        goals={goals}
        canCreate={hasPermission("goals.create")}
        canUpdate={hasPermission("goals.update")}
        canDelete={hasPermission("goals.delete")}
        onNew={() => setShowNew(true)}
        onEdit={setEditing}
        onDelete={handleDelete}
        onSelect={setViewing}
      />
      {showNew && (
        <NewGoalForm
          metadata={metadata}
          onSave={handleSave}
          onClose={() => setShowNew(false)}
          onToast={pushToast}
        />
      )}
      {editing && (
        <NewGoalForm
          goal={editing}
          metadata={metadata}
          onSave={handleUpdate}
          onClose={() => setEditing(null)}
          onToast={pushToast}
        />
      )}
      {viewing && (
        <GoalDetail goal={viewing} onClose={() => setViewing(null)} />
      )}
    </>
  );
}
