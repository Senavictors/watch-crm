<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\OrderMetadata;
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

        $orders = $query
            ->with('sellerUser')
            ->orderByDesc('sale_date')
            ->get()
            ->map(fn (Order $order) => $this->toPayload($order));

        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $product = Product::query()->with(['brand', 'watchModel.quality'])->findOrFail($data['productId']);

        $order = Order::create([
            'customer_id' => $data['customerId'],
            'created_by_user_id' => $request->user()->id,
            'seller_user_id' => $data['sellerUserId'],
            'product_id' => $product->id,
            'product_name' => $this->productLabel($product),
            'channel' => $data['channel'],
            'seller' => User::query()->findOrFail($data['sellerUserId'])->name,
            'status' => $data['status'] ?? 'Novo',
            'sale_price' => $data['salePrice'],
            'cost' => $product->cost,
            'discount' => $data['discount'] ?? 0,
            'freight' => $data['freight'] ?? 0,
            'channel_fee' => $data['channelFee'] ?? 0,
            'payment_method' => $data['paymentMethod'] ?? null,
            'shipping_method' => $data['shippingMethod'],
            'tracking_code' => $data['trackingCode'] ?? null,
            'sale_date' => $data['saleDate'],
            'shipped_date' => $data['shippedDate'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        $order->load('sellerUser');

        $this->audit('orders.created', 'Pedido criado.', $order, [
            'seller_user_id' => $order->seller_user_id,
            'status' => $order->status,
        ]);

        return response()->json($this->toPayload($order), 201);
    }

    public function update(Request $request, int $id)
    {
        $order = Order::query()->with('sellerUser')->find($id);

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

        if (array_key_exists('productId', $data)) {
            $product = Product::query()->with(['brand', 'watchModel.quality'])->findOrFail($data['productId']);
            $order->product_id = $product->id;
            $order->product_name = $this->productLabel($product);
            $order->cost = $product->cost;
        }

        $fieldMap = [
            'channel' => 'channel',
            'status' => 'status',
            'salePrice' => 'sale_price',
            'discount' => 'discount',
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

        $order->save();
        $order->load('sellerUser');

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
            'productId' => [$required, 'integer', Rule::exists('products', 'id')],
            'channel' => [$required, 'string', Rule::in(OrderMetadata::CHANNELS)],
            'status' => [$partial ? 'sometimes' : 'nullable', 'string', Rule::in(OrderMetadata::STATUSES)],
            'salePrice' => [$required, 'numeric', 'min:0'],
            'discount' => [$partial ? 'sometimes' : 'nullable', 'numeric', 'min:0'],
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
        $quality = $product->watchModel?->quality?->name;

        return trim($brand.' '.$model.($quality ? ' · '.$quality : ''));
    }

    private function toPayload(Order $o): array
    {
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
}
