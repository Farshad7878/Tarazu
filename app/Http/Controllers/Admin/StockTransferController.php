<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Services\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * انتقال موجودی بین شعبه‌ها (فقط ادمین).
 */
class StockTransferController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(StockTransfer::STATUS_LABELS))],
            'branch_id' => 'nullable|integer|exists:branches,id',
            'page' => 'sometimes|integer|min:1',
        ]);

        $transfers = StockTransfer::query()
            ->with(['fromBranch:id,name', 'toBranch:id,name', 'creator:id,name'])
            ->withCount('items')
            ->withSum('items', 'quantity')
            ->when(! empty($data['status']), fn ($q) => $q->where('status', $data['status']))
            ->when(! empty($data['branch_id']), fn ($q) => $q->where(function ($inner) use ($data) {
                $inner->where('from_branch_id', $data['branch_id'])->orWhere('to_branch_id', $data['branch_id']);
            }))
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'transfers' => $transfers->getCollection()->map(fn (StockTransfer $t) => $this->summary($t))->values(),
            'pagination' => [
                'current_page' => $transfers->currentPage(),
                'last_page' => $transfers->lastPage(),
                'total' => $transfers->total(),
            ],
            'statuses' => StockTransfer::STATUS_LABELS,
        ]);
    }

    public function show(StockTransfer $stockTransfer): JsonResponse
    {
        return response()->json([
            'transfer' => $this->detail($stockTransfer->load(['fromBranch', 'toBranch', 'items.product', 'creator', 'receiver', 'canceller'])),
        ]);
    }

    public function store(Request $request, StockTransferService $service): JsonResponse
    {
        $data = $request->validate([
            'from_branch_id' => 'required|integer|exists:branches,id',
            'to_branch_id' => 'required|integer|exists:branches,id|different:from_branch_id',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1|max:200',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:100000',
        ]);

        $transfer = $service->create(
            Branch::findOrFail($data['from_branch_id']),
            Branch::findOrFail($data['to_branch_id']),
            $data['items'],
            $data['notes'] ?? null,
            $request->user(),
        );

        return response()->json([
            'message' => "انتقال {$transfer->transfer_number} ثبت شد و موجودی مبدا کم شد.",
            'transfer' => $this->detail($transfer),
        ], 201);
    }

    public function receive(Request $request, StockTransfer $stockTransfer, StockTransferService $service): JsonResponse
    {
        $transfer = $service->receive($stockTransfer, $request->user());

        return response()->json([
            'message' => 'کالاها دریافت و به موجودی شعبه‌ی مقصد اضافه شد.',
            'transfer' => $this->detail($transfer),
        ]);
    }

    public function cancel(Request $request, StockTransfer $stockTransfer, StockTransferService $service): JsonResponse
    {
        $transfer = $service->cancel($stockTransfer, $request->user());

        return response()->json([
            'message' => 'انتقال لغو شد و کالاها به شعبه‌ی مبدا برگشتند.',
            'transfer' => $this->detail($transfer),
        ]);
    }

    private function summary(StockTransfer $t): array
    {
        return [
            'id' => $t->id,
            'transfer_number' => $t->transfer_number,
            'from_branch_id' => $t->from_branch_id,
            'from_branch_name' => $t->fromBranch?->name,
            'to_branch_id' => $t->to_branch_id,
            'to_branch_name' => $t->toBranch?->name,
            'status' => $t->status,
            'status_label' => StockTransfer::STATUS_LABELS[$t->status] ?? $t->status,
            'items_count' => $t->relationLoaded('items') ? $t->items->count() : (int) $t->items_count,
            'total_quantity' => $t->relationLoaded('items') ? (int) $t->items->sum('quantity') : (int) $t->items_sum_quantity,
            'created_by' => $t->creator?->name,
            'created_at' => $t->created_at?->format('Y-m-d H:i'),
            'received_at' => $t->received_at?->format('Y-m-d H:i'),
            'cancelled_at' => $t->cancelled_at?->format('Y-m-d H:i'),
        ];
    }

    private function detail(StockTransfer $t): array
    {
        return array_merge($this->summary($t), [
            'notes' => $t->notes,
            'received_by' => $t->receiver?->name,
            'cancelled_by' => $t->canceller?->name,
            'items' => $t->items->map(fn (StockTransferItem $item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'quantity' => (int) $item->quantity,
            ])->values(),
        ]);
    }
}
