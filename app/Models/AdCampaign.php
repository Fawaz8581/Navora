<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdCampaign extends Model
{
    public const TOP_PRICE = 200000;

    public const SIDEBAR_PRICE = 100000;

    public const DEFAULT_BANNER_WIDTH = 1200;

    public const DEFAULT_BANNER_HEIGHT = 300;

    public const SIDEBAR_BANNER_WIDTH = 300;

    public const SIDEBAR_BANNER_HEIGHT = 900;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PAYMENT_FAILED = 'payment_failed';

    public const STATUS_PAID = 'paid';

    public const STATUS_AWAITING_ASSET = 'awaiting_asset';

    public const STATUS_ASSET_SUBMITTED = 'asset_submitted';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'ad_package_id',
        'vendor_user_id',
        'package_name',
        'ad_title',
        'destination_url',
        'placement',
        'width',
        'height',
        'amount',
        'duration_days',
        'order_id',
        'snap_token',
        'snap_redirect_url',
        'payment_status',
        'midtrans_transaction_id',
        'payment_type',
        'midtrans_transaction_status',
        'status',
        'paid_at',
        'approved_by',
        'approved_at',
        'review_reason',
        'reviewed_by',
        'reviewed_at',
        'refund_key',
        'refund_status',
        'refund_requested_at',
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

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public static function priceForPlacement(string $placement): int
    {
        return $placement === 'sidebar' ? self::SIDEBAR_PRICE : self::TOP_PRICE;
    }

    /**
     * @return array{width: int, height: int}
     */
    public static function dimensionsForPlacement(string $placement): array
    {
        return $placement === 'sidebar'
            ? ['width' => self::SIDEBAR_BANNER_WIDTH, 'height' => self::SIDEBAR_BANNER_HEIGHT]
            : ['width' => self::DEFAULT_BANNER_WIDTH, 'height' => self::DEFAULT_BANNER_HEIGHT];
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
            'reviewed_at' => 'datetime',
            'refund_requested_at' => 'datetime',
            'asset_submitted_at' => 'datetime',
            'published_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
