export type Channel = string;
export type Seller = string;
export type PaymentMethod = string;
export type ShippingMethod = string;
export type OrderStatus = string;
export type StockOrigin = "IN_STOCK" | "SUPPLIER";

export type Brand = {
  id: number;
  name: string;
};

export type Category = {
  id: number;
  name: string;
  hasQuality: boolean;
};

export type Permission =
  | "dashboard.view"
  | "shipping.view"
  | "shipping.update"
  | "customers.view"
  | "customers.create"
  | "customers.update"
  | "customers.delete"
  | "products.view"
  | "products.create"
  | "products.update"
  | "products.delete"
  | "brands.view"
  | "brands.create"
  | "brands.update"
  | "brands.delete"
  | "qualities.view"
  | "qualities.create"
  | "qualities.update"
  | "qualities.delete"
  | "categories.view"
  | "categories.create"
  | "categories.update"
  | "categories.delete"
  | "models.view"
  | "models.create"
  | "models.update"
  | "models.delete"
  | "orders.view"
  | "orders.create"
  | "orders.update"
  | "orders.delete"
  | "returns.view"
  | "returns.create"
  | "returns.update"
  | "returns.delete"
  | "goals.view"
  | "goals.create"
  | "goals.update"
  | "goals.delete"
  | "settings.view"
  | "users.manage"
  | "dashboard.financial.view"
  | "ai.summary.generate"
  | "ai.settings.view"
  | "ai.settings.update"
  | "commissions.view"
  | "commissions.pay"
  | "expenses.view"
  | "expenses.create"
  | "expenses.update"
  | "expenses.delete"
  | "waitlist.view"
  | "waitlist.create"
  | "waitlist.update"
  | "waitlist.delete";

export type UserRole = "owner" | "admin" | "gerente" | "vendedor" | "garantia";

export type AuthUser = {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  permissions: Permission[];
  isActive: boolean;
  lastLoginAt?: string | null;
  twoFactorEnabled: boolean;
};

export type UserOption = {
  id: number;
  name: string;
};

export type PaginationMeta = {
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
  from: number | null;
  to: number | null;
};

export type PaginatedResponse<T> = {
  data: T[];
  meta: PaginationMeta;
};

export type LookupResponse<T> = {
  data: T[];
};

export type CrmUser = {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  isActive: boolean;
  lastLoginAt?: string | null;
};

export type CrmUserInput = {
  name: string;
  email: string;
  password?: string;
  role: UserRole;
};

export type Quality = {
  id: number;
  name: string;
};

export type WatchModel = {
  id: number;
  brandId: number;
  brandName?: string | null;
  name: string;
  categoryId: number;
  categoryName?: string | null;
  categoryHasQuality: boolean;
  qualityId: number | null;
  qualityName?: string | null;
  imageUrl?: string | null;
  qtyInStock?: number;
  qtyAtSupplier?: number;
};

/**
 * TASK-019 — insights de recompra (`GET /customers/{id}`, só no `show`, não
 * no `index`). `averageTicket`/`lastOrderAt` podem ser `null` (sem compra
 * paga ainda). `possibleRepurchase` é `bool|null` — `null` significa "dados
 * insuficientes" (menos de 2 compras pagas), NUNCA tratar como `false`
 * (CA-03): a UI precisa distinguir os 3 estados, nunca linguagem de certeza.
 */
export type CustomerInsights = {
  totalSpent: number;
  orderCount: number;
  averageTicket: number | null;
  lastOrderAt: string | null;
  possibleRepurchase: boolean | null;
};

/**
 * TASK-019 — histórico de atrito (`POST /customers/{id}/friction-notes`,
 * permissão `customers.update`). Imutável por design: sem endpoint de
 * edição/exclusão.
 */
export type CustomerFrictionNote = {
  id: number;
  note: string;
  createdByUserId: number | null;
  createdByUserName: string | null;
  createdAt: string;
};

export type Customer = {
  id: number;
  name: string;
  phone: string;
  instagram?: string | null;
  email?: string | null;
  street?: string | null;
  number?: string | null;
  complement?: string | null;
  zipCode?: string | null;
  city?: string | null;
  state?: string | null;
  ownerUserId?: number | null;
  // Presentes só na resposta do `show` (`GET /customers/{id}`), ausentes no
  // `index` (`GET /customers`).
  insights?: CustomerInsights;
  frictionNotes?: CustomerFrictionNote[];
  hasFrictionHistory?: boolean;
};

