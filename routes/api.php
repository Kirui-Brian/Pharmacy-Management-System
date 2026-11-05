<?php

use App\Http\Controllers\Api\V1\AccountingController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DispensingController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\GoodsReceivedNoteController;
use App\Http\Controllers\Api\V1\ManufacturerController;
use App\Http\Controllers\Api\V1\PosController;
use App\Http\Controllers\Api\V1\PrescriptionController;
use App\Http\Controllers\Api\V1\ProductBatchController;
use App\Http\Controllers\Api\V1\ProductCategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\PurchaseReturnController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\StockAdjustmentController;
use App\Http\Controllers\Api\V1\StockMovementController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\SupplierInvoiceController;
use App\Http\Controllers\Api\V1\SupplierPaymentController;
use App\Http\Controllers\Api\V1\SystemMaintenanceController;
use App\Http\Controllers\Api\V1\SystemSettingController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Models\AuditLog;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public Auth
    Route::get('/health', [SystemMaintenanceController::class, 'getHealthStatus']);
    Route::post('/auth/login', [AuthController::class, 'login'])->name('login');

    // Authenticated Routes
    Route::middleware('auth:sanctum')->group(function () {
        // Auth & Identity
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/switch-branch', [AuthController::class, 'switchBranch']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // System Settings
        Route::get('/settings', [SystemSettingController::class, 'index']);
        Route::post('/settings', [SystemSettingController::class, 'update']);

        // Branches
        Route::get('/branches', function (Request $request) {
            $user = $request->user();
            return response()->json([
                'status' => 'success',
                'data' => Branch::where('pharmacy_id', $user->pharmacy_id)
                    ->where('is_active', true)
                    ->get(),
            ]);
        });

        // Audit Logs
        Route::get('/audit-logs', function (Request $request) {
            $logs = AuditLog::with('user:id,name,email')
                ->latest('created_at')
                ->paginate(25);

            return response()->json([
                'status' => 'success',
                'data' => $logs,
            ]);
        });

        // ==========================================
        // PRODUCT & INVENTORY MANAGEMENT
        // ==========================================

        // Master Data
        Route::get('/categories', [ProductCategoryController::class, 'index']);
        Route::post('/categories', [ProductCategoryController::class, 'store']);

        Route::get('/manufacturers', [ManufacturerController::class, 'index']);
        Route::post('/manufacturers', [ManufacturerController::class, 'store']);

        Route::get('/units', [UnitController::class, 'index']);
        Route::post('/units', [UnitController::class, 'store']);

        Route::get('/suppliers', [SupplierController::class, 'index']);
        Route::post('/suppliers', [SupplierController::class, 'store']);
        Route::get('/suppliers/{supplier}', [SupplierController::class, 'show']);
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update']);

        // Products
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::get('/products/{id}', [ProductController::class, 'show']);
        Route::put('/products/{id}', [ProductController::class, 'update']);

        // Batches & Expiry Control
        Route::get('/batches', [ProductBatchController::class, 'index']);
        Route::post('/batches', [ProductBatchController::class, 'store']);
        Route::get('/batches/expiry-alerts', [ProductBatchController::class, 'expiryAlerts']);

        // Stock Adjustments & Movements
        Route::get('/stock-adjustments', [StockAdjustmentController::class, 'index']);
        Route::post('/stock-adjustments', [StockAdjustmentController::class, 'store']);
        Route::get('/stock-movements', [StockMovementController::class, 'index']);

        // ==========================================
        // POINT OF SALE & DISPENSING
        // ==========================================

        // Customers
        Route::get('/customers', [CustomerController::class, 'index']);
        Route::post('/customers', [CustomerController::class, 'store']);
        Route::get('/customers/{customer}', [CustomerController::class, 'show']);

        // Prescriptions
        Route::get('/prescriptions', [PrescriptionController::class, 'index']);
        Route::post('/prescriptions', [PrescriptionController::class, 'store']);
        Route::get('/prescriptions/{prescription}', [PrescriptionController::class, 'show']);

        // Point of Sale (POS) Counter
        Route::get('/pos/products', [PosController::class, 'searchProducts']);
        Route::post('/pos/checkout', [PosController::class, 'checkout']);
        Route::get('/pos/receipt/{sale}', [PosController::class, 'getReceipt']);
        Route::get('/pos/receipt/{sale}/pdf', [PosController::class, 'downloadPdfReceipt']);
        Route::post('/pos/return/{sale}', [PosController::class, 'processReturn']);
        Route::get('/pos/recent-sales', [PosController::class, 'recentSales']);

        // Dispensing
        Route::get('/dispensations', [DispensingController::class, 'index']);
        Route::post('/dispensations', [DispensingController::class, 'dispense']);
        Route::get('/dispensations/patient-history', [DispensingController::class, 'patientHistory']);

        // ==========================================
        // PURCHASING, PROCUREMENT & SUPPLIERS
        // ==========================================

        // Purchase Orders
        Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
        Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']);
        Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
        Route::patch('/purchase-orders/{purchaseOrder}/status', [PurchaseOrderController::class, 'updateStatus']);

        // Goods Received Notes (Stock Ingestion)
        Route::get('/goods-received-notes', [GoodsReceivedNoteController::class, 'index']);
        Route::post('/goods-received-notes', [GoodsReceivedNoteController::class, 'store']);
        Route::get('/goods-received-notes/{goodsReceivedNote}', [GoodsReceivedNoteController::class, 'show']);

        // Supplier Invoices & Accounts Payable
        Route::get('/supplier-invoices', [SupplierInvoiceController::class, 'index']);
        Route::get('/supplier-invoices/{supplierInvoice}', [SupplierInvoiceController::class, 'show']);

        // Supplier Payments
        Route::get('/supplier-payments', [SupplierPaymentController::class, 'index']);
        Route::post('/supplier-payments', [SupplierPaymentController::class, 'store']);
        Route::get('/supplier-payments/{supplierPayment}', [SupplierPaymentController::class, 'show']);

        // Purchase Returns
        Route::get('/purchase-returns', [PurchaseReturnController::class, 'index']);
        Route::post('/purchase-returns', [PurchaseReturnController::class, 'store']);
        Route::get('/purchase-returns/{purchaseReturn}', [PurchaseReturnController::class, 'show']);

        // ==========================================
        // FINANCIAL ACCOUNTING & EXPENSES
        // ==========================================

        // General Ledger & Chart of Accounts
        Route::get('/accounting/chart-of-accounts', [AccountingController::class, 'getChartOfAccounts']);
        Route::post('/accounting/chart-of-accounts', [AccountingController::class, 'storeAccount']);
        Route::get('/accounting/journal-entries', [AccountingController::class, 'getJournalEntries']);
        Route::post('/accounting/journal-entries', [AccountingController::class, 'storeJournalEntry']);

        // Financial Statements & Reports
        Route::get('/accounting/profit-and-loss', [AccountingController::class, 'getProfitAndLoss']);
        Route::get('/accounting/balance-sheet', [AccountingController::class, 'getBalanceSheet']);
        Route::get('/accounting/trial-balance', [AccountingController::class, 'getTrialBalance']);
        Route::get('/accounting/cash-flow', [AccountingController::class, 'getCashFlow']);

        // Operating Expenses & Vouchers
        Route::get('/expenses/categories', [ExpenseController::class, 'getCategories']);
        Route::post('/expenses/categories', [ExpenseController::class, 'storeCategory']);
        Route::get('/expenses/vouchers', [ExpenseController::class, 'getVouchers']);
        Route::post('/expenses/vouchers', [ExpenseController::class, 'storeVoucher']);
        Route::put('/expenses/vouchers/{id}/approve', [ExpenseController::class, 'approveVoucher']);
        Route::put('/expenses/vouchers/{id}/reject', [ExpenseController::class, 'rejectVoucher']);

        // ==========================================
        // BUSINESS ANALYTICS & DATA EXPORT
        // ==========================================

        Route::get('/reports/dashboard-summary', [ReportController::class, 'getDashboardSummary']);
        Route::get('/reports/sales-analytics', [ReportController::class, 'getSalesAnalytics']);
        Route::get('/reports/top-selling-products', [ReportController::class, 'getTopSellingProducts']);
        Route::get('/reports/inventory-valuation', [ReportController::class, 'getInventoryValuation']);
        Route::get('/reports/expiry-risk-analysis', [ReportController::class, 'getExpiryRiskAnalysis']);
        Route::get('/reports/dead-stock', [ReportController::class, 'getDeadStock']);
        Route::get('/reports/clinical-dispensing', [ReportController::class, 'getClinicalDispensing']);

        // Data Export Streaming Endpoints (CSV)
        Route::get('/reports/export/sales-csv', [ReportController::class, 'exportSalesCsv']);
        Route::get('/reports/export/inventory-csv', [ReportController::class, 'exportInventoryCsv']);
        Route::get('/reports/export/expiry-csv', [ReportController::class, 'exportExpiryCsv']);

        // ==========================================
        // SYSTEM HEALTH, MAINTENANCE & BACKUPS
        // ==========================================

        Route::get('/system/health', [SystemMaintenanceController::class, 'getHealthStatus']);
        Route::get('/system/backups', [SystemMaintenanceController::class, 'getBackupsList']);
        Route::post('/system/backups', [SystemMaintenanceController::class, 'triggerBackup']);
        Route::get('/system/backups/{filename}/download', [SystemMaintenanceController::class, 'downloadBackup']);
    });
});
