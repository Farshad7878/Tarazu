<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    public const TYPE_LABELS = [
        'sale' => 'فروش',
        'cancel' => 'لغو سفارش',
        'return' => 'بازگشت کالا',
        'return_not_restocked' => 'بازگشت بدون ورود به انبار',
        'damage' => 'ضایعات / خرابی',
        'count' => 'شمارش انبار',
        'correction' => 'تعدیل دستی',
        'initial' => 'موجودی اولیه',
        'purchase' => 'خرید از تأمین‌کننده',
        'transfer_out' => 'انتقال به شعبه دیگر',
        'transfer_in' => 'دریافت انتقال از شعبه دیگر',
        'transfer_cancel' => 'لغو انتقال (بازگشت به مبدا)',
    ];

    protected $fillable = [
        'product_id',
        'branch_id',
        'user_id',
        'order_id',
        'order_return_id',
        'purchase_order_id',
        'stock_transfer_id',
        'type',
        'quantity_change',
        'quantity_before',
        'quantity_after',
        'reason',
    ];

    protected $casts = [
        'quantity_change' => 'integer',
        'quantity_before' => 'integer',
        'quantity_after' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function stockTransfer()
    {
        return $this->belongsTo(StockTransfer::class);
    }
}
