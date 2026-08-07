import { Order, PaymentMethod, Product, WatchModel } from "./types";

/**
 * TASK-013: `o.cost` fica ausente na resposta da API pra quem não tem
 * `dashboard.financial.view` — retorna `null` (não 0) pra quem chama
 * distinguir "sem permissão pra ver" de "lucro zero de fato".
 */
export function calcProfit(o: Order): number | null {
  if (o.cost === undefined) return null;
  return o.salePrice - o.discount - o.cost - o.channelFee;
}
export function calcMargin(o: Order): number | null {
  const profit = calcProfit(o);
  if (profit === null) return null;
  const rev = o.salePrice - o.discount;
  if (!rev) return 0;
  return Number(((profit / rev) * 100).toFixed(1));
}
export function fmtBRL(v: number | string | undefined | null) {
  return (
    "R$ " +
    Number(v || 0).toLocaleString("pt-BR", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
  );
}
export function fmtDate(s?: string) {
  if (!s) return "—";
  const [y, m, d] = s.split("-");
  return `${d}/${m}/${y}`;
}

export function fmtDateTime(s?: string | null) {
  if (!s) return "—";
  const date = new Date(s);
  if (Number.isNaN(date.getTime())) return "—";
  return date.toLocaleString("pt-BR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

export function modelLabel(model: WatchModel) {
  return model.categoryHasQuality
    ? `${model.name} — ${model.qualityName ?? "—"}`
    : `${model.name} — ${model.categoryName ?? "—"}`;
}

/**
 * TASK-004 — sugere o preço do item conforme a forma de pagamento: PIX usa
 * `pricePix`, Cartão Crédito/Débito usa `priceCard`; qualquer outra forma
 * (Dinheiro, Boleto) e os casos em que o preço específico não foi cadastrado
 * caem no preço padrão (`price`) — RN-02 (preço padrão como fallback).
 */
export function suggestedUnitPrice(product: Product, paymentMethod: PaymentMethod): number {
  if (paymentMethod === "PIX" && product.pricePix != null) {
    return product.pricePix;
  }

  if (
    (paymentMethod === "Cartão Crédito" || paymentMethod === "Cartão Débito") &&
    product.priceCard != null
  ) {
    return product.priceCard;
  }

  return product.price;
}

export function productLabel(product: Product) {
  const base = `${product.brand || "—"} ${product.model || "—"}`.trim();

  if (!product.categoryHasQuality) {
    return `${base} · ${product.categoryName ?? "—"}`;
  }

  return `${base}${product.modelQualityName ? ` · ${product.modelQualityName}` : ""}`;
}
