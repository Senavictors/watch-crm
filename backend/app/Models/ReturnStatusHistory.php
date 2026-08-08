<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TASK-017 — log imutável de transições de status de uma devolução/garantia
 * (`ReturnController::store()`/`update()` via `ReturnStatusTransition`).
 * Só tem `created_at` (sem `updated_at`): um evento já registrado nunca é
 * editado.
 */
class ReturnStatusHistory extends Model
{
    protected $table = 'return_status_history';

    public const UPDATED_AT = null;

    protected $fillable = [
        'return_id',
        'from_status',
        'to_status',
        'actor_user_id',
        'notes',
        'created_at',
    ];

    public function productReturn(): BelongsTo
    {
        return $this->belongsTo(ProductReturn::class, 'return_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
