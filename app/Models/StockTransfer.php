<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * انتقال موجودی بین دو شعبه. کالا با ایجاد انتقال از مبدا کم می‌شود (در راه)،
 * با دریافت به مقصد اضافه می‌شود، و با لغو (فقط قبل از دریافت) به مبدا برمی‌گردد.
 */
class StockTransfer extends Model
{
    public const STATUS_IN_TRANSIT = 'in_transit';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_IN_TRANSIT => 'در راه',
        self::STATUS_RECEIVED => 'دریافت‌شده',
        self::STATUS_CANCELLED => 'لغوشده',
    ];

    protected $fillable = [
        'transfer_number',
        'from_branch_id',
        'to_branch_id',
        'status',
        'notes',
        'created_by',
        'received_by',
        'cancelled_by',
        'received_at',
        'cancelled_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
