<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::withCount('orders');

        // جستجو
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('membership_number', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'customers' => $customers->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'membership_number' => $customer->membership_number,
                    'first_name' => $customer->first_name,
                    'last_name' => $customer->last_name,
                    'full_name' => $customer->full_name,
                    'phone' => $customer->phone,
                    'date_of_birth' => $customer->date_of_birth?->format('Y-m-d'),
                    'total_purchases' => (float) $customer->total_purchases,
                    'orders_count' => $customer->orders_count,
                    'loyalty_points' => (int) $customer->loyalty_points,
                    'sms_consent' => $customer->sms_consent_at !== null,
                    'sms_opt_out' => $customer->sms_opt_out_at !== null,
                    'created_at' => $customer->created_at->format('Y-m-d H:i'),
                ];
            }),
            'pagination' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => 'required|string|unique:customers,phone',
            'date_of_birth' => 'nullable|date',
            'sms_consent' => 'sometimes|boolean',
        ]);

        $customer = Customer::create([
            'membership_number' => Customer::generateMembershipNumber(),
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone' => $request->phone,
            'date_of_birth' => $request->date_of_birth,
            'total_purchases' => 0,
            'sms_consent_at' => $request->boolean('sms_consent') ? now() : null,
        ]);

        return response()->json([
            'message' => 'مشتری با موفقیت ایجاد شد.',
            'customer' => [
                'id' => $customer->id,
                'membership_number' => $customer->membership_number,
                'full_name' => $customer->full_name,
                'phone' => $customer->phone,
            ]
        ], 201);
    }

    public function show(Customer $customer)
    {

        $customer->load(['orders' => function ($query) {
            $query->orderBy('created_at', 'desc')->with('items.product');
        }]);

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'membership_number' => $customer->membership_number,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'full_name' => $customer->first_name . ' ' .$customer->last_name,
                'phone' => $customer->phone,
                'date_of_birth' => $customer->date_of_birth?->format('Y-m-d'),
                'total_purchases' => (float) $customer->total_purchases,
                'monthly_purchases' => (float) $customer->monthly_purchases,
                'created_at' => $customer->created_at->format('Y-m-d H:i'),
                'sms_consent' => $customer->sms_consent_at !== null,
                'sms_opt_out' => $customer->sms_opt_out_at !== null,
                'loyalty' => app(LoyaltyService::class)->summary($customer),
            ],
            'loyalty_transactions' => $customer->loyaltyTransactions()->limit(30)->get()->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'type_label' => $t->type_label,
                'points' => $t->points,
                'balance_after' => $t->balance_after,
                'reason' => $t->reason,
                'created_at' => $t->created_at->format('Y-m-d H:i'),
            ])->values(),
            'orders' => $customer->orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'total_amount' => (float) $order->total_amount,
                    'discount_amount' => (float) $order->discount_amount,
                    'created_at' => $order->created_at->format('Y-m-d H:i'),
                    'items' => $order->items->map(function ($item) {
                        return [
                            'product_name' => $item->product->name,
                            'quantity' => $item->quantity,
                            'unit_price' => (float) $item->unit_price,
                            'total_price' => (float) $item->total_price,
                        ];
                    })
                ];
            })
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => ['required', 'string', Rule::unique('customers')->ignore($customer->id)],
            'date_of_birth' => 'nullable|date',
        ]);

        $customer->update($request->only(['first_name', 'last_name', 'phone', 'date_of_birth']));

        if ($request->has('sms_consent')) {
            $customer->forceFill([
                'sms_consent_at' => $request->boolean('sms_consent') ? ($customer->sms_consent_at ?? now()) : null,
            ])->save();
        }

        return response()->json(['message' => 'اطلاعات مشتری با موفقیت بروزرسانی شد.']);
    }

    /**
     * تعدیل دستی امتیاز وفاداری (فقط ادمین، با دلیل).
     */
    public function adjustLoyalty(Request $request, Customer $customer, LoyaltyService $loyalty)
    {
        $data = $request->validate([
            'points' => 'required|integer|not_in:0|between:-100000,100000',
            'reason' => 'required|string|max:255',
        ]);

        $transaction = $loyalty->adjust($customer, (int) $data['points'], $data['reason'], $request->user());

        app(\App\Services\AuditLogger::class)->record('loyalty_adjusted', $customer, "تعدیل امتیاز: {$data['points']}", [
            'points' => ['old' => null, 'new' => (int) $data['points']],
            'reason' => ['old' => null, 'new' => $data['reason']],
        ]);

        return response()->json([
            'message' => 'امتیاز مشتری بروزرسانی شد.',
            'points_balance' => $transaction->balance_after,
        ], 201);
    }
}