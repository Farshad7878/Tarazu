<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    /**
     * فیلدهای قابل تخصیص
     */
    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'returned_quantity',
        'unit_price',
        'total_price',
        'notes',
    ];

    /**
     * نوع داده‌ای فیلدها
     */
    protected $casts = [
        'quantity' => 'integer',
        'returned_quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * مقادیر پیش‌فرض
     */
    protected $attributes = [
        'quantity' => 1,
        'unit_price' => 0,
        'total_price' => 0,
    ];

    /**
     * رابطه با سفارش
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * تعداد باقی‌مانده‌ای که هنوز قابل بازگشت است
     */
    public function getReturnableQuantityAttribute(): int
    {
        return max(0, $this->quantity - $this->returned_quantity);
    }

    /**
     * رابطه با محصول
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * محاسبه خودکار total_price قبل از ذخیره
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($orderItem) {
            $orderItem->calculateTotalPrice();
        });

        static::updating(function ($orderItem) {
            $orderItem->calculateTotalPrice();
        });
    }

    /**
     * محاسبه قیمت کل آیتم
     */
    public function calculateTotalPrice()
    {
        $this->total_price = $this->quantity * $this->unit_price;

        return $this;
    }

    /**
     |--------------------------------------------------------------------------
     | QUERY SCOPES
     |--------------------------------------------------------------------------
     */

    /**
     |--------------------------------------------------------------------------
     | STATISTICAL METHODS
     |--------------------------------------------------------------------------
     */
}
