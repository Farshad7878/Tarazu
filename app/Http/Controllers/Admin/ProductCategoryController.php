<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductCategoryController extends Controller
{
    public function index()
    {
        $categories = ProductCategory::withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'categories' => $categories->map(fn (ProductCategory $category) => $this->format($category)),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $category = ProductCategory::create($validated);

        return response()->json([
            'message' => 'دسته‌بندی با موفقیت ایجاد شد.',
            'category' => $this->format($category->loadCount('products')),
        ], 201);
    }

    public function update(Request $request, ProductCategory $productCategory)
    {
        $validated = $request->validate($this->rules($productCategory));

        $productCategory->update($validated);

        return response()->json([
            'message' => 'دسته‌بندی با موفقیت بروزرسانی شد.',
            'category' => $this->format($productCategory->loadCount('products')),
        ]);
    }

    public function destroy(ProductCategory $productCategory)
    {
        // محصولات حذف‌شده (soft delete) هم به دسته‌بندی ارجاع دارند
        $hasProducts = Product::withTrashed()
            ->where('category_id', $productCategory->id)
            ->exists();

        if ($hasProducts) {
            return response()->json([
                'message' => 'این دسته‌بندی دارای محصول است و قابل حذف نیست. ابتدا محصولات آن را به دسته‌بندی دیگری منتقل کنید یا دسته‌بندی را غیرفعال نمایید.',
            ], 422);
        }

        $productCategory->delete();

        return response()->json(['message' => 'دسته‌بندی با موفقیت حذف شد.']);
    }

    private function rules(?ProductCategory $category = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('product_categories', 'name')->ignore($category?->id),
            ],
            'icon' => 'nullable|string|max:16',
            'sku_prefix' => 'nullable|string|max:10|regex:/^[A-Za-z0-9]+$/',
            'description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'boolean',
        ];
    }

    private function format(ProductCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'icon' => $category->icon,
            'sku_prefix' => $category->sku_prefix,
            'description' => $category->description,
            'sort_order' => (int) $category->sort_order,
            'is_active' => (bool) $category->is_active,
            'products_count' => (int) ($category->products_count ?? 0),
        ];
    }
}
