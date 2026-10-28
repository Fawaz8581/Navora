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
        'nib',
        'nib_document_path',
        'work_sample_paths',
        'production_photo_paths',
        'certificate_paths',
        'verification_status',
        'verification_notes',
        'verification_submitted_at',
        'verification_reviewed_at',
        'verified_by',
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
            'nib' => 'encrypted',
            'work_sample_paths' => 'array',
            'production_photo_paths' => 'array',
            'certificate_paths' => 'array',
            'verification_submitted_at' => 'datetime',
            'verification_reviewed_at' => 'datetime',
        ];
    }
}
