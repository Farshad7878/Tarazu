<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * ثبت رویدادهای ممیزی: چه کسی، چه زمانی، روی کدام رکورد، چه چیزی تغییر کرد.
 */
class AuditLogger
{
    /**
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     */
    public function record(string $action, ?Model $subject, string $description, array $changes = []): AuditLog
    {
        $user = auth()->user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subject ? mb_substr($subject->auditLabelForLog(), 0, 255) : null,
            'description' => mb_substr($description, 0, 255),
            'changes' => $changes ?: null,
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * ثبت رویداد دستی که مدل مستقیم ندارد (مثلاً پشتیبان‌گیری)
     *
     * @param  array<string, mixed>  $details
     */
    public function note(string $action, string $description, array $details = []): AuditLog
    {
        $user = auth()->user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => $action,
            'description' => mb_substr($description, 0, 255),
            'changes' => $details ?: null,
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
