<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\GiftCard;
use App\Services\CashShiftService;
use App\Services\GiftCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GiftCardController extends Controller
{
    /**
     * صدور گیفت کارت توسط ریسپشن (پول دریافتی وارد صندوق همان کاربر می‌شود)
     */
    public function store(Request $request, GiftCardService $service, CashShiftService $shifts): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:1000|max:1000000000000',
            'payment_method' => 'required|in:cash,card',
            'customer_membership_number' => 'nullable|string|exists:customers,membership_number',
        ]);

        $user = $request->user();
        $shiftId = $shifts->currentIdFor($user);

        if (config('cash.shift_required') && ! $shiftId) {
            throw ValidationException::withMessages(['shift' => ['ابتدا صندوق را باز کنید.']]);
        }

        $customer = ! empty($data['customer_membership_number'])
            ? Customer::where('membership_number', $data['customer_membership_number'])->first()
            : null;

        $card = $service->issue((float) $data['amount'], $data['payment_method'], $customer, $user, $shiftId);

        return response()->json([
            'message' => 'گیفت کارت صادر شد. کد را به مشتری بدهید.',
            'gift_card' => [
                'code' => $card->code,
                'balance' => (float) $card->balance,
                'expires_at' => $card->expires_at?->format('Y-m-d'),
            ],
        ], 201);
    }

    /**
     * استعلام موجودی با کد (بدون قفل و بدون مصرف)
     */
    public function lookup(string $code): JsonResponse
    {
        $card = GiftCard::where('code', strtoupper(trim($code)))->first();

        if (! $card) {
            return response()->json(['message' => 'گیفت کارت با این کد پیدا نشد.'], 404);
        }

        return response()->json([
            'gift_card' => [
                'code' => $card->code,
                'balance' => (float) $card->balance,
                'is_usable' => $card->isUsable(),
                'expires_at' => $card->expires_at?->format('Y-m-d'),
            ],
        ]);
    }
}
