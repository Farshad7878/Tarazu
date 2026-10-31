<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Events\CustomerRegistered;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Customer extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable;

    protected $fillable = [
        'membership_number',
        'first_name',
        'last_name',
        'phone',
        'date_of_birth',
        'total_purchases',
        'sms_consent_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'total_purchases' => 'decimal:2',
        'loyalty_points' => 'integer',
        'loyalty_lifetime_points' => 'integer',
        'sms_consent_at' => 'datetime',
        'sms_opt_out_at' => 'datetime',
    ];
    protected $appends = ['full_name','monthly_purchases'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($customer) {
            // توکن لغو اشتراک پیامک؛ بدون آن لینک لغو در پیامک‌های گروهی ساخته نمی‌شود
            $customer->unsubscribe_token ??= Str::random(48);
        });

        static::created(function ($customer) {
            event(new CustomerRegistered($customer));
        });
    }
    /**
     * شماره عضویت یکتای ۶ رقمی.
     * از id استفاده نمی‌کنیم، چون بعد از حذف مشتری جدید ممکن است شماره‌ی قبلی تکرار شود.
     */
    public static function generateMembershipNumber(): string
    {
        do {
            $number = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        } while (static::where('membership_number', $number)->exists());

        return $number;
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function discounts()
    {
        return $this->belongsToMany(Discount::class, 'customer_discounts')
                    ->withPivot('used_at')
                    ->withTimestamps();
    }

    public function activeDiscounts()
    {
        return $this->discounts()
                    ->where('is_active', true)
                    ->wherePivot('used_at', null);
    }

    public function loyaltyTransactions()
    {
        return $this->hasMany(LoyaltyTransaction::class)->latest('id');
    }

    /**
     * مشتری‌هایی که رضایت پیامک دارند و لغو نکرده‌اند (تنها گیرنده‌های مجاز پیامک گروهی).
     */
    public function scopeSmsReachable(Builder $query): Builder
    {
        return $query->whereNotNull('sms_consent_at')
            ->whereNull('sms_opt_out_at')
            ->whereNotNull('phone');
    }

    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getMonthlyPurchasesAttribute()
    {
        return $this->orders()
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->sum('total_amount');
    }

    /** @return array<int, string> */
    protected function auditExcludedKeys(): array
    {
        return ['total_purchases', 'loyalty_points', 'loyalty_lifetime_points'];
    }

    protected function auditLabel(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? '')).' ('.$this->membership_number.')';
    }
}
