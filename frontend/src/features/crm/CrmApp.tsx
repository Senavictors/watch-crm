"use client";
import React, { useEffect, useState } from "react";
import Dashboard from "./views/Dashboard";
import OrderList from "./views/OrderList";
import ShippingQueue from "./views/ShippingQueue";
import Customers from "./views/Customers";
import Products from "./views/Products";
import OrderDetail from "./views/OrderDetail";
import NewOrderForm from "./views/NewOrderForm";
import NewProductForm from "./views/NewProductForm";
import NewCustomerForm from "./views/NewCustomerForm";
import Brands from "./views/Brands";
import Models from "./views/Models";
import NewBrandForm from "./views/NewBrandForm";
import NewModelForm from "./views/NewModelForm";
import { NAV } from "./data/mock";
import { Brand, Customer, Order, OrderStatus, Product, ProductInput, WatchModel } from "./types";

const CrmApp: React.FC = () => {
  const [page, setPage] = useState("dashboard");
  const [orders, setOrders] = useState<Order[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [brands, setBrands] = useState<Brand[]>([]);
  const [models, setModels] = useState<WatchModel[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [viewOrder, setViewOrder] = useState<Order | null>(null);
  const [showNew, setShowNew] = useState(false);
  const [showNewProduct, setShowNewProduct] = useState(false);
  const [showNewCustomer, setShowNewCustomer] = useState(false);
  const [showNewBrand, setShowNewBrand] = useState(false);
  const [showNewModel, setShowNewModel] = useState(false);

  const apiBaseUrl = process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000/api";

  useEffect(() => {
    let alive = true;
    async function load() {
      try {
        setLoading(true);
        setError(null);
        const [customersRes, productsRes, ordersRes, brandsRes, modelsRes] = await Promise.all([
          fetch(`${apiBaseUrl}/customers`),
          fetch(`${apiBaseUrl}/products`),
          fetch(`${apiBaseUrl}/orders`),
          fetch(`${apiBaseUrl}/brands`),
          fetch(`${apiBaseUrl}/models`),
        ]);
        if (!customersRes.ok || !productsRes.ok || !ordersRes.ok || !brandsRes.ok || !modelsRes.ok) {
          throw new Error("Falha ao carregar dados da API.");
        }
        const [customersData, productsData, ordersData, brandsData, modelsData] = await Promise.all([
          customersRes.json(),
          productsRes.json(),
          ordersRes.json(),
          brandsRes.json(),
          modelsRes.json(),
        ]);
        if (!alive) return;
        setCustomers(customersData);
        setProducts(productsData);
        setOrders(ordersData);
        setBrands(brandsData);
        setModels(modelsData);
      } catch (err) {
        if (!alive) return;
        setError(err instanceof Error ? err.message : "Erro desconhecido.");
      } finally {
        if (alive) setLoading(false);
      }
    }
    load();
    return () => {
      alive = false;
    };
  }, [apiBaseUrl]);

  function handleUpdateStatus(id: number, status: OrderStatus) {
    setOrders((os) => os.map((o) => (o.id === id ? { ...o, status } : o)));
  }

  function handleSaveOrder(data: Omit<Order, "id">) {
    const id = Math.max(...orders.map((o) => o.id)) + 1;
    setOrders((os) => [{ ...data, id }, ...os]);
    setShowNew(false);
  }

  async function handleSaveProduct(data: ProductInput) {
    try {
      setError(null);
      const response = await fetch(`${apiBaseUrl}/products`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });
      if (!response.ok) {
        let message = "Falha ao cadastrar produto.";
        try {
          const payload = await response.json();
          if (payload?.message) message = payload.message;
          if (payload?.errors && typeof payload.errors === "object") {
            const first = Object.values(payload.errors)[0];
            if (Array.isArray(first) && first[0]) message = first[0];
          }
        } catch {}
        throw new Error(message);
      }
      const created = (await response.json()) as Product;
      setProducts((ps) => [created, ...ps]);
      setShowNewProduct(false);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Erro desconhecido.");
    }
  }

  async function handleSaveCustomer(data: Omit<Customer, "id">) {
    try {
      setError(null);
      const response = await fetch(`${apiBaseUrl}/customers`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });
      if (!response.ok) {
        throw new Error("Falha ao cadastrar cliente.");
      }
      const created = (await response.json()) as Customer;
      setCustomers((cs) => [created, ...cs]);
      setShowNewCustomer(false);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Erro desconhecido.");
    }
  }

  async function handleSaveBrand(data: Omit<Brand, "id">) {
    try {
      setError(null);
      const response = await fetch(`${apiBaseUrl}/brands`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });
      if (!response.ok) {
        let message = "Falha ao cadastrar marca.";
        try {
          const payload = await response.json();
          if (payload?.message) message = payload.message;
          if (payload?.errors && typeof payload.errors === "object") {
            const first = Object.values(payload.errors)[0];
            if (Array.isArray(first) && first[0]) message = first[0];
          }
        } catch {}
        throw new Error(message);
      }
      const created = (await response.json()) as Brand;
      setBrands((bs) => [created, ...bs]);
      setShowNewBrand(false);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Erro desconhecido.");
    }
  }

  async function handleSaveModel(data: Omit<WatchModel, "id" | "imageUrl"> & { imageFile?: File | null }) {
    try {
      setError(null);
      const formData = new FormData();
      formData.append("name", data.name);
      formData.append("brandId", String(data.brandId));
      if (data.imageFile) {
        formData.append("image", data.imageFile);
      }
      const response = await fetch(`${apiBaseUrl}/models`, {
        method: "POST",
        headers: { Accept: "application/json" },
        body: formData,
      });
      if (!response.ok) {
        let message = "Falha ao cadastrar modelo.";
        try {
          const payload = await response.json();
          if (payload?.message) message = payload.message;
          if (payload?.errors && typeof payload.errors === "object") {
            const first = Object.values(payload.errors)[0];
            if (Array.isArray(first) && first[0]) message = first[0];
          }
        } catch {}
        throw new Error(message);
      }
      const created = (await response.json()) as WatchModel;
      setModels((ms) => [created, ...ms]);
      setShowNewModel(false);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Erro desconhecido.");
    }
  }

  const readyCount = orders.filter((o) => o.status === "Pronto para Envio").length;

  return (
    <>
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #0A0A0A; color: #F8FAFC; font-family: 'Inter', sans-serif; }
        button, input, select, textarea { font-family: inherit; }
        ::-webkit-scrollbar { width: 6px; } ::-webkit-scrollbar-track { background: #0A0A0A; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
        select option { background: #0A0A0A; color: #E2E8F0; }
      `}</style>

      <div
        style={{
          position: "fixed",
          inset: 0,
          background: "#0A0A0A",
          zIndex: -2,
        }}
      />
      <div
        style={{
          position: "fixed",
          inset: 0,
          background:
            "radial-gradient(60% 60% at 20% 10%, rgba(59, 130, 246, 0.14), transparent 55%), radial-gradient(50% 50% at 80% 0%, rgba(59, 130, 246, 0.1), transparent 60%)",
          zIndex: -2,
        }}
      />
      <div
        style={{
          position: "fixed",
          inset: 0,
          backgroundImage:
            "linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px)",
          backgroundSize: "64px 64px",
          opacity: 0.15,
          zIndex: -1,
        }}
      />

      <div style={{ display: "flex", minHeight: "100vh", position: "relative", zIndex: 1 }}>
        <div
          style={{
            width: 220,
            background: "rgba(10, 10, 10, 0.85)",
            borderRight: "1px solid rgba(255, 255, 255, 0.08)",
            padding: "28px 16px",
            display: "flex",
            flexDirection: "column",
            flexShrink: 0,
            backdropFilter: "blur(10px)",
          }}
        >
          <div style={{ marginBottom: 32, paddingLeft: 8 }}>
            <div
              style={{
                color: "#60A5FA",
                fontSize: 11,
                letterSpacing: 3,
                textTransform: "uppercase",
                marginBottom: 4,
              }}
            >
              Relojoaria
            </div>
            <div
              style={{
                color: "#F8FAFC",
                fontFamily: "'Inter', sans-serif",
                fontSize: 20,
                fontWeight: 700,
              }}
            >
              QueirozPrimeCRM
            </div>
          </div>

          {NAV.map((n) => (
            <button
              key={n.id}
              onClick={() => setPage(n.id)}
              style={{
                display: "flex",
                alignItems: "center",
                gap: 10,
                padding: "10px 12px",
                borderRadius: 8,
                border: "none",
                cursor: "pointer",
                marginBottom: 4,
                width: "100%",
                background: page === n.id ? "rgba(96, 165, 250, 0.16)" : "transparent",
                boxShadow:
                  page === n.id
                    ? "0 12px 24px rgba(15, 23, 42, 0.45)"
                    : "0 8px 16px rgba(15, 23, 42, 0.25)",
                color: page === n.id ? "#F8FAFC" : "#94A3B8",
                fontFamily: "'Inter', sans-serif",
                fontSize: 14,
                fontWeight: page === n.id ? 700 : 400,
                transition: "all 0.15s",
                textAlign: "left",
              }}
            >
              <span style={{ fontSize: 16 }}>{n.label}</span>
              {n.id === "shipping" && readyCount > 0 && (
                <span
                  style={{
                    marginLeft: "auto",
                    background: "#60A5FA",
                    color: "#0A0A0A",
                    borderRadius: 10,
                    padding: "1px 7px",
                    fontSize: 11,
                    fontWeight: 700,
                  }}
                >
                  {readyCount}
                </span>
              )}
            </button>
          ))}

          <div style={{ marginTop: "auto", paddingLeft: 8 }}>
            <div style={{ color: "#64748B", fontSize: 11 }}>MVP v1.0</div>
          </div>
        </div>

        <div style={{ flex: 1, padding: "36px 32px", maxWidth: "calc(100vw - 220px)", overflowX: "auto" }}>
          {loading && <div style={{ color: "#94A3B8" }}>Carregando dados...</div>}
          {error && !loading && <div style={{ color: "#F87171" }}>{error}</div>}
          {!loading && !error && (
            <>
              {page === "dashboard" && <Dashboard orders={orders} />}
              {page === "orders" && (
                <OrderList
                  orders={orders}
                  customers={customers}
                  onView={(o) => setViewOrder(o)}
                  onNew={() => setShowNew(true)}
                  onUpdateStatus={handleUpdateStatus}
                />
              )}
              {page === "shipping" && <ShippingQueue orders={orders} customers={customers} />}
              {page === "customers" && <Customers customers={customers} onNew={() => setShowNewCustomer(true)} />}
              {page === "products" && <Products products={products} onNew={() => setShowNewProduct(true)} />}
              {page === "brands" && <Brands brands={brands} onNew={() => setShowNewBrand(true)} />}
              {page === "models" && <Models models={models} brands={brands} onNew={() => setShowNewModel(true)} />}
            </>
          )}
        </div>
      </div>

      {viewOrder && (
        <OrderDetail order={viewOrder} customers={customers} onClose={() => setViewOrder(null)} />
      )}
      {showNew && (
        <NewOrderForm
          products={products}
          customers={customers}
          onSave={handleSaveOrder}
          onClose={() => setShowNew(false)}
        />
      )}
      {showNewProduct && (
        <NewProductForm
          brands={brands}
          models={models}
          onSave={handleSaveProduct}
          onClose={() => setShowNewProduct(false)}
        />
      )}
      {showNewCustomer && (
        <NewCustomerForm onSave={handleSaveCustomer} onClose={() => setShowNewCustomer(false)} />
      )}
      {showNewBrand && <NewBrandForm onSave={handleSaveBrand} onClose={() => setShowNewBrand(false)} />}
      {showNewModel && (
        <NewModelForm
          brands={brands}
          onSave={handleSaveModel}
          onClose={() => setShowNewModel(false)}
        />
      )}
    </>
  );
};

export default CrmApp;
