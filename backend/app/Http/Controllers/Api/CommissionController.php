<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\ApiPagination;
use App\Support\CommissionCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TASK-005 — comissões por produto e venda.
 *
 * RN-02: vendedor só vê a própria projeção — aplicado dentro de
 * `CommissionCalculator::report()`, não aqui (mesmo padrão de
 * `OrderFinancialScope` usado pelos outros calculadores financeiros).
 */
class CommissionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $sellerUserId = $request->filled('sellerUserId') ? (int) $request->input('sellerUserId') : null;
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        $report = CommissionCalculator::paginatedReport(
            $user,
            $startDate,
            $endDate,
            $sellerUserId,
            ApiPagination::perPage($request)
        );

        $extra = ['summary' => $report['summary']];

        if ($user->canAccessAllRecords()) {
            $extra['sellers'] = User::query()
                ->whereIn('role', UserRole::sellableRoles())
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $seller) => ['id' => $seller->id, 'name' => $seller->name])
                ->values();
        }

        return ApiPagination::response(
            $report['items'],
            fn (OrderItem $item) => $this->toItemPayload([
                'item' => $item,
                'returnedQty' => (int) $item->getAttribute('returned_qty'),
                'netQty' => max($item->quantity - (int) $item->getAttribute('returned_qty'), 0),
                'lineCommission' => (float) $item->getAttribute('line_commission'),
            ]),
            $extra
        );
    }

    /**
     * Marca a comissão de um conjunto de itens vendidos como paga.
     * Idempotente (risco registrado na task): itens já pagos dentro do lote
     * são ignorados silenciosamente, não geram erro em reprocessamento.
     */
    public function pay(Request $request)
    {
        $data = $request->validate([
            'orderItemIds' => ['required', 'array', 'min:1'],
            'orderItemIds.*' => ['integer', 'exists:order_items,id'],
        ]);

        $user = $request->user();

        $items = OrderItem::query()
            ->whereIn('id', $data['orderItemIds'])
            ->with('order')
            ->get()
            ->filter(function (OrderItem $item) {
                // Só é elegível pra pagamento a comissão de fato apurada:
                // pedido pago, não cancelado, ainda não paga.
                $order = $item->order;

                return $order
                    && $order->paid_at !== null
                    && $order->status !== 'Cancelado'
                    && $item->commission_paid_at === null;
            });

        if ($items->isEmpty()) {
            return response()->json([
                'message' => 'Nenhum item elegível para pagamento (já pago, cancelado ou não apurado).',
            ], 422);
        }

        DB::transaction(function () use ($items, $user) {
            OrderItem::query()
                ->whereIn('id', $items->pluck('id'))
                ->update([
                    'commission_paid_at' => now(),
                    'commission_paid_by_user_id' => $user->id,
                ]);
        });

        $totalAmount = (float) $items->sum(fn (OrderItem $item) => (float) $item->unit_commission * $item->quantity);
        $sellerUserIds = $items->map(fn (OrderItem $item) => $item->order->seller_user_id)->unique()->values();

        $this->audit('commissions.paid', 'Comissão de itens vendidos marcada como paga.', null, [
            'order_item_ids' => $items->pluck('id')->values()->all(),
            'seller_user_ids' => $sellerUserIds->all(),
            'total_amount' => $totalAmount,
        ]);

        return response()->json([
            'paidOrderItemIds' => $items->pluck('id')->values(),
            'totalAmount' => $totalAmount,
        ]);
    }

    private function toItemPayload(array $row): array
    {
        /** @var OrderItem $item */
        $item = $row['item'];
        $order = $item->order;

        return [
            'orderItemId' => $item->id,
            'orderId' => $order?->id,
            'sellerUserId' => $order?->seller_user_id,
            'sellerUserName' => $order?->sellerUser?->name ?? $order?->seller,
            'productName' => $item->product_name,
            'saleDate' => $order?->sale_date,
            'quantity' => $item->quantity,
            'returnedQuantity' => $row['returnedQty'],
            'netQuantity' => $row['netQty'],
            'unitCommission' => (float) $item->unit_commission,
            'lineCommission' => $row['lineCommission'],
            'paid' => $item->commission_paid_at !== null,
            'commissionPaidAt' => $item->commission_paid_at?->toIso8601String(),
            'commissionPaidByUserName' => $item->commissionPaidByUser?->name,
        ];
    }
}
