<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftCardTransaction extends Model
{
    public const TYPE_LABELS = [
        'issue' => 'صدور',
        'redeem' => 'مصرف در سفارش',
        'refund_credit' => 'بازگشت مبلغ (مرجوعی)',
        'cancel_credit' => 'بازگشت مبلغ (لغو سفارش)',
    ];

    protected $fillable = [
        'gift_card_id',
        'type',
        'amount',
        'balance_after',
        'payment_method',
        'order_id',
        'order_return_id',
        'cash_shift_id',
        'user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function giftCard()
    {
        return $this->belongsTo(GiftCard::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }
}
