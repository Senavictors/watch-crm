<?php

namespace App\Models;

use App\Enums\UserRole;
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
     * TASK-026 (RN-03) — escopo ÚNICO de visibilidade de pós-venda. Listagem,
     * detalhe, filtros, contadores e contexto de IA usam este mesmo escopo;
     * qualquer consulta nova sobre `returns` que exponha dados ao usuário
     * precisa passar por aqui, senão reabre o IDOR do achado 3.
     *
     * Matriz aprovada (documentada em DOCUMENTACAO.md, seção 5.12):
     * - `owner`/`admin`/`gerente`: todas (já é o critério de
     *   `canAccessAllRecords()` em todo o sistema).
     * - `garantia`: todas — é a fila de trabalho do papel. É uma decisão
     *   explícita, não o efeito colateral de não filtrar nada.
     * - `vendedor`: devolução de pedido próprio, criada por ele ou atribuída
     *   a ele. As três condições existem porque uma devolução pode nascer
     *   sem pedido vinculado (avulsa) e ainda assim ser trabalho dele.
     */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->canAccessAllRecords() || $user->getRoleName() === UserRole::Guarantee->value) {
            return $query;
        }

        return $query->where(function ($builder) use ($user) {
            $builder->where('returns.created_by_user_id', $user->id)
                ->orWhere('returns.assigned_user_id', $user->id)
                ->orWhereHas('order', fn ($order) => $order->where('seller_user_id', $user->id));
        });
    }

    /**
     * Mesma regra do escopo, aplicada a um registro já carregado. Usada pela
     * `ProductReturnPolicy` — manter as duas leituras na mesma classe evita
     * que lista e detalhe divirjam com o tempo.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($user->canAccessAllRecords() || $user->getRoleName() === UserRole::Guarantee->value) {
            return true;
        }

        if ($this->created_by_user_id === $user->id || $this->assigned_user_id === $user->id) {
            return true;
        }

        $order = $this->relationLoaded('order') ? $this->order : $this->order()->first();

        return $order !== null && $order->seller_user_id === $user->id;
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
