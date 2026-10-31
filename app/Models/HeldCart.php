<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeldCart extends Model
{
    protected $fillable = [
        'user_id',
        'customer_id',
        'items',
        'notes',
    ];

    protected $casts = [
        'items' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
