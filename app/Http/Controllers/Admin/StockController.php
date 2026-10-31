<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * تعدیل دستی موجودی و تاریخچه‌ی انبار (فقط ادمین).
 */
class StockController extends Controller
{
    public function history(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);
        // بدون انتخاب شعبه، تاریخچه‌ی شعبه‌ی اصلی نمایش داده می‌شود
        $branchId = (int) ($data['branch_id'] ?? Branch::mainId());

        $rows = StockAdjustment::with(['user', 'order', 'branch:id,name'])
            ->where('product_id', $product->id)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->latest('id')
            ->paginate(20);

        // موجودی این محصول در همه‌ی شعبه‌ها (برای انتخاب شعبه در فرم تعدیل)
        $quantities = BranchStock::where('product_id', $product->id)->pluck('quantity', 'branch_id');
        $branches = Branch::query()->orderByDesc('is_main')->orderBy('name')->get()->map(fn (Branch $branch) => [
            'id' => $branch->id,
            'name' => $branch->name,
            'code' => $branch->code,
            'is_main' => (bool) $branch->is_main,
            'is_active' => (bool) $branch->is_active,
            'quantity' => (int) ($quantities[$branch->id] ?? 0),
        ])->values();

        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'stock_quantity' => (int) $product->stock_quantity,
                'min_stock' => (int) $product->min_stock,
            ],
            'branches' => $branches,
            'selected_branch_id' => $branchId,
            'history' => $rows->map(fn (StockAdjustment $row) => [
                'id' => $row->id,
                'type' => $row->type,
                'type_label' => $row->type_label,
                'quantity_change' => $row->quantity_change,
                'quantity_before' => $row->quantity_before,
                'quantity_after' => $row->quantity_after,
                'reason' => $row->reason,
                'branch_name' => $row->branch?->name,
                'user_name' => $row->user?->name,
                'order_number' => $row->order?->order_number,
                'created_at' => $row->created_at->format('Y-m-d H:i'),
            ])->values(),
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    public function store(Request $request, Product $product, StockService $stock): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:damage,count,correction',
            'quantity' => 'required_if:type,damage,correction|nullable|integer',
            'counted_quantity' => 'required_if:type,count|nullable|integer|min:0',
            'reason' => 'nullable|string|max:255',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        $branchId = (int) ($data['branch_id'] ?? Branch::mainId());
        $branch = Branch::findOrFail($branchId);

        if ($data['type'] === 'damage' && (int) $data['quantity'] < 1) {
            return response()->json(['message' => 'تعداد ضایعات باید حداقل ۱ باشد.'], 422);
        }

        if ($data['type'] === 'correction' && (int) $data['quantity'] === 0) {
            return response()->json(['message' => 'تعدیل دستی نمی‌تواند صفر باشد.'], 422);
        }

        if (in_array($data['type'], ['damage', 'correction'], true) && blank($data['reason'] ?? null)) {
            return response()->json(['message' => 'ثبت دلیل برای این نوع تعدیل الزامی است.'], 422);
        }

        return DB::transaction(function () use ($data, $product, $stock, $branch) {
            // قفل ردیف محصول تا تعدیل با فروش همزمان تداخل نکند
            $locked = Product::withTrashed()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            // شمارش، موجودی همین شعبه را با عدد شمارش‌شده برابر می‌کند
            $branchStock = BranchStock::lockedFor($locked->id, $branch->id);

            $change = match ($data['type']) {
                'damage' => -(int) $data['quantity'],
                'correction' => (int) $data['quantity'],
                'count' => (int) $data['counted_quantity'] - (int) $branchStock->quantity,
            };

            $row = $stock->move($locked, $change, $data['type'], [
                'branch_id' => $branch->id,
                'reason' => $data['reason'] ?? null,
            ]);

            app(\App\Services\AuditLogger::class)->record('stock_adjusted', $locked, \App\Models\StockAdjustment::TYPE_LABELS[$data['type']] ?? $data['type'], [
                'quantity_change' => ['old' => $row->quantity_before, 'new' => $row->quantity_after],
                'reason' => ['old' => null, 'new' => $data['reason'] ?? null],
                'branch' => ['old' => null, 'new' => $branch->name],
            ]);

            return response()->json([
                'message' => 'موجودی با موفقیت بروزرسانی شد.',
                'stock_quantity' => (int) $locked->stock_quantity,
                'branch_stock_quantity' => (int) $row->quantity_after,
                'branch_name' => $branch->name,
                'adjustment_id' => $row->id,
            ], 201);
        });
    }
}
