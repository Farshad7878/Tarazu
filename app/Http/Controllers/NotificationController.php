<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * اعلان‌های ماندگار کاربر جاری. هر کاربر فقط اعلان‌های خودش را می‌بیند.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $notifications = Notification::where('user_id', $userId)
            ->latest('id')
            ->limit(30)
            ->get();

        return response()->json([
            'notifications' => $notifications->map(fn (Notification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'data' => $n->data,
                'is_read' => $n->read_at !== null,
                'created_at' => $n->created_at->format('Y-m-d H:i'),
            ])->values(),
            'unread_count' => Notification::where('user_id', $userId)->whereNull('read_at')->count(),
        ]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        $notification->update(['read_at' => now()]);

        return response()->json(['message' => 'به عنوان خوانده‌شده ثبت شد.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'همه‌ی اعلان‌ها خوانده شدند.']);
    }
}
