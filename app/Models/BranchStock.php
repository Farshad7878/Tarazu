<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * موجودی هر محصول در هر شعبه. مجموع این ردیف‌ها همان products.stock_quantity است.
 */
class BranchStock extends Model
{
    protected $table = 'branch_stocks';

    protected $fillable = [
        'branch_id',
        'product_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * ردیف موجودی محصول در شعبه را (در صورت نبود) می‌سازد و قفل می‌کند.
     * باید داخل تراکنش فراخوانی شود.
     */
    public static function lockedFor(int $productId, int $branchId): static
    {
        $now = now();
        static::query()->insertOrIgnore([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'quantity' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return static::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * موجودی چند محصول در یک شعبه. محصولی که ردیف ندارد، صفر برمی‌گرداند.
     *
     * @param  array<int>  $productIds
     * @return array<int, int> product_id => quantity
     */
    public static function quantitiesFor(array $productIds, int $branchId, bool $lock = false): array
    {
        $ids = array_values(array_unique(array_map('intval', $productIds)));
        if ($ids === []) {
            return [];
        }

        $query = static::query()
            ->where('branch_id', $branchId)
            ->whereIn('product_id', $ids)
            ->orderBy('product_id');

        if ($lock) {
            $query->lockForUpdate();
        }

        $quantities = array_fill_keys($ids, 0);
        foreach ($query->get(['product_id', 'quantity']) as $row) {
            $quantities[(int) $row->product_id] = (int) $row->quantity;
        }

        return $quantities;
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
