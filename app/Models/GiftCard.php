<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * گیفت کارت: موجودی شارژی که با کد قابل مصرف است.
 * موجودی فقط از طریق GiftCardService تغییر می‌کند تا هر تغییر در gift_card_transactions ثبت شود.
 */
class GiftCard extends Model
{
    use Auditable;

    protected $fillable = [
        'code',
        'initial_amount',
        'balance',
        'is_active',
        'customer_id',
        'issued_by',
        'expires_at',
    ];

    protected $casts = [
        'initial_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function transactions()
    {
        return $this->hasMany(GiftCardTransaction::class)->latest('id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** موجودی قابل مصرف است؟ */
    public function isUsable(): bool
    {
        return $this->is_active
            && (float) $this->balance > 0
            && (! $this->expires_at || $this->expires_at->isFuture());
    }

    /** کد کامل در لاگ ممیزی نمایش داده نمی‌شود */
    public static function maskCode(string $code): string
    {
        return '***'.substr($code, -4);
    }

    public static function generateCode(): string
    {
        do {
            $code = 'GC'.Str::upper(Str::random(10));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /** @return array<int, string> */
    protected function auditExcludedKeys(): array
    {
        return ['balance'];
    }

    /**
     * کد کامل گیفت کارت، کلید مصرف اعتبار است؛ در هر رویداد لاگ فقط ۴ کاراکتر آخر آن می‌آید.
     * این فیلد در auditSnapshot() (ایجاد/حذف) و auditDirty (ویرایش) هم از auditValue() عبور می‌کند.
     *
     * @return array<int, string>
     */
    protected function auditMaskedKeys(): array
    {
        return ['code'];
    }

    protected function auditValue(string $key, mixed $value): mixed
    {
        if ($key === 'code' && is_string($value) && $value !== '') {
            return self::maskCode($value);
        }

        return parent::auditValue($key, $value);
    }

    protected function auditLabel(): string
    {
        return 'گیفت کارت '.self::maskCode((string) $this->code);
    }
}
