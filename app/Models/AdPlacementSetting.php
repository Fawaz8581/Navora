<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdPlacementSetting extends Model
{
    protected $fillable = [
        'placement',
        'max_active_ads',
    ];

    protected function casts(): array
    {
        return [
            'max_active_ads' => 'integer',
        ];
    }
}
