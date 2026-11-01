<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderParticipant extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'quantity',
        'payment_amount',
        'payment_status',
        'quote_status',
        'payment_order_id',
        'snap_token',
        'snap_redirect_url',
        'paid_at',
        'midtrans_transaction_id',
        'payment_type',
        'midtrans_transaction_status',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

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
            'quantity' => 'integer',
            'payment_amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }
}
