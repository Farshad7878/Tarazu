<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderReturnController extends Controller
{
    public function store(Request $request, Order $order, OrderReturnService $service): JsonResponse
    {
        // مثل نمایش سفارش، فقط سفارش‌های همان ریسپشن قابل بازگشت هستند
        abort_unless($order->reception_id === $request->user()->id, 404);

        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'required|integer|exists:order_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'reason' => 'required|string|max:255',
            'restock' => 'sometimes|boolean',
        ]);

        $order->load('items');

        if (! $order->canBeReturned()) {
            return response()->json([
                'message' => 'این سفارش دیگر قابل بازگشت نیست (بازگشت کامل شده یا مهلت '.config('returns.window_days').' روزه گذشته است).',
            ], 422);
        }

        $return = $service->process(
            $order,
            $data['items'],
            $data['reason'],
            (bool) ($data['restock'] ?? true),
            $request->user(),
        );

        $order->refresh();

        return response()->json([
            'message' => 'بازگشت کالا با موفقیت ثبت شد.',
            'return' => [
                'id' => $return->id,
                'refund_amount' => (float) $return->refund_amount,
                'tax_refund' => (float) $return->tax_refund,
                'restock' => $return->restock,
                'reason' => $return->reason,
            ],
            'order' => [
                'id' => $order->id,
                'total_amount' => (float) $order->total_amount,
                'tax_amount' => (float) $order->tax_amount,
                'refunded_amount' => (float) $order->refunded_amount,
                'returned_status' => $order->returnedStatus(),
            ],
        ], 201);
    }
}
