<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderExchange extends Model
{
    protected $fillable = [
        'original_order_id',
        'new_order_id',
        'order_return_id',
        'user_id',
        'difference',
    ];

    protected $casts = [
        'difference' => 'decimal:2',
    ];

    public function originalOrder()
    {
        return $this->belongsTo(Order::class, 'original_order_id');
    }

    public function newOrder()
    {
        return $this->belongsTo(Order::class, 'new_order_id');
    }

    public function orderReturn()
    {
        return $this->belongsTo(OrderReturn::class, 'order_return_id');
    }
}
