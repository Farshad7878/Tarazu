<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * امتیاز وفاداری: کسب، استفاده، برگشت و تعدیل دستی.
 *
 * قواعد:
 *  - کسب: floor(مبلغ پرداختی × ضریب سطح ÷ amount_per_point). مبلغ پرداختی = total_amount سفارش (بعد از همه‌ی تخفیف‌ها).
 *  - استفاده: هر امتیاز config('loyalty.point_value') تومان تخفیف است؛ به‌عنوان بخشی از تخفیف‌های سفارش ثبت می‌شود.
 *  - بازگشت جزئی: امتیاز کسب‌شده به نسبت مبلغ برگشتی کم می‌شود و امتیاز مصرف‌شده به همان نسبت برمی‌گردد.
 *  - بازگشت کامل یا لغو سفارش: کل امتیاز کسب‌شده‌ی باقی‌مانده کم و کل مصرف‌شده برمی‌گردد.
 *  - موجودی امتیاز هرگز منفی نمی‌شود: اگر مشتری امتیاز کسب‌شده را خرج کرده باشد، کسر تا صفر انجام می‌شود.
 *  - سطح مشتری بر اساس loyalty_lifetime_points است و با برگشت کم نمی‌شود.
 *
 * همه‌ی تغییرات باید داخل تراکنش فراخوانی شوند.
 */
class LoyaltyService
{
    /** @return array{name: string, multiplier: float} */
    public function tierFor(int $lifetimePoints): array
    {
        $tiers = collect(config('loyalty.tiers'))->sortByDesc('min');

        return $tiers->first(fn ($tier) => $lifetimePoints >= $tier['min'])
            ?? ['name' => 'برنزی', 'min' => 0, 'multiplier' => 1.0];
    }

    /**
     * خلاصه‌ی امتیاز برای نمایش در فرانت (سطح، موجودی، ارزش هر امتیاز).
     *
     * @return array<string, mixed>
     */
    public function summary(Customer $customer): array
    {
        $tier = $this->tierFor((int) $customer->loyalty_lifetime_points);

        return [
            'points_balance' => (int) $customer->loyalty_points,
            'lifetime_points' => (int) $customer->loyalty_lifetime_points,
            'tier' => $tier['name'],
            'earn_multiplier' => (float) $tier['multiplier'],
            'amount_per_point' => (int) config('loyalty.amount_per_point'),
            'point_value' => (int) config('loyalty.point_value'),
            'min_redeem_points' => (int) config('loyalty.min_redeem_points'),
        ];
    }

    public function earnableFor(Customer $customer, float $paidAmount): int
    {
        $perPoint = max(1, (int) config('loyalty.amount_per_point'));
        $multiplier = $this->tierFor((int) $customer->loyalty_lifetime_points)['multiplier'];

        return (int) floor($paidAmount * $multiplier / $perPoint);
    }

    /**
     * بررسی درخواست استفاده از امتیاز (بدون تغییر در دیتابیس).
     *
     * @return array{points: int, amount: float}
     */
    public function previewRedemption(Customer $customer, int $requested, float $remainingAmount): array
    {
        if ($requested <= 0) {
            return ['points' => 0, 'amount' => 0.0];
        }

        $min = (int) config('loyalty.min_redeem_points');
        $value = max(1, (int) config('loyalty.point_value'));
        $balance = (int) $customer->loyalty_points;
        $maxByAmount = (int) floor($remainingAmount / $value);

        if ($requested < $min) {
            throw ValidationException::withMessages([
                'redeem_points' => ["حداقل {$min} امتیاز برای استفاده لازم است."],
            ]);
        }

        if ($requested > $balance) {
            throw ValidationException::withMessages([
                'redeem_points' => ["موجودی امتیاز مشتری {$balance} امتیاز است."],
            ]);
        }

        if ($requested > $maxByAmount) {
            throw ValidationException::withMessages([
                'redeem_points' => ["حداکثر امتیاز قابل استفاده برای این سفارش {$maxByAmount} امتیاز است."],
            ]);
        }

        return ['points' => $requested, 'amount' => (float) ($requested * $value)];
    }

    /**
     * کم کردن امتیاز مصرفی هنگام ثبت سفارش (بعد از بررسی previewRedemption).
     */
    public function redeem(Customer $customer, Order $order, int $points, ?User $user = null): void
    {
        if ($points <= 0) {
            return;
        }

        $this->change($customer, -$points, 'redeem', [
            'order_id' => $order->id,
            'user_id' => $user?->id,
            'reason' => 'استفاده در سفارش '.$order->order_number,
        ]);

        $order->forceFill(['loyalty_points_redeemed' => $points])->save();
    }

