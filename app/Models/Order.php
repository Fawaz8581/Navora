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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_simulated' => 'boolean',
        ];
    }
}
