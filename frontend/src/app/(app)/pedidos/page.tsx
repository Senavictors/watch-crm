"use client";
import { Suspense, useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiFetch, apiUpdate, apiCreate, getApiBaseUrl } from "../../../features/crm/api";
import { Customer, Order, OrderInput, OrderMetadata, OrderStatus, Product, ReturnInput, ReturnMetadata } from "../../../features/crm/types";
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
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [metadata, setMetadata] = useState<OrderMetadata>(EMPTY_METADATA);
  const [returnMetadata, setReturnMetadata] = useState<ReturnMetadata>(EMPTY_RETURN_METADATA);
  const [loading, setLoading] = useState(true);
  const [viewOrder, setViewOrder] = useState<Order | null>(null);
  const [showNew, setShowNew] = useState(false);
  const [returnForOrder, setReturnForOrder] = useState<Order | null>(null);

  // CA-01: a URL é a fonte do estado inicial dos filtros de categoria/período.
  const [category, setCategory] = useState(() => searchParams.get("category") ?? "");
  const [from, setFrom] = useState(() => searchParams.get("from") ?? "");
  const [to, setTo] = useState(() => searchParams.get("to") ?? "");

  // Dados "estáticos" da tela — carregam uma única vez na montagem, não dependem dos filtros.
  useEffect(() => {
    const apiBaseUrl = getApiBaseUrl();
    let alive = true;

    async function loadStatic() {
      try {
        setLoading(true);
        const canViewReturns = hasPermission("returns.view");
        const fetches: Promise<Response>[] = [
          apiFetch(`${apiBaseUrl}/orders/metadata`),
          apiFetch(`${apiBaseUrl}/customers`),
          apiFetch(`${apiBaseUrl}/products`),
        ];
        if (canViewReturns) {
          fetches.push(apiFetch(`${apiBaseUrl}/returns/metadata`));
        }
        const results = await Promise.all(fetches);
        if (results.some((r) => r.status === 401)) {
          handleUnauthorized();
          return;
        }
        if (results.slice(0, 3).some((r) => !r.ok)) {
          throw new Error("Falha ao carregar dados de pedidos.");
        }
        const [metaData, customersData, productsData] = await Promise.all([
          results[0].json(), results[1].json(), results[2].json(),
        ]);
        const returnMetaData = canViewReturns && results[3]?.ok ? await results[3].json() : EMPTY_RETURN_METADATA;
        if (!alive) return;
        setMetadata(metaData);
        setCustomers(customersData);
        setProducts(productsData);
        setReturnMetadata(returnMetaData);
      } catch (err) {
        if (alive) pushToast(err instanceof Error ? err.message : "Erro.", "error");
      } finally {
        if (alive) setLoading(false);
      }
    }

    loadStatic();
    return () => { alive = false; };
  }, []);

  // Pedidos — refaz o fetch e sincroniza a URL sempre que categoria/período mudam.
  useEffect(() => {
    const params = new URLSearchParams();
    if (category) params.set("category", category);
    if (from) params.set("from", from);
    if (to) params.set("to", to);
    const query = params.toString();
    router.replace(query ? `/pedidos?${query}` : "/pedidos", { scroll: false });

    const apiBaseUrl = getApiBaseUrl();
    let alive = true;

    async function loadOrders() {
      try {
        const response = await apiFetch(`${apiBaseUrl}/orders${query ? `?${query}` : ""}`);
        if (response.status === 401) {
          handleUnauthorized();
          return;
        }
        if (!response.ok) {
          throw new Error("Falha ao carregar pedidos.");
        }
        const ordersData = await response.json();
        if (!alive) return;
        setOrders(ordersData);
      } catch (err) {
        if (alive) pushToast(err instanceof Error ? err.message : "Erro.", "error");
      }
    }

    loadOrders();
    return () => { alive = false; };
  }, [category, from, to]);

  async function handleUpdateStatus(id: number, status: OrderStatus) {
    try {
      const updated = await apiUpdate<Order>(`/orders/${id}`, { status }, "Falha ao atualizar status.");
      setOrders((os) => os.map((o) => (o.id === id ? updated : o)));
      setViewOrder((current) => (current?.id === id ? updated : current));
      pushToast("Status atualizado com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  async function handleSaveOrder(data: OrderInput) {
    try {
      const created = await apiCreate<Order>("/orders", data, "Falha ao criar pedido.");
      setOrders((os) => [created, ...os]);
      setShowNew(false);
      pushToast("Pedido criado com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  async function handleSaveReturn(data: ReturnInput) {
    try {
      await apiCreate("/returns", data, "Falha ao registrar garantia.");
      setReturnForOrder(null);
      setViewOrder(null);
      pushToast("Garantia/Troca registrada com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro.", "error");
    }
  }

  const sellers = Array.from(new Set([
    ...metadata.assignableSellers.map((s) => s.name),
    ...orders.map((o) => o.seller).filter(Boolean),
  ].filter(Boolean)));

  const categories = Array.from(
    new Set(products.map((p) => p.categoryName).filter((c): c is string => Boolean(c)))
  );

  if (loading) return <div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>;

  return (
    <>
      <OrderList
        orders={orders}
        customers={customers}
        channels={metadata.channels}
        sellers={sellers}
        statuses={metadata.statuses}
        categories={categories}
        category={category}
        from={from}
        to={to}
        onCategoryChange={setCategory}
        onFromChange={setFrom}
        onToChange={setTo}
        canCreate={hasPermission("orders.create")}
        canUpdateStatus={hasPermission("orders.update")}
        canViewProfit={hasPermission("dashboard.financial.view")}
        onView={setViewOrder}
        onNew={() => setShowNew(true)}
        onUpdateStatus={handleUpdateStatus}
      />
      {viewOrder && !returnForOrder && (
        <OrderDetail
          order={viewOrder}
          customers={customers}
          canCreateReturn={hasPermission("returns.create")}
          onClose={() => setViewOrder(null)}
          onCreateReturn={(order) => { setReturnForOrder(order); setViewOrder(null); }}
        />
      )}
      {showNew && (
        <NewOrderForm
          products={products}
          customers={customers}
          metadata={metadata}
          canViewFinancials={hasPermission("dashboard.financial.view")}
          onSave={handleSaveOrder}
          onClose={() => setShowNew(false)}
          onToast={pushToast}
        />
      )}
      {returnForOrder && (
        <NewReturnForm
          customers={customers}
          orders={orders}
          metadata={returnMetadata}
          prefilledOrderId={returnForOrder.id}
          onSave={handleSaveReturn}
          onClose={() => setReturnForOrder(null)}
          onToast={pushToast}
        />
      )}
    </>
  );
}
