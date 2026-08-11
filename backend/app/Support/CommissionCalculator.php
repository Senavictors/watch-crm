<?php

namespace App\Support;

use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Comissão (docs/domain/financial-rules.md):
 *
 *   "É um valor fixo por produto/unidade, copiado para o item na venda.
 *    Cancelamento ou devolução remove a parcela correspondente. Troca usa a
 *    comissão do novo produto."
 *
 * RN-01 (TASK-005): valor fixo por unidade, congelado em
 * `order_items.unit_commission` na venda (ver `OrderController::syncItems`)
 * — alterar `products.commission_amount` depois não muda pedidos antigos
 * (CA-01).
 *
 * "Pago" segue o mesmo critério das demais métricas de lucro (TASK-003):
 * `orders.paid_at` preenchido e status diferente de `Cancelado` — um pedido
 * cancelado não gera comissão, mesmo que tenha sido pago antes (RN da
 * comissão: "cancelamento... remove a parcela correspondente").
 *
 * Devolução: reduz a comissão proporcionalmente à quantidade devolvida do
 * item (`return_items.quantity`), não pelo valor manual `refund_amount`
 * (que é um total agregado sem granularidade de produto) — só quando o
 * reembolso foi de fato efetuado (`returns.status = 'Reembolso Efetuado'`),
 * mesmo gatilho que `RevenueCalculator` usa pra reduzir faturamento.
 *
 * Troca (RN-03 "usa a comissão do novo produto"): não precisa de lógica
 * dedicada aqui — o item de troca é um `order_item` novo, criado com a
 * comissão vigente do produto substituto no momento da venda (mesmo
 * mecanismo de snapshot do RN-01).
 */
class CommissionCalculator
{
    /**
     * @return array{items: LengthAwarePaginator, summary: array{accrued: float, paid: float, pending: float}}
     */
    public static function paginatedReport(
        User $user,
        ?string $startDate,
        ?string $endDate,
        ?int $sellerUserId,
        int $perPage
    ): array {
        $query = self::commissionItemsQuery($user, $startDate, $endDate, $sellerUserId);
        $netCommissionSql = self::netCommissionSql();

        $summaryRow = (clone $query)
            ->reorder()
            ->select([])
            ->selectRaw("COALESCE(SUM({$netCommissionSql}), 0) as accrued")
            ->selectRaw("COALESCE(SUM(CASE WHEN order_items.commission_paid_at IS NOT NULL THEN {$netCommissionSql} ELSE 0 END), 0) as paid")
            ->first();

        $accrued = (float) ($summaryRow?->accrued ?? 0);
        $paid = (float) ($summaryRow?->paid ?? 0);

        return [
            'items' => $query
                ->with(['order.sellerUser', 'commissionPaidByUser'])
                ->orderByDesc('order_items.id')
                ->paginate($perPage),
            'summary' => [
                'accrued' => $accrued,
                'paid' => $paid,
                'pending' => $accrued - $paid,
            ],
        ];
    }

    /**
     * Total de comissão apurada no período (bruto de item vendido, líquido
     * de devolução com reembolso efetuado). Usado por `SalesProfitCalculator`
     * como o componente "comissão" da fórmula de lucro das vendas.
     */
    public static function accrued(User $user, ?string $startDate = null, ?string $endDate = null, ?int $sellerUserId = null): float
    {
        return self::report($user, $startDate, $endDate, $sellerUserId)['summary']['accrued'];
    }

