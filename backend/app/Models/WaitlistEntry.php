<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitlistEntry extends Model
{
    protected $table = 'waitlist_entries';

    protected $fillable = [
        'customer_id',
        'product_id',
        'product_name',
        'brand_name',
        'model_name',
        'quality_name',
        'seller_user_id',
        'created_by_user_id',
        'status',
        'notes',
        'notified_at',
        'order_id',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sellerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
