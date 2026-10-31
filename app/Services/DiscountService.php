<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Discount;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * منطق تخفیف‌ها.
 *
 * مقادیر مجاز همان enumهای جدول discounts هستند:
 *  - type:           percentage | fixed_amount | free_product
 *  - condition_type: total_purchases | monthly_purchases | birthday | manual
 *
 * دو مسیر عمومی داریم:
 *  - calculateRealTimeDiscount: پیش‌نمایش در فرانت؛ هیچ تغییری در دیتابیس نمی‌دهد.
 *  - calculateOrderDiscounts:   هنگام ثبت سفارش؛ تخفیف‌ها را روی سفارش اعمال و مصرف را ثبت می‌کند.
 */
class DiscountService
{
    /**
     * پیش‌نمایش تخفیف‌های یک سبد خرید (بدون ذخیره‌سازی).
     */
    public function calculateRealTimeDiscount(Customer $customer, array $cartItems, float $subtotal): array
    {
        $totalDiscount = 0.0;
        $appliedDiscounts = [];

        foreach ($this->eligibleDiscounts($customer) as $discount) {
            $remaining = max(0, $subtotal - $totalDiscount);
            $amount = $this->amountFor($discount, $subtotal, $remaining);

            // تخفیف محصول رایگان در سفارش مبلغی کم نمی‌کند (کالا با قیمت ۰ ثبت می‌شود)،
            // پس در پیش‌نمایش هم مبلغ صفر است تا با مبلغ نهایی سفارش یکی باشد.
            $totalDiscount += $amount;

            $appliedDiscounts[] = [
                'id' => $discount->id,
                'name' => $discount->name,
                'type' => $discount->type,
                'amount' => $amount,
                'message' => $this->message($discount, $amount),
            ];
        }

        return [
            'total_discount' => $totalDiscount,
            'applied_discounts' => $appliedDiscounts,
            'final_amount' => max(0, $subtotal - $totalDiscount),
        ];
    }

    /**
     * اعمال تخفیف‌های خودکار روی سفارش (فراخوانی از ثبت سفارش، داخل تراکنش).
     */
    public function calculateOrderDiscounts(Order $order, bool $applyAutoDiscounts = true): array
    {
        if (! $applyAutoDiscounts) {
            return ['total_discount' => 0, 'applied_discounts' => []];
        }

        $customer = $order->customer;
        $subtotal = (float) $order->subtotal;
        $totalDiscount = 0.0;
        $applied = [];

        foreach ($this->eligibleDiscounts($customer) as $discount) {
            $remaining = max(0, $subtotal - $totalDiscount);
            $result = $this->applyToOrder($order, $discount, $subtotal, $remaining);

            $totalDiscount += $result['discount_amount'];

            $applied[] = [
                'discount' => $discount,
                'amount' => $result['discount_amount'],
                'message' => $result['message'],
            ];

            // مصرف تخفیف فقط یک‌بار و اینجا ثبت می‌شود
            $this->markDiscountAsUsed($customer, $discount);
        }

        return [
            'total_discount' => $totalDiscount,
            'applied_discounts' => $applied,
        ];
    }