    /**
     * Relatório detalhado por item vendido (CA-02: "relatório fecha por
     * vendedor e período"). RN-02 (vendedor só vê a própria projeção) é
     * aplicada aqui, não no controller: quando `$user` não tem
     * `canAccessAllRecords()`, o escopo por vendedor é forçado e
     * `$sellerUserId` é ignorado.
     *
     * @return array{items: \Illuminate\Support\Collection, summary: array{accrued: float, paid: float, pending: float}}
     */
    public static function report(User $user, ?string $startDate = null, ?string $endDate = null, ?int $sellerUserId = null): array
    {
        $orderIds = self::scopedPaidOrderIds($user, $startDate, $endDate, $sellerUserId);

        if ($orderIds->isEmpty()) {
            return [
                'items' => collect(),
                'summary' => ['accrued' => 0.0, 'paid' => 0.0, 'pending' => 0.0],
            ];
        }

        $returnedByOrderItemId = DB::table('return_items')
            ->join('returns', 'returns.id', '=', 'return_items.return_id')
            ->where('returns.status', 'Reembolso Efetuado')
            // TASK-025 (ADR-007): devolucao estornada nao reduz comissao,
            // meta nem dashboard.
            ->whereNull('returns.voided_at')
            ->whereNotNull('return_items.order_item_id')
            ->selectRaw('return_items.order_item_id as order_item_id, SUM(return_items.quantity) as returned_qty')
            ->groupBy('return_items.order_item_id')
            ->pluck('returned_qty', 'order_item_id');

        $items = OrderItem::query()
            ->whereIn('order_id', $orderIds)
            ->with(['order.sellerUser', 'commissionPaidByUser'])
            ->orderByDesc('id')
            ->get()
            ->map(function (OrderItem $item) use ($returnedByOrderItemId) {
                $returnedQty = (int) ($returnedByOrderItemId[$item->id] ?? 0);
                $netQty = max($item->quantity - $returnedQty, 0);

                return [
                    'item' => $item,
                    'returnedQty' => $returnedQty,
                    'netQty' => $netQty,
                    'lineCommission' => (float) $item->unit_commission * $netQty,
                ];
            });

        $accrued = (float) $items->sum('lineCommission');
        $paid = (float) $items
            ->filter(fn (array $row) => $row['item']->commission_paid_at !== null)
            ->sum('lineCommission');

        return [
            'items' => $items,
            'summary' => [
                'accrued' => $accrued,
                'paid' => $paid,
                'pending' => $accrued - $paid,
            ],
        ];
    }

    private static function scopedPaidOrderIds(User $user, ?string $startDate, ?string $endDate, ?int $sellerUserId): \Illuminate\Support\Collection
    {
        // TASK-009 (RN-01): período por `paid_at`, não `sale_date`.
        $query = OrderFinancialScope::ordersQuery($user, $startDate, $endDate, 'paid_at')
            ->whereNotNull('paid_at')
            ->where('status', '!=', 'Cancelado');

        // Owner/admin (canAccessAllRecords) podem filtrar por um vendedor
        // específico; quem não pode já está restrito ao próprio escopo por
        // `OrderFinancialScope::apply`, então um `$sellerUserId` de outra
        // pessoa é silenciosamente ignorado (RN-02).
        if ($user->canAccessAllRecords() && $sellerUserId !== null) {
            $query->where('seller_user_id', $sellerUserId);
        }

        return $query->pluck('id');
    }

    private static function scopedPaidOrdersQuery(User $user, ?string $startDate, ?string $endDate, ?int $sellerUserId)
    {
        $query = OrderFinancialScope::ordersQuery($user, $startDate, $endDate, 'paid_at')
            ->whereNotNull('paid_at')
            ->where('status', '!=', 'Cancelado');

        if ($user->canAccessAllRecords() && $sellerUserId !== null) {
            $query->where('seller_user_id', $sellerUserId);
        }

        return $query;
    }

    private static function commissionItemsQuery(User $user, ?string $startDate, ?string $endDate, ?int $sellerUserId)
    {
        $orders = self::scopedPaidOrdersQuery($user, $startDate, $endDate, $sellerUserId)
            ->select('orders.id');

        $returned = DB::table('return_items')
            ->join('returns', 'returns.id', '=', 'return_items.return_id')
            ->where('returns.status', 'Reembolso Efetuado')
            // TASK-025 (ADR-007): devolucao estornada nao reduz comissao,
            // meta nem dashboard.
            ->whereNull('returns.voided_at')
            ->whereNotNull('return_items.order_item_id')
            ->selectRaw('return_items.order_item_id, SUM(return_items.quantity) as returned_qty')
            ->groupBy('return_items.order_item_id');

        return OrderItem::query()
            ->whereIn('order_items.order_id', $orders)
            ->leftJoinSub($returned, 'returned', 'returned.order_item_id', '=', 'order_items.id')
            ->select('order_items.*')
            ->selectRaw('COALESCE(returned.returned_qty, 0) as returned_qty')
            ->selectRaw(self::netCommissionSql().' as line_commission');
    }

    private static function netCommissionSql(): string
    {
        return 'order_items.unit_commission * CASE '
            .'WHEN order_items.quantity - COALESCE(returned.returned_qty, 0) > 0 '
            .'THEN order_items.quantity - COALESCE(returned.returned_qty, 0) ELSE 0 END';
    }
}
