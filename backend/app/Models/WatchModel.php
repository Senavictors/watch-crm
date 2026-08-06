<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WatchModel extends Model
{
    use HasFactory;

    protected $table = 'models';

    protected $fillable = [
        'brand_id',
        'name',
        'category_id',
        'quality_id',
        'quality_key',
        'image_path',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function quality(): BelongsTo
    {
        return $this->belongsTo(Quality::class, 'quality_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'model_id');
    }
}
