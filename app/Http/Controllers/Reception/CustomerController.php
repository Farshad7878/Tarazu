<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query();

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('membership_number', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'customers' => $customers->getCollection()->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'membership_number' => $customer->membership_number,
                    'full_name' => $customer->full_name,
                    'phone' => $customer->phone,
                    'total_purchases' => (float) $customer->total_purchases,
                ];
            })->values(),
            'pagination' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'total' => $customers->total(),
            ],
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
            // رضایت پیامک فقط با تأیید صریح ریسپشن ثبت می‌شود
            'sms_consent_at' => $request->boolean('sms_consent') ? now() : null,
        ]);

        return response()->json([
            'message' => 'مشتری با موفقیت ثبت نام شد.',
            'customer' => [
                'id' => $customer->id,
                'membership_number' => $customer->membership_number,
                'full_name' => $customer->full_name,
                'phone' => $customer->phone,
            ]
        ], 201);
    }

    public function lookup($membershipNumber)
    {
        $customer = Customer::where('membership_number', $membershipNumber)
                           ->with(['activeDiscounts' => function ($query) {
                               $query->with('freeProduct');
                           }])
                           ->first();

        if (!$customer) {
            return response()->json([
                'error' => 'مشتری با این شماره اشتراک یافت نشد.'
            ], 404);
        }

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'membership_number' => $customer->membership_number,
                'full_name' => $customer->full_name,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'phone' => $customer->phone,
                'total_purchases' => (float) $customer->total_purchases,
                'loyalty' => app(\App\Services\LoyaltyService::class)->summary($customer),
                'active_discounts' => $customer->activeDiscounts->map(function ($discount) {
                    return [
                        'id' => $discount->id,
                        'name' => $discount->name,
                        'type' => $discount->type,
                        'value' => (float) $discount->value,
                        'message' => $discount->message,
                        'free_product' => $discount->freeProduct ? [
                            'name' => $discount->freeProduct->name,
                            'price' => (float) $discount->freeProduct->price,
                        ] : null,
                    ];
                })
            ]
        ]);
    }
}