<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdPackage extends Model
{
    protected $fillable = [
        'name',
        'placement',
        'width',
        'height',
        'price',
        'duration_days',
        'is_active',
    ];

    public function campaigns(): HasMany
    {
        return $this->hasMany(AdCampaign::class);
    }

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'price' => 'integer',
            'duration_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
