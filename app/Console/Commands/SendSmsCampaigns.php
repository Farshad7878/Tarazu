<?php

namespace App\Console\Commands;

use App\Services\SmsCampaignService;
use Illuminate\Console\Command;

class SendSmsCampaigns extends Command
{
    protected $signature = 'sms:send-campaigns';

    protected $description = 'ارسال گیرنده‌های در انتظار کمپین‌های پیامکی تا سقف روزانه';

    public function handle(SmsCampaignService $service): int
    {
        $result = $service->sendPending();

        if ($result['busy']) {
            $this->warn('اجرای دیگری از ارسال کمپین هم‌اکنون در حال انجام است؛ این اجرا کاری نکرد.');

            return self::SUCCESS;
        }

        $this->info("ارسال شد: {$result['sent']} | ناموفق: {$result['failed']} | رد شد: {$result['skipped']} | سهمیه‌ی باقی‌مانده امروز: {$result['quota_left']}");

        return self::SUCCESS;
    }
}
