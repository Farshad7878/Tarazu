<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(PurchaseOrder::STATUS_LABELS))],
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'page' => 'sometimes|integer|min:1',
        ]);

        $orders = PurchaseOrder::with(['supplier:id,name', 'branch:id,name'])
            ->when(! empty($data['status']), fn ($q) => $q->where('status', $data['status']))
            ->when(! empty($data['supplier_id']), fn ($q) => $q->where('supplier_id', $data['supplier_id']))
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'purchase_orders' => $orders->getCollection()->map(fn (PurchaseOrder $po) => $this->summary($po))->values(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
            'statuses' => PurchaseOrder::STATUS_LABELS,
        ]);
    }

    public function store(Request $request, PurchaseOrderService $service): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1|max:200',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:100000',
            'items.*.unit_cost' => 'required|numeric|min:0|max:1000000000000',
        ]);

        $supplier = Supplier::findOrFail($data['supplier_id']);
        $branchId = $data['branch_id'] ?? null;
        if ($branchId && ! Branch::whereKey($branchId)->where('is_active', true)->exists()) {
            return response()->json(['message' => 'شعبه‌ی انتخاب‌شده فعال نیست.'], 422);
        }

        $po = $service->create($supplier, $data['items'], $data['notes'] ?? null, $request->user(), $branchId);

        return response()->json([
            'message' => "سفارش خرید {$po->po_number} ثبت شد.",
            'purchase_order' => $this->detail($po),
        ], 201);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return response()->json([
            'purchase_order' => $this->detail($purchaseOrder->load(['supplier', 'branch', 'items.product'])),
        ]);
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): JsonResponse
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:0|max:100000',
            'items.*.unit_cost' => 'nullable|numeric|min:0|max:1000000000000',
        ]);

        $po = $service->receive($purchaseOrder, $data['items'], $request->user());

        return response()->json([
            'message' => 'کالاها دریافت و موجودی به‌روز شد.',
            'purchase_order' => $this->detail($po),
        ]);
    }

    public function cancel(PurchaseOrder $purchaseOrder, PurchaseOrderService $service): JsonResponse
    {
        $po = $service->cancel($purchaseOrder);

        return response()->json([
            'message' => "سفارش خرید {$po->po_number} لغو شد.",
            'purchase_order' => $this->summary($po->load('supplier:id,name')),
        ]);
    }

    private function summary(PurchaseOrder $po): array
    {
        return [
            'id' => $po->id,
            'po_number' => $po->po_number,
            'supplier_name' => $po->supplier?->name,
            'branch_name' => $po->branch?->name,
            'status' => $po->status,
            'status_label' => $po->status_label,
            'total_cost' => (float) $po->total_cost,
            'ordered_at' => $po->ordered_at?->format('Y-m-d H:i'),
            'received_at' => $po->received_at?->format('Y-m-d H:i'),
        ];
    }

    private function detail(PurchaseOrder $po): array
    {
        return array_merge($this->summary($po), [
            'branch_id' => $po->branch_id,
            'branch_name' => $po->branch?->name,
            'notes' => $po->notes,
            'items' => $po->items->map(fn (PurchaseOrderItem $item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'quantity_ordered' => (int) $item->quantity_ordered,
                'quantity_received' => (int) $item->quantity_received,
                'remaining' => $item->remainingQuantity(),
                'unit_cost' => (float) $item->unit_cost,
                'line_total' => (float) $item->line_total,
            ])->values(),
        ]);
    }
}
