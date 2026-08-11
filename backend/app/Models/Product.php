<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'model_id',
        'cost',
        'price',
        'price_pix',
        'price_card',
        'commission_amount',
        'stock',
        'qty',
        'reserved_qty',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'reserved_qty' => 'integer',
        ];
    }

    /**
     * TASK-024 / ADR-006: unidades realmente vendáveis. `reserved_qty` é a
     * parcela de `qty` já prometida a pedidos vivos ainda não pagos.
     */
    public function availableQty(): int
    {
        return max((int) $this->qty - (int) $this->reserved_qty, 0);
    }

    /**
     * Rótulo legível do produto (marca + modelo + qualidade quando a
     * categoria usa qualidade). Usado em mensagens de erro de estoque.
     */
    public function displayLabel(): string
    {
        $brand = $this->brand?->name ?? '—';
        $model = $this->watchModel?->name ?? '—';
        $quality = $this->watchModel?->category?->has_quality
            ? $this->watchModel?->quality?->name
            : null;

        return trim($brand.' '.$model.($quality ? ' · '.$quality : ''));
    }

    public function stockReservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function watchModel(): BelongsTo
    {
        return $this->belongsTo(WatchModel::class, 'model_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
