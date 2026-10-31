<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashShiftController extends Controller
{
    /**
     * همه‌ی شیفت‌ها با مغایرت نقدی (فقط خواندنی).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'only_mismatched' => 'sometimes|boolean',
        ]);

        $shifts = CashShift::with('user:id,name')
            ->when($request->boolean('only_mismatched'), fn ($q) => $q->whereNotNull('difference')->where('difference', '!=', 0))
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'shifts' => $shifts->getCollection()->map(fn (CashShift $shift) => [
                'id' => $shift->id,
                'user_name' => $shift->user?->name ?? 'کاربر حذف‌شده',
                'opened_at' => $shift->opened_at->format('Y-m-d H:i'),
                'closed_at' => $shift->closed_at?->format('Y-m-d H:i'),
                'is_open' => $shift->isOpen(),
                'opening_cash' => (float) $shift->opening_cash,
                'expected_cash' => $shift->expected_cash !== null ? (float) $shift->expected_cash : null,
                'counted_cash' => $shift->counted_cash !== null ? (float) $shift->counted_cash : null,
                'difference' => $shift->difference !== null ? (float) $shift->difference : null,
                'notes' => $shift->notes,
            ])->values(),
            'pagination' => [
                'current_page' => $shifts->currentPage(),
                'last_page' => $shifts->lastPage(),
                'total' => $shifts->total(),
            ],
        ]);
    }
}
