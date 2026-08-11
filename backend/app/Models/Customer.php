<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'instagram',
        'street',
        'number',
        'complement',
        'zip_code',
        'city',
        'state',
        'owner_user_id',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    /**
     * TASK-025 / ADR-007: cliente arquivado sai das listagens e lookups do
     * dia a dia. Arquivar é visibilidade, não exclusão — os pedidos,
     * devoluções e comissões dele continuam valendo em todo cálculo
     * financeiro.
     */
    public function scopeNotArchived($query)
    {
        return $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by_user_id');
    }

    /**
     * TASK-025 (RN-01): o que impede a exclusão definitiva do cliente.
     * Devoluções e notas entram junto com pedidos — a decisão de negócio
     * (ADR-007) trata os três como histórico.
     *
     * @return array<string, int>
     */
    public function historyCounts(): array
    {
        return array_filter([
            'pedidos' => $this->orders()->count(),
            'devoluções' => ProductReturn::query()->where('customer_id', $this->id)->count(),
            'marcações de atrito' => $this->frictionNotes()->count(),
        ]);
    }

    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $this->nullableString($value);
    }

    public function setInstagramAttribute(?string $value): void
    {
        $this->attributes['instagram'] = $this->nullableString($value);
    }

    public function setStreetAttribute(?string $value): void
    {
        $this->attributes['street'] = $this->nullableString($value);
    }

    public function setComplementAttribute(?string $value): void
    {
        $this->attributes['complement'] = $this->nullableString($value);
    }

    public function setZipCodeAttribute(?string $value): void
    {
        $this->attributes['zip_code'] = $this->nullableString($value);
    }

    public function setCityAttribute(?string $value): void
    {
        $this->attributes['city'] = $this->nullableString($value);
    }

    public function setStateAttribute(?string $value): void
    {
        $this->attributes['state'] = $this->nullableString($value);
    }

    public function setNumberAttribute(?string $value): void
    {
        $this->attributes['number'] = $this->nullableString($value);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * TASK-019 (RN-03) — histórico imutável de marcações de atrito,
     * cronológico (`CustomerController::show()`).
     */
    public function frictionNotes(): HasMany
    {
        return $this->hasMany(CustomerFrictionNote::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    private function nullableString(?string $value): ?string
    {
        $trimmed = $value !== null ? trim($value) : null;

        return $trimmed !== '' ? $trimmed : null;
    }
}
