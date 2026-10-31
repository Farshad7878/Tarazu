<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_customers' => Customer::count(),
            'total_orders' => Order::countable()->count(),
            'total_receptionists' => User::where('role', 'reception')->count(),
            'today_orders' => Order::countable()->whereDate('created_at', today())->count(),
        ];

        return response()->json([
            'stats' => $stats,
            'recent_activity' => $this->getRecentActivity(),
        ]);
    }

    private function getRecentActivity()
    {
        // این قسمت بعداً با داده‌های واقعی پر می‌شود
        return [
            [
                'type' => 'new_customer',
                'message' => 'مشتری جدید ثبت نام کرد',
                'time' => '2 دقیقه پیش'
            ],
            [
                'type' => 'new_order',
                'message' => 'سفارش جدید ثبت شد',
                'time' => '5 دقیقه پیش'
            ]
        ];
    }
}