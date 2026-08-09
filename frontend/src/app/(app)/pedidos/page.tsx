"use client";

import { Suspense, useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiCreate, apiFetch, apiGet, apiUpdate, getApiBaseUrl } from "../../../features/crm/api";
import { Order, OrderInput, OrderMetadata, OrderStatus, PaginatedResponse, PaginationMeta, ReturnInput, ReturnMetadata } from "../../../features/crm/types";
import { appendPagination, EMPTY_PAGINATION } from "../../../features/crm/pagination";
import { useDebouncedValue } from "../../../features/crm/hooks/useDebouncedValue";
import PaginationBar from "../../../features/crm/ui/PaginationBar";
import OrderList from "../../../features/crm/views/OrderList";
import OrderDetail from "../../../features/crm/views/OrderDetail";
import NewOrderForm from "../../../features/crm/views/NewOrderForm";
import NewReturnForm from "../../../features/crm/views/NewReturnForm";

const EMPTY_METADATA: OrderMetadata = {
  channels: [],
  statuses: [],
  paymentMethods: [],
  shippingMethods: [],
  assignableSellers: [],
  categories: [],
};

const EMPTY_RETURN_METADATA: ReturnMetadata = {
  types: [],
  typeLabels: { garantia: "Garantia", troca: "Troca", devolucao: "Devolução" },
  statuses: [],
  transitions: {},
  assignableUsers: [],
};

export default function PedidosPage() {
  return (
    <Suspense fallback={<div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>}>
      <PedidosPageContent />
    </Suspense>
  );
}

