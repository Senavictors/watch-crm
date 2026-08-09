"use client";

import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiCreate, apiFetch, getApiBaseUrl } from "../../../features/crm/api";
import { CommissionReport } from "../../../features/crm/types";
import { appendPagination } from "../../../features/crm/pagination";
import PaginationBar from "../../../features/crm/ui/PaginationBar";
import Commissions from "../../../features/crm/views/Commissions";

export default function ComissoesPage() {
  const { hasPermission, handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const [report, setReport] = useState<CommissionReport | null>(null);
  const [loading, setLoading] = useState(true);
  const [payLoading, setPayLoading] = useState(false);
  const [startDate, setStartDate] = useState("");
  const [endDate, setEndDate] = useState("");
  const [sellerUserId, setSellerUserId] = useState("");
  const [page, setPage] = useState(1);
  const [reloadKey, setReloadKey] = useState(0);

  useEffect(() => {
    const params = appendPagination(new URLSearchParams(), page);
    if (startDate) params.set("startDate", startDate);
    if (endDate) params.set("endDate", endDate);
    if (sellerUserId) params.set("sellerUserId", sellerUserId);
    let alive = true;
    async function load() {
      try {
        setLoading(true);
        const response = await apiFetch(`${getApiBaseUrl()}/commissions?${params}`);
        if (response.status === 401) { handleUnauthorized(); return; }
        if (!response.ok) throw new Error("Falha ao carregar relatório de comissões.");
        const payload = await response.json() as CommissionReport;
        if (alive) setReport(payload);
      } catch (error) { if (alive) pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
      finally { if (alive) setLoading(false); }
    }
    load();
    return () => { alive = false; };
  }, [endDate, handleUnauthorized, page, pushToast, reloadKey, sellerUserId, startDate]);

  function filter(setter: (value: string) => void, value: string) { setPage(1); setter(value); }

  async function handlePay(orderItemIds: number[]) {
    if (orderItemIds.length === 0) return;
    setPayLoading(true);
    try {
      await apiCreate("/commissions/pay", { orderItemIds }, "Falha ao marcar comissão como paga.");
      setReloadKey((key) => key + 1);
      pushToast("Comissão marcada como paga.", "success");
    } catch (error) { pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
    finally { setPayLoading(false); }
  }

  if (!report) return <div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>;

  return (
    <>
      <Commissions
        report={report}
        canPay={hasPermission("commissions.pay")}
        startDate={startDate}
        endDate={endDate}
        sellerUserId={sellerUserId}
        onStartDateChange={(value) => filter(setStartDate, value)}
        onEndDateChange={(value) => filter(setEndDate, value)}
        onSellerUserIdChange={(value) => filter(setSellerUserId, value)}
        onPay={handlePay}
        payLoading={payLoading}
      />
      <PaginationBar meta={report.meta} onPageChange={setPage} disabled={loading || payLoading} />
    </>
  );
}