export type CustomerInput = {
  name: string;
  phone: string;
  instagram?: string | null;
  email?: string | null;
  street?: string | null;
  number?: string | null;
  complement?: string | null;
  zipCode?: string | null;
  city?: string | null;
  state?: string | null;
};

export type Product = {
  id: number;
  brandId: number;
  modelId: number;
  categoryName?: string | null;
  categoryHasQuality: boolean;
  brand?: string;
  model?: string;
  modelQualityName?: string | null;
  // TASK-013: ausente na resposta da API pra quem não tem
  // dashboard.financial.view / products.update — não confundir com "custo
  // zero", trate como "não disponível pra este usuário".
  cost?: number;
  price: number;
  pricePix?: number | null;
  priceCard?: number | null;
  // TASK-005: sempre presente pra quem tem products.view (inclui vendedor) —
  // não é dado de custo/margem, é quanto o vendedor ganha por unidade.
  commissionAmount?: number | null;
  stock: StockOrigin;
  qty: number;
  // TASK-024: `qty` é o saldo físico; `reservedQty` é a parte dele já
  // prometida a pedidos em aberto e `availableQty` (= qty - reservedQty) é o
  // que ainda pode ser vendido. Opcionais porque `data/mock.ts` e respostas
  // antigas em cache podem não trazê-los.
  reservedQty?: number;
  availableQty?: number;
};

export type ProductInput = {
  brandId: number;
  modelId: number;
  cost: number;
  price: number;
  pricePix?: number | null;
  priceCard?: number | null;
  commissionAmount?: number | null;
  stock: StockOrigin;
  qty: number;
};

export type Order = {
  id: number;
  customerId: number;
  customerName?: string | null;
  createdByUserId?: number | null;
  sellerUserId?: number | null;
  sellerUserName?: string | null;
  channel: Channel;
  seller: Seller;
  status: OrderStatus;
  paidAt?: string | null;
  paidByUserId?: number | null;
  paidByUserName?: string | null;
  productId?: number | null;
  productName: string;
  itemsCount: number;
  items: OrderItem[];
  salePrice: number;
  // TASK-013: ausente pra quem não tem dashboard.financial.view (gerente,
  // vendedor, garantia) — não é "custo zero", é "não disponível".
  cost?: number;
  discount: number;
  freight: number;
  channelFee: number;
  paymentMethod: PaymentMethod | "";
  paymentExpiresAt?: string | null;
  shippingMethod: ShippingMethod;
  trackingCode: string;
  saleDate: string;
  shippedDate: string;
  notes: string;
  // TASK-016: calculado no backend a partir do schedule de dias de postagem
  // (`GET /shipping/schedule`) — `null` quando o pedido não é elegível pra
  // fila de envios (ex.: ainda não está "Pronto para Envio").
  nextPostingDate: string | null;
  isLate: boolean;
};

export type OrderItem = {
  id?: number;
  productId: number | null;
  productName: string;
  productType: string;
  brandName?: string | null;
  modelName?: string | null;
  qualityName?: string | null;
  quantity: number;
  unitPrice: number;
  unitCost?: number;
  unitDiscount: number;
  linePrice: number;
  lineCost?: number;
  lineDiscount: number;
};

export type OrderMetadata = {
  channels: Channel[];
  statuses: OrderStatus[];
  paymentMethods: PaymentMethod[];
  shippingMethods: ShippingMethod[];
  assignableSellers: UserOption[];
  categories: string[];
};

export type OrderInput = {
  customerId: number;
  sellerUserId: number;
  channel: Channel;
  items: OrderItemInput[];
  freight: number;
  channelFee: number;
  paymentMethod: PaymentMethod | "";
  paymentExpiresAt?: string | null;
  shippingMethod: ShippingMethod;
  trackingCode: string;
  saleDate: string;
  shippedDate: string;
  notes: string;
  status?: OrderStatus;
};

export type OrderItemInput = {
  productId: number;
  quantity: number;
  unitPrice: number;
  unitDiscount: number;
};

/**
 * TASK-016 — configuração de dias de postagem (`GET/PUT /shipping/schedule`)
 * e fila de envios (`GET /shipping/queue`). `weekday` segue a convenção do
 * `Date.getDay()` do JS (0 = domingo ... 6 = sábado).
 */
