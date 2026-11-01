<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorListing extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minimum_order_quantity' => 'integer',
            'approved_at' => 'datetime',
        ];
    }
}
