<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorListing extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const PRICING_FINAL = 'final';

    public const PRICING_NEGOTIABLE = 'negotiable';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'vendor_user_id',
        'client_reference',
        'name',
        'category',
        'material',
        'minimum_order_quantity',
        'price_per_unit',
        'pricing_type',
        'description',
        'status',
        'approved_by',
        'approved_at',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function media(): HasMany
    {
        return $this->hasMany(VendorListingMedia::class)->orderBy('position');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minimum_order_quantity' => 'integer',
            'price_per_unit' => 'integer',
            'approved_at' => 'datetime',
        ];
    }
}