export type PostingDaySchedule = {
  weekday: number;
  label: string;
  enabled: boolean;
};

export type ShippingQueueItem = {
  id: number;
  customerName: string;
  productName: string;
  itemsCount: number;
  channel: Channel;
  shippingMethod: ShippingMethod;
  freight: number;
  saleDate: string;
  paidAt: string | null;
  nextPostingDate: string | null;
  isLate: boolean;
};

export type GoalScope = "company" | "user";
export type GoalCalculationType = "total_value" | "quantity";
export type GoalPeriodCycle = "monthly" | "quarterly" | "semiannual" | "annual";
export type GoalStatus = "active" | "archived";

export type GoalInterval = {
  id: number;
  startDate: string;
  endDate: string;
  targetValue: number;
  currentValue: number;
  percentage: number;
};

export type Goal = {
  id: number;
  createdByUserId: number;
  createdByUserName: string | null;
  targetUserId: number | null;
  targetUserName: string | null;
  name: string;
  description: string | null;
  scope: GoalScope;
  calculationType: GoalCalculationType;
  productTypeFilter: string | null;
  brandId: number | null;
  brandName: string | null;
  modelId: number | null;
  modelName: string | null;
  periodCycle: GoalPeriodCycle;
  startDate: string;
  endDate: string;
  status: GoalStatus;
  totalTarget: number;
  totalCurrent: number;
  totalPercentage: number;
  intervals: GoalInterval[];
  createdAt: string;
};

export type GoalIntervalInput = {
  startDate: string;
  endDate: string;
  targetValue: number;
};

export type GoalInput = {
  name: string;
  description: string | null;
  scope: GoalScope;
  targetUserId: number | null;
  calculationType: GoalCalculationType;
  productTypeFilter: string | null;
  brandId: number | null;
  modelId: number | null;
  periodCycle: GoalPeriodCycle;
  startDate: string;
  endDate: string;
  status?: GoalStatus;
  intervals: GoalIntervalInput[];
};

export type GoalMetadata = {
  sellers: UserOption[];
  brands: { id: number; name: string }[];
  models: { id: number; name: string; brandId: number; productType: string }[];
  scopes: { value: string; label: string }[];
  calculationTypes: { value: string; label: string }[];
  productTypeFilters: { value: string; label: string }[];
  periodCycles: { value: string; label: string }[];
};

export type ReturnType = "garantia" | "troca" | "devolucao";
export type ReturnStatus = string;

export type ProductReturn = {
  id: number;
  orderId: number | null;
  customerId: number;
  customerName: string;
  customerPhone: string;
  createdByUserId: number | null;
  assignedUserId: number | null;
  assignedUserName: string | null;
  type: ReturnType;
  typeLabel: string;
  status: ReturnStatus;
  reason: string;
  internalNotes: string;
  resolutionNotes: string;
  receivedDate: string;
  resolvedDate: string;
  freightCostIn: number;
  watchmakerCost: number;
  freightCostOut: number;
  otherCosts: number;
  totalCost: number;
  refundAmount: number | null;
  returnTrackingCode: string;
  shippedBackDate: string;
  items: ReturnItemType[];
  createdAt: string;
  // TASK-017 — timeline de transições de status (ordenada cronologicamente,
  // `fromStatus: null` no primeiro item, criado junto com a devolução).
  statusHistory: ReturnStatusHistoryEntry[];
  // TASK-017 — `null` quando não calculável (sem pedido vinculado, ou pedido
  // sem data de venda); não é um terceiro estado a exibir, é "não há dado".
  withinWarrantyWindow: boolean | null;
};

export type ReturnStatusHistoryEntry = {
  fromStatus: string | null;
  toStatus: string;
  actorUserId: number | null;
  actorUserName: string | null;
  notes: string | null;
  createdAt: string;
};

export type ReturnItemType = {
  id?: number;
  orderItemId: number | null;
  productId: number | null;
  productName: string;
  productType: string;
  brandName: string | null;
  modelName: string | null;
  qualityName: string | null;
  quantity: number;
  unitPrice: number;
};

export type ReturnMetadata = {
  types: ReturnType[];
  typeLabels: Record<ReturnType, string>;
  statuses: ReturnStatus[];
  // TASK-017 — `transitions[status]` é a lista de status que são destino
  // válido a partir dele; lista vazia = status terminal (fim do fluxo).
  transitions: Record<string, string[]>;
  assignableUsers: UserOption[];
};

