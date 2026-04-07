export type Channel = "Instagram" | "Site" | "Mercado Livre" | "WhatsApp";
export type Seller = "Ana Karolina" | "Josue" | "Amanda" | "Victor";
export type PaymentMethod =
  | "PIX"
  | "Cartão Crédito"
  | "Cartão Débito"
  | "Dinheiro"
  | "Boleto";
export type ShippingMethod = "Sedex" | "Jadlog" | "Correios PAC" | "Retirada";
export type OrderStatus =
  | "Novo"
  | "Aguardando Pagamento"
  | "Pago"
  | "Separação/Fornecedor"
  | "Pronto para Envio"
  | "Enviado"
  | "Entregue"
  | "Cancelado";
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

export type Quality = {
  id: number;
  name: string;
};

export type WatchModel = {
  id: number;
  brandId: number;
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
