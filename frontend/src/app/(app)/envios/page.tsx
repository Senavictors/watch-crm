"use client";

import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiFetch, apiUpdate, getApiBaseUrl } from "../../../features/crm/api";
import { PaginatedResponse, PaginationMeta, PostingDaySchedule, ProductReturn, ShippingQueueItem } from "../../../features/crm/types";
import { EMPTY_PAGINATION } from "../../../features/crm/pagination";
import PaginationBar from "../../../features/crm/ui/PaginationBar";
import ShippingQueue from "../../../features/crm/views/ShippingQueue";

export default function EnviosPage() {
  const { hasPermission, handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const [queue, setQueue] = useState<ShippingQueueItem[]>([]);
  const [schedule, setSchedule] = useState<PostingDaySchedule[]>([]);
  const [pendingReturns, setPendingReturns] = useState<ProductReturn[]>([]);
  const [queuePage, setQueuePage] = useState(1);
  const [returnPage, setReturnPage] = useState(1);
  const [queueMeta, setQueueMeta] = useState<PaginationMeta>(EMPTY_PAGINATION);
  const [returnMeta, setReturnMeta] = useState<PaginationMeta>(EMPTY_PAGINATION);
  const [loading, setLoading] = useState(true);
  const [reloadKey, setReloadKey] = useState(0);

  useEffect(() => {
    let alive = true;
    async function loadSchedule() {
      try {
        const response = await apiFetch(`${getApiBaseUrl()}/shipping/schedule`);
        if (response.status === 401) { handleUnauthorized(); return; }
        if (!response.ok) throw new Error("Falha ao carregar agenda de envios.");
        const payload = await response.json() as PostingDaySchedule[];
        if (alive) setSchedule(payload);
      } catch (error) { if (alive) pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
    }
    loadSchedule();
    return () => { alive = false; };
  }, [handleUnauthorized, pushToast]);

  useEffect(() => {
    let alive = true;
    async function loadLists() {
      try {
        setLoading(true);
        const canViewReturns = hasPermission("returns.view");
        const requests = [apiFetch(`${getApiBaseUrl()}/shipping/queue?page=${queuePage}&perPage=20`)];
        if (canViewReturns) requests.push(apiFetch(`${getApiBaseUrl()}/returns?status=${encodeURIComponent("Pronto para Reenvio")}&page=${returnPage}&perPage=20`));
        const responses = await Promise.all(requests);
        if (responses.some((response) => response.status === 401)) { handleUnauthorized(); return; }
        if (responses.some((response) => !response.ok)) throw new Error("Falha ao carregar envios.");
        const queuePayload = await responses[0].json() as PaginatedResponse<ShippingQueueItem>;
        const returnPayload = canViewReturns
          ? await responses[1].json() as PaginatedResponse<ProductReturn>
          : { data: [], meta: EMPTY_PAGINATION };
        if (!alive) return;
        setQueue(queuePayload.data); setQueueMeta(queuePayload.meta);
        setPendingReturns(returnPayload.data); setReturnMeta(returnPayload.meta);
      } catch (error) { if (alive) pushToast(error instanceof Error ? error.message : "Erro.", "error"); }
      finally { if (alive) setLoading(false); }
    }
    loadLists();
    return () => { alive = false; };
  }, [handleUnauthorized, hasPermission, queuePage, pushToast, reloadKey, returnPage]);

  async function handleUpdateShipping(item: ShippingQueueItem, trackingCode: string) {
    try {
      await apiUpdate(
        `/orders/${item.id}`,
        {
          status: "Enviado",
          trackingCode,
        },
        "Falha ao atualizar o envio."
      );

      pushToast(`Pedido #${item.id} marcado como enviado.`, "success");

      if (queue.length === 1 && queuePage > 1) {
        setQueuePage((page) => page - 1);
      } else {
        setReloadKey((key) => key + 1);
      }
    } catch (error) {
      pushToast(error instanceof Error ? error.message : "Erro ao atualizar o envio.", "error");
      throw error;
    }
  }

  return (
    <>
      <ShippingQueue
        queue={queue}
        schedule={schedule}
        pendingReturns={pendingReturns}
        canUpdateShipping={hasPermission("orders.update")}
        onUpdateShipping={handleUpdateShipping}
      />
      <PaginationBar meta={queueMeta} onPageChange={setQueuePage} disabled={loading} />
      {hasPermission("returns.view") && returnMeta.total > 0 && (
        <div style={{ marginTop: 12 }}>
          <PaginationBar meta={returnMeta} onPageChange={setReturnPage} disabled={loading} />
        </div>
      )}
    </>
  );
}