export type ReturnInput = {
  orderId: number | null;
  customerId: number;
  assignedUserId: number | null;
  type: ReturnType;
  status?: ReturnStatus;
  reason: string;
  internalNotes: string;
  resolutionNotes: string;
  receivedDate: string;
  resolvedDate: string;
  freightCostIn: number;
  watchmakerCost: number;
  freightCostOut: number;
  otherCosts: number;
  refundAmount: number | null;
  returnTrackingCode: string;
  shippedBackDate: string;
  items: ReturnItemInput[];
};

export type ReturnItemInput = {
  orderItemId: number | null;
  productId: number | null;
  productName: string;
  productType: string;
  brandName: string | null;
  modelName: string | null;
  qualityName: string | null;
  quantity: number;
  unitPrice: number;
};

/**
 * TASK-005 — comissões por produto e venda. `CommissionLine` é uma linha do
 * relatório (`GET /commissions`); RN-02 (vendedor só vê a própria projeção)
 * é aplicada no backend, não aqui.
 */
export type CommissionLine = {
  orderItemId: number;
  orderId: number | null;
  sellerUserId: number | null;
  sellerUserName: string | null;
  productName: string;
  saleDate: string | null;
  quantity: number;
  returnedQuantity: number;
  netQuantity: number;
  unitCommission: number;
  lineCommission: number;
  paid: boolean;
  commissionPaidAt: string | null;
  commissionPaidByUserName: string | null;
};

export type CommissionSummary = {
  accrued: number;
  paid: number;
  pending: number;
};

export type CommissionReport = {
  summary: CommissionSummary;
  data: CommissionLine[];
  meta: PaginationMeta;
  // Presente só pra quem tem visão de todos os vendedores (owner/admin).
  sellers?: UserOption[];
};

/**
 * TASK-006 — módulo de despesas gerais. Categoria é uma lista fechada
 * (`GET /expenses/metadata`), não um cadastro livre (RN-02).
 */
export type ExpenseCategory = string;

export type Expense = {
  id: number;
  category: ExpenseCategory;
  description: string;
  amount: number;
  expenseDate: string;
  createdByUserId: number | null;
  createdByUserName: string | null;
  createdAt: string;
};

export type ExpenseInput = {
  category: ExpenseCategory;
  description: string;
  amount: number;
  expenseDate: string;
};

export type ExpenseMetadata = {
  categories: ExpenseCategory[];
};

export type ExpenseListResponse = PaginatedResponse<Expense> & {
  summary: {
    totalAmount: number;
    totalCount: number;
  };
};

/**
 * TASK-009 — contrato de `GET /dashboard/summary` (docs/api/dashboard.md).
 * Campos opcionais são gateados por permissão no backend (CA-04) —
 * ausentes na resposta pra quem não pode ver, mesmo padrão de `cost` em
 * `Order`/`Product` (TASK-013). Consumido pelas TASK-011/012.
 */
export type DashboardKpi = {
  value: number;
  previousValue: number;
  percentageChange: number | null;
};

export type DashboardCurrentKpi = {
  value: number;
};

export type DashboardEvolutionBucket = {
  bucket: string;
  // Ausente quando o usuário não tem visibilidade de faturamento (ex.:
  // gerente sem `dashboard.financial.view`) — ver
  // `DashboardController::toPayload` (`$canViewRevenue`).
  revenue?: number;
  salesProfit?: number;
  watchesSold: number;
  ordersCount: number;
};

export type DashboardCategoryBreakdown = {
  category: string;
  revenue: number;
  units: number;
};

export type DashboardChannelBreakdown = {
  channel: string;
  revenue: number;
  ordersCount: number;
};

export type DashboardGoalSummary = {
  id: number;
  name: string;
  calculationType: "total_value" | "quantity";
  productTypeFilter: "WATCH" | "BOX" | null;
  totalTarget: number;
  totalCurrent: number;
  totalPercentage: number;
};

export type DashboardNextShipment = {
  orderId: number;
  customerName: string | null;
  status: OrderStatus;
  shippingMethod: ShippingMethod;
  saleDate: string;
};

export type DashboardCommissionSummary = CommissionSummary;

export type DashboardStock = {
  totalCost: number;
  totalPotentialRevenue: number;
  potentialProfit: number;
  totalUnits: number;
};

