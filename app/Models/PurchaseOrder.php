<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * سفارش خرید از تأمین‌کننده. دریافت کالا (کامل یا جزئی) موجودی و بهای تمام‌شده‌ی محصول را به‌روز می‌کند.
 */
class PurchaseOrder extends Model
{
    use Auditable;

    public const STATUS_ORDERED = 'ordered';
    public const STATUS_PARTIAL = 'partially_received';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_ORDERED => 'ثبت‌شده (منتظر دریافت)',
        self::STATUS_PARTIAL => 'دریافت جزئی',
        self::STATUS_RECEIVED => 'دریافت کامل',
        self::STATUS_CANCELLED => 'لغو شده',
    ];

    protected $fillable = [
        'po_number',
        'supplier_id',
        'branch_id',
        'status',
        'ordered_at',
        'total_cost',
        'notes',
        'created_by',
        'received_by',
        'received_at',
        'cancelled_at',
    ];

    protected $casts = [
        'ordered_at' => 'datetime',
        'received_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'total_cost' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public static function generateNumber(): string
    {
        $prefix = 'PO-'.now()->format('Ymd').'-';
        $last = static::query()->where('po_number', 'like', $prefix.'%')->orderByDesc('id')->first();
        $next = $last ? ((int) substr($last->po_number, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
