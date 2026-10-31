<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * انتقال موجودی بین شعبه‌ها.
 *
 * - ایجاد: موجودی مبدا کم می‌شود و انتقال «در راه» ثبت می‌شود.
 * - دریافت: موجودی مقصد زیاد می‌شود (فقط برای انتقال در راه).
 * - لغو: موجودی به مبدا برمی‌گردد (فقط برای انتقال در راه؛ بعد از دریافت قابل لغو نیست).
 *
 * همه‌ی مراحل در تراکنش و با قفل ردیف‌ها انجام می‌شوند.
 */
class StockTransferService
{
    /**
     * @param  array<int, array{product_id: int|string, quantity: int|string}>  $items
     *
     * @throws ValidationException
     */
    public function create(Branch $from, Branch $to, array $items, ?string $notes, User $user): StockTransfer
    {
        if ($from->id === $to->id) {
            throw ValidationException::withMessages([
                'to_branch_id' => ['شعبه‌ی مبدا و مقصد نمی‌توانند یکی باشند.'],
            ]);
        }

        if (! $from->is_active || ! $to->is_active) {
            throw ValidationException::withMessages([
                'branch_id' => ['انتقال فقط بین شعبه‌های فعال امکان‌پذیر است.'],
            ]);
        }

        // ردیف‌های تکراری یک محصول جمع می‌شوند
        $lines = collect($items)
            ->groupBy(fn ($row) => (int) $row['product_id'])
            ->map(fn ($rows) => (int) $rows->sum(fn ($row) => (int) $row['quantity']))
            ->sortKeys();

        return DB::transaction(function () use ($from, $to, $lines, $notes, $user) {
            $products = Product::query()
                ->whereIn('id', $lines->keys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $errors = [];
            foreach ($lines as $productId => $quantity) {
                $product = $products->get($productId);
                if (! $product || $product->trashed() || ! $product->is_active) {
                    $errors[] = "محصول شماره {$productId} فعال نیست یا وجود ندارد.";
                }
            }
            if ($errors !== []) {
                throw ValidationException::withMessages(['items' => $errors]);
            }

            // پیش از هر تغییری، موجودی مبدا بررسی می‌شود
            $available = BranchStock::quantitiesFor($lines->keys()->all(), $from->id, true);
            $shortages = [];
            foreach ($lines as $productId => $quantity) {
                if ($available[$productId] < $quantity) {
                    $shortages[] = "موجودی {$products->get($productId)->name} در شعبه «{$from->name}» کافی نیست. موجودی: {$available[$productId]}";
                }
            }
            if ($shortages !== []) {
                throw ValidationException::withMessages(['items' => $shortages]);
            }

            $transfer = StockTransfer::create([
                'transfer_number' => $this->generateNumber(),
                'from_branch_id' => $from->id,
                'to_branch_id' => $to->id,
                'status' => StockTransfer::STATUS_IN_TRANSIT,
                'notes' => $notes,
                'created_by' => $user->id,
            ]);

            $stock = app(StockService::class);
            foreach ($lines as $productId => $quantity) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                ]);

                $stock->move($products->get($productId), -$quantity, 'transfer_out', [
                    'branch_id' => $from->id,
                    'user_id' => $user->id,
                    'stock_transfer_id' => $transfer->id,
                    'reason' => "انتقال {$transfer->transfer_number} به «{$to->name}»",
                ]);
            }

            return $this->load($transfer);
        });
    }

    /**
     * @throws ValidationException
     */
    public function receive(StockTransfer $transfer, User $user): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user) {
            $locked = StockTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            $this->ensureInTransit($locked);

            $from = Branch::find($locked->from_branch_id);
            $to = Branch::find($locked->to_branch_id);
            if (! $to || ! $to->is_active) {
                throw ValidationException::withMessages([
                    'branch_id' => ['شعبه‌ی مقصد فعال نیست؛ ابتدا آن را فعال کنید.'],
                ]);
            }

            $lines = StockTransferItem::query()
                ->where('stock_transfer_id', $locked->id)
                ->orderBy('product_id')
                ->get();

            $products = Product::withTrashed()
                ->whereIn('id', $lines->pluck('product_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $stock = app(StockService::class);
            foreach ($lines as $line) {
                $stock->move($products->get($line->product_id), (int) $line->quantity, 'transfer_in', [
                    'branch_id' => $to->id,
                    'user_id' => $user->id,
                    'stock_transfer_id' => $locked->id,
                    'reason' => "دریافت انتقال {$locked->transfer_number} از «{$from?->name}»",
                ]);
            }

            $locked->update([
                'status' => StockTransfer::STATUS_RECEIVED,
                'received_by' => $user->id,
                'received_at' => now(),
            ]);

            return $this->load($locked);
        });
    }

    /**
     * @throws ValidationException
     */
    public function cancel(StockTransfer $transfer, User $user): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user) {
            $locked = StockTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            $this->ensureInTransit($locked);

            $from = Branch::findOrFail($locked->from_branch_id);
            $lines = StockTransferItem::query()
                ->where('stock_transfer_id', $locked->id)
                ->orderBy('product_id')
                ->get();

            $products = Product::withTrashed()
                ->whereIn('id', $lines->pluck('product_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $stock = app(StockService::class);
            foreach ($lines as $line) {
                $stock->move($products->get($line->product_id), (int) $line->quantity, 'transfer_cancel', [
                    'branch_id' => $from->id,
                    'user_id' => $user->id,
                    'stock_transfer_id' => $locked->id,
                    'reason' => "لغو انتقال {$locked->transfer_number}",
                ]);
            }

            $locked->update([
                'status' => StockTransfer::STATUS_CANCELLED,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
            ]);

            return $this->load($locked);
        });
    }

    private function ensureInTransit(StockTransfer $transfer): void
    {
        if ($transfer->status !== StockTransfer::STATUS_IN_TRANSIT) {
            throw ValidationException::withMessages([
                'status' => ['این انتقال دیگر در راه نیست و قابل دریافت یا لغو نمی‌باشد.'],
            ]);
        }
    }

    private function generateNumber(): string
    {
        do {
            $number = 'ST-'.now()->format('ymd').'-'.strtoupper(Str::random(4));
        } while (StockTransfer::where('transfer_number', $number)->exists());

        return $number;
    }

    private function load(StockTransfer $transfer): StockTransfer
    {
        return $transfer->load(['fromBranch', 'toBranch', 'items.product', 'creator', 'receiver', 'canceller']);
    }
}