    /**
     * اختصاص تخفیف تولد سالانه به مشتری (یک بار در سال).
     */
    public function assignBirthdayDiscount(Customer $customer, Discount $birthdayDiscount): void
    {
        try {
            $alreadyAssignedThisYear = $customer->discounts()
                ->where('discounts.id', $birthdayDiscount->id)
                ->whereYear('customer_discounts.created_at', now()->year)
                ->exists();

            if ($alreadyAssignedThisYear) {
                Log::info("مشتری {$customer->full_name} قبلاً تخفیف تولد امسال را دریافت کرده است");
                return;
            }

            $customer->discounts()->attach($birthdayDiscount->id, [
                'used_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info("تخفیف تولد برای مشتری {$customer->full_name} اختصاص داده شد");
        } catch (\Exception $e) {
            Log::error('خطا در اختصاص تخفیف تولد: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * ایجاد تخفیف پیش‌فرض تولد
     */
    public function createDefaultBirthdayDiscount(): Discount
    {
        return Discount::create([
            'name' => 'تخفیف ویژه تولد',
            'type' => 'percentage',
            'value' => 15,
            'target' => 'all',
            'condition_type' => 'birthday',
            'condition_value' => null,
            'free_product_id' => null,
            'message' => '🎂 تولدت مبارک! از ۱۵٪ تخفیف ویژه تولد بهره‌مند شوید.',
            'is_active' => true,
            'auto_apply' => true,
            'auto_disable_after_use' => true,
        ]);
    }

    /**
     * تخفیف‌های فعال، خودکار و واجد شرایط برای مشتری (به ترتیب ارزش).
     */
    private function eligibleDiscounts(Customer $customer): Collection
    {
        return Discount::where('is_active', true)
            ->where('auto_apply', true)
            ->where(function ($query) use ($customer) {
                $query->where('target', 'all')
                    ->orWhereHas('customers', function ($q) use ($customer) {
                        $q->where('customers.id', $customer->id)
                          ->whereNull('customer_discounts.used_at');
                    });
            })
            ->get()
            ->filter(fn (Discount $discount) => $this->isEligible($discount, $customer))
            ->sortByDesc('value')
            ->values();
    }

    /**
     * آیا این تخفیف برای این مشتری در این لحظه قابل اعمال است؟
     */
    private function isEligible(Discount $discount, Customer $customer): bool
    {
        if (! $discount->is_active) {
            return false;
        }

        if ($discount->auto_disable_after_use && $this->hasUsedDiscount($customer, $discount)) {
            return false;
        }

        return match ($discount->condition_type) {
            'total_purchases' => (float) $customer->total_purchases >= (float) $discount->condition_value,
            'monthly_purchases' => (float) $customer->monthly_purchases >= (float) $discount->condition_value,
            'birthday' => (bool) $customer->date_of_birth && $customer->date_of_birth->isBirthday(),
            // تخفیف دستی فقط توسط ادمین و به‌صورت assign اعمال می‌شود
            default => false,
        };
    }

    /**
     * مبلغ تخفیف یک تخفیف، با رعایت اینکه مجموع تخفیف‌ها از جمع سفارش بیشتر نشود.
     */
    private function amountFor(Discount $discount, float $subtotal, float $remaining): float
    {
        $amount = match ($discount->type) {
            'percentage' => $subtotal * ((float) $discount->value / 100),
            'fixed_amount' => (float) $discount->value,
            default => 0.0, // free_product: مبلغ تخفیفی ندارد، کالای هدیه است
        };

        return round(min($amount, $remaining), 2);
    }

    /**
     * اعمال روی سفارش: محاسبه مبلغ و (در صورت وجود) افزودن کالای هدیه.
     */
    private function applyToOrder(Order $order, Discount $discount, float $subtotal, float $remaining): array
    {
        $amount = $this->amountFor($discount, $subtotal, $remaining);

        if ($discount->type === 'free_product' && $discount->freeProduct) {
            $order->items()->create([
                'product_id' => $discount->freeProduct->id,
                'quantity' => 1,
                'unit_price' => 0,
                'total_price' => 0,
            ]);
        }

        return [
            'discount_amount' => $amount,
            'message' => $this->message($discount, $amount),
        ];
    }

    private function message(Discount $discount, float $amount): string
    {
        return match ($discount->type) {
            'percentage' => "{$discount->name}: {$discount->value}% تخفیف (" . number_format($amount) . ' تومان)',
            'fixed_amount' => "{$discount->name}: " . number_format($amount) . ' تومان تخفیف',
            'free_product' => $discount->freeProduct
                ? "{$discount->name}: دریافت {$discount->freeProduct->name} رایگان"
                : $discount->message,
            default => $discount->message,
        };
    }

    private function hasUsedDiscount(Customer $customer, Discount $discount): bool
    {
        return $customer->discounts()
            ->where('discounts.id', $discount->id)
            ->wherePivotNotNull('used_at')
            ->exists();
    }

    /**
     * ثبت مصرف تخفیف برای مشتری.
     * برای تخفیف‌های عمومی (target=all) ردیف pivot وجود ندارد، پس ابتدا ایجاد می‌شود.
     */
    private function markDiscountAsUsed(Customer $customer, Discount $discount): void
    {
        if (! $discount->auto_disable_after_use) {
            return;
        }

        $customer->discounts()->syncWithoutDetaching([$discount->id => ['used_at' => null]]);
        $customer->discounts()->updateExistingPivot($discount->id, ['used_at' => now()]);
    }
}
