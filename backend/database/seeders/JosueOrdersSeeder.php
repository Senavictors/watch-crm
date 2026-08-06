<?php

namespace Database\Seeders;

use App\Models\Goal;
use App\Models\Order;
use App\Models\Product;
use App\Support\OrderMetadata;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Gera ~90 dias de pedidos (3/dia, conforme informado pelo usuário em
 * 2026-08-06) sobre o catálogo criado por `JosueCatalogSeeder`, mais metas de
 * exemplo. Idempotente: IDs fixos (2000+), upsert de pedidos e
 * delete-then-insert de itens (mesmo padrão de `OrderSeeder`).
 *
 * Composição de qualidade (90% Base ETA / 10% Clone) e de forma de pagamento
 * dentro de cada qualidade (~65% à vista/PIX, ~35% cartão) são estimativas
 * pra aproximar o faturamento mensal informado (~R$90mil) — não são uma
 * regra de negócio confirmada, só o necessário pra gerar dados plausíveis.
 * Comissão por unidade (R$40 Base ETA / R$150 Clone) e a despesa de anúncio
 * (R$5.000/mês) foram informadas pelo usuário mas NÃO têm coluna no schema
 * ainda (dependem de TASK-005/TASK-006) — não são gravadas aqui.
 */
class JosueOrdersSeeder extends Seeder
{
    private const SELLER_IDS = [3, 4, 5]; // Josué, Karolina, Igor

    private const CREATOR_IDS = [1, 2]; // admin, gerente — sellers não têm orders.create

    private const BOX_BY_BRAND = [
        6 => 6,   // Rolex
        1 => 201, // TAG Heuer
        2 => 202, // Omega
        3 => 203, // Tissot
        7 => 204, // Patek Philippe
    ];

    private const DAYS = 90;

    private const ORDERS_PER_DAY = 3;

    public function run(): void
    {
        $watchProducts = Product::query()
            ->with('watchModel.brand', 'watchModel.category', 'watchModel.quality')
            ->where(function ($q) {
                $q->where('id', 3)->orWhereBetween('id', [101, 200]);
            })
            ->get();

        $baseEtaPool = $watchProducts->filter(fn (Product $p) => $p->watchModel->quality_id === 1)->values();
        $clonePool = $watchProducts->filter(fn (Product $p) => $p->watchModel->quality_id === 2)->values();

        if ($baseEtaPool->isEmpty() || $clonePool->isEmpty()) {
            $this->command?->warn('JosueOrdersSeeder: catálogo não encontrado — rode JosueCatalogSeeder antes.');

            return;
        }

        $boxes = Product::with('watchModel')->whereIn('id', array_values(self::BOX_BY_BRAND))->get()->keyBy('id');

        mt_srand(20260806);

        $today = Carbon::today();
        $orders = [];
        $itemsByOrderId = [];
        $orderId = 2000;

        for ($dayOffset = self::DAYS - 1; $dayOffset >= 0; $dayOffset--) {
            $saleDate = $today->copy()->subDays($dayOffset);
            $ageDays = $dayOffset;

            for ($slot = 0; $slot < self::ORDERS_PER_DAY; $slot++) {
                $id = $orderId++;
                [$order, $items] = $this->buildOrder($id, $saleDate, $ageDays, $baseEtaPool, $clonePool, $boxes);
                $orders[] = $order;
                $itemsByOrderId[$id] = $items;
            }
        }

        Order::upsert($orders, ['id']);

        DB::table('order_items')->whereIn('order_id', array_keys($itemsByOrderId))->delete();
        $now = now();
        $flatItems = [];
        foreach ($itemsByOrderId as $items) {
            foreach ($items as $item) {
                $flatItems[] = $item + ['created_at' => $now, 'updated_at' => $now];
            }
        }
        DB::table('order_items')->insert($flatItems);

        $this->seedGoals($today);
    }

