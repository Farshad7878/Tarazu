<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\StockAdjustment;
use Illuminate\Validation\ValidationException;

/**
 * تنها نقطه‌ی تغییر موجودی. هر حرکت (فروش، لغو، بازگشت، ضایعات، شمارش، تعدیل، انتقال) یک ردیف تاریخچه می‌سازد.
 *
 * موجودی به تفکیک شعبه در branch_stocks نگهداری می‌شود و products.stock_quantity مجموع همه‌ی شعبه‌ها است.
 * اگر branch_id داده نشود، حرکت روی شعبه‌ی اصلی ثبت می‌شود.
 *
 * نکته: فراخواننده باید ردیف محصول را قبلاً در یک تراکنش با lockForUpdate قفل کرده باشد.
 */
class StockService
{
    /**
     * @param  int  $change  تغییر موجودی (مثبت = ورود به انبار، منفی = خروج)
     * @param  array{branch_id?: int|null, user_id?: int|null, order_id?: int|null, order_return_id?: int|null, purchase_order_id?: int|null, stock_transfer_id?: int|null, reason?: string|null}  $context
     *
     * @throws ValidationException اگر موجودی شعبه منفی شود
     */
    public function move(Product $product, int $change, string $type, array $context = []): StockAdjustment
    {
        $branchId = (int) ($context['branch_id'] ?? Branch::mainId());

        $row = BranchStock::lockedFor($product->id, $branchId);
        $before = (int) $row->quantity;
        $after = $before + $change;

        if ($after < 0) {
            $branchName = Branch::whereKey($branchId)->value('name');

            throw ValidationException::withMessages([
                'quantity' => ["موجودی محصول {$product->name} در شعبه «{$branchName}» برای این عملیات کافی نیست. موجودی فعلی: {$before}"],
            ]);
        }

        if ($change !== 0) {
            $row->update(['quantity' => $after]);

            // مجموع کل از ردیف قفل‌شده‌ی خود محصول خوانده می‌شود (نه از نسخه‌ی قدیمی در حافظه)
            $total = (int) Product::withTrashed()->whereKey($product->id)->value('stock_quantity');
            $product->stock_quantity = $total + $change;
            $product->save();
        }

        return StockAdjustment::create([
            'product_id' => $product->id,
            'branch_id' => $branchId,
            'user_id' => array_key_exists('user_id', $context) ? $context['user_id'] : auth()->id(),
            'order_id' => $context['order_id'] ?? null,
            'order_return_id' => $context['order_return_id'] ?? null,
            'purchase_order_id' => $context['purchase_order_id'] ?? null,
            'stock_transfer_id' => $context['stock_transfer_id'] ?? null,
            'type' => $type,
            'quantity_change' => $change,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'reason' => $context['reason'] ?? null,
        ]);
    }
}
