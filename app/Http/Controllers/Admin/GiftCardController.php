<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\GiftCard;
use App\Models\GiftCardTransaction;
use App\Services\GiftCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiftCardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cards = GiftCard::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('code', 'like', '%'.strtoupper(trim($request->search)).'%');
            })
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'gift_cards' => $cards->getCollection()->map(fn (GiftCard $c) => $this->format($c))->values(),
            'pagination' => [
                'current_page' => $cards->currentPage(),
                'last_page' => $cards->lastPage(),
                'total' => $cards->total(),
            ],
        ]);
    }

    public function store(Request $request, GiftCardService $service): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:1000|max:1000000000000',
            'payment_method' => 'required|in:cash,card',
            'customer_membership_number' => 'nullable|string|exists:customers,membership_number',
        ]);

        $customer = ! empty($data['customer_membership_number'])
            ? Customer::where('membership_number', $data['customer_membership_number'])->first()
            : null;

        $card = $service->issue((float) $data['amount'], $data['payment_method'], $customer, $request->user(), null);

        return response()->json([
            'message' => 'گیفت کارت صادر شد.',
            'gift_card' => $this->format($card),
        ], 201);
    }

    public function show(GiftCard $giftCard): JsonResponse
    {
        return response()->json([
            'gift_card' => $this->format($giftCard),
            'transactions' => GiftCardTransaction::where('gift_card_id', $giftCard->id)
                ->with('user:id,name')
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn (GiftCardTransaction $t) => [
                    'id' => $t->id,
                    'type_label' => $t->type_label,
                    'amount' => (float) $t->amount,
                    'balance_after' => (float) $t->balance_after,
                    'payment_method' => $t->payment_method,
                    'order_id' => $t->order_id,
                    'user_name' => $t->user?->name,
                    'created_at' => $t->created_at?->format('Y-m-d H:i'),
                ])->values(),
        ]);
    }

    public function update(Request $request, GiftCard $giftCard): JsonResponse
    {
        $data = $request->validate(['is_active' => 'required|boolean']);

        $giftCard->update(['is_active' => $data['is_active']]);

        return response()->json([
            'message' => $data['is_active'] ? 'گیفت کارت فعال شد.' : 'گیفت کارت غیرفعال شد.',
            'gift_card' => $this->format($giftCard->fresh()),
        ]);
    }

    private function format(GiftCard $card): array
    {
        return [
            'id' => $card->id,
            'code' => $card->code,
            'initial_amount' => (float) $card->initial_amount,
            'balance' => (float) $card->balance,
            'is_active' => (bool) $card->is_active,
            'is_usable' => $card->isUsable(),
            'customer_name' => $card->customer?->full_name,
            'issued_by' => $card->issuer?->name,
            'expires_at' => $card->expires_at?->format('Y-m-d'),
            'created_at' => $card->created_at?->format('Y-m-d H:i'),
        ];
    }
}
