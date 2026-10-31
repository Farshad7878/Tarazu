<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderReturn extends Model
{
    protected $fillable = [
        'gift_card_credit',
        'order_id',
        'user_id',
        'reason',
        'restock',
        'refund_amount',
        'tax_refund',
        'cash_shift_id',
    ];

    protected $casts = [
        'gift_card_credit' => 'decimal:2',
        'restock' => 'boolean',
        'refund_amount' => 'decimal:2',
        'tax_refund' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderReturnItem::class);
    }
}
