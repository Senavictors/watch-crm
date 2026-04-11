"use client";
import { useEffect, useState } from "react";
import { useAuth } from "../../../features/crm/contexts/AuthContext";
import { useToast } from "../../../features/crm/contexts/ToastContext";
import { apiFetch, apiUpdate, apiCreate, getApiBaseUrl } from "../../../features/crm/api";
import { Customer, Order, OrderInput, OrderMetadata, OrderStatus, Product } from "../../../features/crm/types";
import OrderList from "../../../features/crm/views/OrderList";
import OrderDetail from "../../../features/crm/views/OrderDetail";
import NewOrderForm from "../../../features/crm/views/NewOrderForm";

const EMPTY_METADATA: OrderMetadata = {
  channels: [],
  statuses: [],
  paymentMethods: [],
  shippingMethods: [],
  assignableSellers: [],
};

export default function PedidosPage() {
  const { hasPermission, handleUnauthorized } = useAuth();
  const { pushToast } = useToast();
  const [orders, setOrders] = useState<Order[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [metadata, setMetadata] = useState<OrderMetadata>(EMPTY_METADATA);
  const [loading, setLoading] = useState(true);
  const [viewOrder, setViewOrder] = useState<Order | null>(null);
  const [showNew, setShowNew] = useState(false);

  useEffect(() => {
    const apiBaseUrl = getApiBaseUrl();
    let alive = true;

    async function load() {
      try {
        setLoading(true);
        const [ordersRes, metaRes, customersRes, productsRes] = await Promise.all([
          apiFetch(`${apiBaseUrl}/orders`),
          apiFetch(`${apiBaseUrl}/orders/metadata`),
          apiFetch(`${apiBaseUrl}/customers`),
          apiFetch(`${apiBaseUrl}/products`),
        ]);
        if ([ordersRes, metaRes, customersRes, productsRes].some((r) => r.status === 401)) {
          handleUnauthorized();
          return;
        }
        if (!ordersRes.ok || !metaRes.ok || !customersRes.ok || !productsRes.ok) {
          throw new Error("Falha ao carregar pedidos.");
        }
        const [ordersData, metaData, customersData, productsData] = await Promise.all([
          ordersRes.json(), metaRes.json(), customersRes.json(), productsRes.json(),
        ]);
        if (!alive) return;
        setOrders(ordersData);
        setMetadata(metaData);
        setCustomers(customersData);
        setProducts(productsData);
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
      {viewOrder && (
        <OrderDetail order={viewOrder} customers={customers} onClose={() => setViewOrder(null)} />
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
    </>
  );
}
