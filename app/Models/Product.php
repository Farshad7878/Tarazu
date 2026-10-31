<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes, \App\Models\Concerns\Auditable;

    /**
     * پیشوند پیش‌فرض SKU وقتی دسته‌بندی پیشوند ندارد
     */
    const DEFAULT_SKU_PREFIX = 'PROD';

    /**
     * فیلدهای قابل تخصیص
     */
    protected $fillable = [
        'name',
        'description',
        'category_id',
        'parent_product_id',
        'variant_label',
        'price',
        'cost_price',
        'stock_quantity',
        'min_stock',
        'sku',
        'barcode',
        'is_active',
        'is_featured',
        'image_url',
        'created_by',
        'status',
        'notes',
    ];

    /**
     * آدرس قابل نمایش تصویر محصول. ستون image_url مسیر نسبی روی دیسک public را نگه می‌دارد؛
     * آدرس کامل (http/https) هم پذیرفته می‌شود.
     */
    public function getImageSrcAttribute(): ?string
    {
        $path = $this->getRawOriginal('image_url');

        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        // مسیر ریشه‌ای نسبی به دامنه‌ی جاری: با هر host و APP_URL درست کار می‌کند
        return '/storage/'.ltrim($path, '/');
    }

    /**
     * نوع داده‌ای فیلدها
     */
    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'min_stock' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * مقادیر پیش‌فرض
     */
    protected $attributes = [
        'stock_quantity' => 0,
        'min_stock' => 5,
        'is_active' => true,
        'is_featured' => false,
        'status' => 'approved',
    ];

    /**
     * ثابت‌های وضعیت
     */
    const STATUS_PENDING = 'pending';

    const STATUS_APPROVED = 'approved';

    /**
     * فقط همین دو وضعیت در ستون products.status (enum) و اعتبارسنجی API مجاز است
     */
    const STATUSES = [
        self::STATUS_PENDING => 'در انتظار تایید',
        self::STATUS_APPROVED => 'تایید شده',
    ];

    /**
     * رابطه با کاربر ایجادکننده
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * رابطه با دسته‌بندی محصول
     */
    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * رابطه با آیتم‌های سفارش
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * رابطه با تخفیف‌هایی که این محصول به عنوان هدیه استفاده می‌شود
     */
    public function discountAsGift()
    {
        return $this->hasMany(Discount::class, 'free_product_id');
    }

    /**
     * Scope برای محصولات فعال
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope برای محصولات نیازمند موجودی
     */
    public function scopeLowStock($query)
    {
        return $query->whereRaw('stock_quantity <= min_stock');
    }

    /**
     * Scope برای محصولات اخراج شده
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('stock_quantity', '<=', 0);
    }

    /**
     * محاسبات و منطق قبل از ذخیره
     */
    protected static function boot()
    {
        parent::boot();

        // تولید SKU خودکار اگر خالی باشد
        static::creating(function ($product) {
            if (empty($product->sku)) {
                $product->sku = static::generateSku($product->category);
            }

            if (empty($product->barcode)) {
                $product->barcode = static::generateBarcode();
            }
        });

        // موجودی اولیه‌ی محصول جدید (فرم یا سیدر) در شعبه‌ی اصلی ثبت می‌شود تا مجموع شعبه‌ها با کل برابر بماند
        static::created(function ($product) {
            $initial = (int) $product->stock_quantity;
            $mainId = Branch::mainId();

            if ($initial > 0 && $mainId) {
                $row = BranchStock::lockedFor($product->id, $mainId);
                $row->update(['quantity' => $initial]);

                // رکورد تعدیل تا تاریخچه‌ی موجودی از همان ابتدا با مانده‌ی فعلی برابر باشد
                StockAdjustment::create([
                    'product_id' => $product->id,
                    'branch_id' => $mainId,
                    'user_id' => auth()->id(),
                    'type' => 'initial',
                    'quantity_change' => $initial,
                    'quantity_before' => 0,
                    'quantity_after' => $initial,
                    'reason' => 'موجودی اولیه‌ی محصول',
                ]);
            }
        });

        // بررسی موجودی کم
        static::updated(function ($product) {
            if ($product->isLowStock()) {
                // TODO: ارسال نوتیفیکیشن برای ادمین
                \Log::warning("موجودی محصول {$product->name} در حال اتمام است: {$product->stock_quantity}");
            }
        });
    }

    /**
     * تولید SKU خودکار بر اساس پیشوند دسته‌بندی
     */
    public static function generateSku(?ProductCategory $category = null)
    {
        $prefix = $category?->effective_sku_prefix ?? self::DEFAULT_SKU_PREFIX;

        $latest = static::withTrashed()
            ->where('sku', 'like', "{$prefix}-%")
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $latest ? (int) str_replace("{$prefix}-", '', $latest->sku) + 1 : 1;

        return "{$prefix}-".str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * بارکد داخلی EAN-13 (پیشوند ۲۰ = کالای داخلی) که با بارکدخوان قابل خواندن است و یکتا تولید می‌شود
     */
    public static function generateBarcode(): string
    {
        do {
            $body = '20'.str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
            $sum = 0;
            foreach (str_split($body) as $i => $digit) {
                $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
            }
            $code = $body.((10 - $sum % 10) % 10);
        } while (static::withTrashed()->where('barcode', $code)->exists());

        return $code;
    }

    /**
     * نسخه‌های این محصول (سایز/رنگ)
     */
    public function variants()
    {
        return $this->hasMany(Product::class, 'parent_product_id');
    }

    /**
     * محصول والد برای نسخه‌ها
     */
    public function parent()
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    /**
     * موجودی این محصول به تفکیک شعبه
     */
    public function branchStocks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BranchStock::class);
    }

    protected function auditExcludedKeys(): array
    {
        return ['stock_quantity'];
    }

    /**
     * بررسی موجودی کم
     */
    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->min_stock;
    }

    /**
     * نام دسته‌بندی محصول
     */
    public function getCategoryNameAttribute()
    {
        return $this->category?->name ?? 'بدون دسته‌بندی';
    }

    /**
     * دریافت وضعیت به فارسی
     */
    public function getStatusFaAttribute()
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * غیرفعال کردن محصول
     */
    public function deactivate()
    {
        $this->is_active = false;

        return $this->save();
    }

    /**
     * فعال کردن محصول
     */
    public function activate()
    {
        $this->is_active = true;

        return $this->save();
    }

    /**
     * تایید محصول
     */
    public function approve()
    {
        $this->status = self::STATUS_APPROVED;

        return $this->save();
    }

    /**
     |--------------------------------------------------------------------------
     | STATISTICAL METHODS
     |--------------------------------------------------------------------------
     */

    /**
     |--------------------------------------------------------------------------
     | SEARCH AND FILTER
     |--------------------------------------------------------------------------
     */

    /**
     * جستجوی محصولات
     */
    public static function search($term, $filters = [])
    {
        $query = static::with('creator');

        if ($term) {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('barcode', $term);
            });
        }

        // اعمال فیلترها
        foreach ($filters as $field => $value) {
            if (! empty($value)) {
                $query->where($field, $value);
            }
        }

        return $query->orderBy('name')->paginate(20);
    }

    /**
     |--------------------------------------------------------------------------
     | VALIDATION RULES
     |--------------------------------------------------------------------------
     */
}
