export type Channel = string;
export type Seller = string;
export type PaymentMethod = string;
export type ShippingMethod = string;
export type OrderStatus = string;
export type StockOrigin = "IN_STOCK" | "SUPPLIER";
export type ProductType = "WATCH" | "BOX";

export type Brand = {
  id: number;
  name: string;
};

export type Permission =
  | "dashboard.view"
  | "shipping.view"
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
  | "settings.view"
  | "users.manage";

export type UserRole = "admin" | "gerente" | "vendedor" | "garantia";

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
  productType: ProductType;
  qualityId: number | null;
  qualityName?: string | null;
  imageUrl?: string | null;
  qtyInStock?: number;
  qtyAtSupplier?: number;
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
  productType: ProductType;
  brand?: string;
  model?: string;
  modelQualityName?: string | null;
  cost: number;
  price: number;
  stock: StockOrigin;
  qty: number;
};

export type ProductInput = {
  brandId: number;
  modelId: number;
  cost: number;
  price: number;
  stock: StockOrigin;
  qty: number;
};

export type Order = {
  id: number;
  customerId: number;
  createdByUserId?: number | null;
  sellerUserId?: number | null;
  sellerUserName?: string | null;
  channel: Channel;
  seller: Seller;
  status: OrderStatus;
  productId?: number | null;
  productName: string;
  itemsCount: number;
  items: OrderItem[];
  salePrice: number;
  cost: number;
  discount: number;
  freight: number;
  channelFee: number;
  paymentMethod: PaymentMethod | "";
  shippingMethod: ShippingMethod;
  trackingCode: string;
  saleDate: string;
  shippedDate: string;
  notes: string;
};

export type OrderItem = {
  id?: number;
  productId: number | null;
  productName: string;
  productType: ProductType;
  brandName?: string | null;
  modelName?: string | null;
  qualityName?: string | null;
  quantity: number;
  unitPrice: number;
  unitCost: number;
  unitDiscount: number;
  linePrice: number;
  lineCost: number;
  lineDiscount: number;
};

export type OrderMetadata = {
  channels: Channel[];
  statuses: OrderStatus[];
  paymentMethods: PaymentMethod[];
  shippingMethods: ShippingMethod[];
  assignableSellers: UserOption[];
};

export type OrderInput = {
  customerId: number;
  sellerUserId: number;
  channel: Channel;
  items: OrderItemInput[];
  freight: number;
  channelFee: number;
  paymentMethod: PaymentMethod | "";
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
};

export type ReturnItemType = {
  id?: number;
  orderItemId: number | null;
  productId: number | null;
  productName: string;
  productType: ProductType;
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
  productType: ProductType;
  brandName: string | null;
  modelName: string | null;
  qualityName: string | null;
  quantity: number;
  unitPrice: number;
};
