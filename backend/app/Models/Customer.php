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
        'owner_user_id',
    ];

    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $this->nullableString($value);
    }

    public function setInstagramAttribute(?string $value): void
    {
        $this->attributes['instagram'] = $this->nullableString($value);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    private function nullableString(?string $value): ?string
    {
        $trimmed = $value !== null ? trim($value) : null;

        return $trimmed !== '' ? $trimmed : null;
    }
}
