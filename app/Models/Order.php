<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable;

    protected $fillable = [
        'order_number',
        'client_uuid',
        'customer_id',
        'reception_id',
        'branch_id',
        'subtotal',
        'discount_amount',
        'total_amount',
        'tax_rate',
        'tax_amount',
        'refunded_amount',
        'loyalty_points_earned',
        'loyalty_points_redeemed',
        'payment_method',
        'gift_card_id',
        'gift_card_amount',
        'cash_shift_id',
        'notes'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'gift_card_amount' => 'decimal:2',
        'loyalty_points_earned' => 'integer',
        'loyalty_points_redeemed' => 'integer',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashShift()
    {
        return $this->belongsTo(CashShift::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function reception()
    {
        return $this->belongsTo(User::class, 'reception_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function returns()
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function discounts()
    {
        return $this->hasMany(OrderDiscount::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            $order->order_number = static::generateOrderNumber();
        });

        // نکته: مجموع خرید مشتری فقط یک‌بار و با مبلغ نهایی (پس از تخفیف)
        // در OrderController::store به‌روز می‌شود. اینجا نباید دوباره افزایش یابد.
    }

    /**
     * قید «سفارش در شمارش‌ها بیاید»: سفارشی که همه‌ی ردیف‌هایش برگشت خورده (وضعیت full) بیرون می‌ماند.
     * به‌صورت Closure است تا هم روی Eloquent و هم روی Query Builder خام (جدول orders) قابل استفاده باشد.
     */
    public static function countableConstraint(): \Closure
    {
        return function ($query) {
            $query->whereNotExists(function ($items) {
                $items->selectRaw('1')->from('order_items')->whereColumn('order_items.order_id', 'orders.id');
            })->orWhereExists(function ($items) {
                $items->selectRaw('1')->from('order_items')
                    ->whereColumn('order_items.order_id', 'orders.id')
                    ->whereColumn('order_items.returned_quantity', '<', 'order_items.quantity');
            });
        };
    }

    public function scopeCountable($query)
    {
        return $query->where(static::countableConstraint());
    }

    /** none | partial | full — بر اساس تعداد برگشتی ردیف‌ها (items باید لود شده باشند) */
    public function returnedStatus(): string
    {
        $items = $this->items;

        if ($items->isEmpty() || $items->every(fn ($i) => $i->returned_quantity === 0)) {
            return 'none';
        }

        return $items->every(fn ($i) => $i->returned_quantity >= $i->quantity) ? 'full' : 'partial';
    }

    /** آیا هنوز می‌شود بازگشت ثبت کرد؟ (کامل نشده و در مهلت باشد) */
    public function canBeReturned(): bool
    {
        return $this->items->contains(fn ($i) => $i->returnable_quantity > 0)
            && $this->created_at->gte(now()->subDays((int) config('returns.window_days'))->startOfDay());
    }

    private static function generateOrderNumber()
    {
        $prefix = 'ORD';
        $date = now()->format('Ymd');
        // بیشینهٔ شمارهٔ امروز بر اساس خود شماره (نه created_at) تا با رکوردهای
        // بازه‌ای/حذف‌شده برخورد نکند. برای جلوگیری از تداخل همزمان، unique روی ستون است.
        $lastNumber = static::where('order_number', 'like', "{$prefix}{$date}%")
            ->orderByDesc('order_number')
            ->value('order_number');

        $sequence = $lastNumber ? (int) substr($lastNumber, -4) + 1 : 1;

        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /** فقط حذف (لغو) سفارش ثبت می‌شود؛ فروش‌های عادی در جدول سفارش‌ها هستند */
    protected function auditEvents(): array
    {
        return ['deleted'];
    }

    protected function auditLabel(): string
    {
        return 'سفارش '.$this->order_number;
    }

    public function giftCard()
    {
        return $this->belongsTo(GiftCard::class);
    }
}
