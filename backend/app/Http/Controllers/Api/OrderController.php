<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::query()
            ->orderByDesc('sale_date')
            ->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'customerId' => $o->customer_id,
                'channel' => $o->channel,
                'seller' => $o->seller,
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
                'trackingCode' => $o->tracking_code,
                'saleDate' => $o->sale_date,
                'shippedDate' => $o->shipped_date,
                'notes' => $o->notes,
            ]);

        return response()->json($orders);
    }
}
