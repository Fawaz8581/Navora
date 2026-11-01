<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const TYPE_DIRECT = 'langsung';

    public const TYPE_GROUP = 'patungan';

    /**
     * @var array<string, string>
     */
    public const TYPE_LABELS = [
        self::TYPE_DIRECT => 'Pesanan langsung',
        self::TYPE_GROUP => 'Pesanan patungan',
    ];

    public const STATUS_WAITING = 'menunggu_konfirmasi';

    public const STATUS_PROCESSING = 'diproses';

    public const STATUS_PRODUCTION = 'dalam_produksi';

    public const STATUS_SHIPPED = 'dikirim';

    public const STATUS_RECEIVED = 'diterima';

    public const STATUS_CANCELLED = 'dibatalkan';

    public const SIMULATION_STAGE_DELAY_SECONDS = 20;

    /**
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        self::STATUS_WAITING => 'Menunggu konfirmasi',
        self::STATUS_PROCESSING => 'Diproses',
        self::STATUS_PRODUCTION => 'Dalam produksi',
        self::STATUS_SHIPPED => 'Dikirim',
        self::STATUS_RECEIVED => 'Diterima',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'vendor_user_id',
        'type',
        'product_name',
        'quantity',
        'packaging_details',
        'delivery_address',
        'notes',
        'status',
        'is_simulated',
        'unit_price',
        'total_amount',
        'payment_status',
        'payment_order_id',
        'snap_token',
        'snap_redirect_url',
        'paid_at',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_user_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(OrderParticipant::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function nextSimulatedStatus(): ?string
    {
        return match ($this->status) {
            self::STATUS_PROCESSING => self::STATUS_PRODUCTION,
            self::STATUS_PRODUCTION => self::STATUS_SHIPPED,
            self::STATUS_SHIPPED => self::STATUS_RECEIVED,
            default => null,
        };
    }

    public static function simulatedStatusNote(string $status): string
    {
        return match ($status) {
            self::STATUS_PRODUCTION => 'Simulasi: pesanan sedang diproduksi oleh vendor.',
            self::STATUS_SHIPPED => 'Simulasi: pesanan sedang dalam perjalanan ke alamat pengiriman.',
            self::STATUS_RECEIVED => 'Simulasi: pesanan ditandai telah diterima.',
            default => 'Simulasi otomatis; bukan konfirmasi produksi, pengiriman, atau penerimaan nyata.',
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_simulated' => 'boolean',
            'unit_price' => 'integer',
            'total_amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }
}
