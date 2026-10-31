<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsCampaign extends Model
{
    use \App\Models\Concerns\Auditable;

    public const SEGMENT_LABELS = [
        'all' => 'همه‌ی مشتری‌های دارای رضایت',
        'inactive' => 'مشتری‌های غیرفعال (بدون خرید در N روز اخیر)',
        'big_spenders' => 'مشتری‌های پرخرید (مجموع خرید حداقل X)',
    ];

    public const STATUS_LABELS = [
        'active' => 'در حال ارسال',
        'completed' => 'تمام شده',
        'cancelled' => 'لغو شده',
    ];

    protected $fillable = [
        'name',
        'message',
        'segment_type',
        'segment_days',
        'segment_min_total',
        'status',
        'total_recipients',
        'sent_count',
        'failed_count',
        'skipped_count',
        'created_by',
        'completed_at',
    ];

    protected $casts = [
        'segment_min_total' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function recipients()
    {
        return $this->hasMany(SmsCampaignRecipient::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getSegmentLabelAttribute(): string
    {
        return self::SEGMENT_LABELS[$this->segment_type] ?? $this->segment_type;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /** فقط ایجاد کمپین در لاگ ثبت می‌شود؛ پیشرفت ارسال در خود کمپین است */
    protected function auditEvents(): array
    {
        return ['created'];
    }
}
