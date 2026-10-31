<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $suppliers = Supplier::query()
            ->withCount('purchaseOrders')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->search.'%';
                $q->where('name', 'like', $term)->orWhere('phone', 'like', $term);
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'suppliers' => $suppliers->map(fn (Supplier $s) => $this->format($s))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $supplier = Supplier::create($this->validated($request));

        return response()->json([
            'message' => 'تأمین‌کننده ثبت شد.',
            'supplier' => $this->format($supplier),
        ], 201);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($this->validated($request));

        return response()->json(['message' => 'اطلاعات تأمین‌کننده بروزرسانی شد.']);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        if ($supplier->purchaseOrders()->exists()) {
            return response()->json([
                'message' => 'این تأمین‌کننده سابقه‌ی سفارش خرید دارد و حذف نمی‌شود. به‌جای آن غیرفعال کنید.',
            ], 422);
        }

        $supplier->delete();

        return response()->json(['message' => 'تأمین‌کننده حذف شد.']);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
            'address' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'sometimes|boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    private function format(Supplier $supplier): array
    {
        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'address' => $supplier->address,
            'notes' => $supplier->notes,
            'is_active' => (bool) $supplier->is_active,
            'purchase_orders_count' => (int) ($supplier->purchase_orders_count ?? 0),
        ];
    }
}
