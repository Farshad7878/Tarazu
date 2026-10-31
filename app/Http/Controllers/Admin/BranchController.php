<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * مدیریت شعبه‌ها (فقط ادمین). شعبه حذف نمی‌شود؛ با غیرفعال کردن از فروش خارج می‌شود.
 */
class BranchController extends Controller
{
    public function index(): JsonResponse
    {
        $branches = Branch::query()
            ->withCount('users')
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get();

        $units = BranchStock::query()
            ->selectRaw('branch_id, SUM(quantity) as units')
            ->groupBy('branch_id')
            ->pluck('units', 'branch_id');

        return response()->json([
            'branches' => $branches->map(fn (Branch $branch) => $this->payload($branch, $units[$branch->id] ?? 0))->values(),
        ]);
    }

    /**
     * موجودی کالاهای یک شعبه (فقط کالاهای با موجودی بیشتر از صفر) — برای فرم انتقال
     */
    public function stock(Branch $branch): JsonResponse
    {
        $rows = BranchStock::query()
            ->join('products', 'products.id', '=', 'branch_stocks.product_id')
            ->where('branch_stocks.branch_id', $branch->id)
            ->where('branch_stocks.quantity', '>', 0)
            ->whereNull('products.deleted_at')
            ->orderBy('products.name')
            ->get(['products.id', 'products.name', 'products.barcode', 'branch_stocks.quantity']);

        return response()->json([
            'branch' => ['id' => $branch->id, 'name' => $branch->name, 'is_active' => (bool) $branch->is_active],
            'products' => $rows->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'barcode' => $row->barcode,
                'quantity' => (int) $row->quantity,
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $branch = Branch::create($data + ['is_main' => false]);

        return response()->json([
            'message' => 'شعبه با موفقیت ثبت شد.',
            'branch' => $this->payload($branch, 0),
        ], 201);
    }

    public function update(Request $request, Branch $branch): JsonResponse
    {
        $data = $this->validated($request, $branch);

        if ($branch->is_main && ! $data['is_active']) {
            return response()->json(['message' => 'شعبه‌ی اصلی را نمی‌توان غیرفعال کرد.'], 422);
        }

        $branch->update($data);

        $units = (int) BranchStock::where('branch_id', $branch->id)->sum('quantity');

        return response()->json([
            'message' => 'اطلاعات شعبه بروزرسانی شد.',
            'branch' => $this->payload($branch->fresh(), $units),
        ]);
    }

    private function validated(Request $request, ?Branch $branch = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('branches', 'code')->ignore($branch?->id),
            ],
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'is_active' => 'sometimes|boolean',
        ], [
            'code.regex' => 'کد شعبه فقط می‌تواند شامل حروف انگلیسی، عدد، - و _ باشد.',
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : ($branch?->is_active ?? true);

        return $data;
    }

    private function payload(Branch $branch, $units): array
    {
        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'code' => $branch->code,
            'address' => $branch->address,
            'phone' => $branch->phone,
            'is_main' => (bool) $branch->is_main,
            'is_active' => (bool) $branch->is_active,
            'users_count' => (int) ($branch->users_count ?? 0),
            'stock_units' => (int) $units,
        ];
    }
}
