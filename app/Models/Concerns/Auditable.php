<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * ثبت خودکار تغییرات مدل در لاگ ممیزی (ایجاد، ویرایش، حذف) همراه با کاربر و زمان.
 *
 * مدل‌ها با override کردن متدهای زیر تنظیم می‌شوند:
 *  - auditEvents(): کدام رویدادها ثبت شوند
 *  - auditExcludedKeys(): فیلدهایی که تغییرشان ثبت نمی‌شود (فیلدهای خودکار یا پرتکرار)
 *  - auditMaskedKeys(): فیلدهای حساس که مقدارشان در لاگ پنهان می‌شود
 *  - auditLabel(): عنوان خوانا از رکورد
 *
 * برای تغییراتی که از جای دیگری (مثلاً انبار) ثبت می‌شوند، $model->auditSkipOnce را پیش از save پر کنید.
 */
trait Auditable
{
    /** @var array<int, string> فیلدهایی که فقط برای ذخیره‌ی بعدی نادیده گرفته می‌شوند */
    public array $auditSkipOnce = [];

    /** @var array<string, mixed> */
    protected array $auditDirty = [];

    /** @var array<string, mixed> */
    protected array $auditBefore = [];

    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            if (! $model->auditsEvent('created')) {
                return;
            }

            app(AuditLogger::class)->record('created', $model, 'ایجاد', $model->auditSnapshot());
        });

        static::updating(function (Model $model) {
            $dirty = array_diff_key(
                $model->getDirty(),
                array_flip(array_merge(['updated_at'], $model->auditExcludedKeys(), $model->auditSkipOnce)),
            );

            $model->auditDirty = $dirty;
            $model->auditBefore = array_intersect_key($model->getOriginal(), $dirty);
            $model->auditSkipOnce = [];
        });

        static::updated(function (Model $model) {
            $dirty = $model->auditDirty;
            $before = $model->auditBefore;
            $model->auditDirty = [];
            $model->auditBefore = [];

            if (! $model->auditsEvent('updated')) {
                return;
            }

            $changes = [];
            foreach ($dirty as $key => $new) {
                $old = $before[$key] ?? null;

                if ($model->auditScalar($old) === $model->auditScalar($new)) {
                    continue;
                }

                $changes[$key] = [
                    'old' => $model->auditValue($key, $old),
                    'new' => $model->auditValue($key, $new),
                ];
            }

            if ($changes) {
                app(AuditLogger::class)->record('updated', $model, 'ویرایش', $changes);
            }
        });

        static::deleted(function (Model $model) {
            if (! $model->auditsEvent('deleted')) {
                return;
            }

            app(AuditLogger::class)->record('deleted', $model, 'حذف', $model->auditSnapshot());
        });
    }

    /** @return array<int, string> */
    protected function auditEvents(): array
    {
        return ['created', 'updated', 'deleted'];
    }

    /** @return array<int, string> */
    protected function auditExcludedKeys(): array
    {
        return [];
    }

    /** @return array<int, string> */
    protected function auditMaskedKeys(): array
    {
        return ['password', 'remember_token'];
    }

    protected function auditLabel(): string
    {
        return (string) ($this->getAttribute('name')
            ?? $this->getAttribute('po_number')
            ?? ('#'.$this->getKey()));
    }

    public function auditsEvent(string $event): bool
    {
        return in_array($event, $this->auditEvents(), true);
    }

    /**
     * عنوان خوانا برای لاگ (عمومی تا AuditLogger بتواند بخواند)
     */
    public function auditLabelForLog(): string
    {
        return $this->auditLabel();
    }

    /**
     * تصویر کامل فیلدهای ثبت‌شده برای رویدادهای ایجاد/حذف
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    protected function auditSnapshot(): array
    {
        $snapshot = [];
        $excluded = array_merge(['updated_at', 'created_at'], $this->auditExcludedKeys());

        foreach ($this->getAttributes() as $key => $value) {
            if (in_array($key, $excluded, true)) {
                continue;
            }

            $snapshot[$key] = ['old' => null, 'new' => $this->auditValue($key, $value)];
        }

        return $snapshot;
    }

    protected function auditValue(string $key, mixed $value): mixed
    {
        if (in_array($key, $this->auditMaskedKeys(), true)) {
            return '***';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return $value;
    }

    protected function auditScalar(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }
}
