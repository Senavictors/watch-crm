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

    private function nullableString(?string $value): ?string
    {
        $trimmed = $value !== null ? trim($value) : null;

        return $trimmed !== '' ? $trimmed : null;
    }
}