    /**
     * @return array{0: array, 1: array<int, array>}
     */
    private function buildOrder(int $id, Carbon $saleDate, int $ageDays, $baseEtaPool, $clonePool, $boxes): array
    {
        // Ajustado pra aproximar o faturamento mensal informado pelo usuário
        // (~R$90mil/mês, 90 pedidos/mês): a maioria das vendas é Base ETA à
        // vista; Clone e cartão puxam a média pra cima, então ficam raros.
        $isClone = $this->chance(2);
        $pool = $isClone ? $clonePool : $baseEtaPool;
        /** @var Product $watch */
        $watch = $pool[array_rand($pool->all())];

        $isCardPayment = $this->chance(10);
        $unitPrice = $isCardPayment
            ? (float) ($watch->price_card ?? $watch->price)
            : (float) ($watch->price_pix ?? $watch->price);
        $paymentMethod = $isCardPayment
            ? (mt_rand(0, 1) === 0 ? 'Cartão Crédito' : 'Cartão Débito')
            : (mt_rand(0, 4) === 0 ? 'Dinheiro' : 'PIX');

        $items = [$this->watchItemRow($id, $watch, $unitPrice)];

        $boxProductId = self::BOX_BY_BRAND[$watch->brand_id] ?? null;
        $includeBox = $boxProductId && $this->chance(10);
        if ($includeBox) {
            $items[] = $this->boxItemRow($id, $boxes[$boxProductId]);
        }

        $discount = $this->chance(10) ? [20, 30, 50][mt_rand(0, 2)] : 0;
        $items[0]['unit_discount'] = $discount;

        $salePrice = array_sum(array_map(fn ($i) => $i['unit_price'] * $i['quantity'], $items));
        $cost = array_sum(array_map(fn ($i) => $i['unit_cost'] * $i['quantity'], $items));
        $totalDiscount = array_sum(array_map(fn ($i) => $i['unit_discount'] * $i['quantity'], $items));

        $status = $this->pickStatus($ageDays);
        $isPaid = in_array($status, OrderMetadata::PAID_STATUSES, true);
        $paidAt = $isPaid ? $saleDate->copy()->addHours(mt_rand(1, 48)) : null;
        $paidByUserId = $isPaid ? [1, 2][mt_rand(0, 1)] : null;
        $isShipped = in_array($status, ['Enviado', 'Entregue'], true);
        $shippedDate = $isShipped ? $saleDate->copy()->addDays(mt_rand(2, 5))->min(Carbon::today()) : null;

        $seller = self::SELLER_IDS[array_rand(self::SELLER_IDS)];
        $sellerName = ['Josué', 'Karolina', 'Igor'][array_search($seller, self::SELLER_IDS, true)];

        $order = [
            'id' => $id,
            'customer_id' => 100 + mt_rand(0, 79),
            'created_by_user_id' => self::CREATOR_IDS[array_rand(self::CREATOR_IDS)],
            'seller_user_id' => $seller,
            'product_id' => $items[0]['product_id'],
            'product_name' => count($items) > 1 ? $items[0]['product_name'].' + 1 item(ns)' : $items[0]['product_name'],
            'channel' => OrderMetadata::CHANNELS[array_rand(OrderMetadata::CHANNELS)],
            'seller' => $sellerName,
            'status' => $status,
            'paid_at' => $paidAt,
            'paid_by_user_id' => $paidByUserId,
            'sale_price' => $salePrice,
            'cost' => $cost,
            'discount' => $totalDiscount,
            'freight' => mt_rand(15, 40),
            'channel_fee' => 0,
            'payment_method' => $paymentMethod,
            'shipping_method' => OrderMetadata::SHIPPING_METHODS[array_rand(OrderMetadata::SHIPPING_METHODS)],
            'tracking_code' => $isShipped ? 'BR'.mt_rand(100000000, 999999999).'BR' : null,
            'sale_date' => $saleDate->toDateString(),
            'shipped_date' => $shippedDate?->toDateString(),
            'notes' => null,
            'created_at' => $saleDate,
            'updated_at' => now(),
        ];

        return [$order, $items];
    }

    private function watchItemRow(int $orderId, Product $watch, float $unitPrice): array
    {
        $model = $watch->watchModel;

        return [
            'order_id' => $orderId,
            'product_id' => $watch->id,
            'product_name' => trim($model->brand->name.' '.$model->name),
            'product_type' => $model->category->name,
            'brand_name' => $model->brand->name,
            'model_name' => $model->name,
            'quality_name' => $model->quality?->name,
            'quantity' => 1,
            'unit_price' => $unitPrice,
            'unit_cost' => (float) $watch->cost,
            'unit_discount' => 0,
        ];
    }

