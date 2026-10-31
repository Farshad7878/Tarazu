<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * تخفیف‌هایی که واقعاً روی یک سفارش اعمال شده‌اند (برای تاریخچه و گزارش‌ها)
 */
class OrderDiscount extends Model
{
    protected $fillable = [
        'order_id',
        'discount_id',
        'name',
        'message',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }
}
