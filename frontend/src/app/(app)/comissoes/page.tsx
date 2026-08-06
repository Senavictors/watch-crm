"use client";
import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiFetch, apiCreate, getApiBaseUrl } from "../../../features/crm/api";
import { CommissionReport } from "../../../features/crm/types";
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

  const canPay = hasPermission("commissions.pay");

  function buildQuery() {
    const params = new URLSearchParams();
    if (startDate) params.set("startDate", startDate);
    if (endDate) params.set("endDate", endDate);
    if (sellerUserId) params.set("sellerUserId", sellerUserId);
    const query = params.toString();
    return query ? `?${query}` : "";
  }

  useEffect(() => {
    const apiBaseUrl = getApiBaseUrl();
    let alive = true;

    async function load() {
      try {
        setLoading(true);
        const response = await apiFetch(`${apiBaseUrl}/commissions${buildQuery()}`);
        if (response.status === 401) {
          handleUnauthorized();
          return;
        }
        if (!response.ok) throw new Error("Falha ao carregar relatório de comissões.");
        const data = (await response.json()) as CommissionReport;
        if (alive) setReport(data);
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
  }, [startDate, endDate, sellerUserId]);

  async function handlePay(orderItemIds: number[]) {
    if (orderItemIds.length === 0) return;
    setPayLoading(true);
    try {
      await apiCreate("/commissions/pay", { orderItemIds }, "Falha ao marcar comissão como paga.");
      pushToast("Comissão marcada como paga.", "success");

      const apiBaseUrl = getApiBaseUrl();
      const response = await apiFetch(`${apiBaseUrl}/commissions${buildQuery()}`);
      if (response.ok) setReport(await response.json());
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    } finally {
      setPayLoading(false);
    }
  }

  if (loading || !report) {
    return <div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>;
  }

  return (
    <Commissions
      report={report}
      canPay={canPay}
      startDate={startDate}
      endDate={endDate}
      sellerUserId={sellerUserId}
      onStartDateChange={setStartDate}
      onEndDateChange={setEndDate}
      onSellerUserIdChange={setSellerUserId}
      onPay={handlePay}
      payLoading={payLoading}
    />
  );
}
