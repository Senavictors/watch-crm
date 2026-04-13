<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Goal extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'created_by_user_id',
        'target_user_id',
        'name',
        'description',
        'scope',
        'calculation_type',
        'product_type_filter',
        'brand_id',
        'model_id',
        'period_cycle',
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function watchModel(): BelongsTo
    {
        return $this->belongsTo(WatchModel::class, 'model_id');
    }

    public function intervals(): HasMany
    {
        return $this->hasMany(GoalInterval::class)->orderBy('start_date');
    }
}
