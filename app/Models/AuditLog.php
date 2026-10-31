<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_LABELS = [
        'created' => 'ایجاد',
        'updated' => 'ویرایش',
        'deleted' => 'حذف',
        'stock_adjusted' => 'تعدیل دستی موجودی',
        'return' => 'بازگشت کالا',
        'loyalty_adjusted' => 'تعدیل امتیاز',
        'campaign_cancelled' => 'لغو کمپین پیامکی',
        'backup_created' => 'ایجاد پشتیبان',
        'backup_restored' => 'بازیابی پشتیبان',
        'backup_deleted' => 'حذف پشتیبان',
    ];

    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'subject_type',
        'subject_id',
        'subject_label',
        'description',
        'changes',
        'ip',
        'created_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getActionLabelAttribute(): string
    {
        return self::ACTION_LABELS[$this->action] ?? $this->action;
    }
}
