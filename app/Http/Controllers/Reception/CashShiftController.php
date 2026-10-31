<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Models\CashShift;
use App\Services\CashShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashShiftController extends Controller
{
    public function current(Request $request, CashShiftService $shifts): JsonResponse
    {
        $shift = $shifts->currentFor($request->user());

        return response()->json([
            'shift' => $shift ? $this->format($shift, $shifts->summary($shift)) : null,
        ]);
    }

    public function store(Request $request, CashShiftService $shifts): JsonResponse
    {
        $data = $request->validate([
            'opening_cash' => 'required|numeric|min:0|max:1000000000000',
            'notes' => 'nullable|string|max:500',
        ]);

        $shift = $shifts->open($request->user(), (float) $data['opening_cash'], $data['notes'] ?? null);

        return response()->json([
            'message' => 'صندوق با موفقیت باز شد.',
            'shift' => $this->format($shift, $shifts->summary($shift)),
        ], 201);
    }

    public function close(Request $request, CashShift $cashShift, CashShiftService $shifts): JsonResponse
    {
        // ریسپشن فقط صندوق خودش را می‌بندد
        abort_unless($cashShift->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'counted_cash' => 'required|numeric|min:0|max:1000000000000',
            'notes' => 'nullable|string|max:500',
        ]);

        $closed = $shifts->close($cashShift, (float) $data['counted_cash'], $data['notes'] ?? null);

        return response()->json([
            'message' => 'صندوق بسته شد.',
            'shift' => $this->format($closed, null),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $shifts = CashShift::where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate(10);

        return response()->json([
            'shifts' => $shifts->getCollection()->map(fn (CashShift $shift) => $this->format($shift, null))->values(),
            'pagination' => [
                'current_page' => $shifts->currentPage(),
                'last_page' => $shifts->lastPage(),
                'total' => $shifts->total(),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $summary  فقط برای صندوق باز (مبالغ زنده)
     */
    private function format(CashShift $shift, ?array $summary): array
    {
        return [
            'id' => $shift->id,
            'opened_at' => $shift->opened_at->format('Y-m-d H:i'),
            'closed_at' => $shift->closed_at?->format('Y-m-d H:i'),
            'is_open' => $shift->isOpen(),
            'opening_cash' => (float) $shift->opening_cash,
            'expected_cash' => $shift->expected_cash !== null ? (float) $shift->expected_cash : null,
            'counted_cash' => $shift->counted_cash !== null ? (float) $shift->counted_cash : null,
            'difference' => $shift->difference !== null ? (float) $shift->difference : null,
            'notes' => $shift->notes,
            'summary' => $summary,
        ];
    }
}
