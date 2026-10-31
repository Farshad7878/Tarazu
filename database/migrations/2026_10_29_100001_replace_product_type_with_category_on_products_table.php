<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * نام دسته‌بندی‌های پیش‌فرضی که از نوع‌های قدیمی ساخته می‌شوند.
     * فقط برای دسته‌بندی‌هایی ساخته می‌شوند که واقعاً محصولی به آن‌ها اشاره می‌کند.
     */
    private const LEGACY_CATEGORIES = [
        'juice' => ['name' => 'آبمیوه', 'icon' => '🍹', 'sku_prefix' => 'JUICE', 'sort_order' => 1],
        'ice_cream' => ['name' => 'بستنی', 'icon' => '🍦', 'sku_prefix' => 'ICE', 'sort_order' => 2],
        'other' => ['name' => 'سایر', 'icon' => '📦', 'sku_prefix' => 'PROD', 'sort_order' => 99],
    ];

    /**
     * ستون‌هایی که مدل Product از آن‌ها استفاده می‌کند ولی در مهاجرت‌های قبلی ساخته نشده بودند.
     */
    private const MISSING_COLUMNS = [
        'description',
        'sku',
        'barcode',
        'cost_price',
        'stock_quantity',
        'min_stock',
        'is_active',
        'is_featured',
        'image_url',
        'notes',
        'deleted_at',
    ];

    public function up(): void
    {
        // ۱) افزودن ستون‌های ناقص و ستون category_id (بدون کلید خارجی، تا مرحلهٔ انتقال داده)
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'category_id')) {
                $table->unsignedBigInteger('category_id')->nullable()->after('name');
            }
            if (! Schema::hasColumn('products', 'description')) {
                $table->text('description')->nullable();
            }
            if (! Schema::hasColumn('products', 'sku')) {
                $table->string('sku', 50)->nullable()->unique();
            }
            if (! Schema::hasColumn('products', 'barcode')) {
                $table->string('barcode', 100)->nullable()->unique();
            }
            if (! Schema::hasColumn('products', 'cost_price')) {
                $table->decimal('cost_price', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'stock_quantity')) {
                $table->integer('stock_quantity')->default(0);
            }
            if (! Schema::hasColumn('products', 'min_stock')) {
                $table->integer('min_stock')->default(5);
            }
            if (! Schema::hasColumn('products', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (! Schema::hasColumn('products', 'is_featured')) {
                $table->boolean('is_featured')->default(false);
            }
            if (! Schema::hasColumn('products', 'image_url')) {
                $table->string('image_url', 500)->nullable();
            }
            if (! Schema::hasColumn('products', 'notes')) {
                $table->string('notes', 500)->nullable();
            }
            if (! Schema::hasColumn('products', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // ۲) انتقال داده: هر نوع قدیمی به یک دسته‌بندی تبدیل می‌شود
        if (Schema::hasColumn('products', 'type')) {
            $categoryIds = [];

            $resolveCategory = function (?string $type) use (&$categoryIds) {
                $key = array_key_exists($type, self::LEGACY_CATEGORIES) ? $type : 'other';

                if (! isset($categoryIds[$key]))
                {
                    $definition = self::LEGACY_CATEGORIES[$key];

                    $existingId = DB::table('product_categories')
                        ->where('name', $definition['name'])
                        ->value('id');

                    $categoryIds[$key] = $existingId ?? DB::table('product_categories')->insertGetId(array_merge(
                        $definition,
                        ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
                    ));
                }

                return $categoryIds[$key];
            };

            foreach (DB::table('products')->select('id', 'type')->get() as $product) {
                DB::table('products')
                    ->where('id', $product->id)
                    ->update(['category_id' => $resolveCategory($product->type)]);
            }
        }

        // ۳) حذف ستون type و افزودن کلید خارجی
        if (Schema::hasColumn('products', 'type')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')
                ->on('product_categories')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // ۱) حذف کلید خارجی
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        // ۲) بازگرداندن ستون type و تلاش برای پرکردن آن بر اساس نام دسته‌بندی
        Schema::table('products', function (Blueprint $table) {
            $table->enum('type', ['juice', 'ice_cream', 'other'])->default('other')->after('name');
        });

        $legacyTypeByName = [
            self::LEGACY_CATEGORIES['juice']['name'] => 'juice',
            self::LEGACY_CATEGORIES['ice_cream']['name'] => 'ice_cream',
        ];

        foreach (DB::table('product_categories')->get() as $category) {
            DB::table('products')
                ->where('category_id', $category->id)
                ->update(['type' => $legacyTypeByName[$category->name] ?? 'other']);
        }

        // ۳) حذف ستون category_id و ستون‌های اضافه‌شده
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('category_id');
        });

        // ایندکس‌های یکتا قبل از حذف ستون باید حذف شوند (به‌خصوص در SQLite)
        Schema::table('products', function (Blueprint $table) {
            foreach (['sku', 'barcode'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropUnique([$column]);
                }
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $columns = array_values(array_filter(
                self::MISSING_COLUMNS,
                fn ($column) => Schema::hasColumn('products', $column)
            ));

            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
