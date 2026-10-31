<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\GiftCard;
use App\Models\GiftCardTransaction;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * گیفت کارت: صدور، مصرف در سفارش و بازگشت مبلغ.
 * هر تغییر موجودی همزمان در gift_card_transactions ثبت می‌شود.
 * فراخواننده باید کارت را با lockForUpdate گرفته باشد.
 */
class GiftCardService
{
    public function issue(float $amount, string $paymentMethod, ?Customer $customer, User $user, ?int $cashShiftId): GiftCard
    {
        $card = GiftCard::create([
            'code' => GiftCard::generateCode(),
            'initial_amount' => $amount,
            'balance' => $amount,
            'is_active' => true,
            'customer_id' => $customer?->id,
            'issued_by' => $user->id,
            'expires_at' => now()->addDays((int) config('gift_cards.validity_days', 365)),
        ]);

        $this->log($card, 'issue', $amount, [
            'payment_method' => $paymentMethod,
            'cash_shift_id' => $cashShiftId,
            'user_id' => $user->id,
        ]);

        return $card;
    }

    /**
     * کارت را با کد پیدا و قفل می‌کند؛ نبودن کارت یعنی خطای اعتبارسنجی
     */
    public function findForUpdate(string $code): ?GiftCard
    {
        return GiftCard::where('code', strtoupper(trim($code)))->lockForUpdate()->first();
    }

    /**
     * مبلغی که از این کارت برای مبلغ قابل پرداخت استفاده می‌شود
     */
    public function applicableAmount(GiftCard $card, float $payable): float
    {
        if (! $card->isUsable() || $payable <= 0) {
            return 0.0;
        }

        return round(min((float) $card->balance, $payable), 2);
    }

    /**
     * کارت معتبر باشد وگرنه خطای اعتبارسنجی (برای ثبت سفارش)
     */
    public function assertUsable(?GiftCard $card): GiftCard
    {
        if (! $card) {
            throw ValidationException::withMessages(['gift_card_code' => ['گیفت کارت با این کد پیدا نشد.']]);
        }

        if (! $card->isUsable()) {
            throw ValidationException::withMessages(['gift_card_code' => ['این گیفت کارت معتبر نیست (غیرفعال، منقضی یا بدون موجودی).']]);
        }

        return $card;
    }

    public function redeem(GiftCard $card, float $amount, Order $order, ?User $user): void
    {
        $this->changeBalance($card, -$amount, 'redeem', [
            'order_id' => $order->id,
            'user_id' => $user?->id,
        ]);
    }

    /**
     * بازگشت مبلغ به گیفت کارت (مرجوعی یا لغو سفارش)؛ اعتبار کارت را تمدید نمی‌کند ولی غیرفعال‌شده را تغییر نمی‌دهد
     */
    public function credit(GiftCard $card, float $amount, string $type, Order $order, ?int $orderReturnId, ?User $user): void
    {
        $this->changeBalance($card, $amount, $type, [
            'order_id' => $order->id,
            'order_return_id' => $orderReturnId,
            'user_id' => $user?->id,
        ]);
    }

    private function changeBalance(GiftCard $card, float $delta, string $type, array $context): void
    {
        $card->balance = round((float) $card->balance + $delta, 2);
        $card->save();

        $this->log($card, $type, $delta, $context);
    }

    private function log(GiftCard $card, string $type, float $amount, array $context): void
    {
        GiftCardTransaction::create([
            'gift_card_id' => $card->id,
            'type' => $type,
            'amount' => round($amount, 2),
            'balance_after' => $card->balance,
            'payment_method' => $context['payment_method'] ?? null,
            'order_id' => $context['order_id'] ?? null,
            'order_return_id' => $context['order_return_id'] ?? null,
            'cash_shift_id' => $context['cash_shift_id'] ?? null,
            'user_id' => $context['user_id'] ?? null,
        ]);
    }
}
