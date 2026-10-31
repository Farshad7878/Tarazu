<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ثبت اعلان ماندگار برای همه‌ی ادمین‌های فعال.
 * notifications.user_id کلید خارجی و NOT NULL است، پس برای هر ادمین یک ردیف جدا ساخته می‌شود.
 */
class AdminNotifier
{
    /**
     * @return int تعداد اعلان‌های ثبت‌شده
     */
    public function notify(string $title, string $body, array $data = []): int
    {
        try {
            $adminIds = User::where('role', 'admin')->where('is_active', true)->pluck('id');

            foreach ($adminIds as $adminId) {
                Notification::create([
                    'user_id' => $adminId,
                    'title' => $title,
                    'body' => $body,
                    'data' => $data,
                    'read_at' => null,
                ]);
            }

            return $adminIds->count();
        } catch (Throwable $e) {
            Log::error('ثبت اعلان ادمین ناموفق بود: '.$e->getMessage());

            return 0;
        }
    }
}
