<?php

namespace App\Models;

use Database\Factories\BusinessProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessProfile extends Model
{
    /** @use HasFactory<BusinessProfileFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_name',
        'phone',
        'category',
        'location',
        'description',
        'product_type',
        'packaging_type',
        'preferred_material',
        'monthly_quantity',
        'product_categories',
        'materials',
        'minimum_order_quantity',
        'monthly_capacity',
        'production_lead_time_days',
        'onboarding_step',
        'completed_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_quantity' => 'integer',
            'minimum_order_quantity' => 'integer',
            'monthly_capacity' => 'integer',
            'production_lead_time_days' => 'integer',
            'onboarding_step' => 'integer',
            'completed_at' => 'datetime',
        ];
    }
}
