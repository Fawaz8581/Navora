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
        'delivery_contact_name',
        'delivery_contact_phone',
        'delivery_address',
        'delivery_area_id',
        'delivery_area_name',
        'payment_amount',
        'subtotal_amount',
        'shipping_amount',
        'platform_fee_amount',
        'shipping_courier',
        'shipping_service',
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
            'subtotal_amount' => 'integer',
            'shipping_amount' => 'integer',
            'platform_fee_amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }
}
