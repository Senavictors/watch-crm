<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TASK-019 (RN-03) — log imutável de marcações de atrito de um cliente
 * (`CustomerController::addFrictionNote()`). Só tem `created_at` (sem
 * `updated_at`): uma observação já registrada nunca é editada nem removida
 * — mesmo padrão de `ReturnStatusHistory` (TASK-017).
 */
class CustomerFrictionNote extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'customer_id',
        'note',
        'created_by_user_id',
        'created_at',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
