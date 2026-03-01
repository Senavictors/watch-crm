"use client";
import React, { useEffect, useLayoutEffect, useState } from "react";
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
  const [theme, setTheme] = useState<"light" | "dark" | "system">("system");

  const apiBaseUrl = process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000/api";

  useLayoutEffect(() => {
    const stored = localStorage.getItem("crm-theme");
    if (stored === "light" || stored === "dark" || stored === "system") {
      setTheme(stored);
    }
  }, []);

  useLayoutEffect(() => {
    const root = document.documentElement;
    const media = window.matchMedia("(prefers-color-scheme: dark)");
    const resolvedTheme = theme === "system" ? (media.matches ? "dark" : "light") : theme;
    root.dataset.theme = resolvedTheme;
    localStorage.setItem("crm-theme", theme);
    if (theme !== "system") return;
    const handleChange = () => {
      root.dataset.theme = media.matches ? "dark" : "light";
    };
    if (media.addEventListener) {
      media.addEventListener("change", handleChange);
      return () => media.removeEventListener("change", handleChange);
    }
    media.addListener(handleChange);
    return () => media.removeListener(handleChange);
  }, [theme]);

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
        body { background: var(--crm-bg); color: var(--crm-text); font-family: 'Inter', sans-serif; }
        button, input, select, textarea { font-family: inherit; }
        ::-webkit-scrollbar { width: 6px; } ::-webkit-scrollbar-track { background: var(--crm-scrollbar-track); }
        ::-webkit-scrollbar-thumb { background: var(--crm-scrollbar-thumb); border-radius: 3px; }
        select option { background: var(--crm-bg); color: var(--crm-input-text); }
      `}</style>

      <div
        style={{
          position: "fixed",
          inset: 0,
          background: "var(--crm-bg)",
          zIndex: -2,
        }}
      />
      <div
        style={{
          position: "fixed",
          inset: 0,
          background: "var(--crm-bg-gradient)",
          zIndex: -2,
        }}
      />
      <div
        style={{
          position: "fixed",
          inset: 0,
          backgroundImage:
            "linear-gradient(var(--crm-grid) 1px, transparent 1px), linear-gradient(90deg, var(--crm-grid) 1px, transparent 1px)",
          backgroundSize: "64px 64px",
          opacity: 0.15,
          zIndex: -1,
        }}
      />

      <div style={{ display: "flex", minHeight: "100vh", position: "relative", zIndex: 1 }}>
        <div
          style={{
            width: 220,
            background: "var(--crm-panel-bg)",
            borderRight: "1px solid var(--crm-panel-border)",
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
                color: "var(--crm-primary)",
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
                color: "var(--crm-text)",
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
                background: page === n.id ? "var(--crm-primary-soft)" : "transparent",
                boxShadow:
                  page === n.id
                    ? "0 12px 24px rgba(15, 23, 42, 0.25)"
                    : "0 8px 16px rgba(15, 23, 42, 0.14)",
                color: page === n.id ? "var(--crm-text)" : "var(--crm-text-muted)",
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
                    background: "var(--crm-primary)",
                    color: "var(--crm-primary-contrast)",
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
            <div
              style={{
                color: "var(--crm-text-soft)",
                fontSize: 11,
                textTransform: "uppercase",
                letterSpacing: 1,
                marginBottom: 8,
              }}
            >
              Tema
            </div>
            <div
              style={{
                position: "relative",
                display: "inline-flex",
                alignItems: "center",
                height: 18,
                padding: "1px",
                borderRadius: 999,
                border: "1px solid var(--crm-button-border)",
                background: "var(--crm-button-secondary-bg)",
                gap: 2,
                marginBottom: 12,
                alignSelf: "flex-start",
              }}
            >
              <div
                style={{
                  position: "absolute",
                  top: 1,
                  left: 1,
                  height: 16,
                  width: 20,
                  borderRadius: 999,
                  background: "var(--crm-card-bg)",
                  boxShadow: "0 6px 14px rgba(15, 23, 42, 0.18)",
                  transform:
                    theme === "light"
                      ? "translateX(0px)"
                      : theme === "system"
                      ? "translateX(22px)"
                      : "translateX(44px)",
                  transition: "transform 0.2s ease",
                }}
              />
              <button
                onClick={() => setTheme("light")}
                aria-label="Light mode"
                type="button"
                style={{
                  position: "relative",
                  zIndex: 1,
                  width: 20,
                  height: 16,
                  borderRadius: 999,
                  border: "none",
                  background: "transparent",
                  color: theme === "light" ? "var(--crm-text)" : "var(--crm-text-muted)",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  cursor: "pointer",
                }}
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="12"
                  height="12"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                >
                  <circle cx="12" cy="12" r="4" />
                  <path d="M12 2v2" />
                  <path d="M12 20v2" />
                  <path d="m4.93 4.93 1.41 1.41" />
                  <path d="m17.66 17.66 1.41 1.41" />
                  <path d="M2 12h2" />
                  <path d="M20 12h2" />
                  <path d="m6.34 17.66-1.41 1.41" />
                  <path d="m19.07 4.93-1.41 1.41" />
                </svg>
              </button>
              <button
                onClick={() => setTheme("system")}
                aria-label="System theme"
                type="button"
                style={{
                  position: "relative",
                  zIndex: 1,
                  width: 20,
                  height: 16,
                  borderRadius: 999,
                  border: "none",
                  background: "transparent",
                  color: theme === "system" ? "var(--crm-text)" : "var(--crm-text-muted)",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  cursor: "pointer",
                }}
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="12"
                  height="12"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                >
                  <rect width="20" height="14" x="2" y="3" rx="2" />
                  <line x1="8" x2="16" y1="21" y2="21" />
                  <line x1="12" x2="12" y1="17" y2="21" />
                </svg>
              </button>
              <button
                onClick={() => setTheme("dark")}
                aria-label="Dark mode"
                type="button"
                style={{
                  position: "relative",
                  zIndex: 1,
                  width: 20,
                  height: 16,
                  borderRadius: 999,
                  border: "none",
                  background: "transparent",
                  color: theme === "dark" ? "var(--crm-text)" : "var(--crm-text-muted)",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  cursor: "pointer",
                }}
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="12"
                  height="12"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                >
                  <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z" />
                </svg>
              </button>
            </div>
            <div style={{ color: "var(--crm-text-soft)", fontSize: 11 }}>MVP v1.0</div>
          </div>
        </div>

        <div style={{ flex: 1, padding: "36px 32px", maxWidth: "calc(100vw - 220px)", overflowX: "auto" }}>
          {loading && <div style={{ color: "var(--crm-text-muted)" }}>Carregando dados...</div>}
          {error && !loading && <div style={{ color: "var(--crm-danger)" }}>{error}</div>}
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
