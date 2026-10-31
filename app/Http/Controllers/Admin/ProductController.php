<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['creator', 'category', 'parent:id,name'])->withCount('variants');

        // فیلتر بر اساس دسته‌بندی
        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        // فیلتر بر اساس وضعیت
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // جستجو بر اساس نام، کد انبار (SKU) یا بارکد
        if ($request->filled('search')) {
            $term = trim((string) $request->search);
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('barcode', $term);
            });
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'products' => $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category_id' => $product->category_id,
                    'category_name' => $product->category_name,
                    'category_icon' => $product->category?->icon,
                    'price' => (float) $product->price,
                    'stock_quantity' => (int) $product->stock_quantity,
                    'min_stock' => (int) $product->min_stock,
                    'barcode' => $product->barcode,
                    'parent_product_id' => $product->parent_product_id,
                    'parent_name' => $product->parent?->name,
                    'variant_label' => $product->variant_label,
                    'image_url' => $product->image_src,
                    'variants_count' => (int) $product->variants_count,
                    'is_active' => (bool) $product->is_active,
                    'status' => $product->status,
                    'status_fa' => $product->status === 'approved' ? 'تایید شده' : 'در انتظار تایید',
                    'created_by' => $product->creator?->name,
                    'created_at' => $product->created_at->format('Y-m-d H:i'),
                ];
            }),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:product_categories,id',
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'barcode' => 'nullable|string|max:100|unique:products,barcode',
        ]);

        $product = Product::create([
            'barcode' => $validated['barcode'] ?? null,
            'name' => $validated['name'],
            'category_id' => $validated['category_id'],
            'price' => $validated['price'],
            'stock_quantity' => $validated['stock_quantity'] ?? 0,
            'min_stock' => $validated['min_stock'] ?? 5,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => auth()->id(),
            'status' => 'approved',
        ]);

        $product->load('category');

        return response()->json([
            'message' => 'محصول با موفقیت ایجاد شد.',
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'category_id' => $product->category_id,
                'category_name' => $product->category_name,
                'price' => (float) $product->price,
                'stock_quantity' => (int) $product->stock_quantity,
                'min_stock' => (int) $product->min_stock,
                'is_active' => (bool) $product->is_active,
                'barcode' => $product->barcode,
            ],
        ], 201);
    }

    /**
     * آپلود یا جایگزینی تصویر محصول. فایل روی دیسک public در پوشه products ذخیره می‌شود.
     */
    public function uploadImage(Request $request, Product $product)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,webp|max:2048',
        ]);

        $previous = $product->getRawOriginal('image_url');
        $path = $request->file('image')->store('products', 'public');

        $product->update(['image_url' => $path]);

        // فایل قبلی فقط اگر در پوشه‌ی محصولات بود پاک می‌شود
        if ($previous && $previous !== $path && str_starts_with($previous, 'products/')) {
            Storage::disk('public')->delete($previous);
        }

        return response()->json([
            'message' => 'تصویر محصول ذخیره شد.',
            'image_url' => $product->fresh()->image_src,
        ]);
    }

    /**
     * حذف تصویر محصول.
     */
    public function removeImage(Product $product)
    {
        $previous = $product->getRawOriginal('image_url');

        if ($previous && str_starts_with($previous, 'products/')) {
            Storage::disk('public')->delete($previous);
        }

        $product->update(['image_url' => null]);

        return response()->json([
            'message' => 'تصویر محصول حذف شد.',
            'image_url' => null,
        ]);
    }

    public function update(Request $request, Product $product, StockService $stock)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:product_categories,id',
            'price' => 'required|numeric|min:0',
            'status' => 'required|in:pending,approved',
            'stock_quantity' => 'required|integer|min:0',
            'min_stock' => 'required|integer|min:0',
            'is_active' => 'required|boolean',
            'barcode' => 'nullable|string|max:100|unique:products,barcode,'.$product->id,
        ]);

        DB::transaction(function () use ($validated, $product, $stock) {
            $locked = Product::withTrashed()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $locked->update([
                'name' => $validated['name'],
                'category_id' => $validated['category_id'],
                'price' => $validated['price'],
                'status' => $validated['status'],
                'min_stock' => $validated['min_stock'],
                'is_active' => $validated['is_active'],
                'barcode' => $validated['barcode'] ?? $locked->barcode,
            ]);

            // تغییر موجودی از فرم محصول هم باید در تاریخچه‌ی انبار با کاربر و زمان ثبت شود
            $change = (int) $validated['stock_quantity'] - (int) $locked->stock_quantity;
            if ($change !== 0) {
                $stock->move($locked, $change, 'correction', ['reason' => 'ویرایش موجودی از فرم محصول']);
            }
        });

        return response()->json(['message' => 'محصول با موفقیت بروزرسانی شد.']);
    }

    /**
     * ساخت نسخه (سایز/رنگ) برای محصول: نسخه یک محصول فروشی مستقل است با نام «والد - عنوان»
     */
    public function storeVariant(Request $request, Product $product, StockService $stock)
    {
        if ($product->parent_product_id) {
            return response()->json(['message' => 'به یک نسخه نمی‌توان نسخه‌ی دیگری اضافه کرد.'], 422);
        }

        $data = $request->validate([
            'variant_label' => 'required|string|max:100',
            'barcode' => 'nullable|string|max:100|unique:products,barcode',
            'price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
        ]);

        $label = trim($data['variant_label']);

        if (Product::withTrashed()->where('parent_product_id', $product->id)->where('variant_label', $label)->exists()) {
            return response()->json(['message' => 'نسخه‌ای با این عنوان برای این محصول وجود دارد.'], 422);
        }

        return DB::transaction(function () use ($data, $product, $label, $stock) {
            $variant = Product::create([
                'name' => $product->name.' - '.$label,
                'description' => $product->description,
                'category_id' => $product->category_id,
                'parent_product_id' => $product->id,
                'variant_label' => $label,
                'price' => $data['price'] ?? $product->price,
                'stock_quantity' => 0,
                'min_stock' => $product->min_stock,
                'barcode' => $data['barcode'] ?? null,
                'is_active' => true,
                'created_by' => auth()->id(),
                'status' => 'approved',
            ]);

            $initial = (int) ($data['stock_quantity'] ?? 0);
            if ($initial > 0) {
                $stock->move($variant, $initial, 'correction', ['reason' => 'موجودی اولیه نسخه']);
            }

            return response()->json([
                'message' => 'نسخه با موفقیت ایجاد شد.',
                'variant' => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'barcode' => $variant->barcode,
                    'stock_quantity' => (int) $variant->fresh()->stock_quantity,
                ],
            ], 201);
        });
    }

    /**
     * اگر محصول بارکد ندارد (مثلاً محصولات قدیمی)، یک بارکد داخلی یکتا تولید و ذخیره می‌شود
     */
    public function ensureBarcode(Product $product)
    {
        if (blank($product->barcode)) {
            $product->barcode = Product::generateBarcode();
            $product->save();
        }

        return response()->json(['barcode' => $product->barcode]);
    }

    public function destroy(Product $product)
    {
        // محصولی که در سفارش ثبت شده یا کالای هدیه‌ی یک تخفیف است، حذف نمی‌شود.
        // حذف آن تاریخچه‌ی فروش و گزارش‌ها را خراب می‌کند؛ به‌جای آن باید غیرفعال شود.
        if ($product->orderItems()->exists()) {
            return response()->json([
                'message' => 'این محصول در سفارش‌ها استفاده شده و قابل حذف نیست. به‌جای حذف، آن را غیرفعال کنید.',
            ], 422);
        }

        if ($product->discountAsGift()->exists()) {
            return response()->json([
                'message' => 'این محصول به‌عنوان کالای هدیه‌ی یک تخفیف تعریف شده و قابل حذف نیست.',
            ], 422);
        }

        $product->delete();

        return response()->json(['message' => 'محصول با موفقیت حذف شد.']);
    }
}
