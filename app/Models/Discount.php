<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable;

    protected $fillable = [
        'name',
        'type',
        'value',
        'target',
        'condition_type',
        'condition_value',
        'free_product_id',
        'message',
        'is_active',
        'auto_apply',
        'auto_disable_after_use'
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'condition_value' => 'decimal:2',
        'is_active' => 'boolean',
        'auto_apply' => 'boolean',
        'auto_disable_after_use' => 'boolean'
    ];

    public function freeProduct()
    {
        return $this->belongsTo(Product::class, 'free_product_id');
    }

    public function customers()
    {
        return $this->belongsToMany(Customer::class, 'customer_discounts')
                    ->withPivot('used_at')
                    ->withTimestamps();
    }
}
