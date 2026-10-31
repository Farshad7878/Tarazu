<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Services\DiscountService;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    protected $discountService;

    public function __construct(DiscountService $discountService)
    {
        $this->discountService = $discountService;
    }

    /**
     * پیش‌نمایش مبلغ سفارش: تخفیف‌های خودکار (اگر روشن باشند) و استفاده از امتیاز وفاداری.
     * همان منطقی است که هنگام ثبت سفارش اجرا می‌شود؛ مبلغ نهایی را سرور تعیین می‌کند.
     */
    public function calculateOrderDiscount(Request $request, LoyaltyService $loyalty)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'apply_discounts' => 'boolean',
            'redeem_points' => 'nullable|integer|min:0',
            'gift_card_code' => 'nullable|string|max:20',
        ]);

        $customer = Customer::find($request->customer_id);
        $cartItems = $request->items;

        // محاسبه سابتوتال
        $subtotal = 0;
        foreach ($cartItems as $item) {
            $product = Product::find($item['product_id']);
            $subtotal += $product->price * $item['quantity'];
        }

        $auto = $request->boolean('apply_discounts', true)
            ? $this->discountService->calculateRealTimeDiscount($customer, $cartItems, $subtotal)
            : ['total_discount' => 0, 'applied_discounts' => []];

        $redemption = $loyalty->previewRedemption(
            $customer,
            (int) $request->input('redeem_points', 0),
            $subtotal - $auto['total_discount'],
        );

        $appliedDiscounts = $auto['applied_discounts'];

        if ($redemption['points'] > 0) {
            $appliedDiscounts[] = [
                'id' => 'loyalty',
                'name' => 'امتیاز وفاداری',
                'type' => 'loyalty',
                'amount' => $redemption['amount'],
                'message' => "استفاده از {$redemption['points']} امتیاز",
            ];
        }

        $totalDiscount = $auto['total_discount'] + $redemption['amount'];

        $finalAmount = max(0, $subtotal - $totalDiscount);

        // پیش‌نمایش گیفت کارت: فقط نمایش؛ کارت در ثبت نهایی قفل و مصرف می‌شود
        $gift = ['applied' => 0.0, 'balance' => null, 'error' => null];
        if ($request->filled('gift_card_code')) {
            $service = app(\App\Services\GiftCardService::class);
            $card = \App\Models\GiftCard::where('code', strtoupper(trim($request->gift_card_code)))->first();

            if (! $card) {
                $gift['error'] = 'گیفت کارت با این کد پیدا نشد.';
            } elseif (! $card->isUsable()) {
                $gift['error'] = 'این گیفت کارت معتبر نیست (غیرفعال، منقضی یا بدون موجودی).';
                $gift['balance'] = (float) $card->balance;
            } else {
                $gift['balance'] = (float) $card->balance;
                $gift['applied'] = $service->applicableAmount($card, (float) $finalAmount);
            }
        }

        return response()->json([
            'total_discount' => $totalDiscount,
            'applied_discounts' => $appliedDiscounts,
            'subtotal' => $subtotal,
            'final_amount' => $finalAmount,
            'gift_card' => $gift,
            'payable_amount' => round($finalAmount - $gift['applied'], 2),
            'loyalty_points_used' => $redemption['points'],
            'loyalty_discount' => $redemption['amount'],
        ]);
    }
}
