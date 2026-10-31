<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class BackupController extends Controller
{
    public function index(BackupService $backups): JsonResponse
    {
        return response()->json([
            'backups' => $backups->list(),
            'keep' => (int) config('backup.keep'),
        ]);
    }

    public function store(BackupService $backups, AuditLogger $audit): JsonResponse
    {
        $result = $backups->create('manual');

        $audit->note('backup_created', "پشتیبان دستی: {$result['name']}", ['rows' => $result['rows']]);

        return response()->json([
            'message' => 'پشتیبان با موفقیت ایجاد شد.',
            'backup' => $result,
        ], 201);
    }

    public function download(string $name, BackupService $backups)
    {
        return Response::download($backups->path($name), $name);
    }

    public function restore(Request $request, BackupService $backups, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string',
            // تأیید صریح: بازیابی کل داده‌ها عملیات پرخطری است
            'confirm' => 'required|in:RESTORE',
        ]);

        $result = $backups->restore($data['name']);

        // یادداشت بعد از بازیابی نوشته می‌شود تا در داده‌ی بازگردانده‌شده گم نشود
        $audit->note('backup_restored', "بازیابی از پشتیبان: {$data['name']}", [
            'tables' => $result['tables'],
            'rows' => $result['rows'],
            'safety_backup' => $result['safety_backup'],
        ]);

        return response()->json([
            'message' => 'بازیابی با موفقیت انجام شد. ممکن است لازم باشد دوباره وارد شوید.',
            'result' => $result,
        ]);
    }

    public function destroy(string $name, BackupService $backups, AuditLogger $audit): JsonResponse
    {
        $backups->delete($name);
        $audit->note('backup_deleted', "حذف پشتیبان: {$name}");

        return response()->json(['message' => 'پشتیبان حذف شد.']);
    }
}
