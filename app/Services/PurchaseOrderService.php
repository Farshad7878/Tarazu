<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * سفارش خرید از تأمین‌کننده.
 *
 * دریافت کالا:
 *  - موجودی با نوع 'purchase' در تاریخچه‌ی انبار ثبت می‌شود و به سفارش خرید پیوند دارد.
 *  - بهای تمام‌شده‌ی محصول به میانگین موزون می‌رسد: (موجودی قبلی × بهای قبلی + تعداد دریافتی × بهای جدید) ÷ (موجودی قبلی + تعداد دریافتی)
 *  - دریافت جزئی مجاز است؛ هر بار فقط تعداد باقی‌مانده‌ی همان ردیف پذیرفته می‌شود.
 */
class PurchaseOrderService
{
    /**
     * @param  array<int, array{product_id: int, quantity: int, unit_cost: float|int|string}>  $items
     */
    public function create(Supplier $supplier, array $items, ?string $notes, User $user, ?int $branchId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($supplier, $items, $notes, $user, $branchId) {
            $po = PurchaseOrder::create([
                'po_number' => PurchaseOrder::generateNumber(),
                'supplier_id' => $supplier->id,
                'branch_id' => $branchId ?? Branch::mainId(),
                'status' => PurchaseOrder::STATUS_ORDERED,
                'ordered_at' => now(),
                'total_cost' => 0,
                'notes' => $notes,
                'created_by' => $user->id,
            ]);

            $total = 0;
            foreach ($items as $row) {
                $unit = round((float) $row['unit_cost'], 2);
                $qty = (int) $row['quantity'];
                $line = round($unit * $qty, 2);
                $total += $line;

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $row['product_id'],
                    'quantity_ordered' => $qty,
                    'quantity_received' => 0,
                    'unit_cost' => $unit,
                    'line_total' => $line,
                ]);
            }

            $po->update(['total_cost' => round($total, 2)]);

            return $po->load(['supplier', 'items.product']);
        });
    }

    /**
     * @param  array<int, array{item_id: int, quantity: int, unit_cost?: float|int|string|null}>  $lines
     */
    public function receive(PurchaseOrder $po, array $lines, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $lines, $user) {
            $po = PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail();

            if (in_array($po->status, [PurchaseOrder::STATUS_CANCELLED, PurchaseOrder::STATUS_RECEIVED], true)) {
                throw ValidationException::withMessages(['status' => ['این سفارش خرید دیگر قابل دریافت نیست.']]);
            }

            $items = PurchaseOrderItem::where('purchase_order_id', $po->id)->lockForUpdate()->get()->keyBy('id');
            $requested = collect($lines)->filter(fn ($l) => (int) ($l['quantity'] ?? 0) > 0);

            if ($requested->isEmpty()) {
                throw ValidationException::withMessages(['items' => ['حداقل یک ردیف با تعداد بیشتر از صفر لازم است.']]);
            }

            $errors = [];
            foreach ($requested as $line) {
                $item = $items->get((int) $line['item_id']);

                if (! $item) {
                    $errors[] = 'ردیف انتخابی متعلق به این سفارش نیست.';
                } elseif ((int) $line['quantity'] > $item->remainingQuantity()) {
                    $errors[] = "حداکثر تعداد قابل دریافت برای {$item->product?->name} برابر {$item->remainingQuantity()} است.";
                }
            }

            if ($errors) {
                throw ValidationException::withMessages(['items' => $errors]);
            }

            $products = Product::withTrashed()
                ->whereIn('id', $items->whereIn('id', $requested->pluck('item_id'))->pluck('product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $stock = app(StockService::class);

            foreach ($requested as $line) {
                /** @var PurchaseOrderItem $item */
                $item = $items->get((int) $line['item_id']);
                $qty = (int) $line['quantity'];

                if (isset($line['unit_cost']) && $line['unit_cost'] !== '' && $line['unit_cost'] !== null) {
                    $item->unit_cost = round((float) $line['unit_cost'], 2);
                    $item->line_total = round((float) $item->unit_cost * (int) $item->quantity_ordered, 2);
                }

                $product = $products->get($item->product_id);
                $unit = (float) $item->unit_cost;

                // میانگین موزون بهای تمام‌شده
                $stockBefore = max(0, (int) $product->stock_quantity);
                $oldCost = (float) ($product->cost_price ?? 0);
                $product->cost_price = $stockBefore + $qty > 0
                    ? round((($stockBefore * $oldCost) + ($qty * $unit)) / ($stockBefore + $qty), 2)
                    : round($unit, 2);

                $stock->move($product, $qty, 'purchase', [
                    'branch_id' => $po->branch_id,
                    'purchase_order_id' => $po->id,
                    'user_id' => $user->id,
                    'reason' => "دریافت سفارش خرید {$po->po_number}",
                ]);

                $item->quantity_received = (int) $item->quantity_received + $qty;
                $item->save();
            }

            $allReceived = PurchaseOrderItem::where('purchase_order_id', $po->id)
                ->get()
                ->every(fn (PurchaseOrderItem $i) => $i->remainingQuantity() === 0);

            $po->update([
                'status' => $allReceived ? PurchaseOrder::STATUS_RECEIVED : PurchaseOrder::STATUS_PARTIAL,
                'received_by' => $user->id,
                'received_at' => now(),
                'total_cost' => round((float) PurchaseOrderItem::where('purchase_order_id', $po->id)->sum('line_total'), 2),
            ]);

            return $po->load(['supplier', 'items.product']);
        });
    }

    public function cancel(PurchaseOrder $po): PurchaseOrder
    {
        return DB::transaction(function () use ($po) {
            $po = PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail();

            if ($po->status !== PurchaseOrder::STATUS_ORDERED) {
                throw ValidationException::withMessages(['status' => ['فقط سفارش خرید بدون دریافت کالا قابل لغو است.']]);
            }

            $po->update([
                'status' => PurchaseOrder::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            return $po;
        });
    }
}
