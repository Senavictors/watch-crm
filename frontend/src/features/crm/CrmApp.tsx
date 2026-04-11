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
import Users from "./views/Users";
import NewUserForm from "./views/NewUserForm";
import LoginView from "./views/LoginView";
import { NAV } from "./data/mock";
import { apiFetch, ensureCsrfCookie, getApiBaseUrl } from "./api";
import {
  AuthUser,
  Brand,
  CrmUser,
  CrmUserInput,
  Customer,
  CustomerInput,
  Order,
  OrderInput,
  OrderMetadata,
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

const EMPTY_ORDER_METADATA: OrderMetadata = {
  channels: [],
  statuses: [],
  paymentMethods: [],
  shippingMethods: [],
  assignableSellers: [],
};

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
  const [orderMetadata, setOrderMetadata] = useState<OrderMetadata>(EMPTY_ORDER_METADATA);
  const [loading, setLoading] = useState(true);
  const [toasts, setToasts] = useState<
    { id: number; message: string; variant: "success" | "error" }[]
  >([]);
  const [viewOrder, setViewOrder] = useState<Order | null>(null);
  const [showNew, setShowNew] = useState(false);
  const [showNewProduct, setShowNewProduct] = useState(false);
  const [showNewCustomer, setShowNewCustomer] = useState(false);
  const [editingCustomer, setEditingCustomer] = useState<Customer | null>(null);
  const [showNewModel, setShowNewModel] = useState(false);
  const [users, setUsers] = useState<CrmUser[]>([]);
  const [showNewUser, setShowNewUser] = useState(false);
  const [editingUser, setEditingUser] = useState<CrmUser | null>(null);
  const [resetPasswordUser, setResetPasswordUser] = useState<CrmUser | null>(null);
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

  function hasPermission(permission: Permission) {
    return currentUser?.permissions.includes(permission) ?? false;
  }

  useEffect(() => {
    if (sessionStatus !== "authenticated" || !currentUser) {
      setLoading(false);
      return;
    }

    const user = currentUser;
    let alive = true;

    async function loadData() {
      try {
        setLoading(true);
        const requests: Array<{ key: string; request: Promise<Response> }> = [];

        if (user.permissions.includes("customers.view")) {
          requests.push({ key: "customers", request: apiFetch(`${apiBaseUrl}/customers`) });
        }
        if (user.permissions.includes("products.view")) {
          requests.push({ key: "products", request: apiFetch(`${apiBaseUrl}/products`) });
        }
        if (user.permissions.includes("orders.view")) {
          requests.push({ key: "orders", request: apiFetch(`${apiBaseUrl}/orders`) });
          requests.push({ key: "orderMetadata", request: apiFetch(`${apiBaseUrl}/orders/metadata`) });
        }
        if (user.permissions.includes("brands.view")) {
          requests.push({ key: "brands", request: apiFetch(`${apiBaseUrl}/brands`) });
        }
        if (user.permissions.includes("models.view")) {
          requests.push({ key: "models", request: apiFetch(`${apiBaseUrl}/models`) });
        }
        if (user.permissions.includes("qualities.view")) {
          requests.push({ key: "qualities", request: apiFetch(`${apiBaseUrl}/qualities`) });
        }
        if (user.permissions.includes("users.manage")) {
          requests.push({ key: "users", request: apiFetch(`${apiBaseUrl}/users`) });
        }

        const responses = await Promise.all(requests.map((item) => item.request));

        if (responses.some((response) => response.status === 401)) {
          setCurrentUser(null);
          setSessionStatus("unauthenticated");
          throw new Error("Sua sessão expirou. Entre novamente.");
        }

        if (responses.some((response) => !response.ok)) {
          throw new Error("Falha ao carregar dados da API.");
        }

        const payloads = await Promise.all(responses.map((response) => response.json()));
        const data = new Map<string, unknown>();
        requests.forEach((request, index) => {
          data.set(request.key, payloads[index]);
        });

        if (!alive) return;

        setCustomers((data.get("customers") as Customer[] | undefined) ?? []);
        setProducts((data.get("products") as Product[] | undefined) ?? []);
        setOrders((data.get("orders") as Order[] | undefined) ?? []);
        setBrands((data.get("brands") as Brand[] | undefined) ?? []);
        setModels((data.get("models") as WatchModel[] | undefined) ?? []);
        setQualities((data.get("qualities") as Quality[] | undefined) ?? []);
        setUsers((data.get("users") as CrmUser[] | undefined) ?? []);
        setOrderMetadata((data.get("orderMetadata") as OrderMetadata | undefined) ?? EMPTY_ORDER_METADATA);
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
  }, [apiBaseUrl, currentUser, sessionStatus]);

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
      setUsers([]);
      setOrderMetadata(EMPTY_ORDER_METADATA);
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro ao sair.", "error");
    }
  }

  async function getErrorMessage(response: Response, fallbackMessage: string) {
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

    return message;
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
      throw new Error(await getErrorMessage(response, fallbackMessage));
    }

    return response.json() as Promise<T>;
  }

  async function updateJson<T>(path: string, body: unknown, fallbackMessage: string): Promise<T> {
    await ensureCsrfCookie(apiBaseUrl);
    const response = await apiFetch(
      `${apiBaseUrl}${path}`,
      {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      },
      { csrf: true }
    );

    if (!response.ok) {
      throw new Error(await getErrorMessage(response, fallbackMessage));
    }

    return response.json() as Promise<T>;
  }

  async function handleUpdateStatus(id: number, status: OrderStatus) {
    try {
      const updated = await updateJson<Order>(`/orders/${id}`, { status }, "Falha ao atualizar status.");
      setOrders((os) => os.map((o) => (o.id === id ? updated : o)));
      setViewOrder((current) => (current?.id === id ? updated : current));
      pushToast("Status do pedido atualizado com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro desconhecido.", "error");
    }
  }

  async function handleSaveOrder(data: OrderInput) {
    try {
      const created = await createJson<Order>("/orders", data, "Falha ao cadastrar pedido.");
      setOrders((os) => [created, ...os]);
      setShowNew(false);
      pushToast("Pedido criado com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro desconhecido.", "error");
    }
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

  async function handleSaveCustomer(data: CustomerInput) {
    try {
      const created = await createJson<Customer>("/customers", data, "Falha ao cadastrar cliente.");
      setCustomers((cs) => [created, ...cs]);
      setShowNewCustomer(false);
      pushToast("Cliente cadastrado com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro desconhecido.", "error");
    }
  }

  async function handleUpdateCustomer(data: CustomerInput) {
    if (!editingCustomer) return;

    try {
      const updated = await updateJson<Customer>(
        `/customers/${editingCustomer.id}`,
        data,
        "Falha ao atualizar cliente."
      );
      setCustomers((cs) => cs.map((customer) => (customer.id === updated.id ? updated : customer)));
      setEditingCustomer(null);
      pushToast("Cliente atualizado com sucesso.", "success");
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
      formData.append("productType", data.productType);
      if (data.qualityId !== null) {
        formData.append("qualityId", String(data.qualityId));
      }
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

  async function handleSaveUser(data: CrmUserInput) {
    try {
      const created = await createJson<CrmUser>("/users", data, "Falha ao cadastrar usuário.");
      setUsers((us) => [created, ...us]);
      setShowNewUser(false);
      pushToast("Usuário cadastrado com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro desconhecido.", "error");
    }
  }

  async function handleUpdateUser(data: CrmUserInput) {
    if (!editingUser) return;
    try {
      const updated = await updateJson<CrmUser>(
        `/users/${editingUser.id}`,
        data,
        "Falha ao atualizar usuário."
      );
      setUsers((us) => us.map((u) => (u.id === updated.id ? updated : u)));
      setEditingUser(null);
      pushToast("Usuário atualizado com sucesso.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro desconhecido.", "error");
    }
  }

  async function handleToggleUserActive(user: CrmUser) {
    try {
      const updated = await updateJson<CrmUser>(
        `/users/${user.id}/active`,
        {},
        "Falha ao alterar status do usuário."
      );
      setUsers((us) => us.map((u) => (u.id === updated.id ? updated : u)));
      pushToast(updated.isActive ? "Usuário ativado." : "Usuário bloqueado.", "success");
    } catch (err) {
      pushToast(err instanceof Error ? err.message : "Erro desconhecido.", "error");
    }
  }

  async function handleResetUserPassword(data: CrmUserInput) {
    if (!resetPasswordUser) return;
    try {
      await updateJson<{ ok: boolean }>(
        `/users/${resetPasswordUser.id}/password`,
        { password: data.password },
        "Falha ao redefinir senha."
      );
      setResetPasswordUser(null);
      pushToast("Senha redefinida com sucesso.", "success");
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
    if (item.id === "users") return hasPermission("users.manage");
    return hasPermission("orders.view");
  });

  useEffect(() => {
    if (!nav.some((item) => item.id === page) && nav[0]) {
      setPage(nav[0].id);
    }
  }, [nav, page]);

  const readyCount = orders.filter((o) => o.status === "Pronto para Envio").length;
  const canCreateOrders = hasPermission("orders.create");
  const canUpdateOrders = hasPermission("orders.update");
  const canCreateCustomers = hasPermission("customers.create");
  const canUpdateCustomers = hasPermission("customers.update");
  const canCreateProducts = hasPermission("products.create");
  const canCreateModels = hasPermission("models.create");
  const canManageCatalog = hasPermission("settings.view");
  const canManageUsers = hasPermission("users.manage");
  const dashboardChannels = orderMetadata.channels.length
    ? orderMetadata.channels
    : Array.from(new Set(orders.map((order) => order.channel)));
  const dashboardStatuses = orderMetadata.statuses.length
    ? orderMetadata.statuses
    : Array.from(new Set(orders.map((order) => order.status)));
  const orderSellers = Array.from(
    new Set(
      [
        ...orderMetadata.assignableSellers.map((seller) => seller.name),
        ...orders.map((order) => order.seller).filter(Boolean),
      ].filter(Boolean)
    )
  );

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
            {page === "dashboard" && <Dashboard orders={orders} channels={dashboardChannels} statuses={dashboardStatuses} />}
            {page === "orders" && (
              <OrderList
                orders={orders}
                customers={customers}
                channels={orderMetadata.channels}
                sellers={orderSellers}
                statuses={orderMetadata.statuses}
                canCreate={canCreateOrders}
                canUpdateStatus={canUpdateOrders}
                onView={(o) => setViewOrder(o)}
                onNew={() => setShowNew(true)}
                onUpdateStatus={handleUpdateStatus}
              />
            )}
            {page === "shipping" && <ShippingQueue orders={orders} customers={customers} />}
            {page === "customers" && (
              <Customers
                customers={customers}
                canCreate={canCreateCustomers}
                canUpdate={canUpdateCustomers}
                onNew={() => setShowNewCustomer(true)}
                onEdit={setEditingCustomer}
              />
            )}
            {page === "products" && (
              <Products
                products={products}
                canCreate={canCreateProducts}
                compact={!canManageCatalog}
                onNew={() => setShowNewProduct(true)}
              />
            )}
            {page === "models" && (
              <Models models={models} canCreate={canCreateModels} onNew={() => setShowNewModel(true)} />
            )}
            {page === "settings" && (
              <Settings
                brands={brands}
                qualities={qualities}
                onAddBrand={handleSaveBrand}
                onAddQuality={handleSaveQuality}
                onToast={pushToast}
              />
            )}
            {page === "users" && (
              <Users
                users={users}
                currentUserRole={currentUser.role}
                canCreate={canManageUsers}
                onNew={() => setShowNewUser(true)}
                onEdit={setEditingUser}
                onToggleActive={handleToggleUserActive}
                onResetPassword={setResetPasswordUser}
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
          metadata={orderMetadata}
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
          customer={null}
          onSave={handleSaveCustomer}
          onClose={() => setShowNewCustomer(false)}
          onToast={pushToast}
        />
      )}
      {editingCustomer && (
        <NewCustomerForm
          customer={editingCustomer}
          onSave={handleUpdateCustomer}
          onClose={() => setEditingCustomer(null)}
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
      {showNewUser && (
        <NewUserForm
          user={null}
          currentUserRole={currentUser.role}
          onSave={handleSaveUser}
          onClose={() => setShowNewUser(false)}
          onToast={pushToast}
        />
      )}
      {editingUser && (
        <NewUserForm
          user={editingUser}
          currentUserRole={currentUser.role}
          onSave={handleUpdateUser}
          onClose={() => setEditingUser(null)}
          onToast={pushToast}
        />
      )}
      {resetPasswordUser && (
        <NewUserForm
          user={resetPasswordUser}
          resetPasswordMode={true}
          currentUserRole={currentUser.role}
          onSave={handleResetUserPassword}
          onClose={() => setResetPasswordUser(null)}
          onToast={pushToast}
        />
      )}
    </>
  );
};

export default CrmApp;
