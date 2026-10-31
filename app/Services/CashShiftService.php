<?php

namespace App\Services;

use App\Models\CashShift;
use App\Models\GiftCardTransaction;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * شیفت صندوق.
 *
 * موجودی مورد انتظار = موجودی اولیه + فروش نقدی شیفت − بازگشت نقدی ثبت‌شده در همین شیفت.
 * مبلغ پرداختی هر سفارش = subtotal − discount_amount (امتیاز وفاداری هم جزو discount_amount است و پول نقد نیست).
 * سفارش‌های لغوشده حذف فیزیکی می‌شوند، پس از جمع‌ها خارج می‌شوند.
 */
class CashShiftService
{
    public function currentFor(User $user): ?CashShift
    {
        return CashShift::where('user_id', $user->id)->whereNull('closed_at')->latest('id')->first();
    }

    public function currentIdFor(User $user): ?int
    {
        return $this->currentFor($user)?->id;
    }

    public function open(User $user, float $openingCash, ?string $notes = null): CashShift
    {
        return DB::transaction(function () use ($user, $openingCash, $notes) {
            // قفل ردیف کاربر تا دو درخواست همزمان یکدیگر را منتظر بگذارند
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($this->currentFor($user)) {
                throw $this->alreadyOpen();
            }

            try {
                // open_user_id ایندکس یکتا دارد: حتی اگر قفل بالا کار نکند، دومین صندوق باز ثبت نمی‌شود
                return CashShift::create([
                    'user_id' => $user->id,
                    'open_user_id' => $user->id,
                    'opened_at' => now(),
                    'opening_cash' => $openingCash,
                    'notes' => $notes,
                ]);
            } catch (QueryException $e) {
                // SQLSTATE 23000 = نقض محدودیت یکتایی (MySQL و SQLite)
                if ((string) $e->getCode() === '23000') {
                    throw $this->alreadyOpen();
                }

                throw $e;
            }
        });
    }

    private function alreadyOpen(): ValidationException
    {
        return ValidationException::withMessages([
            'opening_cash' => ['صندوق شما قبلاً باز است.'],
        ]);
    }

    /**
     * @return array<string, float|int>
     */
    public function summary(CashShift $shift): array
    {
        $sales = Order::where('cash_shift_id', $shift->id)
            ->selectRaw('payment_method, COUNT(*) as orders_count, SUM(subtotal - discount_amount - gift_card_amount) as paid')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $refunds = OrderReturn::query()
            ->join('orders', 'orders.id', '=', 'order_returns.order_id')
            ->where('order_returns.cash_shift_id', $shift->id)
            ->selectRaw('orders.payment_method, SUM(order_returns.refund_amount - order_returns.gift_card_credit) as refunded')
            ->groupBy('orders.payment_method')
            ->get()
            ->keyBy('payment_method');

        // گیفت کارتی که در این صندوق فروخته شده، پول نقد یا کارتی است که وارد صندوق شده
        $giftCardSales = GiftCardTransaction::where('cash_shift_id', $shift->id)
            ->where('type', 'issue')
            ->selectRaw('payment_method, SUM(amount) as sold')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $giftSoldCash = (float) ($giftCardSales['cash']->sold ?? 0);
        $giftSoldCard = (float) ($giftCardSales['card']->sold ?? 0);

        $salesCash = (float) ($sales['cash']->paid ?? 0);
        $salesCard = (float) ($sales['card']->paid ?? 0);
        $refundsCash = (float) ($refunds['cash']->refunded ?? 0);
        $refundsCard = (float) ($refunds['card']->refunded ?? 0);
        $opening = (float) $shift->opening_cash;

        return [
            'opening_cash' => $opening,
            'orders_count' => (int) ($sales->sum('orders_count')),
            'sales_cash' => $salesCash,
            'sales_card' => $salesCard,
            'refunds_cash' => $refundsCash,
            'refunds_card' => $refundsCard,
            'gift_cards_sold_cash' => $giftSoldCash,
            'gift_cards_sold_card' => $giftSoldCard,
            'expected_cash' => $opening + $salesCash + $giftSoldCash - $refundsCash,
        ];
    }

    public function close(CashShift $shift, float $countedCash, ?string $notes = null): CashShift
    {
        return DB::transaction(function () use ($shift, $countedCash, $notes) {
            $locked = CashShift::whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages([
                    'counted_cash' => ['این صندوق قبلاً بسته شده است.'],
                ]);
            }

            $expected = (float) $this->summary($locked)['expected_cash'];

            // open_user_id پاک می‌شود تا کاربر بتواند صندوق جدید باز کند
            $locked->update([
                'closed_at' => now(),
                'open_user_id' => null,
                'expected_cash' => $expected,
                'counted_cash' => $countedCash,
                'difference' => round($countedCash - $expected, 2),
                'notes' => $notes ?? $locked->notes,
            ]);

            return $locked->fresh();
        });
    }
}