export type DashboardConversionRate = {
  value: number | null;
  previousValue: number | null;
  percentagePointChange: number | null;
};

export type DashboardConversionBreakdown = {
  ordersCreated: number;
  paidOrders: number;
  rate: number | null;
};

export type DashboardConversion = {
  current: DashboardConversionBreakdown & {
    channels: Array<DashboardConversionBreakdown & { channel: string }>;
  };
  previous: DashboardConversionBreakdown & {
    channels: Array<DashboardConversionBreakdown & { channel: string }>;
  };
  percentagePointChange: number | null;
};

export type DashboardPendingPayments = {
  count: number;
  amount: number;
  averageWaitHours: number;
  oldestWaitHours: number;
  expiredCount: number;
  expiringSoonCount: number;
  expirationWindowHours: number;
};

export type OperationalAlert = {
  type: string;
  severity: "critical" | "warning" | "success";
  title: string;
  message: string;
  value?: number;
  unit?: string;
  action?: { label: string; href: string };
};

export type DashboardSummaryResponse = {
  period: { from: string; to: string; grouping: "day" | "week" | "month" };
  comparison: { from: string; to: string };
  kpis: {
    watchesSold: DashboardKpi;
    ordersCount: DashboardKpi;
    conversionRate: DashboardConversionRate;
    activeOrders: DashboardCurrentKpi;
    pendingAmount: DashboardCurrentKpi;
    revenue?: DashboardKpi;
    salesProfit?: DashboardKpi;
    netResult?: DashboardKpi;
    generalExpenses?: DashboardKpi;
  };
  evolution: DashboardEvolutionBucket[];
  goal: {
    company: DashboardGoalSummary | null;
    individual: DashboardGoalSummary | null;
  };
  conversion: DashboardConversion;
  pendingPayments: DashboardPendingPayments;
  operationalAlerts: OperationalAlert[];
  nextShipments: DashboardNextShipment[];
  categories?: DashboardCategoryBreakdown[];
  channels?: DashboardChannelBreakdown[];
  commission?: DashboardCommissionSummary;
  stock?: DashboardStock;
};

export type AiSummarySource = {
  label: string;
  value: string;
};

export type AiSummaryItem = {
  id: string;
  context: string;
  type: "currency" | "quantity" | "percentage" | "count";
  values: Record<string, unknown>;
  text: string;
  sources: AiSummarySource[];
};

export type AiSummaryResponse = {
  items: AiSummaryItem[];
  period: { from: string; to: string };
  generatedAt: string;
  model: string;
  cached: boolean;
};

export type AiSettingsResponse = {
  provider: "openai";
  model: string;
  projectId: string | null;
  enabled: boolean;
  featureEnabled: boolean;
  configured: boolean;
  apiKeySource: "database" | "environment" | "none";
};

/**
 * TASK-018 — lista de espera por produto (`GET/POST/PUT/PATCH/DELETE
 * /waitlist`). RN-02: `index` é escopado por ownership no backend — um
 * vendedor sempre recebe só as próprias entradas, mesmo enviando
 * `sellerUserId` de outra pessoa no filtro. RN-03: `productCurrentQty` /
 * `isAvailable` são calculados no momento da consulta — só leitura, nunca
 * recalculado no frontend.
 */
export type WaitlistStatus = "Pendente" | "Avisado" | "Convertido" | "Encerrado";

export type WaitlistEntry = {
  id: number;
  customerId: number;
  customerName: string;
  customerPhone: string;
  productId: number | null;
  productName: string;
  brandName: string | null;
  modelName: string | null;
  qualityName: string | null;
  sellerUserId: number | null;
  sellerUserName: string | null;
  createdByUserId: number | null;
  createdByUserName: string | null;
  status: WaitlistStatus;
  notes: string;
  notifiedAt: string | null;
  orderId: number | null;
  productCurrentQty: number | null;
  isAvailable: boolean;
  createdAt: string;
  updatedAt: string;
};

export type WaitlistMetadata = {
  statuses: WaitlistStatus[];
  assignableUsers: UserOption[];
};

export type WaitlistInput = {
  customerId: number;
  productId?: number | null;
  productName: string;
  brandName?: string | null;
  modelName?: string | null;
  qualityName?: string | null;
  sellerUserId?: number | null;
  status?: WaitlistStatus;
  notes?: string;
  notifiedAt?: string | null;
  orderId?: number | null;
};
