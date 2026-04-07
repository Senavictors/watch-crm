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
import LoginView from "./views/LoginView";
import { NAV } from "./data/mock";
import { apiFetch, ensureCsrfCookie, getApiBaseUrl } from "./api";
import {
  AuthUser,
  Brand,
  Customer,
  Order,
  OrderStatus,
  Permission,
  Product,
  ProductInput,
  Quality,
  WatchModel,
} from "./types";
import Background from "./components/Background/Background";
import AppShell from "./components/AppShell/AppShell";
import Sidebar from "./components/Sidebar/Sidebar";
import Toasts from "./components/Toasts/Toasts";
import styles from "./CrmApp.module.css";

const CrmApp: React.FC = () => {
  const [sessionStatus, setSessionStatus] = useState<"unknown" | "authenticated" | "unauthenticated">("unknown");
  const [currentUser, setCurrentUser] = useState<AuthUser | null>(null);
  const [authLoading, setAuthLoading] = useState(false);
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

  const apiBaseUrl = getApiBaseUrl();

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

    async function loadSession() {
      try {
        const response = await apiFetch(`${apiBaseUrl}/me`);

        if (response.status === 401) {
          if (alive) {
            setCurrentUser(null);
            setSessionStatus("unauthenticated");
          }
          return;
        }

        if (!response.ok) {
          throw new Error("Falha ao carregar a sessão atual.");
        }

        const payload = (await response.json()) as { user: AuthUser };
        if (!alive) return;
        setCurrentUser(payload.user);
        setSessionStatus("authenticated");
      } catch (err) {
        if (!alive) return;
        setCurrentUser(null);
        setSessionStatus("unauthenticated");
        pushToast(err instanceof Error ? err.message : "Erro ao verificar sessão.", "error");
      }
    }

    loadSession();

    return () => {
      alive = false;
    };
  }, [apiBaseUrl]);

  useEffect(() => {
    if (sessionStatus !== "authenticated") {
      setLoading(false);
      return;
    }

    let alive = true;

    async function loadData() {
      try {
        setLoading(true);
        const [customersRes, productsRes, ordersRes, brandsRes, modelsRes, qualitiesRes] = await Promise.all([
          apiFetch(`${apiBaseUrl}/customers`),
          apiFetch(`${apiBaseUrl}/products`),
          apiFetch(`${apiBaseUrl}/orders`),
          apiFetch(`${apiBaseUrl}/brands`),
          apiFetch(`${apiBaseUrl}/models`),
          apiFetch(`${apiBaseUrl}/qualities`),
        ]);

        if (
          !customersRes.ok ||
          !productsRes.ok ||
          !ordersRes.ok ||
          !brandsRes.ok ||
          !modelsRes.ok ||
          !qualitiesRes.ok
        ) {
          const hasAuthError = [customersRes, productsRes, ordersRes, brandsRes, modelsRes, qualitiesRes].some(
            (response) => response.status === 401
          );

          if (hasAuthError) {
            setCurrentUser(null);
            setSessionStatus("unauthenticated");
            throw new Error("Sua sessão expirou. Entre novamente.");
          }

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

    loadData();

    return () => {
      alive = false;
    };
  }, [apiBaseUrl, sessionStatus]);

  async function handleLogin(email: string, password: string) {
    try {
      setAuthLoading(true);
      await ensureCsrfCookie(apiBaseUrl);

      const response = await apiFetch(
        `${apiBaseUrl}/login`,
        {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ email, password }),
        },
        { csrf: true }
      );

      const payload = await response.json().catch(() => ({}));

      if (!response.ok) {
        const message = payload?.errors?.email?.[0] ?? payload?.message ?? "Não foi possível autenticar.";
        throw new Error(message);
      }

      setCurrentUser(payload.user as AuthUser);
      setSessionStatus("authenticated");
      pushToast("Login realizado com sucesso.", "success");
    } finally {
      setAuthLoading(false);
    }
  }

  async function handleLogout() {
    try {
      await ensureCsrfCookie(apiBaseUrl);
      const response = await apiFetch(
        `${apiBaseUrl}/logout`,
        { method: "POST" },
        { csrf: true }
      );

      if (!response.ok) {
        throw new Error("Não foi possível encerrar a sessão.");
      }

      setCurrentUser(null);
      setSessionStatus("unauthenticated");
      setOrders([]);
      setCustomers([]);
      setProducts([]);
      setBrands([]);
      setModels([]);
      setQualities([]);
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro ao sair.", "error");
    }
  }

  function hasPermission(permission: Permission) {
    return currentUser?.permissions.includes(permission) ?? false;
  }

  async function createJson<T>(path: string, body: unknown, fallbackMessage: string): Promise<T> {
    await ensureCsrfCookie(apiBaseUrl);
    const response = await apiFetch(
      `${apiBaseUrl}${path}`,
      {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      },
      { csrf: true }
    );

    if (!response.ok) {
      let message = fallbackMessage;
      try {
        const payload = await response.json();
        if (payload?.message) message = payload.message;
        if (payload?.errors && typeof payload.errors === "object") {
          const first = Object.values(payload.errors)[0];
          if (Array.isArray(first) && first[0]) message = String(first[0]);
        }
      } catch {
        // noop
      }
      throw new Error(message);
    }

    return response.json() as Promise<T>;
  }

  function handleUpdateStatus(id: number, status: OrderStatus) {
    setOrders((os) => os.map((o) => (o.id === id ? { ...o, status } : o)));
  }

  function handleSaveOrder(data: Omit<Order, "id">) {
    const nextId = orders.length ? Math.max(...orders.map((o) => o.id)) + 1 : 1;
    setOrders((os) => [{ ...data, id: nextId }, ...os]);
    setShowNew(false);
    pushToast("Pedido criado com sucesso.", "success");
  }

  async function handleSaveProduct(data: ProductInput) {
    try {
      const created = await createJson<Product>("/products", data, "Falha ao cadastrar produto.");
      setProducts((ps) => [created, ...ps]);
      setShowNewProduct(false);
      pushToast("Produto cadastrado com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro desconhecido.", "error");
    }
  }

  async function handleSaveCustomer(data: Omit<Customer, "id">) {
    try {
      const created = await createJson<Customer>("/customers", data, "Falha ao cadastrar cliente.");
      setCustomers((cs) => [created, ...cs]);
      setShowNewCustomer(false);
      pushToast("Cliente cadastrado com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro desconhecido.", "error");
    }
  }

  async function handleSaveBrand(name: string) {
    try {
      const created = await createJson<Brand>("/brands", { name }, "Falha ao cadastrar marca.");
      setBrands((bs) => [created, ...bs]);
      pushToast("Marca cadastrada com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro desconhecido.", "error");
    }
  }

  async function handleSaveQuality(name: string) {
    try {
      const created = await createJson<Quality>("/qualities", { name }, "Falha ao cadastrar qualidade.");
      setQualities((qs) => [created, ...qs]);
      pushToast("Qualidade cadastrada com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro desconhecido.", "error");
    }
  }

  async function handleSaveModel(data: Omit<WatchModel, "id" | "imageUrl"> & { imageFile?: File | null }) {
    try {
      await ensureCsrfCookie(apiBaseUrl);
      const formData = new FormData();
      formData.append("name", data.name);
      formData.append("brandId", String(data.brandId));
      formData.append("qualityId", String(data.qualityId));
      if (data.imageFile) {
        formData.append("image", data.imageFile);
      }

      const response = await apiFetch(
        `${apiBaseUrl}/models`,
        {
          method: "POST",
          body: formData,
        },
        { csrf: true }
      );

      if (!response.ok) {
        let message = "Falha ao cadastrar modelo.";
        try {
          const payload = await response.json();
          if (payload?.message) message = payload.message;
          if (payload?.errors && typeof payload.errors === "object") {
            const first = Object.values(payload.errors)[0];
            if (Array.isArray(first) && first[0]) message = String(first[0]);
          }
        } catch {
          // noop
        }
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

  const nav = NAV.filter((item) => {
    if (item.id === "dashboard") return hasPermission("dashboard.view");
    if (item.id === "shipping") return hasPermission("shipping.view");
    if (item.id === "customers") return hasPermission("customers.view");
    if (item.id === "products") return hasPermission("products.view");
    if (item.id === "models") return hasPermission("models.view");
    if (item.id === "settings") return hasPermission("settings.view");
    return hasPermission("orders.view");
  });

  useEffect(() => {
    if (!nav.some((item) => item.id === page) && nav[0]) {
      setPage(nav[0].id);
    }
  }, [nav, page]);

  const readyCount = orders.filter((o) => o.status === "Pronto para Envio").length;

  if (sessionStatus === "unknown") {
    return (
      <>
        <Background />
        <div className={styles.centerState}>Carregando sessão...</div>
      </>
    );
  }

  if (sessionStatus === "unauthenticated" || !currentUser) {
    return (
      <>
        <Background />
        <LoginView loading={authLoading} onLogin={handleLogin} />
        <Toasts toasts={toasts} onDismiss={(id) => setToasts((ts) => ts.filter((t) => t.id !== id))} />
      </>
    );
  }

  return (
    <>
      <Background />
      <AppShell
        sidebar={
          <Sidebar
            page={page}
            onNavigate={setPage}
            nav={nav}
            readyCount={readyCount}
            theme={theme}
            onChangeTheme={setTheme}
            user={currentUser}
            onLogout={handleLogout}
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
