<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorSubscription extends Model
{
    protected $fillable = [
        'vendor_user_id',
        'order_id',
        'amount',
        'payment_status',
        'snap_token',
        'snap_redirect_url',
        'midtrans_transaction_id',
        'payment_type',
        'midtrans_transaction_status',
        'paid_at',
        'starts_at',
        'ends_at',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_user_id');
    }

    public function isActive(): bool
    {
        return $this->payment_status === 'paid'
            && $this->starts_at !== null
            && $this->ends_at !== null
            && $this->starts_at->isPast()
            && $this->ends_at->isFuture();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
