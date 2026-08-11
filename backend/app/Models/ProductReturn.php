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

    protected function casts(): array
    {
        return [
            'voided_at' => 'datetime',
        ];
    }

    /**
     * TASK-025 / ADR-007: devolução estornada permanece no histórico, mas
     * deixa de valer em faturamento, comissão, meta e dashboard. Consultas
     * que fazem `join('returns', ...)` precisam do equivalente
     * `whereNull('returns.voided_at')` — o escopo só alcança quem consulta
     * pelo model.
     */
    public function scopeEffective($query)
    {
        return $query->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    /**
     * TASK-025 (RN-04): o que impede apagar a devolução em definitivo.
     *
     * @return array<string, int|string>
     */
    public function financialFootprint(): array
    {
        return array_filter([
            'reembolso' => $this->refund_amount !== null && (float) $this->refund_amount > 0
                ? (float) $this->refund_amount
                : null,
            'custos lançados' => $this->total_cost > 0 ? $this->total_cost : null,
            'transições de status' => max($this->statusHistories()->count() - 1, 0) ?: null,
        ], fn ($value) => $value !== null);
    }

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

    /**
     * Ordenado por `created_at`/`id` asc (log cronológico) — ver
     * `ReturnController::toStatusHistoryPayload()`.
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(ReturnStatusHistory::class, 'return_id')
            ->orderBy('created_at')
            ->orderBy('id');
    }
}
