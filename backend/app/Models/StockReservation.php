<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TASK-024 / ADR-006: estado de estoque de um pedido sobre um produto.
 * Uma linha por (order_id, product_id) — não é um log, é o estado atual;
 * o log é `StockMovement`.
 */
class StockReservation extends Model
{
    public const STATUS_RESERVED = 'reserved';

    public const STATUS_COMMITTED = 'committed';

    public const STATUS_RELEASED = 'released';

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Unidades que esta linha segura em `products.reserved_qty`. */
    public function heldReserved(): int
    {
        return $this->status === self::STATUS_RESERVED ? $this->quantity : 0;
    }

    /** Unidades que esta linha já baixou de `products.qty`. */
    public function heldCommitted(): int
    {
        return $this->status === self::STATUS_COMMITTED ? $this->quantity : 0;
    }
}
