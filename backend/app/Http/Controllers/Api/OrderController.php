<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Support\ApiPagination;
use App\Support\OrderMetadata;
use App\Support\OrderPaymentTransition;
use App\Support\ShippingScheduleCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function metadata()
    {
        $assignableSellers = User::query()
            ->whereIn('role', UserRole::sellableRoles())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->values();

        return response()->json([
            'channels' => OrderMetadata::CHANNELS,
            'statuses' => OrderMetadata::STATUSES,
            'paymentMethods' => OrderMetadata::PAYMENT_METHODS,
            'shippingMethods' => OrderMetadata::SHIPPING_METHODS,
            'assignableSellers' => $assignableSellers,
            'categories' => Category::query()->orderBy('name')->pluck('name')->values(),
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $filters = $request->validate([
            'category' => ['sometimes', 'string'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'search' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string'],
            'channel' => ['sometimes', 'string'],
            'sellerUserId' => ['sometimes', 'integer'],
            'customer_id' => ['sometimes', 'integer'],
            'paymentStatus' => ['sometimes', 'string', Rule::in(['pending'])],
        ]);

        if (isset($filters['from']) && isset($filters['to']) && $filters['to'] < $filters['from']) {
            return response()->json([
                'message' => 'Período inválido: data final anterior à inicial.',
            ], 422);
        }

        $query = Order::query();

        if (! $user->canAccessAllRecords()) {
            $query->where('seller_user_id', $user->id);
        }

        if (isset($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (($filters['paymentStatus'] ?? null) === 'pending') {
            $query->whereIn('status', OrderMetadata::PENDING_PAYMENT_STATUSES);
        }

        if (isset($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (isset($filters['sellerUserId']) && $user->canAccessAllRecords()) {
            $query->where('seller_user_id', $filters['sellerUserId']);
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($builder) use ($search) {
                $builder->where('id', ctype_digit($search) ? (int) $search : -1)
                    ->orWhere('product_name', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"));
            });
        }

        if (isset($filters['category'])) {
            $category = $filters['category'];
            $query->whereHas('items', fn ($q) => $q->where('product_type', $category));
        }

        if (isset($filters['from'])) {
            $query->where('paid_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (isset($filters['to'])) {
            $query->where('paid_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        $canViewFinancials = $user->canViewFinancialReports();

        $orders = $query
            ->with(['customer', 'sellerUser', 'paidByUser', 'items'])
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->paginate(ApiPagination::perPage($request));

        return ApiPagination::response($orders, fn (Order $order) => $this->toPayload($order, $canViewFinancials, false));
    }

    public function lookup(Request $request)
    {
        $user = $request->user();
        $query = Order::query()->with(['customer', 'sellerUser', 'paidByUser', 'items']);

        if (! $user->canAccessAllRecords()) {
            $query->where('seller_user_id', $user->id);
        }

        if ($request->filled('customerId')) {
            $query->where('customer_id', $request->integer('customerId'));
        }

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->where(function ($builder) use ($search) {
                $builder->where('id', ctype_digit($search) ? (int) $search : -1)
                    ->orWhere('product_name', 'like', "%{$search}%");
            });
        }

        $orders = $query->orderByDesc('sale_date')->orderByDesc('id')->limit(20)->get();

        return response()->json([
            'data' => $orders->map(fn (Order $order) => $this->toPayload($order, $user->canViewFinancialReports()))->values(),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $order = Order::query()->with(['customer', 'sellerUser', 'paidByUser', 'items'])->find($id);
        if (! $order) {
            return response()->json(['message' => 'Pedido não encontrado.'], 404);
        }

        $this->authorize('view', $order);

        return response()->json($this->toPayload($order, $request->user()->canViewFinancialReports()));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $seller = User::query()->findOrFail($data['sellerUserId']);
        $products = $this->productsById($data['items']);
        $totals = $this->calculateTotals($data['items'], $products);

        $paymentEvent = null;

        $order = DB::transaction(function () use ($data, $products, $request, $seller, $totals, &$paymentEvent) {
            $order = Order::create([
                'customer_id' => $data['customerId'],
                'created_by_user_id' => $request->user()->id,
                'seller_user_id' => $data['sellerUserId'],
                'product_id' => $totals['first_product_id'],
                'product_name' => $totals['product_name'],
                'channel' => $data['channel'],
                'seller' => $seller->name,
                'status' => $data['status'] ?? 'Novo',
                'sale_price' => $totals['sale_price'],
                'cost' => $totals['cost'],
                'discount' => $totals['discount'],
                'freight' => $data['freight'] ?? 0,
                'channel_fee' => $data['channelFee'] ?? 0,
                'payment_method' => $data['paymentMethod'] ?? null,
                'payment_expires_at' => filled($data['paymentExpiresAt'] ?? null)
                    ? Carbon::parse($data['paymentExpiresAt'])->utc()
                    : null,
                'shipping_method' => $data['shippingMethod'],
                'tracking_code' => $data['trackingCode'] ?? null,
                'sale_date' => $data['saleDate'],
                'shipped_date' => $data['shippedDate'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $paymentEvent = OrderPaymentTransition::applyOnCreate($order, $request->user());
            if ($paymentEvent) {
                $order->save();
            }

            $this->syncItems($order, $data['items'], $products);

            return $order;
        });
        $order->load(['customer', 'sellerUser', 'paidByUser', 'items']);

        $this->audit('orders.created', 'Pedido criado.', $order, [
            'seller_user_id' => $order->seller_user_id,
            'status' => $order->status,
        ]);

        if ($paymentEvent) {
            $this->audit($paymentEvent, 'Pagamento confirmado na criação do pedido.', $order, [
                'paid_at' => $order->paid_at?->toIso8601String(),
                'paid_by_user_id' => $order->paid_by_user_id,
            ]);
        }

        return response()->json($this->toPayload($order, $request->user()->canViewFinancialReports()), 201);
    }

    public function update(Request $request, int $id)
    {
        $order = Order::query()->with(['customer', 'sellerUser', 'paidByUser', 'items'])->find($id);

        if (! $order) {
            return response()->json(['message' => 'Pedido não encontrado.'], 404);
        }

        $this->authorize('update', $order);

        $data = $this->validateData($request, true);

        // TASK-005 (CA-03): `syncItems` recria as linhas do pedido (delete +
        // insert), o que apagaria silenciosamente `commission_paid_at` de
        // qualquer item já conciliado. Uma comissão paga não pode ser
        // reaberta por uma edição de itens — se precisar corrigir a venda,
        // é preciso reverter o pagamento da comissão primeiro.
        if (array_key_exists('items', $data) && $order->items->contains(fn (OrderItem $item) => $item->commission_paid_at !== null)) {
            return response()->json([
                'message' => 'Não é possível alterar os itens de um pedido com comissão já paga.',
            ], 422);
        }

        $previousStatus = $order->status;

        if (array_key_exists('customerId', $data)) {
            $order->customer_id = $data['customerId'];
        }

        if (array_key_exists('sellerUserId', $data)) {
            $seller = User::query()->findOrFail($data['sellerUserId']);
            $order->seller_user_id = $seller->id;
            $order->seller = $seller->name;
        }

        $products = null;

        $fieldMap = [
            'channel' => 'channel',
            'status' => 'status',
            'freight' => 'freight',
            'channelFee' => 'channel_fee',
            'paymentMethod' => 'payment_method',
            'shippingMethod' => 'shipping_method',
            'trackingCode' => 'tracking_code',
            'saleDate' => 'sale_date',
            'shippedDate' => 'shipped_date',
            'notes' => 'notes',
        ];

        foreach ($fieldMap as $input => $column) {
            if (array_key_exists($input, $data)) {
                $order->{$column} = $data[$input];
            }
        }

        if (array_key_exists('paymentExpiresAt', $data)) {
            $order->payment_expires_at = filled($data['paymentExpiresAt'])
                ? Carbon::parse($data['paymentExpiresAt'])->utc()
                : null;
        }

        $paymentEvent = OrderPaymentTransition::apply($order, $previousStatus, $request->user());

        DB::transaction(function () use (&$order, $data, &$products) {
            if (array_key_exists('items', $data)) {
                $products = $this->productsById($data['items']);
                $totals = $this->calculateTotals($data['items'], $products);
                $order->product_id = $totals['first_product_id'];
                $order->product_name = $totals['product_name'];
                $order->sale_price = $totals['sale_price'];
                $order->cost = $totals['cost'];
                $order->discount = $totals['discount'];
            }

            $order->save();

            if (array_key_exists('items', $data) && $products !== null) {
                $this->syncItems($order, $data['items'], $products);
            }
        });
        $order->load(['customer', 'sellerUser', 'paidByUser', 'items']);

        $metadata = [
            'seller_user_id' => $order->seller_user_id,
            'status' => $order->status,
        ];
        if ($previousStatus !== $order->status) {
            $metadata['previous_status'] = $previousStatus;
        }

        $this->audit('orders.updated', 'Pedido atualizado.', $order, $metadata);

        if ($paymentEvent === OrderPaymentTransition::EVENT_CONFIRMED) {
            $this->audit($paymentEvent, 'Pagamento confirmado.', $order, [
                'previous_status' => $previousStatus,
                'paid_at' => $order->paid_at?->toIso8601String(),
                'paid_by_user_id' => $order->paid_by_user_id,
            ]);
        } elseif ($paymentEvent === OrderPaymentTransition::EVENT_REVERTED) {
            $this->audit($paymentEvent, 'Confirmação de pagamento revertida.', $order, [
                'previous_status' => $previousStatus,
                'status' => $order->status,
            ]);
        }

        return response()->json($this->toPayload($order, $request->user()->canViewFinancialReports()));
    }

    public function destroy(int $id)
    {
        $order = Order::find($id);

        if (! $order) {
            return response()->json(['message' => 'Pedido não encontrado.'], 404);
        }

        $this->authorize('delete', $order);

        $order->delete();
        $this->audit('orders.deleted', 'Pedido removido.', null, ['order_id' => $id]);

        return response()->json(['ok' => true]);
    }

    private function validateData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'customerId' => [$required, 'integer', Rule::exists('customers', 'id')],
            'sellerUserId' => [
                $required,
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->whereIn('role', UserRole::sellableRoles())
                    ->where('is_active', true)),
            ],
            'items' => [$required, 'array', 'min:1'],
            'items.*.productId' => ['required', 'integer', Rule::exists('products', 'id')],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unitPrice' => ['required', 'numeric', 'min:0'],
            'items.*.unitDiscount' => ['required', 'numeric', 'min:0'],
            'channel' => [$required, 'string', Rule::in(OrderMetadata::CHANNELS)],
            'status' => [$partial ? 'sometimes' : 'nullable', 'string', Rule::in(OrderMetadata::STATUSES)],
            'freight' => [$partial ? 'sometimes' : 'nullable', 'numeric', 'min:0'],
            'channelFee' => [$partial ? 'sometimes' : 'nullable', 'numeric', 'min:0'],
            'paymentMethod' => [$partial ? 'sometimes' : 'nullable', 'string', Rule::in(OrderMetadata::PAYMENT_METHODS)],
            'paymentExpiresAt' => [$partial ? 'sometimes' : 'nullable', 'date'],
            'shippingMethod' => [$required, 'string', Rule::in(OrderMetadata::SHIPPING_METHODS)],
            'trackingCode' => [$partial ? 'sometimes' : 'nullable', 'string', 'max:255'],
            'saleDate' => [$required, 'date'],
            'shippedDate' => [$partial ? 'sometimes' : 'nullable', 'date'],
            'notes' => [$partial ? 'sometimes' : 'nullable', 'string'],
        ]);
    }

    private function productLabel(Product $product): string
    {
        $brand = $product->brand?->name ?? '—';
        $model = $product->watchModel?->name ?? '—';
        $quality = $product->watchModel?->category?->has_quality
            ? $product->watchModel?->quality?->name
            : null;

        return trim($brand.' '.$model.($quality ? ' · '.$quality : ''));
    }

    /**
     * TASK-013 (RN-02): `cost` só entra no payload pra quem tem
     * `dashboard.financial.view` — não é só uma questão de UI, o objetivo é
     * o vendedor/gerente nunca receberem o custo no JSON, nem inspecionando
     * a resposta da API diretamente.
     */
    /**
     * TASK-016: `nextPostingDate`/`isLate` sempre presentes (mesmo cálculo
     * de `ShippingController::queue()`, via `ShippingScheduleCalculator`),
     * mas `null`/`false` quando o pedido não é elegível pra fila
     * (`ShippingScheduleCalculator::isEligibleForQueue`) — o frontend
     * (`OrderDetail.tsx`) usa este campo real em vez do helper hardcoded.
     */
    private function toPayload(Order $o, bool $canViewFinancials, bool $includeItems = true): array
    {
        $items = $o->relationLoaded('items') ? $o->items : $o->items()->get();

        $isEligibleForQueue = ShippingScheduleCalculator::isEligibleForQueue($o);
        $nextPostingDate = $isEligibleForQueue ? ShippingScheduleCalculator::nextPostingDate($o->paid_at) : null;

        $payload = [
            'id' => $o->id,
            'customerId' => $o->customer_id,
            'customerName' => $o->customer?->name,
            'createdByUserId' => $o->created_by_user_id,
            'sellerUserId' => $o->seller_user_id,
            'sellerUserName' => $o->sellerUser?->name ?? $o->seller,
            'channel' => $o->channel,
            'seller' => $o->sellerUser?->name ?? $o->seller,
            'status' => $o->status,
            'paidAt' => $o->paid_at?->toIso8601String(),
            'paidByUserId' => $o->paid_by_user_id,
            'paidByUserName' => $o->paidByUser?->name,
            'productId' => $o->product_id,
            'productName' => $o->product_name,
            'itemsCount' => $items->sum('quantity'),
            'items' => $includeItems
                ? $items->map(fn (OrderItem $item) => $this->toItemPayload($item, $canViewFinancials))->values()
                : [],
            'salePrice' => (float) $o->sale_price,
            'discount' => (float) $o->discount,
            'freight' => (float) $o->freight,
            'channelFee' => (float) $o->channel_fee,
            'paymentMethod' => $o->payment_method,
            'paymentExpiresAt' => $o->payment_expires_at?->toIso8601String(),
            'shippingMethod' => $o->shipping_method,
            'trackingCode' => $o->tracking_code ?? '',
            'saleDate' => $o->sale_date,
            'shippedDate' => $o->shipped_date ?? '',
            'notes' => $o->notes ?? '',
            'nextPostingDate' => $nextPostingDate?->toDateString(),
            'isLate' => $isEligibleForQueue ? ShippingScheduleCalculator::isLate($nextPostingDate, Carbon::today()) : false,
        ];

        if ($canViewFinancials) {
            $payload['cost'] = (float) $o->cost;
        }

        return $payload;
    }

    private function toItemPayload(OrderItem $item, bool $canViewFinancials): array
    {
        $payload = [
            'id' => $item->id,
            'productId' => $item->product_id,
            'productName' => $item->product_name,
            'productType' => $item->product_type,
            'brandName' => $item->brand_name,
            'modelName' => $item->model_name,
            'qualityName' => $item->quality_name,
            'quantity' => $item->quantity,
            'unitPrice' => (float) $item->unit_price,
            'unitDiscount' => (float) $item->unit_discount,
            'linePrice' => (float) $item->unit_price * $item->quantity,
            'lineDiscount' => (float) $item->unit_discount * $item->quantity,
        ];

        if ($canViewFinancials) {
            $payload['unitCost'] = (float) $item->unit_cost;
            $payload['lineCost'] = (float) $item->unit_cost * $item->quantity;
        }

        return $payload;
    }

    private function productsById(array $items)
    {
        return Product::query()
            ->with(['brand', 'watchModel.quality', 'watchModel.category'])
            ->whereIn('id', collect($items)->pluck('productId')->all())
            ->get()
            ->keyBy('id');
    }

    private function calculateTotals(array $items, $products): array
    {
        $salePrice = 0;
        $cost = 0;
        $discount = 0;

        foreach ($items as $item) {
            /** @var Product $product */
            $product = $products->get($item['productId']);
            $quantity = (int) $item['quantity'];
            $salePrice += (float) $item['unitPrice'] * $quantity;
            $cost += (float) $product->cost * $quantity;
            $discount += (float) $item['unitDiscount'] * $quantity;
        }

        return [
            'sale_price' => $salePrice,
            'cost' => $cost,
            'discount' => $discount,
            'product_name' => $this->productSummary($items, $products),
            'first_product_id' => $items[0]['productId'] ?? null,
        ];
    }

    private function productSummary(array $items, $products): string
    {
        $totalUnits = collect($items)->sum('quantity');
        /** @var Product|null $firstProduct */
        $firstProduct = isset($items[0]['productId']) ? $products->get($items[0]['productId']) : null;
        $firstLabel = $firstProduct ? $this->productLabel($firstProduct) : 'Produto';

        if (count($items) === 1) {
            $quantity = (int) ($items[0]['quantity'] ?? 1);

            return $quantity > 1 ? $firstLabel.' x'.$quantity : $firstLabel;
        }

        return $firstLabel.' + '.max($totalUnits - 1, 0).' item(ns)';
    }

    private function syncItems(Order $order, array $items, $products): void
    {
        $order->items()->delete();

        $payload = collect($items)
            ->map(function (array $item) use ($order, $products) {
                /** @var Product $product */
                $product = $products->get($item['productId']);

                return [
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $this->productLabel($product),
                    'product_type' => $product->watchModel?->category?->name ?? 'Relógios',
                    'brand_name' => $product->brand?->name,
                    'model_name' => $product->watchModel?->name,
                    'quality_name' => $product->watchModel?->category?->has_quality
                        ? $product->watchModel?->quality?->name
                        : null,
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => (float) $item['unitPrice'],
                    'unit_cost' => (float) $product->cost,
                    // TASK-005 (RN-01): comissão vigente do produto congelada
                    // no item — trocar/editar o produto depois não afeta
                    // pedidos já vendidos (CA-01). RN-03 (troca usa a
                    // comissão do novo produto) decorre naturalmente daqui:
                    // um item novo sempre lê a comissão atual do produto.
                    'unit_commission' => (float) ($product->commission_amount ?? 0),
                    'unit_discount' => (float) $item['unitDiscount'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->all();

        $order->items()->insert($payload);
    }
}
