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
import Models from "./views/Models";
import NewModelForm from "./views/NewModelForm";
import Settings from "./views/Settings";
import { NAV } from "./data/mock";
import { Brand, Customer, Order, OrderStatus, Product, ProductInput, Quality, WatchModel } from "./types";
import Background from "./components/Background/Background";
import AppShell from "./components/AppShell/AppShell";
import Sidebar from "./components/Sidebar/Sidebar";
import Toasts from "./components/Toasts/Toasts";
import styles from "./CrmApp.module.css";

const CrmApp: React.FC = () => {
  const [page, setPage] = useState("dashboard");
  const [orders, setOrders] = useState<Order[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [brands, setBrands] = useState<Brand[]>([]);
  const [models, setModels] = useState<WatchModel[]>([]);
  const [qualities, setQualities] = useState<Quality[]>([]);
  const [loading, setLoading] = useState(true);
  const [toasts, setToasts] = useState<
    { id: number; message: string; variant: "success" | "error" }[]
  >([]);
  const [viewOrder, setViewOrder] = useState<Order | null>(null);
  const [showNew, setShowNew] = useState(false);
  const [showNewProduct, setShowNewProduct] = useState(false);
  const [showNewCustomer, setShowNewCustomer] = useState(false);
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

  function pushToast(message: string, variant: "success" | "error" = "success") {
    const id = Date.now() + Math.floor(Math.random() * 1000);
    setToasts((ts) => [...ts, { id, message, variant }]);
    window.setTimeout(() => {
      setToasts((ts) => ts.filter((t) => t.id !== id));
    }, 4200);
  }

  useEffect(() => {
    let alive = true;
    async function load() {
      try {
        setLoading(true);
        const [customersRes, productsRes, ordersRes, brandsRes, modelsRes, qualitiesRes] = await Promise.all([
          fetch(`${apiBaseUrl}/customers`),
          fetch(`${apiBaseUrl}/products`),
          fetch(`${apiBaseUrl}/orders`),
          fetch(`${apiBaseUrl}/brands`),
          fetch(`${apiBaseUrl}/models`),
          fetch(`${apiBaseUrl}/qualities`),
        ]);
        if (
          !customersRes.ok ||
          !productsRes.ok ||
          !ordersRes.ok ||
          !brandsRes.ok ||
          !modelsRes.ok ||
          !qualitiesRes.ok
        ) {
          throw new Error("Falha ao carregar dados da API.");
        }
        const [customersData, productsData, ordersData, brandsData, modelsData, qualitiesData] = await Promise.all([
          customersRes.json(),
          productsRes.json(),
          ordersRes.json(),
          brandsRes.json(),
          modelsRes.json(),
          qualitiesRes.json(),
        ]);
        if (!alive) return;
        setCustomers(customersData);
        setProducts(productsData);
        setOrders(ordersData);
        setBrands(brandsData);
        setModels(modelsData);
        setQualities(qualitiesData);
      } catch (err) {
        if (!alive) return;
        pushToast(err instanceof Error ? err.message : "Erro desconhecido.", "error");
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
    pushToast("Pedido criado com sucesso.", "success");
  }

  async function handleSaveProduct(data: ProductInput) {
    try {
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
      pushToast("Produto cadastrado com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro desconhecido.", "error");
    }
  }

  async function handleSaveCustomer(data: Omit<Customer, "id">) {
    try {
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
      pushToast("Cliente cadastrado com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro desconhecido.", "error");
    }
  }

  async function handleSaveBrand(name: string) {
    try {
      const response = await fetch(`${apiBaseUrl}/brands`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name }),
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
      pushToast("Marca cadastrada com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro desconhecido.", "error");
    }
  }

  async function handleSaveQuality(name: string) {
    try {
      const response = await fetch(`${apiBaseUrl}/qualities`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name }),
      });
      if (!response.ok) {
        let message = "Falha ao cadastrar qualidade.";
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
      const created = (await response.json()) as Quality;
      setQualities((qs) => [created, ...qs]);
      pushToast("Qualidade cadastrada com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro desconhecido.", "error");
    }
  }

  async function handleSaveModel(data: Omit<WatchModel, "id" | "imageUrl"> & { imageFile?: File | null }) {
    try {
      const formData = new FormData();
      formData.append("name", data.name);
      formData.append("brandId", String(data.brandId));
      formData.append("qualityId", String(data.qualityId));
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
      pushToast("Modelo cadastrado com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro desconhecido.", "error");
    }
  }

  const readyCount = orders.filter((o) => o.status === "Pronto para Envio").length;

  return (
    <>
      <Background />
      <AppShell
        sidebar={
          <Sidebar
            page={page}
            onNavigate={setPage}
            nav={NAV}
            readyCount={readyCount}
            theme={theme}
            onChangeTheme={setTheme}
          />
        }
      >
        {loading && <div className={styles.loading}>Carregando dados...</div>}
        {!loading && (
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
            {page === "models" && <Models models={models} brands={brands} onNew={() => setShowNewModel(true)} />}
            {page === "settings" && (
              <Settings
                brands={brands}
                qualities={qualities}
                onAddBrand={handleSaveBrand}
                onAddQuality={handleSaveQuality}
                onToast={pushToast}
              />
            )}
          </>
        )}
      </AppShell>

      <Toasts toasts={toasts} onDismiss={(id) => setToasts((ts) => ts.filter((t) => t.id !== id))} />

      {viewOrder && (
        <OrderDetail order={viewOrder} customers={customers} onClose={() => setViewOrder(null)} />
      )}
      {showNew && (
        <NewOrderForm
          products={products}
          customers={customers}
          onSave={handleSaveOrder}
          onClose={() => setShowNew(false)}
          onToast={pushToast}
        />
      )}
      {showNewProduct && (
        <NewProductForm
          brands={brands}
          models={models}
          onSave={handleSaveProduct}
          onClose={() => setShowNewProduct(false)}
          onToast={pushToast}
        />
      )}
      {showNewCustomer && (
        <NewCustomerForm
          onSave={handleSaveCustomer}
          onClose={() => setShowNewCustomer(false)}
          onToast={pushToast}
        />
      )}
      {showNewModel && (
        <NewModelForm
          brands={brands}
          qualities={qualities}
          onSave={handleSaveModel}
          onClose={() => setShowNewModel(false)}
          onToast={pushToast}
        />
      )}
    </>
  );
};

export default CrmApp;