    /**
     * ثبت امتیاز کسب‌شده‌ی سفارش.
     */
    public function earn(Customer $customer, Order $order, ?User $user = null): int
    {
        $points = $this->earnableFor($customer, (float) $order->total_amount);

        if ($points > 0) {
            $this->change($customer, $points, 'earn', [
                'order_id' => $order->id,
                'user_id' => $user?->id,
                'reason' => 'خرید سفارش '.$order->order_number,
            ]);
        }

        $order->forceFill(['loyalty_points_earned' => $points])->save();

        return $points;
    }

    /**
     * برگشت کالا: امتیاز به نسبت مبلغ برگشتی یا کامل (وقتی سفارش کامل برگشت) اصلاح می‌شود.
     * فیلدهای امتیاز سفارش همین‌جا کم می‌شوند؛ ذخیره‌ی سفارش با فراخواننده است.
     */
    public function reverseForReturn(
        Order $order,
        float $refundAmount,
        float $totalBefore,
        bool $fullyReturned,
        OrderReturn $return,
        ?User $user = null,
    ): void {
        if ($fullyReturned) {
            $earnBack = (int) $order->loyalty_points_earned;
            $redeemBack = (int) $order->loyalty_points_redeemed;
        } else {
            $fraction = $totalBefore > 0 ? min(1.0, $refundAmount / $totalBefore) : 0.0;
            $earnBack = (int) round($order->loyalty_points_earned * $fraction);
            $redeemBack = (int) round($order->loyalty_points_redeemed * $fraction);
        }

        $this->reverseAmounts($order, $earnBack, $redeemBack, $return->id, $user);
    }

    /**
     * لغو سفارش: همه‌ی امتیاز باقی‌مانده برمی‌گردد.
     */
    public function reverseForCancel(Order $order, ?User $user = null): void
    {
        $this->reverseAmounts(
            $order,
            (int) $order->loyalty_points_earned,
            (int) $order->loyalty_points_redeemed,
            null,
            $user,
        );
    }

    /**
     * تعدیل دستی توسط ادمین (مثبت یا منفی).
     */
    public function adjust(Customer $customer, int $points, string $reason, User $user): LoyaltyTransaction
    {
        $locked = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();

        if ($points < 0 && -$points > $locked->loyalty_points) {
            throw ValidationException::withMessages([
                'points' => ["موجودی امتیاز مشتری {$locked->loyalty_points} امتیاز است."],
            ]);
        }

        $this->change($locked, $points, 'adjust', [
            'user_id' => $user->id,
            'reason' => $reason,
        ]);

        return LoyaltyTransaction::where('customer_id', $customer->id)->latest('id')->firstOrFail();
    }

    private function reverseAmounts(Order $order, int $earnBack, int $redeemBack, ?int $returnId, ?User $user): void
    {
        $customer = Customer::findOrFail($order->customer_id);
        $context = [
            'order_id' => $order->id,
            'order_return_id' => $returnId,
            'user_id' => $user?->id,
            'reason' => $returnId ? 'بازگشت کالای سفارش '.$order->order_number : 'لغو سفارش '.$order->order_number,
        ];

        if ($earnBack > 0) {
            $this->change($customer, -$earnBack, 'reverse_earn', $context);
        }

        if ($redeemBack > 0) {
            $this->change($customer, $redeemBack, 'restore_redeem', $context);
        }

        $order->forceFill([
            'loyalty_points_earned' => max(0, (int) $order->loyalty_points_earned - $earnBack),
            'loyalty_points_redeemed' => max(0, (int) $order->loyalty_points_redeemed - $redeemBack),
        ]);
    }

    /**
     * تغییر موجودی با قفل ردیف مشتری. کسر هرگز موجودی را منفی نمی‌کند.
     */
    private function change(Customer $customer, int $delta, string $type, array $context): void
    {
        $locked = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();

        if ($delta < 0) {
            $delta = -min(-$delta, (int) $locked->loyalty_points);
        }

        if ($delta === 0) {
            return;
        }

        $balance = (int) $locked->loyalty_points + $delta;
        $locked->loyalty_points = $balance;

        if ($type === 'earn') {
            $locked->loyalty_lifetime_points = (int) $locked->loyalty_lifetime_points + $delta;
        }

        $locked->save();

        LoyaltyTransaction::create(array_merge([
            'customer_id' => $locked->id,
            'type' => $type,
            'points' => $delta,
            'balance_after' => $balance,
        ], array_intersect_key($context, array_flip([
            'order_id', 'order_return_id', 'user_id', 'reason',
        ]))));

        // مقدار مشتری ورودی هم به‌روز بماند تا فراخواننده‌ی بعدی اشتباه نکند
        $customer->setRawAttributes($locked->getAttributes(), true);
    }
}
