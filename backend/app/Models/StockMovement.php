<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * TASK-024 / ADR-006: ledger append-only de estoque (CA-05).
 *
 * Não tem `updated_at` nem foreign key: linha de movimento nunca é editada e
 * precisa sobreviver à exclusão do produto/pedido que a originou.
 */
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'product_id',
        'order_id',
        'type',
        'quantity',
        'qty_delta',
        'reserved_delta',
        'qty_after',
        'reserved_after',
        'actor_user_id',
        'idempotency_key',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'qty_delta' => 'integer',
            'reserved_delta' => 'integer',
            'qty_after' => 'integer',
            'reserved_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
