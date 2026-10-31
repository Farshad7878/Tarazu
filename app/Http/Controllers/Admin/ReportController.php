<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductCategory;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function stats(Request $request)
    {
        $period = $this->getDateRange($request->period, $request->start_date, $request->end_date);

        // آمار کلی
        $totalRevenue = Order::whereBetween('created_at', $period)
            ->when($request->reception_id, function ($query) use ($request) {
                return $query->where('reception_id', $request->reception_id);
            })
            ->when($request->branch_id, fn ($query) => $query->where('branch_id', $request->branch_id))
            ->sum('total_amount');

        $totalOrders = Order::whereBetween('created_at', $period)
            ->when($request->reception_id, function ($query) use ($request) {
                return $query->where('reception_id', $request->reception_id);
            })
            ->when($request->branch_id, fn ($query) => $query->where('branch_id', $request->branch_id))
            ->countable()
            ->count();

        $totalCustomers = Customer::whereBetween('created_at', $period)->count();
        $totalDiscounts = Order::whereBetween('created_at', $period)
            ->when($request->reception_id, function ($query) use ($request) {
                return $query->where('reception_id', $request->reception_id);
            })
            ->when($request->branch_id, fn ($query) => $query->where('branch_id', $request->branch_id))
            ->sum('discount_amount');
        $totalTax = Order::whereBetween('created_at', $period)
            ->when($request->reception_id, function ($query) use ($request) {
                return $query->where('reception_id', $request->reception_id);
            })
            ->when($request->branch_id, fn ($query) => $query->where('branch_id', $request->branch_id))
            ->sum('tax_amount');

        // آمار دوره قبلی برای مقایسه
        $previousPeriod = $this->getPreviousPeriod($request->period, $request->start_date, $request->end_date);

        // مقایسه باید با همان فیلتر ریسپشن انجام شود، وگرنه درصد تغییر معنا ندارد
        $previousOrdersQuery = fn () => $this->applyReceptionFilter(
            Order::whereBetween('created_at', $previousPeriod),
            $request
        );

        $previousRevenue = $previousOrdersQuery()->sum('total_amount');
        $previousOrders = $previousOrdersQuery()->countable()->count();
        $previousCustomers = Customer::whereBetween('created_at', $previousPeriod)->count();
        $previousDiscounts = $previousOrdersQuery()->sum('discount_amount');

        return response()->json([
            'total_revenue' => (float) $totalRevenue,
            'total_orders' => $totalOrders,
            'total_customers' => $totalCustomers,
            'total_discounts' => (float) $totalDiscounts,
            'total_tax' => round((float) $totalTax, 2),
            'revenue_change' => $this->calculateChange($previousRevenue, $totalRevenue),
            'orders_change' => $this->calculateChange($previousOrders, $totalOrders),
            'customers_change' => $this->calculateChange($previousCustomers, $totalCustomers),
            'discounts_change' => $this->calculateChange($previousDiscounts, $totalDiscounts),
        ]);
    }

    /**
     * درآمد و تعداد سفارش هر روز در بازه انتخابی (روزهای بدون فروش صفر)
     */
    public function dailyRevenue(Request $request)
    {
        [$start, $end] = $this->resolvePeriod($request);

        $orders = $this->applyReceptionFilter(
            Order::whereBetween('created_at', [$start, $end])->countable(),
            $request
        )->get(['created_at', 'total_amount']);

        $grouped = $orders->groupBy(fn ($order) => $order->created_at->format('Y-m-d'));

        $days = collect(CarbonPeriod::create($start->copy()->startOfDay(), '1 day', $end->copy()->startOfDay()))
            ->map(function ($day) use ($grouped) {
                $key = $day->format('Y-m-d');
                $dayOrders = $grouped->get($key, collect());

                return [
                    'date' => $key,
                    'revenue' => (float) $dayOrders->sum('total_amount'),
                    'orders' => $dayOrders->count(),
                ];
            })->values();

        return response()->json(['days' => $days]);
    }

    /**
     * عملکرد هر ریسپشن (تعداد سفارش و درآمد) در بازه انتخابی
     */
    public function receptionPerformance(Request $request)
    {
        $period = $this->getDateRange($request->period, $request->start_date, $request->end_date);

        $rows = DB::table('orders')
            ->where(Order::countableConstraint())
            ->join('users', 'users.id', '=', 'orders.reception_id')
            ->whereBetween('orders.created_at', $period)
            ->when($request->reception_id, function ($query) use ($request) {
                return $query->where('orders.reception_id', $request->reception_id);
            })
            ->when($request->branch_id, fn ($query) => $query->where('orders.branch_id', $request->branch_id))
            ->groupBy('orders.reception_id', 'users.name')
            ->select([
                'orders.reception_id',
                'users.name',
                DB::raw('COUNT(orders.id) as total_orders'),
                DB::raw('SUM(orders.total_amount) as total_revenue'),
            ])
            ->get();

        return response()->json([
            'receptions' => $rows->map(function ($row) {
                $orders = (int) $row->total_orders;
                $revenue = (float) $row->total_revenue;

                return [
                    'id' => $row->reception_id,
                    'name' => $row->name,
                    'orders' => $orders,
                    'revenue' => $revenue,
                    'average_order' => $orders > 0 ? round($revenue / $orders, 2) : 0,
                ];
            })->sortByDesc('orders')->values(),
        ]);
    }

    /**
     * تعداد سفارش و درآمد در بازه‌های ۲ ساعته شبانه‌روز
     */
    public function hourlyOrders(Request $request)
    {
        [$start, $end] = $this->resolvePeriod($request);

        $orders = $this->applyReceptionFilter(
            Order::whereBetween('created_at', [$start, $end])->countable(),
            $request
        )->get(['created_at', 'total_amount']);

        // ۱۲ سطل ۲ ساعته؛ هر سطل: [تعداد سفارش، درآمد]
        $counts = array_fill(0, 12, 0);
        $revenues = array_fill(0, 12, 0.0);

        foreach ($orders as $order) {
            $index = intdiv((int) $order->created_at->format('G'), 2);
            $counts[$index]++;
            $revenues[$index] += (float) $order->total_amount;
        }

        $buckets = [];
        for ($index = 0; $index < 12; $index++) {
            $from = $index * 2;
            $buckets[] = [
                'label' => sprintf('%02d:00-%02d:00', $from, $from + 2),
                'orders' => $counts[$index],
                'revenue' => round($revenues[$index], 2),
            ];
        }

        return response()->json(['buckets' => $buckets]);
    }

    /**
     * فروش به تفکیک دسته‌بندی محصول در بازه انتخابی
     */
    public function categorySales(Request $request)
    {
        $period = $this->getDateRange($request->period, $request->start_date, $request->end_date);

        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereBetween('orders.created_at', $period)
            ->when($request->reception_id, function ($query) use ($request) {
                return $query->where('orders.reception_id', $request->reception_id);
            })
            ->when($request->branch_id, fn ($query) => $query->where('orders.branch_id', $request->branch_id))
            ->groupBy('products.category_id')
            ->select([
                'products.category_id',
                // کالاهای برگشتی از تعداد و فروش دسته‌بندی کم می‌شوند
                DB::raw('SUM(order_items.quantity - order_items.returned_quantity) as total_quantity'),
                DB::raw('SUM(order_items.total_price - COALESCE((SELECT SUM(ori.refund_amount) FROM order_return_items ori WHERE ori.order_item_id = order_items.id), 0)) as total_revenue'),
            ])
            ->get();

        $categories = ProductCategory::whereIn('id', $rows->pluck('category_id')->filter())
            ->get()
            ->keyBy('id');

        return response()->json([
            'categories' => $rows->map(function ($row) use ($categories) {
                $category = $categories->get($row->category_id);

                return [
                    'category_id' => $row->category_id,
                    'name' => $category?->name ?? 'بدون دسته‌بندی',
                    'icon' => $category?->icon,
                    'total_quantity' => (int) $row->total_quantity,
                    'total_revenue' => (float) $row->total_revenue,
                ];
            })->sortByDesc('total_revenue')->values(),
        ]);
    }

    public function recentOrders(Request $request)
    {
        $period = $this->getDateRange($request->period, $request->start_date, $request->end_date);

        $orders = Order::with(['customer', 'reception'])
            ->whereBetween('created_at', $period)
            ->when($request->reception_id, function ($query) use ($request) {
                return $query->where('reception_id', $request->reception_id);
            })
            ->when($request->branch_id, fn ($query) => $query->where('branch_id', $request->branch_id))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'orders' => $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer->full_name,
                    'reception_name' => $order->reception->name,
                    'total_amount' => (float) $order->total_amount,
                    'discount_amount' => (float) $order->discount_amount,
                    'created_at' => $order->created_at->format('Y-m-d H:i'),
                ];
            }),
        ]);
    }

    /**
     * خروجی CSV سفارش‌های بازه (با BOM تا Excel فارسی را درست نمایش دهد).
     */
    public function export(Request $request)
    {
        [$start, $end] = $this->resolvePeriod($request);

        $query = $this->applyReceptionFilter(
            Order::with(['customer', 'reception', 'discounts'])
                ->whereBetween('created_at', [$start, $end]),
            $request
        );

        $filename = 'orders-'.$start->format('Y-m-d').'_'.$end->format('Y-m-d').'.csv';

        // استریم دسته‌ای: سفارش‌ها به‌صورت batch خوانده می‌شوند تا مصرف حافظه ثابت بماند
        return response()->streamDownload(function () use ($query) {
            // BOM برای شناسایی UTF-8 توسط Excel
            echo "\xEF\xBB\xBF";

            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'شماره سفارش', 'تاریخ', 'مشتری', 'شماره عضویت', 'ریسپشن',
                'جمع قبل از تخفیف', 'تخفیف', 'مبلغ نهایی', 'تخفیف‌های اعمال‌شده', 'مالیات (ارزش افزوده)',
            ]);

            $query->chunkById(500, function ($orders) use ($out) {
                foreach ($orders as $order) {
                    fputcsv($out, [
                        $this->csvSafe($order->order_number),
                        $order->created_at->format('Y-m-d H:i'),
                        $this->csvSafe($order->customer?->full_name),
                        $this->csvSafe($order->customer?->membership_number),
                        $this->csvSafe($order->reception?->name),
                        (float) $order->subtotal,
                        (float) $order->discount_amount,
                        (float) $order->total_amount,
                        $this->csvSafe($order->discounts->pluck('name')->implode(' | ')),
                        (float) $order->tax_amount,
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * جلوگیری از CSV Injection: مقادیری که با = + - @ شروع شوند، به‌صورت متن خنثی می‌شوند.
     */
    private function csvSafe($value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    private function applyReceptionFilter($query, Request $request)
    {
        return $query->when($request->reception_id, function ($query) use ($request) {
            return $query->where('reception_id', $request->reception_id);
        })->when($request->branch_id, fn ($query) => $query->where('branch_id', $request->branch_id));
    }

    /**
     * بازه زمانی به صورت دو Carbon (شروع و پایان) برمی‌گرداند
     */
    private function resolvePeriod(Request $request): array
    {
        [$start, $end] = $this->getDateRange($request->period, $request->start_date, $request->end_date);

        return [\Carbon\Carbon::parse($start), \Carbon\Carbon::parse($end)];
    }

    private function getDateRange($period, $startDate = null, $endDate = null)
    {
        $now = now();

        switch ($period) {
            case 'today':
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];

            case 'yesterday':
                $yesterday = $now->copy()->subDay();

                return [$yesterday->startOfDay(), $yesterday->endOfDay()];

            case 'week':
                return [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()];

            case 'month':
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];

            case 'last_month':
                $lastMonth = $now->copy()->subMonth();

                return [$lastMonth->startOfMonth(), $lastMonth->endOfMonth()];

            case 'year':
                return [$now->copy()->startOfYear(), $now->copy()->endOfYear()];

            case 'custom':
                return [
                    $startDate ? $startDate.' 00:00:00' : $now->copy()->startOfMonth(),
                    $endDate ? $endDate.' 23:59:59' : $now->copy()->endOfMonth(),
                ];

            default:
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
        }
    }

    /**
     * بازه‌ی مقایسه: برای بازه‌ی سفارشی، همان طول زمانی بلافاصله قبل از آن.
     */
    private function getPreviousPeriod($period, $startDate = null, $endDate = null)
    {
        $now = now();

        switch ($period) {
            case 'custom':
                [$start, $end] = $this->resolvePeriod(new Request([
                    'period' => 'custom',
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]));

                $previousEnd = $start->copy()->subSecond();
                $length = $start->diffInSeconds($end);

                return [$previousEnd->copy()->subSeconds($length), $previousEnd];

            case 'today':
                $yesterday = $now->copy()->subDay();

                return [$yesterday->startOfDay(), $yesterday->endOfDay()];

            case 'yesterday':
                $dayBefore = $now->copy()->subDays(2);

                return [$dayBefore->startOfDay(), $dayBefore->endOfDay()];

            case 'week':
                $lastWeek = $now->copy()->subWeek();

                return [$lastWeek->startOfWeek(), $lastWeek->endOfWeek()];

            case 'month':
                $lastMonth = $now->copy()->subMonth();

                return [$lastMonth->startOfMonth(), $lastMonth->endOfMonth()];

            case 'last_month':
                $twoMonthsAgo = $now->copy()->subMonths(2);

                return [$twoMonthsAgo->startOfMonth(), $twoMonthsAgo->endOfMonth()];

            case 'year':
                $lastYear = $now->copy()->subYear();

                return [$lastYear->startOfYear(), $lastYear->endOfYear()];

            default:
                $lastMonth = $now->copy()->subMonth();

                return [$lastMonth->startOfMonth(), $lastMonth->endOfMonth()];
        }
    }

    private function calculateChange($previous, $current)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }
}
