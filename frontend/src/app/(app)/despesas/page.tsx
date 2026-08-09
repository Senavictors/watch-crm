"use client";

import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiCreate, apiDelete, apiFetch, apiUpdate, getApiBaseUrl } from "../../../features/crm/api";
import { Expense, ExpenseInput, ExpenseListResponse, ExpenseMetadata, PaginationMeta } from "../../../features/crm/types";
import { appendPagination, EMPTY_PAGINATION } from "../../../features/crm/pagination";
import PaginationBar from "../../../features/crm/ui/PaginationBar";
import Expenses from "../../../features/crm/views/Expenses";
import NewExpenseForm from "../../../features/crm/views/NewExpenseForm";

export default function DespesasPage() {
  const { hasPermission, handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const [expenses, setExpenses] = useState<Expense[]>([]);
  const [metadata, setMetadata] = useState<ExpenseMetadata | null>(null);
  const [summary, setSummary] = useState({ totalAmount: 0, totalCount: 0 });
  const [meta, setMeta] = useState<PaginationMeta>(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [showNew, setShowNew] = useState(false);
  const [editing, setEditing] = useState<Expense | null>(null);
  const [category, setCategory] = useState("");
  const [startDate, setStartDate] = useState("");
  const [endDate, setEndDate] = useState("");
  const [page, setPage] = useState(1);
  const [reloadKey, setReloadKey] = useState(0);

  useEffect(() => {
    let alive = true;
    async function loadMetadata() {
      try {
        const response = await apiFetch(`${getApiBaseUrl()}/expenses/metadata`);
        if (response.status === 401) { handleUnauthorized(); return; }
        if (!response.ok) throw new Error("Falha ao carregar dados de despesas.");
        const payload = await response.json() as ExpenseMetadata;
        if (alive) setMetadata(payload);
      } catch (error) { if (alive) pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
    }
    loadMetadata();
    return () => { alive = false; };
  }, [handleUnauthorized, pushToast]);

  useEffect(() => {
    const params = appendPagination(new URLSearchParams(), page);
    if (category) params.set("category", category);
    if (startDate) params.set("startDate", startDate);
    if (endDate) params.set("endDate", endDate);
    let alive = true;
    async function loadExpenses() {
      try {
        setLoading(true);
        const response = await apiFetch(`${getApiBaseUrl()}/expenses?${params}`);
        if (response.status === 401) { handleUnauthorized(); return; }
        if (!response.ok) throw new Error("Falha ao carregar despesas.");
        const payload = await response.json() as ExpenseListResponse;
        if (!alive) return;
        setExpenses(payload.data); setMeta(payload.meta); setSummary(payload.summary);
      } catch (error) { if (alive) pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
      finally { if (alive) setLoading(false); }
    }
    loadExpenses();
    return () => { alive = false; };
  }, [category, endDate, handleUnauthorized, page, pushToast, reloadKey, startDate]);

  function filter(setter: (value: string) => void, value: string) { setPage(1); setter(value); }
  function reload() { setReloadKey((key) => key + 1); }

  async function handleSave(data: ExpenseInput) {
    try { await apiCreate<Expense>("/expenses", data, "Falha ao registrar despesa."); setShowNew(false); setPage(1); reload(); pushToast("Despesa registrada com sucesso.", "success"); }
    catch (error) { pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
  }

  async function handleUpdate(data: ExpenseInput) {
    if (!editing) return;
    try { await apiUpdate<Expense>(`/expenses/${editing.id}`, data, "Falha ao atualizar despesa."); setEditing(null); reload(); pushToast("Despesa atualizada com sucesso.", "success"); }
    catch (error) { pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
  }

  async function handleDelete(expense: Expense) {
    if (!confirm(`Excluir a despesa "${expense.description}"?`)) return;
    try { await apiDelete(`/expenses/${expense.id}`, "Falha ao excluir despesa."); reload(); pushToast("Despesa excluída com sucesso.", "success"); }
    catch (error) { pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
  }

  if (!metadata) return <div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>;

  return (
    <>
      <Expenses
        expenses={expenses}
        metadata={metadata}
        summary={summary}
        canCreate={hasPermission("expenses.create")}
        canUpdate={hasPermission("expenses.update")}
        canDelete={hasPermission("expenses.delete")}
        category={category}
        startDate={startDate}
        endDate={endDate}
        onCategoryChange={(value) => filter(setCategory, value)}
        onStartDateChange={(value) => filter(setStartDate, value)}
        onEndDateChange={(value) => filter(setEndDate, value)}
        onNew={() => setShowNew(true)}
        onEdit={setEditing}
        onDelete={handleDelete}
      />
      <PaginationBar meta={meta} onPageChange={setPage} disabled={loading} />
      {showNew && <NewExpenseForm metadata={metadata} onSave={handleSave} onClose={() => setShowNew(false)} onToast={pushToast} />}
      {editing && <NewExpenseForm expense={editing} metadata={metadata} onSave={handleUpdate} onClose={() => setEditing(null)} onToast={pushToast} />}
    </>
  );
}
