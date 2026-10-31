<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DiscountController extends Controller
{
    public function index()
    {
        $discounts = Discount::with('freeProduct')->get();

        return response()->json([
            'discounts' => $discounts->map(function ($discount) {
                return [
                    'id' => $discount->id,
                    'name' => $discount->name,
                    'type' => $discount->type,
                    'value' => $discount->value,
                    'target' => $discount->target,
                    'condition_type' => $discount->condition_type,
                    'condition_value' => $discount->condition_value,
                    'free_product' => $discount->freeProduct?->name,
                    'message' => $discount->message,
                    'is_active' => $discount->is_active,
                    'auto_apply' => $discount->auto_apply,
                    'created_at' => $discount->created_at->format('Y-m-d H:i'),
                ];
            }),
        ]);
    }

    public function store(Request $request)
    {
        $discount = Discount::create($this->validatedData($request));

        return response()->json([
            'message' => 'تخفیف با موفقیت ایجاد شد.',
            'discount' => $discount,
        ], 201);
    }

    public function update(Request $request, Discount $discount)
    {
        $discount->update($this->validatedData($request));

        return response()->json(['message' => 'تخفیف با موفقیت بروزرسانی شد.']);
    }

    /**
     * اعتبارسنجی مشترک store/update.
     * - سقف ۱۰۰ فقط برای تخفیف درصدی اعمال می‌شود (تخفیف مبلغی سقف ندارد).
     * - فیلدهایی که با نوع تخفیف یا شرط آن بی‌ربط‌اند پاک می‌شوند، تا داده‌ی ناسازگار ذخیره نشود.
     */
    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:percentage,fixed_amount,free_product',
            'value' => 'nullable|numeric|min:0|required_if:type,percentage,fixed_amount',
            'target' => 'required|in:all,specific_customer',
            'condition_type' => 'required|in:total_purchases,monthly_purchases,birthday,manual',
            'condition_value' => 'nullable|numeric|min:0|required_if:condition_type,total_purchases,monthly_purchases',
            'free_product_id' => 'nullable|required_if:type,free_product|exists:products,id',
            'message' => 'required|string|max:500',
            'is_active' => 'boolean',
            'auto_apply' => 'boolean',
            'auto_disable_after_use' => 'boolean',
        ]);

        if ($data['type'] === 'percentage' && (float) ($data['value'] ?? 0) > 100) {
            throw ValidationException::withMessages([
                'value' => ['درصد تخفیف نمی‌تواند بیشتر از ۱۰۰ باشد.'],
            ]);
        }

        if (! in_array($data['type'], ['percentage', 'fixed_amount'], true)) {
            $data['value'] = null;
        }

        if ($data['type'] !== 'free_product') {
            $data['free_product_id'] = null;
        }

        if (! in_array($data['condition_type'], ['total_purchases', 'monthly_purchases'], true)) {
            $data['condition_value'] = null;
        }

        return $data;
    }

    public function assignToCustomer(Request $request, Discount $discount)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
        ]);

        $customer = Customer::find($request->customer_id);

        $customer->discounts()->syncWithoutDetaching([$discount->id => ['used_at' => null]]);

        return response()->json(['message' => 'تخفیف با موفقیت به مشتری اختصاص داده شد.']);
    }
}
