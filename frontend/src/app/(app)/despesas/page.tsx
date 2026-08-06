"use client";
import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiFetch, apiCreate, apiUpdate, apiDelete, getApiBaseUrl } from "../../../features/crm/api";
import { Expense, ExpenseInput, ExpenseMetadata } from "../../../features/crm/types";
import Expenses from "../../../features/crm/views/Expenses";
import NewExpenseForm from "../../../features/crm/views/NewExpenseForm";

export default function DespesasPage() {
  const { hasPermission, handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const [expenses, setExpenses] = useState<Expense[]>([]);
  const [metadata, setMetadata] = useState<ExpenseMetadata | null>(null);
  const [loading, setLoading] = useState(true);
  const [showNew, setShowNew] = useState(false);
  const [editing, setEditing] = useState<Expense | null>(null);
  const [category, setCategory] = useState("");
  const [startDate, setStartDate] = useState("");
  const [endDate, setEndDate] = useState("");

  const canCreate = hasPermission("expenses.create");
  const canUpdate = hasPermission("expenses.update");
  const canDelete = hasPermission("expenses.delete");

  function buildQuery() {
    const params = new URLSearchParams();
    if (category) params.set("category", category);
    if (startDate) params.set("startDate", startDate);
    if (endDate) params.set("endDate", endDate);
    const query = params.toString();
    return query ? `?${query}` : "";
  }

  useEffect(() => {
    const apiBaseUrl = getApiBaseUrl();
    let alive = true;

    async function load() {
      try {
        setLoading(true);
        const requests: Promise<Response>[] = [apiFetch(`${apiBaseUrl}/expenses${buildQuery()}`)];
        if (!metadata) requests.push(apiFetch(`${apiBaseUrl}/expenses/metadata`));
        const responses = await Promise.all(requests);
        if (responses.some((r) => r.status === 401)) {
          handleUnauthorized();
          return;
        }
        if (responses.some((r) => !r.ok)) throw new Error("Falha ao carregar despesas.");
        const [expensesData, metadataData] = await Promise.all(responses.map((r) => r.json()));
        if (!alive) return;
        setExpenses(expensesData);
        if (metadataData) setMetadata(metadataData);
      } catch (err) {
        if (alive) pushToast(err instanceof Error ? err.message : "Erro.", "error");
      } finally {
        if (alive) setLoading(false);
      }
    }

    load();
    return () => {
      alive = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [category, startDate, endDate]);

  async function handleSave(data: ExpenseInput) {
    try {
      const created = await apiCreate<Expense>("/expenses", data, "Falha ao registrar despesa.");
      setExpenses((es) => [created, ...es]);
      setShowNew(false);
      pushToast("Despesa registrada com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  async function handleUpdate(data: ExpenseInput) {
    if (!editing) return;
    try {
      const updated = await apiUpdate<Expense>(`/expenses/${editing.id}`, data, "Falha ao atualizar despesa.");
      setExpenses((es) => es.map((e) => (e.id === updated.id ? updated : e)));
      setEditing(null);
      pushToast("Despesa atualizada com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  async function handleDelete(expense: Expense) {
    if (!confirm(`Excluir a despesa "${expense.description}"?`)) return;
    try {
      await apiDelete(`/expenses/${expense.id}`, "Falha ao excluir despesa.");
      setExpenses((es) => es.filter((e) => e.id !== expense.id));
      pushToast("Despesa excluída com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  if (loading || !metadata) {
    return <div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>;
  }

  return (
    <>
      <Expenses
        expenses={expenses}
        metadata={metadata}
        canCreate={canCreate}
        canUpdate={canUpdate}
        canDelete={canDelete}
        category={category}
        startDate={startDate}
        endDate={endDate}
        onCategoryChange={setCategory}
        onStartDateChange={setStartDate}
        onEndDateChange={setEndDate}
        onNew={() => setShowNew(true)}
        onEdit={setEditing}
        onDelete={handleDelete}
      />
      {showNew && (
        <NewExpenseForm metadata={metadata} onSave={handleSave} onClose={() => setShowNew(false)} onToast={pushToast} />
      )}
      {editing && (
        <NewExpenseForm
          expense={editing}
          metadata={metadata}
          onSave={handleUpdate}
          onClose={() => setEditing(null)}
          onToast={pushToast}
        />
      )}
    </>
  );
}
