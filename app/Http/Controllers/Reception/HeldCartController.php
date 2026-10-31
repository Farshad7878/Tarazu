<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Models\BranchStock;
use App\Models\Customer;
use App\Models\HeldCart;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * سبد معلق: سبد فعلی ریسپشن برای ادامه‌ی بعدی ذخیره می‌شود.
 * نکته‌ها:
 *  - معلق‌کردن موجودی را رزرو نمی‌کند؛ موجودی فقط هنگام ثبت سفارش بررسی و کم می‌شود.
 *  - امتیاز وفاداری، کارت هدیه و تخفیف‌های انتخاب‌شده ذخیره نمی‌شوند؛ هنگام بازیابی دوباره محاسبه می‌شوند.
 *  - بازیابی اتمیک است: ردیف با قفل خوانده و حذف می‌شود تا یک سبد دو بار بازیابی نشود.
 */
class HeldCartController extends Controller
{
    private const MAX_PER_RECEPTION = 20;

    public function index(Request $request): JsonResponse
    {
        $carts = HeldCart::with('customer')
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at')
            ->get();

        $productNames = Product::withTrashed()
            ->whereIn('id', $this->productIds($carts->pluck('items')))
            ->get()
            ->keyBy('id');

        $activeIds = Product::active()
            ->whereIn('id', $this->productIds($carts->pluck('items')))
            ->pluck('id')
            ->all();

        return response()->json([
            'held_carts' => $carts->map(function (HeldCart $cart) use ($productNames, $activeIds) {
                return [
                    'id' => $cart->id,
                    'customer' => $cart->customer ? [
                        'name' => $cart->customer->full_name,
                        'membership_number' => $cart->customer->membership_number,
                    ] : null,
                    'notes' => $cart->notes,
                    'items_count' => collect($cart->items)->sum('quantity'),
                    'lines' => collect($cart->items)->map(fn ($line) => [
                        'product_id' => $line['product_id'],
                        'name' => $productNames->get($line['product_id'])?->name ?? 'کالای حذف‌شده',
                        'quantity' => $line['quantity'],
                        'available' => in_array($line['product_id'], $activeIds, true),
                    ])->values(),
                    'created_at' => $cart->created_at->format('Y-m-d H:i'),
                ];
            })->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_membership_number' => 'nullable|string|exists:customers,membership_number',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:999',
            'notes' => 'nullable|string|max:500',
        ]);

        $count = HeldCart::where('user_id', $request->user()->id)->count();
        if ($count >= self::MAX_PER_RECEPTION) {
            return response()->json([
                'message' => 'حداکثر '.self::MAX_PER_RECEPTION.' سبد معلق دارید. ابتدا یکی را بازیابی یا حذف کنید.',
            ], 422);
        }

        $items = $this->mergeLines($data['items']);
        $activeCount = Product::active()->whereIn('id', array_keys($items))->count();

        if ($activeCount !== count($items)) {
            throw ValidationException::withMessages([
                'items' => ['برخی کالاها فعال نیستند و نمی‌توان سبد را معلق کرد.'],
            ]);
        }

        $customer = empty($data['customer_membership_number'] ?? null)
            ? null
            : Customer::where('membership_number', $data['customer_membership_number'])->first();

        $cart = HeldCart::create([
            'user_id' => $request->user()->id,
            'customer_id' => $customer?->id,
            'items' => array_values(array_map(
                fn ($productId, $quantity) => ['product_id' => $productId, 'quantity' => $quantity],
                array_keys($items),
                array_values($items),
            )),
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'سبد معلق شد.',
            'held_cart' => ['id' => $cart->id],
        ], 201);
    }

    public function resume(Request $request, HeldCart $heldCart): JsonResponse
    {
        abort_unless($heldCart->user_id === $request->user()->id, 404);

        return DB::transaction(function () use ($heldCart) {
            $cart = HeldCart::whereKey($heldCart->id)->lockForUpdate()->firstOrFail();

            $lines = collect($cart->items);
            $ids = $lines->pluck('product_id')->unique()->all();

            $names = Product::withTrashed()->whereIn('id', $ids)->get()->keyBy('id');
            $active = Product::active()->whereIn('id', $ids)->get()->keyBy('id');

            // موجودی کالاها در شعبه‌ی همین کاربر پذیرش بررسی می‌شود
            $stockAt = BranchStock::quantitiesFor($ids, (int) auth()->user()->branch_id);

            $restore = [];
            $skipped = [];
            $adjusted = [];

            foreach ($lines as $line) {
                $name = $names->get($line['product_id'])?->name ?? 'کالای حذف‌شده';
                $product = $active->get($line['product_id']);

                if (! $product || $stockAt[(int) $product->id] <= 0) {
                    $skipped[] = $name;

                    continue;
                }

                $quantity = min((int) $line['quantity'], $stockAt[(int) $product->id]);

                if ($quantity < (int) $line['quantity']) {
                    $adjusted[] = "{$name} ({$line['quantity']} → {$quantity})";
                }

                $restore[] = ['product_id' => $product->id, 'quantity' => $quantity];
            }

            $customer = $cart->customer_id ? Customer::find($cart->customer_id) : null;
            $notes = $cart->notes;

            $cart->delete();

            return response()->json([
                'customer_membership_number' => $customer?->membership_number,
                'items' => $restore,
                'notes' => $notes,
                'skipped' => $skipped,
                'adjusted' => $adjusted,
            ]);
        });
    }

    public function destroy(Request $request, HeldCart $heldCart): JsonResponse
    {
        abort_unless($heldCart->user_id === $request->user()->id, 404);

        $heldCart->delete();

        return response()->json(['message' => 'سبد معلق حذف شد.']);
    }

    /** @return array<int, int> product_id => quantity (ردیف‌های تکراری جمع می‌شوند) */
    private function mergeLines(array $lines): array
    {
        $merged = [];

        foreach ($lines as $line) {
            $id = (int) $line['product_id'];
            $merged[$id] = ($merged[$id] ?? 0) + (int) $line['quantity'];
        }

        return $merged;
    }

    /** @param  iterable<int, array>  $itemLists */
    private function productIds($itemLists): array
    {
        return collect($itemLists)->flatten(1)->pluck('product_id')->unique()->values()->all();
    }
}
