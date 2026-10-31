<?php

namespace App\Http\Controllers\Reception;

use App\Events\DiscountApplied;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Customer;
use App\Models\GiftCard;
use App\Models\Order;
use App\Models\OrderDiscount;
use App\Models\OrderExchange;
use App\Models\Product;
use App\Models\User;
use App\Services\CashShiftService;
use App\Services\DiscountService;
use App\Services\GiftCardService;
use App\Services\AuditLogger;
use App\Services\LoyaltyService;
use App\Services\OrderReturnService;
use App\Services\StockService;
use App\Services\TaxService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    private const MAX_ORDER_NUMBER_ATTEMPTS = 3;

    public function index(Request $request)
    {
        $request->validate([
            'search' => 'sometimes',
            'date_range' => 'sometimes|in:today,yesterday,week,month,all',
            'sort' => 'sometimes|in:newest,oldest,highest,lowest',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = Order::with(['customer', 'items.product', 'discounts'])
            ->where('reception_id', auth()->id());

        // فیلتر جستجو
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('membership_number', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        // فیلتر بازه زمانی
        if ($request->has('date_range') && $request->date_range !== 'all') {
            $dateRange = $this->getDateRange($request->date_range);
            $query->whereBetween('created_at', $dateRange);
        }

        // مرتب‌سازی
        $sortBy = $this->getSortOrder($request->sort);
        $query->orderBy($sortBy['field'], $sortBy['direction']);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $orders = $query->paginate($perPage);

        // محاسبه آمار
        $stats = $this->getOrdersStats(auth()->id(), $request->date_range);

        return response()->json([
            'orders' => $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer->full_name,
                    'customer_membership' => $order->customer->membership_number,
                    'customer_phone' => $order->customer->phone,
                    'subtotal' => (float) $order->subtotal,
                    'total_amount' => (float) $order->total_amount,
                    'tax_amount' => (float) $order->tax_amount,
                    'refunded_amount' => (float) $order->refunded_amount,
                    'returned_status' => $order->returnedStatus(),
                    'discount_amount' => (float) $order->discount_amount,
                    'created_at' => $order->created_at,
                    'created_date' => $order->created_at->format('Y-m-d'),
                    'created_time' => $order->created_at->format('H:i'),
                    'items_count' => $order->items->count(),
                    'notes' => $order->notes,
                    'applied_discounts' => $this->formatAppliedDiscounts($order),
                ];
            }),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ],
            'stats' => $stats,
            'filters' => [
                'search' => $request->search,
                'date_range' => $request->date_range,
                'sort' => $request->sort,
            ],
        ]);
    }

    public function show($id)
    {
        $order = Order::with([
            'customer',
            'items.product',
            'reception',
            'discounts',
            'returns.items.orderItem.product',
            'returns.user',
        ])->where('reception_id', auth()->id())->findOrFail($id);

        return response()->json([
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer->full_name,
                'customer_membership' => $order->customer->membership_number,
                'customer_phone' => $order->customer->phone,
                'subtotal' => (float) $order->subtotal,
                'total_amount' => (float) $order->total_amount,
                'tax_rate' => (float) $order->tax_rate,
                'tax_amount' => (float) $order->tax_amount,
                'refunded_amount' => (float) $order->refunded_amount,
                'payment_method' => $order->payment_method,
                'gift_card_amount' => (float) $order->gift_card_amount,
                'loyalty_points_earned' => (int) $order->loyalty_points_earned,
                'loyalty_points_redeemed' => (int) $order->loyalty_points_redeemed,
                'returned_status' => $order->returnedStatus(),
                'can_return' => $order->canBeReturned(),
                'return_window_days' => (int) config('returns.window_days'),
                'discount_amount' => (float) $order->discount_amount,
                'created_at' => $order->created_at,
                'created_date' => $order->created_at->format('Y-m-d'),
                'created_time' => $order->created_at->format('H:i'),
                'notes' => $order->notes,
                'reception_name' => $order->reception->name,
                'returns' => $order->returns->sortByDesc('id')->values()->map(fn ($return) => [
                    'id' => $return->id,
                    'reason' => $return->reason,
                    'restock' => $return->restock,
                    'refund_amount' => (float) $return->refund_amount,
                    'tax_refund' => (float) $return->tax_refund,
                    'user_name' => $return->user?->name,
                    'created_at' => $return->created_at->format('Y-m-d H:i'),
                    'items' => $return->items->map(fn ($line) => [
                        'product_name' => $line->orderItem?->product?->name ?? 'کالای حذف‌شده',
                        'quantity' => $line->quantity,
                        'refund_amount' => (float) $line->refund_amount,
                    ])->values(),
                ])->values(),
                'items' => $order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_name' => $item->product->name,
                        'product_category' => $item->product->category_name,
                        'quantity' => $item->quantity,
                        'returned_quantity' => $item->returned_quantity,
                        'returnable_quantity' => $item->returnable_quantity,
                        'unit_price' => (float) $item->unit_price,
                        'total_price' => (float) $item->total_price,
                    ];
                }),
                'applied_discounts' => $this->formatAppliedDiscounts($order),
                'exchanges' => OrderExchange::with('newOrder:id,order_number')
                    ->where('original_order_id', $order->id)
                    ->get()
                    ->map(fn ($exchange) => [
                        'new_order_number' => $exchange->newOrder?->order_number,
                        'difference' => (float) $exchange->difference,
                        'created_at' => $exchange->created_at->format('Y-m-d H:i'),
                    ])->values(),
                'exchange_of' => OrderExchange::with('originalOrder:id,order_number')
                    ->where('new_order_id', $order->id)
                    ->first()?->originalOrder?->order_number,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_membership_number' => 'required|string|exists:customers,membership_number',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'apply_discounts' => 'boolean',
            'redeem_points' => 'nullable|integer|min:0',
            'payment_method' => 'nullable|in:cash,card',
            'notes' => 'nullable|string|max:500',
            'client_uuid' => 'nullable|string|max:36',
            'gift_card_code' => 'nullable|string|max:20',
        ]);

        // سفارش آفلاینی که قبلاً ثبت شده (همگام‌سازی دوباره): همان سفارش برگردانده می‌شود، نه سفارش تازه
        if ($request->filled('client_uuid')) {
            $existing = Order::where('client_uuid', $request->client_uuid)->first();

            if ($existing) {
                return $this->duplicateResponse($existing);
            }
        }

        $customer = Customer::where('membership_number', $request->customer_membership_number)->firstOrFail();

        // شماره‌ی سفارش با بیشینه‌ی موجود ساخته می‌شود؛ دو ثبت هم‌زمان ممکن است به یک شماره برسند.
        // در آن صورت unique روی ستون خطا می‌دهد و ثبت دوباره (با شماره‌ی تازه) انجام می‌شود.
        for ($attempt = 1; ; $attempt++) {
            try {
                $result = $this->placeOrder($request, $customer);
                break;
            } catch (UniqueConstraintViolationException $e) {
                // ثبت هم‌زمان همان سفارش آفلاین از دو تلاش: نتیجه‌ی تلاش اول را برگردان
                if ($request->filled('client_uuid')) {
                    $existing = Order::where('client_uuid', $request->client_uuid)->first();

                    if ($existing) {
                        return $this->duplicateResponse($existing);
                    }
                }

                if ($attempt >= self::MAX_ORDER_NUMBER_ATTEMPTS) {
                    throw $e;
                }
            }
        }

        return response()->json([
            'message' => 'سفارش با موفقیت ثبت شد.',
            'order' => $result['order'],
        ], 201);
    }

    /**
     * ثبت کامل سفارش در یک تراکنش: قفل موجودی، بررسی، کم‌کردن موجودی، تخفیف‌ها و مجموع مشتری.
     */
    private function placeOrder(Request $request, Customer $customer): array
    {
        return DB::transaction(function () use ($request, $customer) {
            // قفل مشتری: موجودی امتیاز باید در لحظه‌ی ثبت قطعی باشد
            $customer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();

            // قفل کاربر ریسپشن تا باز/بسته شدن صندوق همزمان با ثبت سفارش تداخل نکند
            $cashier = User::whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            $currentShift = app(CashShiftService::class)->currentFor($cashier);

            // فروش و کسر موجودی فقط از شعبه‌ی کاربر پذیرش انجام می‌شود
            $branch = $cashier->branch_id ? Branch::find($cashier->branch_id) : null;
            if (! $branch || ! $branch->is_active) {
                throw ValidationException::withMessages([
                    'branch' => ['کاربر به یک شعبه‌ی فعال متصل نیست. با مدیر سیستم تماس بگیرید.'],
                ]);
            }

            if (config('cash.shift_required') && ! $currentShift) {
                throw ValidationException::withMessages([
                    'shift' => ['ابتدا صندوق را باز کنید.'],
                ]);
            }

            // قفل ردیف محصولات تا دو سفارش همزمان نتوانند موجودی را دوبار مصرف کنند
            $products = Product::active()
                ->whereIn('id', collect($request->items)->pluck('product_id')->unique())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // تعداد درخواستی برای هر محصول (اگر یک محصول چند ردیف داشته باشد)
            $requestedQuantities = collect($request->items)
                ->groupBy('product_id')
                ->map(fn ($rows) => (int) $rows->sum('quantity'));

            // ۱) بررسی کامل قبل از هر تغییری (موجودی همین شعبه، قفل‌شده)
            $branchStock = BranchStock::quantitiesFor($requestedQuantities->keys()->all(), $branch->id, true);
            $errors = [];
            foreach ($requestedQuantities as $productId => $quantity) {
                $product = $products->get($productId);

                if (! $product) {
                    $errors[] = "محصول شماره {$productId} فعال نیست یا وجود ندارد.";
                } elseif ($branchStock[(int) $productId] < $quantity) {
                    $errors[] = "موجودی محصول {$product->name} در این شعبه کافی نیست. موجودی: {$branchStock[(int) $productId]}";
                }
            }

            if (! empty($errors)) {
                throw $this->stockError($errors);
            }

            // ۲) محاسبه قیمت‌ها بر اساس قیمت واقعی محصول در دیتابیس
            $subtotal = 0;
            $orderItems = [];
            foreach ($request->items as $item) {
                $product = $products->get($item['product_id']);
                $itemTotal = $product->price * $item['quantity'];

                $orderItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'total_price' => $itemTotal,
                ];

                $subtotal += $itemTotal;
            }

            // نرخ ارزش افزوده در لحظه‌ی ثبت روی سفارش ذخیره می‌شود (مالیات نهایی بعد از تخفیف محاسبه می‌شود)
            $taxService = app(TaxService::class);
            $taxRate = $taxService->rate();

            $order = Order::create([
                'customer_id' => $customer->id,
                'reception_id' => auth()->id(),
                'branch_id' => $branch->id,
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'total_amount' => $subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => 0,
                'payment_method' => $request->input('payment_method') ?? 'cash',
                'client_uuid' => $request->input('client_uuid'),
                'cash_shift_id' => $currentShift?->id,
                'notes' => $request->notes,
            ]);

            $order->items()->createMany($orderItems);
            $originalItemIds = $order->items()->pluck('id')->all();

            // ۳) کم کردن موجودی (با ثبت تاریخچه‌ی فروش؛ ردیف‌ها قبلاً با lockForUpdate قفل شده‌اند)
            $stock = app(StockService::class);
            foreach ($requestedQuantities as $productId => $quantity) {
                $stock->move($products->get($productId), -$quantity, 'sale', ['order_id' => $order->id, 'branch_id' => $branch->id]);
            }

            $appliedDiscounts = [];
            $totalDiscount = 0;

            // ۴) اعمال تخفیف‌های خودکار و ثبت آن‌ها روی سفارش
            // boolean() برای بدنه‌ی JSON کار می‌کند؛ get() فقط فرم/query را می‌خواند
            if ($request->boolean('apply_discounts', true)) {
                $discountResult = app(DiscountService::class)->calculateOrderDiscounts($order, true);

                $totalDiscount = $discountResult['total_discount'];

                foreach ($discountResult['applied_discounts'] as $applied) {
                    $discount = $applied['discount'];

                    OrderDiscount::create([
                        'order_id' => $order->id,
                        'discount_id' => $discount->id,
                        'name' => $discount->name,
                        'message' => $applied['message'],
                        'amount' => $applied['amount'],
                    ]);

                    $appliedDiscounts[] = [
                        'id' => $discount->id,
                        'name' => $discount->name,
                        'message' => $applied['message'],
                        'amount' => (float) $applied['amount'],
                    ];
                }

                // کالاهای هدیه‌ای که تخفیف به سفارش اضافه کرده هم باید از موجودی کم شوند
                $giftItems = $order->items()->whereNotIn('id', $originalItemIds)->get();
                foreach ($giftItems as $gift) {
                    $giftProduct = Product::whereKey($gift->product_id)->lockForUpdate()->first();
                    $giftAvailable = BranchStock::quantitiesFor([$gift->product_id], $branch->id, true);

                    if (! $giftProduct || $giftAvailable[(int) $gift->product_id] < $gift->quantity) {
                        throw $this->stockError(['موجودی کالای هدیه تخفیف در این شعبه کافی نیست.']);
                    }

                    $stock->move($giftProduct, -$gift->quantity, 'sale', [
                        'order_id' => $order->id,
                        'branch_id' => $branch->id,
                        'reason' => 'کالای هدیه تخفیف',
                    ]);
                }

            }

            // ۴-ب) استفاده از امتیاز وفاداری (بعد از تخفیف‌های خودکار، هر دو مستقل از هم)
            $loyalty = app(LoyaltyService::class);
            $redemption = $loyalty->previewRedemption(
                $customer,
                (int) $request->input('redeem_points', 0),
                $subtotal - $totalDiscount,
            );
            $loyalty->redeem($customer, $order, $redemption['points'], $cashier);

            $discountTotal = $totalDiscount + $redemption['amount'];

            if ($redemption['points'] > 0) {
                OrderDiscount::create([
                    'order_id' => $order->id,
                    'discount_id' => null,
                    'name' => 'امتیاز وفاداری',
                    'message' => "استفاده از {$redemption['points']} امتیاز",
                    'amount' => $redemption['amount'],
                ]);
            }

            $netAmount = max(0, $subtotal - $discountTotal);

            $order->update([
                'discount_amount' => $discountTotal,
                'total_amount' => $netAmount,
                'tax_amount' => $taxService->includedIn($netAmount, $taxRate),
            ]);

            // ۴-ج) پرداخت با گیفت کارت از باقی‌مانده‌ی بعد از تخفیف و امتیاز
            $netAfterDiscounts = max(0, $subtotal - $discountTotal);
            $giftAmount = 0.0;

            if ($request->filled('gift_card_code') && $netAfterDiscounts > 0) {
                $giftService = app(GiftCardService::class);
                $card = $giftService->assertUsable($giftService->findForUpdate($request->gift_card_code));
                $giftAmount = $giftService->applicableAmount($card, $netAfterDiscounts);

                $giftService->redeem($card, $giftAmount, $order, $cashier);

                $order->update([
                    'gift_card_id' => $card->id,
                    'gift_card_amount' => $giftAmount,
                    // اگر گیفت کارت کل مبلغ را پوشش داد، سفارش با گیفت کارت تسویه شده است
                    'payment_method' => $giftAmount >= $netAfterDiscounts ? 'gift_card' : $order->payment_method,
                ]);
            }

            // ۵) امتیاز جدید روی مبلغ نهایی؛ مجموع خرید مشتری فقط یک‌بار و با مبلغ نهایی
            $loyalty->earn($customer, $order, $cashier);
            $customer->increment('total_purchases', $order->total_amount);

            // رویدادهای زنده فقط بعد از commit ارسال می‌شوند؛ اگر تراکنش برگردد، اعلان هم ارسال نمی‌شود
            DB::afterCommit(function () use ($order, $appliedDiscounts) {
                event(new \App\Events\NewOrderCreated($order));
                event(new \App\Events\OrderCreated($order));

                // اطلاع به ریسپشن ثبت‌کننده برای هر تخفیفی که روی سفارش اعمال شد
                foreach ($appliedDiscounts as $applied) {
                    event(new DiscountApplied(
                        receptionId: (int) $order->reception_id,
                        orderNumber: (string) $order->order_number,
                        discountName: $applied['name'],
                        message: (string) $applied['message'],
                        amount: (float) $applied['amount'],
                    ));
                }
            });

            $responseDiscounts = $appliedDiscounts;

            if ($redemption['points'] > 0) {
                $responseDiscounts[] = [
                    'id' => 'loyalty',
                    'name' => 'امتیاز وفاداری',
                    'type' => 'loyalty',
                    'amount' => (float) $redemption['amount'],
                    'message' => "استفاده از {$redemption['points']} امتیاز",
                ];
            }

            return [
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $customer->full_name,
                    'subtotal' => (float) $subtotal,
                    'discount_amount' => (float) $discountTotal,
                    'total_amount' => (float) $order->total_amount,
                    'tax_rate' => (float) $order->tax_rate,
                    'tax_amount' => (float) $order->tax_amount,
                    'payment_method' => $order->payment_method,
                    'gift_card_amount' => (float) $giftAmount,
                    'payable_amount' => round(max(0, $subtotal - $discountTotal) - $giftAmount, 2),
                    'loyalty_points_earned' => (int) $order->loyalty_points_earned,
                    'loyalty_points_redeemed' => (int) $order->loyalty_points_redeemed,
                    'applied_discounts' => $responseDiscounts,
                    'created_at' => $order->created_at->format('Y-m-d H:i'),
                ],
            ];
        });
    }

    /**
     * پاسخ سفارش تکراری (همان client_uuid): سفارش قبلی برگردانده می‌شود و سفارش جدیدی ساخته نمی‌شود
     */
    private function duplicateResponse(Order $order)
    {
        return response()->json([
            'message' => 'این سفارش قبلاً ثبت شده است.',
            'duplicate' => true,
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'total_amount' => (float) $order->total_amount,
                'tax_amount' => (float) $order->tax_amount,
                'payment_method' => $order->payment_method,
                'gift_card_amount' => (float) $order->gift_card_amount,
                'loyalty_points_earned' => (int) $order->loyalty_points_earned,
            ],
        ]);
    }

    /**
     * خطای موجودی به صورت پاسخ 422 با پیام واقعی (برای نمایش در فرانت).
     * پرتاب آن داخل تراکنش، باعث rollback همه تغییرات می‌شود.
     */
    private function stockError(array $messages): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'message' => implode(' ', $messages),
            'errors' => ['items' => $messages],
        ], 422));
    }

    public function destroy($id)
    {
        $order = Order::with(['customer', 'items.product'])
            ->where('reception_id', auth()->id())
            ->where('created_at', '>=', now()->startOfDay())
            ->findOrFail($id);

        if ($order->returns()->exists()) {
            return response()->json([
                'message' => 'این سفارش بازگشت کالا داشته و قابل لغو نیست.',
            ], 422);
        }

        if (OrderExchange::where('new_order_id', $order->id)->exists()) {
            return response()->json([
                'message' => 'این سفارش حاصل تعویض کالا است و جداگانه قابل لغو نیست.',
            ], 422);
        }

        return DB::transaction(function () use ($order) {
            // بازگرداندن موجودی محصولات (با ثبت تاریخچه)
            $stock = app(StockService::class);
            foreach ($order->items as $item) {
                $product = Product::withTrashed()->whereKey($item->product_id)->lockForUpdate()->first();
                $stock->move($product, $item->quantity, 'cancel', ['order_id' => $order->id, 'branch_id' => $order->branch_id]);
            }

            // بازگرداندن امتیاز کسب‌شده و مصرف‌شده (قبل از حذف، تا ردیف‌های تراکنش ارجاع درست داشته باشند)
            app(LoyaltyService::class)->reverseForCancel($order, auth()->user());
            $order->save();

            // مبلغ پرداخت‌شده با گیفت کارت به همان کارت برمی‌گردد
            if ($order->gift_card_id && (float) $order->gift_card_amount > 0) {
                $card = GiftCard::whereKey($order->gift_card_id)->lockForUpdate()->firstOrFail();
                app(GiftCardService::class)->credit($card, (float) $order->gift_card_amount, 'cancel_credit', $order, null, auth()->user());
            }

            // کاهش مجموع خرید مشتری
            $order->customer->decrement('total_purchases', $order->total_amount);

            // حذف سفارش
            $order->delete();

            return response()->json([
                'message' => 'سفارش با موفقیت لغو شد.',
            ]);
        });
    }

    /**
     * تعویض کالا: کالاهای انتخابی از سفارش اصلی با همان منطق بازگشت برمی‌گردند و کالاهای جدید
     * به‌عنوان سفارش تازه ثبت می‌شوند. هر دو در یک تراکنش‌اند؛ اگر یکی شکست بخورد، هیچ‌کدام ثبت نمی‌شود.
     * مابه‌التفاوت = مبلغ سفارش جدید − ریفاند بازگشت (مثبت: مشتری می‌پردازد، منفی: بازپرداخت می‌شود).
     */
    public function exchange(Request $request, Order $order)
    {
        abort_unless($order->reception_id === $request->user()->id, 404);

        $data = $request->validate([
            'return_items' => 'required|array|min:1',
            'return_items.*.order_item_id' => 'required|integer|exists:order_items,id',
            'return_items.*.quantity' => 'required|integer|min:1',
            'new_items' => 'required|array|min:1',
            'new_items.*.product_id' => 'required|integer|exists:products,id',
            'new_items.*.quantity' => 'required|integer|min:1|max:999',
            'reason' => 'required|string|max:255',
            'restock' => 'sometimes|boolean',
            'payment_method' => 'nullable|in:cash,card',
            'apply_discounts' => 'sometimes|boolean',
        ]);

        $order->load('items');

        if (! $order->canBeReturned()) {
            return response()->json([
                'message' => 'این سفارش برای تعویض کالا قابل بازگشت نیست (بازگشت کامل شده یا مهلت گذشته است).',
            ], 422);
        }

        // سفارش جدید از همان مسیر ثبت سفارش ساخته می‌شود (موجودی، تخفیف، امتیاز، مالیات، شیفت)
        $newOrderRequest = Request::create('/api/reception/orders', 'POST', [
            'items' => $data['new_items'],
            'apply_discounts' => $request->boolean('apply_discounts', false),
            'payment_method' => $data['payment_method'] ?? 'cash',
            'notes' => 'تعویض کالا با سفارش '.$order->order_number,
        ]);
        $newOrderRequest->setUserResolver(fn () => $request->user());

        $result = DB::transaction(function () use ($order, $data, $newOrderRequest, $request) {
            $return = app(OrderReturnService::class)->process(
                $order,
                $data['return_items'],
                $data['reason'],
                (bool) ($data['restock'] ?? true),
                $request->user(),
            );

            $placed = null;

            for ($attempt = 1; ; $attempt++) {
                try {
                    $placed = $this->placeOrder($newOrderRequest, $order->customer);
                    break;
                } catch (UniqueConstraintViolationException $e) {
                    if ($attempt >= self::MAX_ORDER_NUMBER_ATTEMPTS) {
                        throw $e;
                    }
                }
            }

            $newOrder = Order::findOrFail($placed['order']['id']);
            $difference = round((float) $newOrder->total_amount - (float) $return->refund_amount, 2);

            OrderExchange::create([
                'original_order_id' => $order->id,
                'new_order_id' => $newOrder->id,
                'order_return_id' => $return->id,
                'user_id' => $request->user()->id,
                'difference' => $difference,
            ]);

            app(AuditLogger::class)->record('exchange', $order, "تعویض کالا با سفارش {$newOrder->order_number}", [
                'new_order' => ['old' => null, 'new' => $newOrder->order_number],
                'difference' => ['old' => null, 'new' => $difference],
            ]);

            return ['return' => $return, 'new_order' => $newOrder, 'difference' => $difference];
        });

        $order->refresh()->load('items');

        return response()->json([
            'message' => 'تعویض کالا با موفقیت ثبت شد.',
            'return' => [
                'id' => $result['return']->id,
                'refund_amount' => (float) $result['return']->refund_amount,
                'tax_refund' => (float) $result['return']->tax_refund,
            ],
            'new_order' => [
                'id' => $result['new_order']->id,
                'order_number' => $result['new_order']->order_number,
                'total_amount' => (float) $result['new_order']->total_amount,
                'tax_amount' => (float) $result['new_order']->tax_amount,
            ],
            'difference' => (float) $result['difference'],
            'original_order' => [
                'id' => $order->id,
                'total_amount' => (float) $order->total_amount,
                'refunded_amount' => (float) $order->refunded_amount,
                'returned_status' => $order->returnedStatus(),
            ],
        ], 201);
    }

    public function customerOrders(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'period' => 'sometimes|in:all,month,last_month,year',
            'sort' => 'sometimes|in:newest,oldest,highest,lowest',
            'page' => 'sometimes|integer|min:1',
        ]);

        $query = Order::with(['items.product', 'reception'])
            ->where('customer_id', $request->customer_id)
            ->where('reception_id', auth()->id());

        // فیلتر بازه زمانی
        if ($request->period !== 'all') {
            $dateRange = $this->getDateRange($request->period);
            $query->whereBetween('created_at', $dateRange);
        }

        // مرتب‌سازی
        $sortBy = $this->getSortOrder($request->sort);
        $orders = $query->orderBy($sortBy['field'], $sortBy['direction'])
            ->paginate(10);

        return response()->json([
            'orders' => $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'total_amount' => (float) $order->total_amount,
                    'discount_amount' => (float) $order->discount_amount,
                    'created_at' => $order->created_at->format('Y-m-d H:i'),
                    'reception_name' => $order->reception->name,
                    'notes' => $order->notes,
                    'items' => $order->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'product_name' => $item->product->name,
                            'quantity' => $item->quantity,
                            'total_price' => (float) $item->total_price,
                        ];
                    }),
                ];
            }),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * دریافت بازه زمانی بر اساس فیلتر
     */
    private function getDateRange($dateRange)
    {
        $now = now();

        switch ($dateRange) {
            case 'today':
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];

            case 'yesterday':
                $yesterday = $now->copy()->subDay();

                return [$yesterday->startOfDay(), $yesterday->endOfDay()];

            case 'week':
                return [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()];

            case 'month':
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];

            case 'last_month':
                $lastMonth = $now->copy()->subMonth();

                return [$lastMonth->startOfMonth(), $lastMonth->endOfMonth()];

            case 'year':
                return [$now->copy()->startOfYear(), $now->copy()->endOfYear()];

            default:
                return [$now->copy()->subYear(), $now->copy()];
        }
    }

    /**
     * دریافت تنظیمات مرتب‌سازی
     */
    private function getSortOrder($sort)
    {
        switch ($sort) {
            case 'oldest':
                return ['field' => 'created_at', 'direction' => 'asc'];

            case 'highest':
                return ['field' => 'total_amount', 'direction' => 'desc'];

            case 'lowest':
                return ['field' => 'total_amount', 'direction' => 'asc'];

            default: // newest
                return ['field' => 'created_at', 'direction' => 'desc'];
        }
    }

    /**
     * محاسبه آمار سفارشات
     */
    private function getOrdersStats($receptionId, $dateRange = null)
    {
        $query = Order::where('reception_id', $receptionId);

        if ($dateRange && $dateRange !== 'all') {
            $dateRange = $this->getDateRange($dateRange);
            $query->whereBetween('created_at', $dateRange);
        }

        return [
            'total_orders' => $query->count(),
            'total_revenue' => (float) $query->sum('total_amount'),
            'total_discounts' => (float) $query->sum('discount_amount'),
            'total_tax' => round((float) $query->sum('tax_amount'), 2),
            'unique_customers' => $query->distinct('customer_id')->count('customer_id'),
            'average_order_value' => (float) $query->avg('total_amount'),
        ];
    }

    /**
     * فرمت تخفیف‌های واقعاً اعمال‌شده روی سفارش (از جدول order_discounts)
     */
    private function formatAppliedDiscounts($order)
    {
        return $order->discounts->map(function ($discount) {
            return [
                'id' => $discount->id,
                'name' => $discount->name,
                'message' => $discount->message,
                'amount' => (float) $discount->amount,
            ];
        })->values();
    }

    /**
     * دریافت آمار روزانه برای نمودار
     */
    public function dailyStats(Request $request)
    {
        $request->validate([
            'days' => 'sometimes|integer|min:1|max:30',
        ]);

        $days = $request->get('days', 7);
        $startDate = now()->subDays($days - 1)->startOfDay();

        $stats = Order::where('reception_id', auth()->id())
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                         DATE(created_at) as date,
                         COUNT(*) as orders_count,
                         SUM(total_amount) as total_revenue,
                         SUM(discount_amount) as total_discount,
                         SUM(tax_amount) as total_tax,
                         AVG(total_amount) as avg_order_value
                     ')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'stats' => $stats,
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
                'days' => $days,
            ],
        ]);
    }
}
