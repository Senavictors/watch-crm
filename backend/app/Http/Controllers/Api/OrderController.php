<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\WatchModel;
use App\Models\User;
use App\Support\OrderMetadata;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function metadata()
    {
        $assignableSellers = User::query()
            ->where('role', 'vendedor')
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
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Order::query();

        if (! $user->canAccessAllRecords()) {
            $query->where('seller_user_id', $user->id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', (int) $request->input('customer_id'));
        }

        $orders = $query
            ->with(['sellerUser', 'items'])
            ->orderByDesc('sale_date')
            ->get()
            ->map(fn (Order $order) => $this->toPayload($order));

        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $seller = User::query()->findOrFail($data['sellerUserId']);
        $products = $this->productsById($data['items']);
        $totals = $this->calculateTotals($data['items'], $products);

        $order = DB::transaction(function () use ($data, $products, $request, $seller, $totals) {
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
                'shipping_method' => $data['shippingMethod'],
                'tracking_code' => $data['trackingCode'] ?? null,
                'sale_date' => $data['saleDate'],
                'shipped_date' => $data['shippedDate'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($order, $data['items'], $products);

            return $order;
        });
        $order->load(['sellerUser', 'items']);

        $this->audit('orders.created', 'Pedido criado.', $order, [
            'seller_user_id' => $order->seller_user_id,
            'status' => $order->status,
        ]);

        return response()->json($this->toPayload($order), 201);
    }

    public function update(Request $request, int $id)
    {
        $order = Order::query()->with(['sellerUser', 'items'])->find($id);

        if (! $order) {
            return response()->json(['message' => 'Pedido não encontrado.'], 404);
        }

        $this->authorize('update', $order);

        $data = $this->validateData($request, true);
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
        $order->load(['sellerUser', 'items']);

        $metadata = [
            'seller_user_id' => $order->seller_user_id,
            'status' => $order->status,
        ];
        if ($previousStatus !== $order->status) {
            $metadata['previous_status'] = $previousStatus;
        }

        $this->audit('orders.updated', 'Pedido atualizado.', $order, $metadata);

        return response()->json($this->toPayload($order));
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
                    ->where('role', 'vendedor')
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
        $quality = $product->watchModel?->product_type === WatchModel::TYPE_WATCH
            ? $product->watchModel?->quality?->name
            : null;

        return trim($brand.' '.$model.($quality ? ' · '.$quality : ''));
    }

    private function toPayload(Order $o): array
    {
        $items = $o->relationLoaded('items') ? $o->items : $o->items()->get();

        return [
            'id' => $o->id,
            'customerId' => $o->customer_id,
            'createdByUserId' => $o->created_by_user_id,
            'sellerUserId' => $o->seller_user_id,
            'sellerUserName' => $o->sellerUser?->name ?? $o->seller,
            'channel' => $o->channel,
            'seller' => $o->sellerUser?->name ?? $o->seller,
            'status' => $o->status,
            'productId' => $o->product_id,
            'productName' => $o->product_name,
            'itemsCount' => $items->sum('quantity'),
            'items' => $items->map(fn (OrderItem $item) => $this->toItemPayload($item))->values(),
            'salePrice' => (float) $o->sale_price,
            'cost' => (float) $o->cost,
            'discount' => (float) $o->discount,
            'freight' => (float) $o->freight,
            'channelFee' => (float) $o->channel_fee,
            'paymentMethod' => $o->payment_method,
            'shippingMethod' => $o->shipping_method,
            'trackingCode' => $o->tracking_code ?? '',
            'saleDate' => $o->sale_date,
            'shippedDate' => $o->shipped_date ?? '',
            'notes' => $o->notes ?? '',
        ];
    }

    private function toItemPayload(OrderItem $item): array
    {
        return [
            'id' => $item->id,
            'productId' => $item->product_id,
            'productName' => $item->product_name,
            'productType' => $item->product_type,
            'brandName' => $item->brand_name,
            'modelName' => $item->model_name,
            'qualityName' => $item->quality_name,
            'quantity' => $item->quantity,
            'unitPrice' => (float) $item->unit_price,
            'unitCost' => (float) $item->unit_cost,
            'unitDiscount' => (float) $item->unit_discount,
            'linePrice' => (float) $item->unit_price * $item->quantity,
            'lineCost' => (float) $item->unit_cost * $item->quantity,
            'lineDiscount' => (float) $item->unit_discount * $item->quantity,
        ];
    }

    private function productsById(array $items)
    {
        return Product::query()
            ->with(['brand', 'watchModel.quality'])
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
                    'product_type' => $product->watchModel?->product_type ?? WatchModel::TYPE_WATCH,
                    'brand_name' => $product->brand?->name,
                    'model_name' => $product->watchModel?->name,
                    'quality_name' => $product->watchModel?->product_type === WatchModel::TYPE_WATCH
                        ? $product->watchModel?->quality?->name
                        : null,
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => (float) $item['unitPrice'],
                    'unit_cost' => (float) $product->cost,
                    'unit_discount' => (float) $item['unitDiscount'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->all();

        $order->items()->insert($payload);
    }
}