function PedidosPageContent() {
  const { hasPermission, handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const router = useRouter();
  const searchParams = useSearchParams();
  const [orders, setOrders] = useState<Order[]>([]);
  const [metadata, setMetadata] = useState<OrderMetadata>(EMPTY_METADATA);
  const [returnMetadata, setReturnMetadata] = useState<ReturnMetadata>(EMPTY_RETURN_METADATA);
  const [loading, setLoading] = useState(true);
  const [listLoading, setListLoading] = useState(false);
  const [viewOrder, setViewOrder] = useState<Order | null>(null);
  const [showNew, setShowNew] = useState(false);
  const [returnForOrder, setReturnForOrder] = useState<Order | null>(null);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState<OrderStatus | "">(() => (searchParams.get("status") as OrderStatus | null) ?? "");
  const [paymentStatus, setPaymentStatus] = useState(() => searchParams.get("paymentStatus") ?? "");
  const [channel, setChannel] = useState("");
  const [sellerUserId, setSellerUserId] = useState("");
  const [category, setCategory] = useState(() => searchParams.get("category") ?? "");
  const [from, setFrom] = useState(() => searchParams.get("from") ?? "");
  const [to, setTo] = useState(() => searchParams.get("to") ?? "");
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState<PaginationMeta>(EMPTY_PAGINATION);
  const [reloadKey, setReloadKey] = useState(0);
  const debouncedSearch = useDebouncedValue(search);

  useEffect(() => {
    const apiBaseUrl = getApiBaseUrl();
    let alive = true;

    async function loadMetadata() {
      try {
        const canViewReturns = hasPermission("returns.view");
        const requests = [apiFetch(`${apiBaseUrl}/orders/metadata`)];
        if (canViewReturns) requests.push(apiFetch(`${apiBaseUrl}/returns/metadata`));
        const responses = await Promise.all(requests);
        if (responses.some((response) => response.status === 401)) { handleUnauthorized(); return; }
        if (!responses[0].ok) throw new Error("Falha ao carregar dados de pedidos.");
        const orderMetadata = await responses[0].json() as OrderMetadata;
        const loadedReturnMetadata = canViewReturns && responses[1]?.ok
          ? await responses[1].json() as ReturnMetadata
          : EMPTY_RETURN_METADATA;
        if (!alive) return;
        setMetadata(orderMetadata);
        setReturnMetadata(loadedReturnMetadata);
      } catch (error) {
        if (alive) pushToast(error instanceof Error ? error.message : "Erro.", "error");
      } finally {
        if (alive) setLoading(false);
      }
    }

    loadMetadata();
    return () => { alive = false; };
  }, [handleUnauthorized, hasPermission, pushToast]);

  useEffect(() => {
    const urlParams = new URLSearchParams();
    if (category) urlParams.set("category", category);
    if (from) urlParams.set("from", from);
    if (to) urlParams.set("to", to);
    if (status) urlParams.set("status", status);
    if (paymentStatus) urlParams.set("paymentStatus", paymentStatus);
    const urlQuery = urlParams.toString();
    router.replace(urlQuery ? `/pedidos?${urlQuery}` : "/pedidos", { scroll: false });

    const params = appendPagination(new URLSearchParams(urlParams), page);
    if (debouncedSearch) params.set("search", debouncedSearch);
    if (status) params.set("status", status);
    if (paymentStatus) params.set("paymentStatus", paymentStatus);
    if (channel) params.set("channel", channel);
    if (sellerUserId) params.set("sellerUserId", sellerUserId);
    let alive = true;

    async function loadOrders() {
      try {
        setListLoading(true);
        const response = await apiFetch(`${getApiBaseUrl()}/orders?${params}`);
        if (response.status === 401) { handleUnauthorized(); return; }
        if (!response.ok) throw new Error("Falha ao carregar pedidos.");
        const payload = await response.json() as PaginatedResponse<Order>;
        if (!alive) return;
        setOrders(payload.data);
        setMeta(payload.meta);
      } catch (error) {
        if (alive) pushToast(error instanceof Error ? error.message : "Erro.", "error");
      } finally {
        if (alive) setListLoading(false);
      }
    }

    loadOrders();
    return () => { alive = false; };
  }, [category, channel, debouncedSearch, from, handleUnauthorized, page, paymentStatus, pushToast, reloadKey, router, sellerUserId, status, to]);

  useEffect(() => { setPage(1); }, [debouncedSearch]);

  function resetPageAnd<T>(setter: (value: T) => void, value: T) {
    setPage(1);
    setter(value);
  }

  async function handleView(order: Order) {
    try {
      setViewOrder(await apiGet<Order>(`/orders/${order.id}`, "Falha ao carregar o pedido."));
    } catch (error) {
      pushToast(error instanceof Error ? error.message : "Erro.", "error");
    }
  }

  async function handleUpdateStatus(id: number, nextStatus: OrderStatus) {
    try {
      const updated = await apiUpdate<Order>(`/orders/${id}`, { status: nextStatus }, "Falha ao atualizar status.");
      setOrders((current) => current.map((order) => order.id === id ? { ...order, status: updated.status } : order));
      setViewOrder((current) => current?.id === id ? updated : current);
      pushToast("Status atualizado com sucesso.", "success");
    } catch (error) {
      pushToast(error instanceof Error ? error.message : "Erro.", "error");
    }
  }

  async function handleSaveOrder(data: OrderInput) {
    try {
      await apiCreate<Order>("/orders", data, "Falha ao criar pedido.");
      setShowNew(false);
      setPage(1);
      setReloadKey((key) => key + 1);
      pushToast("Pedido criado com sucesso.", "success");
    } catch (error) {
      pushToast(error instanceof Error ? error.message : "Erro.", "error");
    }
  }

  async function handleSaveReturn(data: ReturnInput) {
    try {
      await apiCreate("/returns", data, "Falha ao registrar garantia.");
      setReturnForOrder(null);
      setViewOrder(null);
      pushToast("Garantia/Troca registrada com sucesso.", "success");
    } catch (error) {
      pushToast(error instanceof Error ? error.message : "Erro.", "error");
    }
  }

  if (loading) return <div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>;

  return (
    <>
      <OrderList
        orders={orders}
        channels={metadata.channels}
        sellers={metadata.assignableSellers}
        statuses={metadata.statuses}
        categories={metadata.categories}
        search={search}
        status={status}
        paymentStatus={paymentStatus}
        channel={channel}
        sellerUserId={sellerUserId}
        category={category}
        from={from}
        to={to}
        onSearchChange={setSearch}
        onStatusChange={(value) => {
          setPaymentStatus("");
          resetPageAnd(setStatus, value);
        }}
        onPaymentStatusChange={(value) => resetPageAnd(setPaymentStatus, value)}
        onChannelChange={(value) => resetPageAnd(setChannel, value)}
        onSellerChange={(value) => resetPageAnd(setSellerUserId, value)}
        onCategoryChange={(value) => resetPageAnd(setCategory, value)}
        onFromChange={(value) => resetPageAnd(setFrom, value)}
        onToChange={(value) => resetPageAnd(setTo, value)}
        canCreate={hasPermission("orders.create")}
        canUpdateStatus={hasPermission("orders.update")}
        canViewProfit={hasPermission("dashboard.financial.view")}
        onView={handleView}
        onNew={() => setShowNew(true)}
        onUpdateStatus={handleUpdateStatus}
      />
      <PaginationBar meta={meta} onPageChange={setPage} disabled={listLoading} />
      {viewOrder && !returnForOrder && (
        <OrderDetail
          order={viewOrder}
          canCreateReturn={hasPermission("returns.create")}
          onClose={() => setViewOrder(null)}
          onCreateReturn={(order) => { setReturnForOrder(order); setViewOrder(null); }}
        />
      )}
      {showNew && (
        <NewOrderForm
          metadata={metadata}
          canViewFinancials={hasPermission("dashboard.financial.view")}
          onSave={handleSaveOrder}
          onClose={() => setShowNew(false)}
          onToast={pushToast}
        />
      )}
      {returnForOrder && (
        <NewReturnForm
          metadata={returnMetadata}
          prefilledOrder={returnForOrder}
          onSave={handleSaveReturn}
          onClose={() => setReturnForOrder(null)}
          onToast={pushToast}
        />
      )}
    </>
  );
}
