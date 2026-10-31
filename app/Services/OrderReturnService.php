<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\GiftCard;
use App\Models\Discount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * بازگشت کالا (کامل یا جزئی) و ریفاند.
 *
 * قواعد:
 *  - مبلغ هر ردیف = سهم آن ردیف از مبلغ کل، منهای سهم تخفیف سفارش (به نسبت).
 *  - total_amount سفارش «مبلغ خالص» است و بعد از هر بازگشت کم می‌شود؛ پس گزارش‌ها و مجموع خرید مشتری خودکار اصلاح می‌شوند.
 *  - بازگشت کامل: باقی‌مانده‌ی گرد کردن به آخرین ردیف پولی می‌رود تا مجموع دقیقاً با مبلغ خالص برابر باشد.
 *  - کالای هدیه (مبلغ صفر) با تعداد برگشت می‌خورد ولی روی مبلغ اثری ندارد.
 *  - تخفیف‌های یک‌بارمصرف فقط با بازگشت کامل سفارش دوباره فعال می‌شوند (بازگشت جزئی، مصرف تخفیف را برنمی‌گرداند).
 */
class OrderReturnService
{
    /**
     * @param  array<int, array{order_item_id: int, quantity: int}>  $lines
     */
    public function process(Order $order, array $lines, string $reason, bool $restock, User $user): OrderReturn
    {
        return DB::transaction(function () use ($order, $lines, $reason, $restock, $user) {
            // قفل سفارش: دو بازگشت هم‌زمان نتوانند مبلغ یا تعداد را دوبار برگردانند
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            $allItems = OrderItem::where('order_id', $order->id)->lockForUpdate()->get()->keyBy('id');

            // جمع تعداد درخواستی برای هر ردیف
            $requested = collect($lines)->groupBy('order_item_id')
                ->map(fn ($rows) => (int) $rows->sum('quantity'));

            $errors = [];
            foreach ($requested as $itemId => $quantity) {
                $item = $allItems->get($itemId);

                if (! $item) {
                    $errors[] = "ردیف {$itemId} متعلق به این سفارش نیست.";
                } elseif ($quantity > $item->returnable_quantity) {
                    $errors[] = "حداکثر تعداد قابل بازگشت برای ردیف {$itemId} برابر {$item->returnable_quantity} است.";
                }
            }

            if ($errors) {
                throw ValidationException::withMessages(['items' => $errors]);
            }

            $productIds = $allItems->whereIn('id', $requested->keys())->pluck('product_id')->unique();
            $products = Product::withTrashed()->whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

            $discountRatio = (float) $order->subtotal > 0
                ? (float) $order->discount_amount / (float) $order->subtotal
                : 0.0;

            // آیا بعد از این بازگشت، همه‌ی ردیف‌های «پولی» کامل برگشته‌اند؟
            // کالای هدیه‌ی تخفیف مبلغ ندارد و نباید مانع بازگشت کامل پولی شود.
            $paidItems = $allItems->filter(fn (OrderItem $item) => (float) $item->total_price > 0);
            $fullyReturned = $paidItems->every(function (OrderItem $item) use ($requested) {
                $now = (int) $requested->get($item->id, 0);

                return $item->returned_quantity + $now >= $item->quantity;
            });

            // ردیف پولی آخرِ این بازگشت؛ باقی‌مانده‌ی گرد کردن به آن می‌رود
            $lastPaidId = collect($requested->keys())
                ->filter(fn ($id) => (float) $allItems->get($id)->total_price > 0)
                ->last();

            // ۱) محاسبه‌ی مبلغ هر ردیف
            $lineRefunds = [];
            foreach ($requested as $itemId => $quantity) {
                $item = $allItems->get($itemId);
                $gross = (float) $item->total_price * $quantity / $item->quantity;
                $lineRefunds[$itemId] = round($gross * (1 - $discountRatio), 2);
            }

            $totalRefund = $fullyReturned && $lastPaidId !== null
                ? (float) $order->total_amount
                : array_sum($lineRefunds);

            if ($fullyReturned && $lastPaidId !== null) {
                // باقی‌مانده‌ی گرد کردن را به آخرین ردیف پولی می‌دهیم
                $lineRefunds[$lastPaidId] = round($totalRefund - (array_sum($lineRefunds) - $lineRefunds[$lastPaidId]), 2);
            }

            $totalBefore = (float) $order->total_amount;

            // بخش گیفت‌کارتیِ سفارش اول به گیفت کارت برمی‌گردد (تا سقف مبلغ پرداخت‌شده با کارت)
            $giftAvailable = (float) $order->gift_card_amount
                - (float) OrderReturn::where('order_id', $order->id)->sum('gift_card_credit');
            $giftCredit = $order->gift_card_id && $giftAvailable > 0
                ? round(min($totalRefund, $giftAvailable), 2)
                : 0.0;

            // مالیات: مالیات باقی‌مانده‌ی سفارش روی مبلغ خالص جدید حساب می‌شود؛ تفاوت با قبل، مالیات برگشتی است
            $taxService = app(TaxService::class);
            $taxBefore = (float) $order->tax_amount;
            $taxAfter = $taxService->includedIn(max(0, $totalBefore - $totalRefund), (float) $order->tax_rate);
            $taxRefund = round(max(0, $taxBefore - $taxAfter), 2);

            // ۲) ثبت بازگشت و ردیف‌ها (بازگشت به شیفت صندوق باز همین کاربر تعلق می‌گیرد)
            $return = OrderReturn::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'reason' => $reason,
                'restock' => $restock,
                'refund_amount' => $totalRefund,
                'tax_refund' => $taxRefund,
                'gift_card_credit' => $giftCredit,
                'cash_shift_id' => app(CashShiftService::class)->currentIdFor($user),
            ]);

            if ($giftCredit > 0) {
                $card = GiftCard::whereKey($order->gift_card_id)->lockForUpdate()->firstOrFail();
                app(GiftCardService::class)->credit($card, $giftCredit, 'refund_credit', $order, $return->id, $user);
            }

            $stock = app(StockService::class);

            foreach ($requested as $itemId => $quantity) {
                $item = $allItems->get($itemId);

                $return->items()->create([
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $quantity,
                    'refund_amount' => $lineRefunds[$itemId],
                ]);

                // increment روی رویداد updating (که total_price را دوباره حساب می‌کند) تأثیر ندارد
                $item->increment('returned_quantity', $quantity);

                $product = $products->get($item->product_id);
                $context = [
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    // کالای برگشتی به شعبه‌ای برمی‌گردد که فروش از آن انجام شده است
                    'branch_id' => $order->branch_id,
                    'order_return_id' => $return->id,
                    'reason' => $reason,
                ];

                if ($restock) {
                    $stock->move($product, $quantity, 'return', $context);
                } else {
                    // کالا وارد انبار نشده؛ ثبت تاریخچه بدون تغییر موجودی
                    $stock->move($product, 0, 'return_not_restocked', $context);
                }
            }

            // ۳) امتیاز وفاداری به نسبت مبلغ برگشتی اصلاح می‌شود (فیلدهای سفارش همین‌جا کم می‌شوند)
            app(LoyaltyService::class)->reverseForReturn(
                $order,
                $totalRefund,
                $totalBefore,
                $fullyReturned,
                $return,
                $user,
            );

            // سفارش و مجموع خرید مشتری
            $order->refunded_amount = (float) $order->refunded_amount + $totalRefund;
            $order->total_amount = max(0, (float) $order->total_amount - $totalRefund);
            $order->tax_amount = $taxAfter;
            $order->save();

            Customer::whereKey($order->customer_id)->decrement('total_purchases', $totalRefund);

            // ۴) بازگردانی تخفیف‌های یک‌بارمصرف فقط برای بازگشت کامل
            if ($fullyReturned) {
                $this->restoreSingleUseDiscounts($order);
            }

            app(AuditLogger::class)->record('return', $order, "بازگشت کالا ({$return->id})", [
                'refund_amount' => ['old' => null, 'new' => $totalRefund],
                'tax_refund' => ['old' => null, 'new' => $taxRefund],
                'gift_card_credit' => ['old' => null, 'new' => $giftCredit],
                'reason' => ['old' => null, 'new' => $reason],
            ]);

            return $return->load('items');
        });
    }

    private function restoreSingleUseDiscounts(Order $order): void
    {
        $customer = $order->customer()->first();

        foreach ($order->discounts()->get() as $orderDiscount) {
            $discount = Discount::find($orderDiscount->discount_id);

            if (! $discount || ! $discount->auto_disable_after_use) {
                continue;
            }

            // مصرف این تخفیف باید بعد از ثبت همین سفارش ثبت شده باشد
            $usedByThisOrder = $customer->discounts()
                ->where('discounts.id', $discount->id)
                ->wherePivotNotNull('used_at')
                ->wherePivot('used_at', '>=', $order->created_at)
                ->exists();

            if ($usedByThisOrder) {
                $customer->discounts()->updateExistingPivot($discount->id, ['used_at' => null]);
            }
        }
    }
}
