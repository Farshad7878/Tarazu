<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * هر تغییر امتیاز وفاداری یک ردیف دارد (کسب، استفاده، برگشت، تعدیل دستی).
 * points علامت‌دار است؛ balance_after موجودی بعد از همین تغییر.
 */
class LoyaltyTransaction extends Model
{
    public const TYPE_LABELS = [
        'earn' => 'کسب امتیاز',
        'redeem' => 'استفاده از امتیاز',
        'reverse_earn' => 'لغو امتیاز کسب‌شده',
        'restore_redeem' => 'بازگشت امتیاز مصرف‌شده',
        'adjust' => 'تعدیل دستی',
    ];

    protected $fillable = [
        'customer_id',
        'order_id',
        'order_return_id',
        'user_id',
        'type',
        'points',
        'balance_after',
        'reason',
    ];

    protected $casts = [
        'points' => 'integer',
        'balance_after' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }
}
