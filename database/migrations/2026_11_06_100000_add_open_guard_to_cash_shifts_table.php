<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * قفل یکتایی صندوق باز برای هر کاربر در سطح پایگاه داده.
 *
 * open_user_id فقط برای صندوق بازِ کاربر پر است و هنگام بستن NULL می‌شود.
 * ایندکس یکتا روی آن یعنی هر کاربر حداکثر یک صندوق باز دارد، حتی اگر دو درخواست هم‌زمان
 * از منطق برنامه عبور کنند. MySQL و SQLite چند NULL را در ایندکس یکتا می‌پذیرند،
 * پس صندوق‌های بسته اثری ندارند.
 */
return new class extends Migration
{
    private const INDEX = 'cash_shifts_open_user_id_unique';

    public function up(): void
    {
        Schema::table('cash_shifts', function (Blueprint $table) {
            $table->unsignedBigInteger('open_user_id')->nullable()->after('user_id');
        });

        // برای هر کاربر فقط آخرین صندوق باز (همان چیزی که currentFor برمی‌گرداند) قفل می‌شود.
        $latestOpenIds = DB::table('cash_shifts')
            ->whereNull('closed_at')
            ->whereNotNull('user_id')
            ->selectRaw('MAX(id) as id')
            ->groupBy('user_id')
            ->pluck('id');

        if ($latestOpenIds->isNotEmpty()) {
            DB::table('cash_shifts')
                ->whereIn('id', $latestOpenIds)
                ->update(['open_user_id' => DB::raw('user_id')]);
        }

        // صندوق‌های بازِ قدیمی‌تر از یک کاربر (از تلاش‌های قبلی هم‌زمان) قفل نمی‌شوند، چون ایندکس یکتا را نقض می‌کنند.
        // بسته شدن یا بررسی آن‌ها تصمیم مالی است و خودکار انجام نمی‌شود؛ فقط فهرست می‌شوند.
        $unguardedOpen = DB::table('cash_shifts')
            ->whereNull('closed_at')
            ->whereNotNull('user_id')
            ->whereNull('open_user_id')
            ->pluck('id');

        if ($unguardedOpen->isNotEmpty()) {
            Log::warning('cash_shifts: صندوق‌های باز تکراری بدون قفل ماند؛ بررسی دستی لازم است.', [
                'shift_ids' => $unguardedOpen->values()->all(),
            ]);
        }

        Schema::table('cash_shifts', function (Blueprint $table) {
            $table->unique('open_user_id', self::INDEX);
        });
    }

    public function down(): void
    {
        Schema::table('cash_shifts', function (Blueprint $table) {
            $table->dropUnique(self::INDEX);
        });

        Schema::table('cash_shifts', function (Blueprint $table) {
            $table->dropColumn('open_user_id');
        });
    }
};
