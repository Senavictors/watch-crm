<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnItem;
use Illuminate\Validation\ValidationException;

/**
 * TASK-028 — integridade da árvore devolução → pedido → item → produto.
 *
 * Antes desta task o backend validava apenas que cada ID existia
 * isoladamente e gravava nome, categoria e preço vindos do request. Dava
 * para relacionar uma devolução ao cliente errado, usar o item de outro
 * pedido, devolver mais unidades do que foram vendidas e adulterar os
 * snapshots que alimentam faturamento, comissão e meta (achado 5).
 *
 * Aqui a regra é uma só: **o request diz QUAL item e QUANTAS unidades; o
 * servidor diz todo o resto**. Nome, categoria, marca, modelo, qualidade e
 * preço saem sempre de `OrderItem` (devolução vinculada a pedido) ou de
 * `Product` (devolução avulsa) — nunca do corpo da requisição.
 *
 * Sempre chamado dentro da transação de escrita da devolução: os
 * `lockForUpdate()` do cálculo de saldo só valem até o commit dela (RN-05).
 */
class ReturnItemResolver
{
    /**
     * @param  array<int, array<string, mixed>>  $items  itens vindos do request
     * @param  int|null  $excludeReturnId  devolução sendo editada (não consome o próprio saldo)
     * @return array<int, array<string, mixed>> linhas prontas para `return_items`
     *
     * @throws ValidationException
     */
    public static function resolve(?Order $order, array $items, ?int $excludeReturnId = null): array
    {
        return $order === null
            ? self::resolveManual($items)
            : self::resolveFromOrder($order, $items, $excludeReturnId);
    }

    /**
     * Devolução vinculada a pedido: todo item é uma linha daquele pedido
     * (RN-02), escolhida por ID, e a quantidade respeita o saldo devolvível
     * (RN-04).
     */
    private static function resolveFromOrder(Order $order, array $items, ?int $excludeReturnId): array
    {
        $aggregated = [];

        foreach ($items as $index => $item) {
            $orderItemId = $item['orderItemId'] ?? null;

            if ($orderItemId === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.orderItemId" => 'Devolução vinculada a um pedido exige que cada item seja uma linha desse pedido.',
                ]);
            }

            $quantity = (int) ($item['quantity'] ?? 0);

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => 'A quantidade devolvida precisa ser de pelo menos 1.',
                ]);
            }

            // Itens repetidos do mesmo `order_item` no payload são somados
            // antes de validar — mesma lógica de agregação do estoque
            // (TASK-024), pelo mesmo motivo: senão dá para furar o saldo
            // repartindo a quantidade em várias linhas.
            $aggregated[(int) $orderItemId] = ($aggregated[(int) $orderItemId] ?? 0) + $quantity;
        }

        // RN-05: ordem determinística de lock, igual ao `StockLedger`.
        $orderItemIds = array_keys($aggregated);
        sort($orderItemIds);

        $orderItems = OrderItem::query()
            ->whereIn('id', $orderItemIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $rows = [];

        foreach ($orderItemIds as $orderItemId) {
            /** @var OrderItem|null $orderItem */
            $orderItem = $orderItems->get($orderItemId);

            // RN-02: existir não basta — precisa ser deste pedido. É o caso
            // de "IDs válidos, mas incompatíveis" do achado 5.
            if ($orderItem === null || (int) $orderItem->order_id !== (int) $order->id) {
                throw ValidationException::withMessages([
                    'items' => "O item #{$orderItemId} não pertence ao pedido #{$order->id}.",
                ]);
            }

            $quantity = $aggregated[$orderItemId];
            $available = self::returnableQuantity($orderItem, $excludeReturnId);

            if ($quantity > $available) {
                throw ValidationException::withMessages([
                    'items' => "Não é possível devolver {$quantity} unidade(s) de \"{$orderItem->product_name}\": "
                        ."o saldo devolvível desse item é {$available}.",
                ]);
            }

            // RN-03: snapshot derivado da venda real, não do request.
            $rows[] = [
                'order_item_id' => $orderItem->id,
                'product_id' => $orderItem->product_id,
                'product_name' => $orderItem->product_name,
                'product_type' => $orderItem->product_type,
                'brand_name' => $orderItem->brand_name,
                'model_name' => $orderItem->model_name,
                'quality_name' => $orderItem->quality_name,
                'quantity' => $quantity,
                'unit_price' => $orderItem->unit_price,
            ];
        }

        return $rows;
    }

    /**
     * Devolução avulsa (RN-06): sem pedido, o item é identificado por um
     * produto do catálogo e os snapshots saem do `Product`. Não há saldo a
     * consumir nem efeito financeiro automático — não existe venda
     * correspondente para reduzir.
     */
    private static function resolveManual(array $items): array
    {
        $rows = [];

        foreach ($items as $index => $item) {
            if (($item['orderItemId'] ?? null) !== null) {
                throw ValidationException::withMessages([
                    "items.{$index}.orderItemId" => 'Item de pedido só pode ser usado quando a devolução está vinculada a um pedido.',
                ]);
            }

            $productId = $item['productId'] ?? null;

            if ($productId === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.productId" => 'Devolução sem pedido vinculado exige escolher o produto no catálogo.',
                ]);
            }

            /** @var Product|null $product */
            $product = Product::query()
                ->with(['brand', 'watchModel.quality', 'watchModel.category'])
                ->find($productId);

            if ($product === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.productId" => 'Produto não encontrado no catálogo.',
                ]);
            }

            $quantity = (int) ($item['quantity'] ?? 0);

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => 'A quantidade devolvida precisa ser de pelo menos 1.',
                ]);
            }

            $rows[] = [
                'order_item_id' => null,
                'product_id' => $product->id,
                'product_name' => $product->displayLabel(),
                'product_type' => $product->watchModel?->category?->name ?? 'Relógios',
                'brand_name' => $product->brand?->name,
                'model_name' => $product->watchModel?->name,
                'quality_name' => $product->watchModel?->category?->has_quality
                    ? $product->watchModel?->quality?->name
                    : null,
                'quantity' => $quantity,
                'unit_price' => $product->price,
            ];
        }

        return $rows;
    }

    /**
     * Saldo devolvível de uma linha de pedido: vendido menos o que já foi
     * devolvido em outras devoluções.
     *
     * Devoluções estornadas (`returns.voided_at`, TASK-025/ADR-007) não
     * consomem saldo — o estorno anulou o efeito, então a unidade volta a
     * poder ser devolvida.
     */
    public static function returnableQuantity(OrderItem $orderItem, ?int $excludeReturnId = null): int
    {
        $alreadyReturned = (int) ReturnItem::query()
            ->where('order_item_id', $orderItem->id)
            ->when($excludeReturnId !== null, fn ($query) => $query->where('return_id', '!=', $excludeReturnId))
            ->whereHas('productReturn', fn ($query) => $query->whereNull('voided_at'))
            ->sum('quantity');

        return max((int) $orderItem->quantity - $alreadyReturned, 0);
    }
}
