<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PostingDay;
use App\Support\ShippingScheduleCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * TASK-016 — agenda de postagem e fila de envios. Base backend para o que
 * hoje é hardcoded no frontend (`ShippingQueue.tsx`/`helpers.ts::nextShippingDay()`).
 */
class ShippingController extends Controller
{
    /**
     * Rótulos em português por `weekday` (0=domingo...6=sábado) — só uso de
     * apresentação, o cálculo de negócio não conhece este mapa.
     */
    private const WEEKDAY_LABELS = [
        0 => 'Domingo',
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
    ];

    public function schedule()
    {
        $days = PostingDay::query()->orderBy('weekday')->get();

        return response()->json($days->map(fn (PostingDay $day) => $this->toSchedulePayload($day))->values());
    }

    public function updateSchedule(Request $request)
    {
        $data = $request->validate([
            'days' => ['required', 'array', 'size:7'],
            'days.*.weekday' => ['required', 'integer', 'between:0,6', 'distinct'],
            'days.*.enabled' => ['required', 'boolean'],
        ]);

        $weekdays = collect($data['days'])->pluck('weekday')->sort()->values()->all();
        if ($weekdays !== range(0, 6)) {
            return response()->json([
                'message' => 'É necessário informar todos os 7 dias da semana, cada um uma única vez.',
            ], 422);
        }

        $enabledCount = collect($data['days'])->where('enabled', true)->count();
        if ($enabledCount === 0) {
            return response()->json([
                'message' => 'É necessário manter ao menos um dia de postagem habilitado.',
            ], 422);
        }

        DB::transaction(function () use ($data) {
            foreach ($data['days'] as $day) {
                PostingDay::query()
                    ->where('weekday', $day['weekday'])
                    ->update(['enabled' => $day['enabled']]);
            }
        });

        $days = PostingDay::query()->orderBy('weekday')->get();

        $this->audit('shipping.schedule_updated', 'Agenda de postagem atualizada.', null, [
            'days' => $data['days'],
        ]);

        return response()->json($days->map(fn (PostingDay $day) => $this->toSchedulePayload($day))->values());
    }

    public function queue(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today();

        $query = Order::query()
            ->with(['customer', 'items'])
            ->whereNotNull('paid_at')
            ->whereNull('shipped_date')
            ->whereNotIn('status', ['Cancelado', 'Entregue']);

        if (! $user->canAccessAllRecords()) {
            $query->where('seller_user_id', $user->id);
        }

        $orders = $query->get()->map(function (Order $order) use ($today) {
            $nextPostingDate = ShippingScheduleCalculator::nextPostingDate($order->paid_at);

            return [
                'id' => $order->id,
                'customerName' => $order->customer?->name,
                'productName' => $order->product_name,
                'itemsCount' => $order->items->sum('quantity'),
                'channel' => $order->channel,
                'shippingMethod' => $order->shipping_method,
                'freight' => (float) $order->freight,
                'saleDate' => $order->sale_date,
                'paidAt' => $order->paid_at?->toIso8601String(),
                'nextPostingDate' => $nextPostingDate?->toDateString(),
                'isLate' => ShippingScheduleCalculator::isLate($nextPostingDate, $today),
            ];
        });

        $sorted = $orders->sortBy('nextPostingDate')->values();

        return response()->json($sorted);
    }

    private function toSchedulePayload(PostingDay $day): array
    {
        return [
            'weekday' => $day->weekday,
            'label' => self::WEEKDAY_LABELS[$day->weekday],
            'enabled' => $day->enabled,
        ];
    }
}
