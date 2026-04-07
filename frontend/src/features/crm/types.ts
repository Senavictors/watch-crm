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
  | "settings.view"
  | "users.manage";

export type AuthUser = {
  id: number;
  name: string;
  email: string;
  role: "admin" | "gerente" | "vendedor";
  permissions: Permission[];
  isActive: boolean;
  lastLoginAt?: string | null;
  twoFactorEnabled: boolean;
};

export type UserOption = {
  id: number;
  name: string;
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
  qualityId: number;
  qualityName?: string | null;
  imageUrl?: string | null;
};

export type Customer = {
  id: number;
  name: string;
  phone: string;
  instagram: string;
  email?: string;
  ownerUserId?: number | null;
};

export type Product = {
  id: number;
  brandId: number;
  modelId: number;
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
  productId: number;
  productName: string;
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
  productId: number;
  salePrice: number;
  discount: number;
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
