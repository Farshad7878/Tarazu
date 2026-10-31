<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * شیفت صندوق یک ریسپشن: باز شدن با موجودی اولیه، بسته شدن با شمارش نقدی و مغایرت.
 */
class CashShift extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $fillable = [
        'user_id',
        'opened_at',
        'closed_at',
        'opening_cash',
        'expected_cash',
        'counted_cash',
        'difference',
        'notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_cash' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'counted_cash' => 'decimal:2',
        'difference' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    protected function auditLabel(): string
    {
        return 'صندوق #'.$this->getKey();
    }
}
