<?php

namespace App\Support;

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * TASK-024 / ADR-006 — ponto único de escrita de saldo de estoque.
 *
 * Disponível para venda = `products.qty - products.reserved_qty`.
 * Três estágios, derivados do próprio pedido (não há campo novo em `orders`):
 *
 * - `reserved`  — pedido vivo e não pago: segura unidades em `reserved_qty`.
 * - `committed` — pedido com `paid_at`: baixa de `qty` (e solta o `reserved_qty`).
 * - `released`  — pedido cancelado/excluído: devolve o que aquele pedido segurava.
 *
 * `syncOrder()` é declarativo: calcula o estado desejado a partir das linhas
 * persistidas do pedido, compara com `stock_reservations` e aplica só o
 * delta. Por isso reprocessar a mesma operação é inofensivo (CA-03) e
 * cancelar libera exatamente o que aquele pedido segurava, nunca o saldo de
 * outro pedido (RN-04).
 *
 * Toda chamada assume estar dentro de uma transação do chamador — os
 * `lockForUpdate()` daqui só valem até o commit dela.
 */
class StockLedger
{
    public const TYPE_RESERVE = 'reserve';

    public const TYPE_RELEASE = 'release';

    public const TYPE_COMMIT = 'commit';

    public const TYPE_UNCOMMIT = 'uncommit';

    public const TYPE_MANUAL_ENTRY = 'manual_entry';

    public const TYPE_MANUAL_ADJUST = 'manual_adjust';

    /**
     * Reconcilia o estoque com o estado atual do pedido (itens + pagamento +
     * status). Chame sempre DEPOIS de gravar itens e status.
     */
    public static function syncOrder(Order $order, ?User $actor = null): void
    {
        self::apply($order, $actor, forceRelease: false);
    }

    /**
     * Libera tudo que o pedido segura. Usado na exclusão do pedido, quando as
     * linhas de item ainda existem mas o pedido deixa de valer.
     */
    public static function releaseOrder(Order $order, ?User $actor = null): void
    {
        self::apply($order, $actor, forceRelease: true);
    }

    private static function apply(Order $order, ?User $actor, bool $forceRelease): void
    {
        $released = $forceRelease || $order->status === 'Cancelado';
        $committed = ! $released && $order->paid_at !== null;

        $desired = $released ? [] : self::desiredQuantities($order);

        $existing = StockReservation::query()
            ->where('order_id', $order->id)
            ->get()
            ->keyBy('product_id');

        // RN-03: ordem determinística de lock — todos os fluxos bloqueiam os
        // produtos por id crescente, então dois pedidos concorrentes que
        // compartilham produtos nunca travam em ordem invertida.
        $productIds = collect($desired)->keys()
            ->merge($existing->keys())
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values();

        foreach ($productIds as $productId) {
            $reservation = $existing->get($productId);
            $targetQuantity = (int) ($desired[$productId] ?? 0);

            $product = Product::query()->whereKey($productId)->lockForUpdate()->first();

            if ($product === null) {
                continue;
            }

            // CA-06: produto de fornecedor não tem saldo local — não valida
            // nem consome. Só entra aqui se já houver reserva antiga (produto
            // que virou SUPPLIER depois da venda), para não prender saldo.
            if ($product->stock === 'SUPPLIER' && $reservation === null) {
                continue;
            }

            self::applyToProduct(
                $product,
                $order,
                $reservation,
                $targetQuantity,
                $committed,
                $actor,
            );
        }
    }

    /**
     * CA-02: itens repetidos do mesmo produto são agregados ANTES de validar
     * — três linhas de 1 unidade do mesmo relógio são uma demanda de 3.
     *
     * @return array<int, int>
     */
    private static function desiredQuantities(Order $order): array
    {
        $items = $order->relationLoaded('items') ? $order->items : $order->items()->get();

        $desired = [];

        foreach ($items as $item) {
            /** @var OrderItem $item */
            if ($item->product_id === null) {
                continue;
            }

            $productId = (int) $item->product_id;
            $desired[$productId] = ($desired[$productId] ?? 0) + (int) $item->quantity;
        }

        return $desired;
    }

