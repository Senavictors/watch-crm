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

export type WatchModel = {
  id: number;
  brandId: number;
  name: string;
  imageUrl?: string | null;
};

export type Customer = {
  id: number;
  name: string;
  phone: string;
  instagram: string;
  email?: string;
};

export type Product = {
  id: number;
  brandId: number;
  modelId: number;
  brand?: string;
  model?: string;
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
