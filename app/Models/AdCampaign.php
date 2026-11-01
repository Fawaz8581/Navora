<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdCampaign extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PAYMENT_FAILED = 'payment_failed';

    public const STATUS_PAID = 'paid';

    public const STATUS_AWAITING_ASSET = 'awaiting_asset';

    public const STATUS_ASSET_SUBMITTED = 'asset_submitted';

    public const STATUS_ACTIVE = 'active';

    protected $fillable = [
        'ad_package_id',
        'vendor_user_id',
        'package_name',
        'placement',
        'width',
        'height',
        'amount',
        'duration_days',
        'order_id',
        'snap_token',
        'snap_redirect_url',
        'payment_status',
        'status',
        'paid_at',
        'approved_by',
        'approved_at',
        'asset_path',
        'asset_submitted_at',
        'published_by',
        'published_at',
        'starts_at',
        'ends_at',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_user_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(AdPackage::class, 'ad_package_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'amount' => 'integer',
            'duration_days' => 'integer',
            'paid_at' => 'datetime',
            'approved_at' => 'datetime',
            'asset_submitted_at' => 'datetime',
            'published_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