    private static function applyToProduct(
        Product $product,
        Order $order,
        ?StockReservation $reservation,
        int $targetQuantity,
        bool $committed,
        ?User $actor,
    ): void {
        $reservedNow = $reservation?->heldReserved() ?? 0;
        $committedNow = $reservation?->heldCommitted() ?? 0;

        $targetReserved = ($targetQuantity > 0 && ! $committed) ? $targetQuantity : 0;
        $targetCommitted = ($targetQuantity > 0 && $committed) ? $targetQuantity : 0;

        $reservedDelta = $targetReserved - $reservedNow;
        // `committed` já saiu de `qty`; aumentar a baixa é decrementar `qty`.
        $qtyDelta = -($targetCommitted - $committedNow);

        if ($reservedDelta === 0 && $qtyDelta === 0) {
            self::persistReservation($reservation, $order, $product, $targetQuantity, $committed);

            return;
        }

        $newQty = (int) $product->qty + $qtyDelta;
        $newReserved = (int) $product->reserved_qty + $reservedDelta;

        // RN-02: `qty` nunca negativo e nunca mais prometido do que existe.
        if ($newQty < 0 || $newReserved < 0 || $newQty - $newReserved < 0) {
            $available = max((int) $product->qty - (int) $product->reserved_qty, 0)
                + $reservedNow + $committedNow;

            throw new InsufficientStockException(
                (int) $product->id,
                $product->displayLabel(),
                $targetQuantity,
                $available,
            );
        }

        $product->qty = $newQty;
        $product->reserved_qty = $newReserved;
        $product->save();

        self::recordMovement(
            product: $product,
            orderId: $order->id,
            type: self::resolveType($targetCommitted - $committedNow, $reservedDelta),
            quantity: abs($qtyDelta !== 0 ? $qtyDelta : $reservedDelta),
            qtyDelta: $qtyDelta,
            reservedDelta: $reservedDelta,
            actor: $actor,
            notes: null,
        );

        self::persistReservation($reservation, $order, $product, $targetQuantity, $committed);
    }

    private static function resolveType(int $committedDelta, int $reservedDelta): string
    {
        if ($committedDelta > 0) {
            return self::TYPE_COMMIT;
        }

        if ($committedDelta < 0) {
            return self::TYPE_UNCOMMIT;
        }

        return $reservedDelta > 0 ? self::TYPE_RESERVE : self::TYPE_RELEASE;
    }

    private static function persistReservation(
        ?StockReservation $reservation,
        Order $order,
        Product $product,
        int $targetQuantity,
        bool $committed,
    ): void {
        $status = match (true) {
            $targetQuantity === 0 => StockReservation::STATUS_RELEASED,
            $committed => StockReservation::STATUS_COMMITTED,
            default => StockReservation::STATUS_RESERVED,
        };

        if ($reservation === null) {
            if ($targetQuantity === 0) {
                return;
            }

            StockReservation::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $targetQuantity,
                'status' => $status,
            ]);

            return;
        }

        $reservation->quantity = $targetQuantity;
        $reservation->status = $status;
        $reservation->save();
    }

    /**
     * Entrada/ajuste manual de catálogo. É por aqui que a reposição de uma
     * peça devolvida fica auditável (ADR-006, item 4: devolução não repõe
     * automaticamente).
     */
    public static function recordManualChange(
        Product $product,
        int $qtyDelta,
        string $type,
        ?User $actor,
        ?string $notes = null,
    ): void {
        if ($qtyDelta === 0) {
            return;
        }

        self::recordMovement(
            product: $product,
            orderId: null,
            type: $type,
            quantity: abs($qtyDelta),
            qtyDelta: $qtyDelta,
            reservedDelta: 0,
            actor: $actor,
            notes: $notes,
        );
    }

    /**
     * RN-06: origem, ator, quantidade e chave de idempotência em todo
     * movimento. A chave é derivada do par (pedido, produto) mais a sequência
     * já gravada — determinística dentro do lock e única no banco, então uma
     * repetição concorrente do mesmo efeito colide em vez de duplicar.
     */
    private static function recordMovement(
        Product $product,
        ?int $orderId,
        string $type,
        int $quantity,
        int $qtyDelta,
        int $reservedDelta,
        ?User $actor,
        ?string $notes,
    ): void {
        $scope = $orderId !== null ? "order:{$orderId}" : 'manual';

        $sequence = StockMovement::query()
            ->where('product_id', $product->id)
            ->when($orderId !== null, fn ($q) => $q->where('order_id', $orderId))
            ->when($orderId === null, fn ($q) => $q->whereNull('order_id'))
            ->count() + 1;

        StockMovement::create([
            'product_id' => $product->id,
            'order_id' => $orderId,
            'type' => $type,
            'quantity' => $quantity,
            'qty_delta' => $qtyDelta,
            'reserved_delta' => $reservedDelta,
            'qty_after' => (int) $product->qty,
            'reserved_after' => (int) $product->reserved_qty,
            'actor_user_id' => $actor?->id,
            'idempotency_key' => "{$scope}:product:{$product->id}:{$type}:{$sequence}",
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }

    /**
     * Reexecuta o callback quando o banco aborta a transação por deadlock.
     * `DB::transaction($cb, $attempts)` só reprocessa na transação mais
     * externa — este helper existe para os controllers declararem isso de
     * forma explícita e uniforme.
     */
    public static function transaction(callable $callback, int $attempts = 3): mixed
    {
        return DB::transaction($callback, $attempts);
    }
}
