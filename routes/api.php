<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\CashShiftController as AdminCashShiftController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DiscountController;
use App\Http\Controllers\Admin\GiftCardController as AdminGiftCardController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\ProductCategoryController as AdminProductCategoryController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SmsCampaignController;
use App\Http\Controllers\Admin\StockController;
use App\Http\Controllers\Admin\StockTransferController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Reception\CashShiftController as ReceptionCashShiftController;
use App\Http\Controllers\Reception\CustomerController as ReceptionCustomerController;
use App\Http\Controllers\Reception\GiftCardController as ReceptionGiftCardController;
use App\Http\Controllers\Reception\HeldCartController;
use App\Http\Controllers\Reception\DiscountController as ReceptionDiscountController;
use App\Http\Controllers\Reception\OrderController as ReceptionOrderController;
use App\Http\Controllers\Reception\OrderReturnController;
use App\Http\Controllers\Reception\ProductController as ReceptionProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// احراز هویت
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:30,1'); // محدودیت سطح IP، علاوه بر قفل ایمیل+IP داخل کنترلر
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// کاربر جاری (برای بازیابی نشست بعد از refresh)
Route::get('/user', function (Request $request) {
    $user = $request->user();

    return response()->json([
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'role' => $user->role,
        'branch' => $user->branch?->only(['id', 'name', 'code']),
    ]);
})->middleware('auth:sanctum');

// اعلان‌های ماندگار کاربر جاری (مثلاً تولد مشتری‌ها برای ادمین)
Route::prefix('notifications')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::post('/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/{notification}/read', [NotificationController::class, 'markRead']);
});

// روت‌های ادمین
// نکته: فقط متدهایی ثبت می‌شوند که کنترلر واقعاً دارد؛ وگرنه روت به خطای ۵۰۰ منجر می‌شود.
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::apiResource('/users', AdminUserController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('/customers', AdminCustomerController::class)->only(['index', 'store', 'show', 'update']);
    Route::apiResource('/product-categories', AdminProductCategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('/products', AdminProductController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('/branches', BranchController::class)->only(['index', 'store', 'update']);
    Route::get('/branches/{branch}/stock', [BranchController::class, 'stock']);
    Route::apiResource('/stock-transfers', StockTransferController::class)->only(['index', 'store', 'show']);
    Route::post('/stock-transfers/{stockTransfer}/receive', [StockTransferController::class, 'receive']);
    Route::post('/stock-transfers/{stockTransfer}/cancel', [StockTransferController::class, 'cancel']);
    Route::get('/products/{product}/stock-history', [StockController::class, 'history']);
    Route::post('/products/{product}/stock-adjustments', [StockController::class, 'store']);
    Route::post('/products/{product}/variants', [AdminProductController::class, 'storeVariant']);
    Route::post('/products/{product}/barcode', [AdminProductController::class, 'ensureBarcode']);
    Route::post('/products/{product}/image', [AdminProductController::class, 'uploadImage']);
    Route::delete('/products/{product}/image', [AdminProductController::class, 'removeImage']);
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
    Route::apiResource('/suppliers', SupplierController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']);
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
    Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive']);
    Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel']);
    Route::get('/gift-cards', [AdminGiftCardController::class, 'index']);
    Route::post('/gift-cards', [AdminGiftCardController::class, 'store']);
    Route::get('/gift-cards/{giftCard}', [AdminGiftCardController::class, 'show']);
    Route::patch('/gift-cards/{giftCard}', [AdminGiftCardController::class, 'update']);
    Route::get('/backups', [BackupController::class, 'index']);
    Route::post('/backups', [BackupController::class, 'store']);
    Route::get('/backups/{name}/download', [BackupController::class, 'download'])->where('name', '[A-Za-z0-9._-]+');
    Route::post('/backups/restore', [BackupController::class, 'restore']);
    Route::delete('/backups/{name}', [BackupController::class, 'destroy'])->where('name', '[A-Za-z0-9._-]+');

    Route::post('/customers/{customer}/loyalty-adjustments', [AdminCustomerController::class, 'adjustLoyalty']);
    Route::get('/cash-shifts', [AdminCashShiftController::class, 'index']);

    Route::get('/sms-campaigns', [SmsCampaignController::class, 'index']);
    Route::post('/sms-campaigns/preview', [SmsCampaignController::class, 'preview']);
    Route::post('/sms-campaigns', [SmsCampaignController::class, 'store']);
    Route::post('/sms-campaigns/{campaign}/cancel', [SmsCampaignController::class, 'cancel']);

    Route::apiResource('/discounts', DiscountController::class)->only(['index', 'store', 'update']);
    Route::post('/discounts/{discount}/assign', [DiscountController::class, 'assignToCustomer']);

    Route::get('/reports/stats', [ReportController::class, 'stats']);
    Route::get('/reports/recent-orders', [ReportController::class, 'recentOrders']);
    Route::get('/reports/category-sales', [ReportController::class, 'categorySales']);
    Route::get('/reports/daily-revenue', [ReportController::class, 'dailyRevenue']);
    Route::get('/reports/reception-performance', [ReportController::class, 'receptionPerformance']);
    Route::get('/reports/hourly-orders', [ReportController::class, 'hourlyOrders']);
    Route::get('/reports/export', [ReportController::class, 'export']);
});

// روت‌های ریسپشن
Route::prefix('reception')->middleware(['auth:sanctum', 'reception'])->group(function () {
    Route::apiResource('/orders', ReceptionOrderController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::post('/orders/{order}/returns', [OrderReturnController::class, 'store']);
    Route::post('/orders/{order}/exchanges', [ReceptionOrderController::class, 'exchange']);
    Route::get('/held-carts', [HeldCartController::class, 'index']);
    Route::post('/held-carts', [HeldCartController::class, 'store']);
    Route::post('/held-carts/{heldCart}/resume', [HeldCartController::class, 'resume']);
    Route::delete('/held-carts/{heldCart}', [HeldCartController::class, 'destroy']);
    Route::apiResource('/customers', ReceptionCustomerController::class)->only(['index', 'store']);
    Route::get('/customers/lookup/{membershipNumber}', [ReceptionCustomerController::class, 'lookup']);
    Route::get('/products', [ReceptionProductController::class, 'index']);
    Route::get('/products/barcode/{code}', [ReceptionProductController::class, 'byBarcode']);
    Route::post('/gift-cards', [ReceptionGiftCardController::class, 'store']);
    Route::get('/gift-cards/{code}', [ReceptionGiftCardController::class, 'lookup']);
    Route::get('/today-stats', [ReceptionProductController::class, 'todayStats']);
    Route::get('/customer-orders', [ReceptionOrderController::class, 'customerOrders']);
    Route::post('/calculate-discount', [ReceptionDiscountController::class, 'calculateOrderDiscount']);
    Route::get('/orders/stats/daily', [ReceptionOrderController::class, 'dailyStats']);

    Route::get('/cash-shifts/current', [ReceptionCashShiftController::class, 'current']);
    Route::get('/cash-shifts', [ReceptionCashShiftController::class, 'index']);
    Route::post('/cash-shifts', [ReceptionCashShiftController::class, 'store']);
    Route::post('/cash-shifts/{cashShift}/close', [ReceptionCashShiftController::class, 'close']);
});
