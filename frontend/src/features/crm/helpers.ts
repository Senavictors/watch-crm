import { Order, Product, ProductType, WatchModel } from "./types";

export function calcProfit(o: Order) {
  return o.salePrice - o.discount - o.cost - o.channelFee;
}
export function calcMargin(o: Order) {
  const rev = o.salePrice - o.discount;
  if (!rev) return 0;
  return Number(((calcProfit(o) / rev) * 100).toFixed(1));
}
export function nextShippingDay(dateStr?: string) {
  const d = dateStr ? new Date(dateStr) : new Date();
  const day = d.getDay();
  let add = 0;
  if (day === 0) add = 1;
  else if (day === 1) add = 0;
  else if (day === 2) add = 1;
  else if (day === 3) add = 0;
  else if (day === 4) add = 1;
  else if (day === 5) add = 0;
  else if (day === 6) add = 2;
  const nd = new Date(d);
  nd.setDate(nd.getDate() + add);
  return nd.toLocaleDateString("pt-BR");
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

export function productTypeLabel(type?: ProductType | null) {
  return type === "BOX" ? "Caixa" : "Relógio";
}

export function modelLabel(model: WatchModel) {
  return model.productType === "BOX"
    ? `${model.name} — ${productTypeLabel(model.productType)}`
    : `${model.name} — ${model.qualityName ?? "—"}`;
}

export function productLabel(product: Product) {
  const base = `${product.brand || "—"} ${product.model || "—"}`.trim();

  if (product.productType === "BOX") {
    return `${base} · ${productTypeLabel(product.productType)}`;
  }

  return `${base}${product.modelQualityName ? ` · ${product.modelQualityName}` : ""}`;
}
