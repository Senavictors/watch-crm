"use client";
import { useEffect, useState } from "react";
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
  assignableUsers: [],
};

export default function PedidosPage() {
  const { hasPermission, handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const [orders, setOrders] = useState<Order[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [metadata, setMetadata] = useState<OrderMetadata>(EMPTY_METADATA);
  const [returnMetadata, setReturnMetadata] = useState<ReturnMetadata>(EMPTY_RETURN_METADATA);
  const [loading, setLoading] = useState(true);
  const [viewOrder, setViewOrder] = useState<Order | null>(null);
  const [showNew, setShowNew] = useState(false);
  const [returnForOrder, setReturnForOrder] = useState<Order | null>(null);

  useEffect(() => {
    const apiBaseUrl = getApiBaseUrl();
    let alive = true;

    async function load() {
      try {
        setLoading(true);
        const canViewReturns = hasPermission("returns.view");
        const fetches: Promise<Response>[] = [
          apiFetch(`${apiBaseUrl}/orders`),
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
        if (results.slice(0, 4).some((r) => !r.ok)) {
          throw new Error("Falha ao carregar pedidos.");
        }
        const [ordersData, metaData, customersData, productsData] = await Promise.all([
          results[0].json(), results[1].json(), results[2].json(), results[3].json(),
        ]);
        const returnMetaData = canViewReturns && results[4]?.ok ? await results[4].json() : EMPTY_RETURN_METADATA;
        if (!alive) return;
        setOrders(ordersData);
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

    load();
    return () => { alive = false; };
  }, []);

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

  if (loading) return <div style={{ color: "var(--crm-text-muted)", padding: 32 }}>Carregando...</div>;

  return (
    <>
      <OrderList
        orders={orders}
        customers={customers}
        channels={metadata.channels}
        sellers={sellers}
        statuses={metadata.statuses}
        canCreate={hasPermission("orders.create")}
        canUpdateStatus={hasPermission("orders.update")}
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
