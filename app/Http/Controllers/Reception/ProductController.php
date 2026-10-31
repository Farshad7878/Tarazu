<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Models\BranchStock;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\TaxService;
use Carbon\Carbon;

class ProductController extends Controller
{
    public function index(TaxService $tax)
    {
        // محصولی که نسخه دارد به‌جای خودش، نسخه‌هایش فروخته می‌شوند
        $products = Product::with('category')
                          ->active()
                          ->whereDoesntHave('variants')
                          ->orderBy('name')
                          ->get();

        // موجودی نمایش‌داده‌شده‌ی POS، موجودی شعبه‌ی همین کاربر است
        $stockAt = BranchStock::quantitiesFor($products->pluck('id')->all(), (int) auth()->user()->branch_id);

        $categories = ProductCategory::active()
                          ->orderBy('sort_order')
                          ->orderBy('name')
                          ->get(['id', 'name', 'icon']);

        return response()->json([
            // نرخ ارزش افزوده برای نمایش مالیات در فرانت (و کش آفلاین)
            'vat_rate' => $tax->rate(),
            'products' => $products->map(function ($product) use ($stockAt) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'image_url' => $product->image_src,
                    'category_id' => $product->category_id,
                    'category_name' => $product->category_name,
                    'category_icon' => $product->category?->icon,
                    'price' => (float) $product->price,
                    'stock_quantity' => (int) ($stockAt[(int) $product->id] ?? 0),
                    'min_stock' => (int) $product->min_stock,
                    'barcode' => $product->barcode,
                    'variant_label' => $product->variant_label,
                ];
            }),
            'categories' => $categories,
        ]);
    }

    public function todayStats(){
        $todayOrder = Order::whereDate('created_at', Carbon::today())->count();
        $todayCustomer = Customer::whereDate('created_at', Carbon::today())->count();
        $todaySell = OrderItem::whereDate('created_at', Carbon::today())
                   ->sum('total_price');
        return response()->json([
            'orders' => $todayOrder,
            'customers' => $todayCustomer,
            'revenue' => $todaySell
        ]);
    }

    /**
     * جستجوی دقیق با بارکد (بارکدخوان): فقط محصولات فعال و تایید‌شده
     */
    public function byBarcode(string $code)
    {
        $product = Product::with('category')
            ->active()
            ->where('barcode', trim($code))
            ->first();

        if (! $product) {
            return response()->json(['message' => 'محصولی با این بارکد پیدا نشد.'], 404);
        }

        $stock = BranchStock::quantitiesFor([$product->id], (int) auth()->user()->branch_id);

        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'category_id' => $product->category_id,
                'category_name' => $product->category_name,
                'category_icon' => $product->category?->icon,
                'price' => (float) $product->price,
                'stock_quantity' => (int) ($stock[(int) $product->id] ?? 0),
                'min_stock' => (int) $product->min_stock,
                'barcode' => $product->barcode,
                'variant_label' => $product->variant_label,
            ],
        ]);
    }
}
