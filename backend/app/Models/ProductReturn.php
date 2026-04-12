<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductReturn extends Model
{
    protected $table = 'returns';

    protected $fillable = [
        'order_id',
        'customer_id',
        'created_by_user_id',
        'assigned_user_id',
        'type',
        'status',
        'reason',
        'internal_notes',
        'resolution_notes',
        'received_date',
        'resolved_date',
        'freight_cost_in',
        'watchmaker_cost',
        'freight_cost_out',
        'other_costs',
        'refund_amount',
        'return_tracking_code',
        'shipped_back_date',
    ];

    public function getTotalCostAttribute(): float
    {
        return (float) $this->freight_cost_in
            + (float) $this->watchmaker_cost
            + (float) $this->freight_cost_out
            + (float) $this->other_costs;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }
}
