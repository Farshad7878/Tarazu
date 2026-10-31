<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public const SUBJECT_TYPES = [
        'Product', 'ProductCategory', 'Discount', 'Customer', 'User', 'Order',
        'CashShift', 'SmsCampaign', 'GiftCard', 'Supplier', 'PurchaseOrder',
    ];

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user' => 'nullable|string|max:120',
            'action' => 'nullable|string|max:30',
            'subject_type' => 'nullable|in:'.implode(',', self::SUBJECT_TYPES),
            'search' => 'nullable|string|max:120',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'page' => 'sometimes|integer|min:1',
        ]);

        $query = AuditLog::query()->latest('id');

        if (! empty($data['user'])) {
            $query->where('user_name', 'like', '%'.$data['user'].'%');
        }

        if (! empty($data['action'])) {
            $query->where('action', $data['action']);
        }

        if (! empty($data['subject_type'])) {
            $query->where('subject_type', $data['subject_type']);
        }

        if (! empty($data['search'])) {
            $term = '%'.$data['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('subject_label', 'like', $term)->orWhere('description', 'like', $term);
            });
        }

        if (! empty($data['from'])) {
            $query->where('created_at', '>=', $data['from'].' 00:00:00');
        }

        if (! empty($data['to'])) {
            $query->where('created_at', '<=', $data['to'].' 23:59:59');
        }

        $logs = $query->paginate(30);

        return response()->json([
            'logs' => $logs->getCollection()->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'user_name' => $log->user_name ?? 'سیستم',
                'action' => $log->action,
                'action_label' => $log->action_label,
                'subject_type' => $log->subject_type,
                'subject_label' => $log->subject_label,
                'description' => $log->description,
                'changes' => $log->changes,
                'ip' => $log->ip,
                'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
            ])->values(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
            'actions' => AuditLog::ACTION_LABELS,
        ]);
    }
}
