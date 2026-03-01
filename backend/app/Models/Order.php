<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'customer_id',
        'product_id',
        'product_name',
        'channel',
        'seller',
        'status',
        'sale_price',
        'cost',
        'discount',
        'freight',
        'channel_fee',
        'payment_method',
        'shipping_method',
        'tracking_code',
        'sale_date',
        'shipped_date',
        'notes',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
