<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'has_quality',
    ];

    protected function casts(): array
    {
        return [
            'has_quality' => 'boolean',
        ];
    }

    public function models(): HasMany
    {
        return $this->hasMany(WatchModel::class, 'category_id');
    }
}