    private function boxItemRow(int $orderId, Product $box): array
    {
        $model = $box->watchModel;

        return [
            'order_id' => $orderId,
            'product_id' => $box->id,
            'product_name' => $model->name,
            'product_type' => $model->category->name,
            'brand_name' => $model->brand->name,
            'model_name' => $model->name,
            'quality_name' => null,
            'quantity' => 1,
            'unit_price' => (float) $box->price,
            'unit_cost' => (float) $box->cost,
            'unit_discount' => 0,
        ];
    }

    /**
     * Pedidos recentes ainda estão em algum ponto do fluxo; pedidos antigos
     * (>10 dias) majoritariamente já foram entregues, com uma fração
     * cancelada — não há uma distribuição real informada, é só pra parecer
     * um histórico de negócio de verdade.
     */
    private function pickStatus(int $ageDays): string
    {
        if ($this->chance(5)) {
            return 'Cancelado';
        }

        if ($ageDays > 10) {
            return $this->chance(8) ? 'Enviado' : 'Entregue';
        }

        if ($ageDays <= 1 && $this->chance(30)) {
            return 'Novo';
        }

        $pipeline = ['Aguardando Pagamento', 'Pago', 'Separação/Fornecedor', 'Pronto para Envio', 'Enviado', 'Entregue'];

        return $pipeline[mt_rand(0, count($pipeline) - 1)];
    }

    private function chance(int $percent): bool
    {
        return mt_rand(1, 100) <= $percent;
    }

    /**
     * Metas de exemplo (RN informada pelo usuário: 3 vendas/dia na empresa
     * com bônus de R$500 pros vendedores; vendedor individual com meta de 30
     * relógios/mês e bônus de R$200). O valor do bônus não tem campo próprio
     * no schema — registrado na descrição, texto livre.
     */
    private function seedGoals(Carbon $today): void
    {
        $monthStarts = [
            $today->copy()->startOfMonth()->subMonths(2),
            $today->copy()->startOfMonth()->subMonths(1),
            $today->copy()->startOfMonth(),
        ];

        Goal::upsert([[
            'id' => 1,
            'created_by_user_id' => 1,
            'target_user_id' => null,
            'name' => 'Meta da empresa — 3 vendas por dia',
            'description' => 'Meta operacional: média de 3 relógios vendidos por dia. Bônus de R$500,00 para os vendedores quando a meta do mês é batida (valor informado pelo usuário — sem campo próprio no sistema ainda, ver TASK-005/TASK-006).',
            'scope' => 'company',
            'calculation_type' => 'quantity',
            'product_type_filter' => null,
            'brand_id' => null,
            'model_id' => null,
            'period_cycle' => 'monthly',
            'start_date' => $monthStarts[0]->toDateString(),
            'end_date' => $today->copy()->endOfMonth()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['id']);

        $companyIntervals = [];
        foreach ($monthStarts as $monthStart) {
            $companyIntervals[] = [
                'goal_id' => 1,
                'start_date' => $monthStart->toDateString(),
                'end_date' => $monthStart->copy()->endOfMonth()->toDateString(),
                'target_value' => 3 * $monthStart->daysInMonth,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $sellers = [3 => 'Josué', 4 => 'Karolina', 5 => 'Igor'];
        $goalId = 2;
        $intervalRows = $companyIntervals;

        foreach ($sellers as $userId => $name) {
            Goal::upsert([[
                'id' => $goalId,
                'created_by_user_id' => 1,
                'target_user_id' => $userId,
                'name' => "Meta individual — {$name} (30 relógios/mês)",
                'description' => 'Bônus de R$200,00 ao vendedor que atingir 30 relógios vendidos no mês (valor informado pelo usuário — sem campo próprio no sistema ainda, ver TASK-005/TASK-006).',
                'scope' => 'user',
                'calculation_type' => 'quantity',
                'product_type_filter' => null,
                'brand_id' => null,
                'model_id' => null,
                'period_cycle' => 'monthly',
                'start_date' => $monthStarts[0]->toDateString(),
                'end_date' => $today->copy()->endOfMonth()->toDateString(),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]], ['id']);

            foreach ($monthStarts as $monthStart) {
                $intervalRows[] = [
                    'goal_id' => $goalId,
                    'start_date' => $monthStart->toDateString(),
                    'end_date' => $monthStart->copy()->endOfMonth()->toDateString(),
                    'target_value' => 30,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $goalId++;
        }

        DB::table('goal_intervals')->whereIn('goal_id', [1, 2, 3, 4])->delete();
        DB::table('goal_intervals')->insert($intervalRows);
    }
}
