<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostingDay extends Model
{
    protected $fillable = [
        'weekday',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
