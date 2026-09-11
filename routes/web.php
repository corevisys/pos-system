<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SmsHistoryController;
use App\Http\Controllers\SmsSendController;
use App\Http\Controllers\SmsTemplateController;
use App\Http\Controllers\SmsCampaignController;
use App\Http\Controllers\SmsLogController;
use App\Http\Controllers\SmsAutoRuleController;
use App\Http\Controllers\SmsSettingsController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Technical SEO: Dynamic Sitemap
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');
Route::get('/dashboard/chart-data', [DashboardController::class, 'getChartData'])->middleware(['auth', 'verified'])->name('dashboard.chart-data');
Route::get('/dashboard/data', [DashboardController::class, 'getDashboardData'])->middleware(['auth', 'verified'])->name('dashboard.data');

// Super-Admin only: Multi-Store Network Dashboard
Route::get('/multi-store-dashboard', [App\Http\Controllers\MultiStoreDashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('multi-store-dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Help Documentation Route
Route::get('/docs', [App\Http\Controllers\DocsController::class, 'index'])->middleware(['auth', 'verified'])->name('docs.index');

// Legal Pages Routes
Route::get('/privacy', [App\Http\Controllers\LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/terms', [App\Http\Controllers\LegalController::class, 'terms'])->name('legal.terms');


require __DIR__.'/auth.php';

Route::middleware(['auth', 'verified'])->group(function () {
    // Global Search
    Route::get('/global-search', [App\Http\Controllers\GlobalSearchController::class, 'search'])->name('global.search');

    // Users
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('list', [App\Http\Controllers\UserController::class, 'index'])->name('list');
        Route::get('create', [App\Http\Controllers\UserController::class, 'create'])->name('create');
        Route::get('show/{id}', [App\Http\Controllers\UserController::class, 'show'])->name('show');
        Route::get('edit/{id}', [App\Http\Controllers\UserController::class, 'edit'])->name('edit');
        Route::post('store', [App\Http\Controllers\UserController::class, 'store'])->name('store');
        Route::post('update/{id}', [App\Http\Controllers\UserController::class, 'update'])->name('update');
        Route::patch('toggle-status/{id}', [App\Http\Controllers\UserController::class, 'toggleStatus'])->name('toggle.status');
        Route::delete('delete/{id}', [App\Http\Controllers\UserController::class, 'destroy'])->name('delete');
        
        Route::get('roles', [App\Http\Controllers\RoleController::class, 'index'])->name('roles');
        Route::get('roles/create', [App\Http\Controllers\RoleController::class, 'create'])->name('roles.create');
        Route::get('roles/show/{role}', [App\Http\Controllers\RoleController::class, 'show'])->name('roles.show');
        Route::get('roles/edit/{role}', [App\Http\Controllers\RoleController::class, 'edit'])->name('roles.edit');
        Route::post('roles', [App\Http\Controllers\RoleController::class, 'store'])->name('roles.store');
        Route::put('roles/{role}', [App\Http\Controllers\RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [App\Http\Controllers\RoleController::class, 'destroy'])->name('roles.destroy');
    });

    // Sales
    Route::prefix('sales')->name('sales.')->group(function () {
        Route::get('pos', [App\Http\Controllers\PosController::class, 'index'])->name('pos');
        Route::get('pos/search-items', [App\Http\Controllers\PosController::class, 'searchItems'])->name('pos.search.items');
        Route::get('pos/available-serials', [App\Http\Controllers\PosController::class, 'getAvailableSerials'])->name('pos.search.serials');
        Route::post('pos/store', [App\Http\Controllers\PosController::class, 'store'])->name('pos.store');
        Route::post('pos/hold', [App\Http\Controllers\PosController::class, 'hold'])->name('pos.hold');
        Route::get('hold-list', [App\Http\Controllers\PosController::class, 'holdList'])->name('hold.list');
        Route::delete('pos/hold/{id}', [App\Http\Controllers\PosController::class, 'deleteHold'])->name('pos.hold.delete');
        Route::post('pos/emi', [App\Http\Controllers\PosController::class, 'storeEmi'])->name('pos.emi');
        Route::get('pos/customer-due/{id}', [App\Http\Controllers\PosController::class, 'getCustomerDue'])->name('pos.customer.due');
        Route::get('add', [App\Http\Controllers\SaleController::class, 'create'])->name('add');
        Route::post('store', [App\Http\Controllers\SaleController::class, 'store'])->name('store');
        Route::post('emi', [App\Http\Controllers\SaleController::class, 'storeEmi'])->name('emi');
        Route::get('list', [App\Http\Controllers\SaleController::class, 'index'])->name('list');
        Route::get('show/{id}', [App\Http\Controllers\SaleController::class, 'show'])->name('show');
        Route::get('edit/{id}', [App\Http\Controllers\SaleController::class, 'edit'])->name('edit');
        Route::post('update/{id}', [App\Http\Controllers\SaleController::class, 'update'])->name('update');
        Route::delete('delete/{id}', [App\Http\Controllers\SaleController::class, 'destroy'])->name('delete');
        Route::get('payments', [App\Http\Controllers\SaleController::class, 'paymentsList'])->name('payments');
        Route::get('payments/receive/{id}', [App\Http\Controllers\SaleController::class, 'receivePayment'])->name('payments.receive');
        Route::post('payments/store', [App\Http\Controllers\SaleController::class, 'storePayment'])->name('payments.store');
        Route::delete('payments/{id}', [App\Http\Controllers\SaleController::class, 'destroyPayment'])->name('payments.destroy');
        Route::get('returns', [App\Http\Controllers\SalesReturnController::class, 'index'])->name('returns');
        Route::get('return/{sale_id}', [App\Http\Controllers\SalesReturnController::class, 'create'])->name('return.create');
        Route::get('return/show/{id}', [App\Http\Controllers\SalesReturnController::class, 'show'])->name('return.show');
        Route::post('return/store', [App\Http\Controllers\SalesReturnController::class, 'store'])->name('return.store');
        Route::delete('return/{id}', [App\Http\Controllers\SalesReturnController::class, 'destroy'])->name('return.delete');
        Route::get('emi-list', [App\Http\Controllers\SaleController::class, 'emiList'])->name('emi.list');
        Route::get('emi/show/{id}', [App\Http\Controllers\SaleController::class, 'emiShow'])->name('emi.show');
        Route::post('emi/pay', [App\Http\Controllers\SaleController::class, 'payEmiInstallment'])->name('emi.pay');
        Route::get('invoice/{id}', [App\Http\Controllers\SaleInvoiceController::class, 'show'])->name('invoice');
        Route::post('coupon/validate', [App\Http\Controllers\CouponController::class, 'validateCoupon'])->name('coupon.validate');
    });

    // Contacts
    Route::prefix('contacts')->name('contacts.')->group(function () {
        Route::get('customers/add', [App\Http\Controllers\CustomerController::class, 'create'])->name('customers.add');
        Route::post('customers/store', [App\Http\Controllers\CustomerController::class, 'store'])->name('customers.store');
        Route::post('customers/quick-store', [App\Http\Controllers\CustomerController::class, 'quickStore'])->name('customers.quick-store');
        Route::post('customers/save-step', [App\Http\Controllers\CustomerController::class, 'saveStep'])->name('customers.save-step');
        Route::get('customers', [App\Http\Controllers\CustomerController::class, 'index'])->name('customers.list');
        Route::get('customers/{id}/edit', [App\Http\Controllers\CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('customers/{id}', [App\Http\Controllers\CustomerController::class, 'update'])->name('customers.update');
        Route::delete('customers/{id}', [App\Http\Controllers\CustomerController::class, 'destroy'])->name('customers.delete');
        Route::get('suppliers/add', [App\Http\Controllers\SupplierController::class, 'create'])->name('suppliers.add');
        Route::post('suppliers/store', [App\Http\Controllers\SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('suppliers', [App\Http\Controllers\SupplierController::class, 'index'])->name('suppliers.list');
        Route::get('suppliers/{id}/edit', [App\Http\Controllers\SupplierController::class, 'edit'])->name('suppliers.edit');
        Route::post('suppliers/{id}', [App\Http\Controllers\SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('suppliers/{id}', [App\Http\Controllers\SupplierController::class, 'destroy'])->name('suppliers.delete');
        Route::get('import/customers', [App\Http\Controllers\CustomerController::class, 'import'])->name('customers.import');
        Route::post('import/customers', [App\Http\Controllers\CustomerController::class, 'importStore'])->name('customers.import.store');
        Route::get('import/customers/template', [App\Http\Controllers\CustomerController::class, 'importTemplate'])->name('customers.import.template');
        Route::get('import/suppliers', [App\Http\Controllers\SupplierController::class, 'import'])->name('suppliers.import');
        Route::post('import/suppliers', [App\Http\Controllers\SupplierController::class, 'importStore'])->name('suppliers.import.store');
        Route::get('import/suppliers/template', [App\Http\Controllers\SupplierController::class, 'importTemplate'])->name('suppliers.import.template');
    });

    // Advance
    Route::prefix('advance')->name('advance.')->group(function () {
        Route::get('list', [App\Http\Controllers\AdvanceController::class, 'index'])->name('list');
        Route::get('add', [App\Http\Controllers\AdvanceController::class, 'create'])->name('add');
        Route::post('store', [App\Http\Controllers\AdvanceController::class, 'store'])->name('store');
        Route::get('{id}/edit', [App\Http\Controllers\AdvanceController::class, 'edit'])->name('edit');
        Route::post('{id}/update', [App\Http\Controllers\AdvanceController::class, 'update'])->name('update');
        Route::delete('{id}', [App\Http\Controllers\AdvanceController::class, 'destroy'])->name('delete');
    });

    // Coupons
    Route::prefix('coupons')->name('coupons.')->group(function () {
        Route::get('customer/create', [App\Http\Controllers\CustomerCouponController::class, 'create'])->name('customer.create');
        Route::post('customer/store', [App\Http\Controllers\CustomerCouponController::class, 'store'])->name('customer.store');
        Route::get('customer/list', [App\Http\Controllers\CustomerCouponController::class, 'index'])->name('customer.list');
        Route::get('customer/{id}/edit', [App\Http\Controllers\CustomerCouponController::class, 'edit'])->name('customer.edit');
        Route::put('customer/{id}', [App\Http\Controllers\CustomerCouponController::class, 'update'])->name('customer.update');
        Route::delete('customer/{id}', [App\Http\Controllers\CustomerCouponController::class, 'destroy'])->name('customer.delete');
        
        Route::get('create', [App\Http\Controllers\CouponController::class, 'create'])->name('create');
        Route::post('store', [App\Http\Controllers\CouponController::class, 'store'])->name('store');
        Route::get('master', [App\Http\Controllers\CouponController::class, 'index'])->name('master');
        Route::get('master/{id}/edit', [App\Http\Controllers\CouponController::class, 'edit'])->name('edit');
        Route::put('master/{id}', [App\Http\Controllers\CouponController::class, 'update'])->name('update');
        Route::delete('master/{id}', [App\Http\Controllers\CouponController::class, 'destroy'])->name('delete');
    });

    // Quotation
    Route::prefix('quotation')->name('quotation.')->group(function () {
        Route::get('new', [App\Http\Controllers\QuotationController::class, 'create'])->name('new');
        Route::post('store', [App\Http\Controllers\QuotationController::class, 'store'])->name('store');
        Route::get('list', [App\Http\Controllers\QuotationController::class, 'index'])->name('list');
        Route::get('invoice/{id}', [App\Http\Controllers\QuotationController::class, 'invoice'])->name('invoice');
        Route::get('edit/{id}', [App\Http\Controllers\QuotationController::class, 'edit'])->name('edit');
        Route::post('update/{id}', [App\Http\Controllers\QuotationController::class, 'update'])->name('update');
        Route::get('search-items', [App\Http\Controllers\QuotationController::class, 'searchItems'])->name('search.items');
        Route::delete('delete/{id}', [App\Http\Controllers\QuotationController::class, 'destroy'])->name('delete');
        Route::post('{id}/status', [App\Http\Controllers\QuotationController::class, 'updateStatus'])->name('status.update');
        Route::post('{id}/convert', [App\Http\Controllers\QuotationController::class, 'convertToSale'])->name('convert');
    });

    // Purchase
    Route::prefix('purchase')->name('purchase.')->group(function () {
        Route::get('new', [App\Http\Controllers\PurchaseController::class, 'create'])->name('new');
        Route::post('store', [App\Http\Controllers\PurchaseController::class, 'store'])->name('store');
        Route::get('list', [App\Http\Controllers\PurchaseController::class, 'index'])->name('list');
        Route::get('invoice/{id}', [App\Http\Controllers\PurchaseController::class, 'invoice'])->name('invoice');
        Route::get('barcode/{id}', [App\Http\Controllers\PurchaseController::class, 'barcode'])->name('barcode');
        Route::get('edit/{id}', [App\Http\Controllers\PurchaseController::class, 'edit'])->name('edit');
        Route::post('update/{id}', [App\Http\Controllers\PurchaseController::class, 'update'])->name('update');
        Route::get('search-items', [App\Http\Controllers\PurchaseController::class, 'searchItems'])->name('search.items');
        Route::post('quick-item-store', [App\Http\Controllers\PurchaseController::class, 'quickStoreItem'])->name('quick.item.store');
        
        // Returns
        Route::get('returns', [App\Http\Controllers\PurchaseController::class, 'returnList'])->name('returns');
        Route::get('return/{id}', [App\Http\Controllers\PurchaseController::class, 'createReturn'])->name('return');
        Route::post('return-store', [App\Http\Controllers\PurchaseController::class, 'storeReturn'])->name('return.store');
        Route::get('return-invoice/{id}', [App\Http\Controllers\PurchaseController::class, 'returnInvoice'])->name('return.invoice');
        Route::delete('return-delete/{id}', [App\Http\Controllers\PurchaseController::class, 'destroyReturn'])->name('return.delete');

        // Delete & Payment actions
        Route::delete('delete/{id}', [App\Http\Controllers\PurchaseController::class, 'destroy'])->name('delete');
        Route::post('{id}/payment', [App\Http\Controllers\PurchaseController::class, 'storePayment'])->name('payment.store');
    });

    // Accounts
    Route::prefix('accounts')->name('accounts.')->group(function () {
        Route::get('list', [App\Http\Controllers\AccountController::class, 'index'])->name('list');
        Route::get('add', [App\Http\Controllers\AccountController::class, 'create'])->name('add');
        Route::post('store', [App\Http\Controllers\AccountController::class, 'store'])->name('store');
        Route::get('{id}/edit', [App\Http\Controllers\AccountController::class, 'edit'])->whereNumber('id')->name('edit');
        Route::match(['put', 'patch'], '{id}', [App\Http\Controllers\AccountController::class, 'update'])->whereNumber('id')->name('update');
        Route::post('bulk-delete', [App\Http\Controllers\AccountController::class, 'bulkDestroy'])->name('bulk-delete');
        Route::delete('delete/{id}', [App\Http\Controllers\AccountController::class, 'destroy'])->name('delete');
        Route::get('transfer', [App\Http\Controllers\TransferController::class, 'index'])->name('transfer');
        Route::get('transfer/add', [App\Http\Controllers\TransferController::class, 'create'])->name('transfer.add');
        Route::post('transfer/store', [App\Http\Controllers\TransferController::class, 'store'])->name('transfer.store');
        Route::get('transfer/{id}/edit', [App\Http\Controllers\TransferController::class, 'edit'])->whereNumber('id')->name('transfer.edit');
        Route::match(['put', 'patch'], 'transfer/{id}', [App\Http\Controllers\TransferController::class, 'update'])->whereNumber('id')->name('transfer.update');
        Route::post('transfer/bulk-delete', [App\Http\Controllers\TransferController::class, 'bulkDestroy'])->name('transfer.bulk-delete');
        Route::delete('transfer/delete/{id}', [App\Http\Controllers\TransferController::class, 'destroy'])->name('transfer.delete');
        Route::get('deposit', [App\Http\Controllers\DepositController::class, 'index'])->name('deposit');
        Route::get('deposit/add', [App\Http\Controllers\DepositController::class, 'create'])->name('deposit.add');
        Route::post('deposit/store', [App\Http\Controllers\DepositController::class, 'store'])->name('deposit.store');
        Route::get('deposit/{id}/edit', [App\Http\Controllers\DepositController::class, 'edit'])->whereNumber('id')->name('deposit.edit');
        Route::match(['put', 'patch'], 'deposit/{id}', [App\Http\Controllers\DepositController::class, 'update'])->whereNumber('id')->name('deposit.update');
        Route::post('deposit/bulk-delete', [App\Http\Controllers\DepositController::class, 'bulkDestroy'])->name('deposit.bulk-delete');
        Route::delete('deposit/delete/{id}', [App\Http\Controllers\DepositController::class, 'destroy'])->name('deposit.delete');
        Route::get('transactions', [App\Http\Controllers\TransactionController::class, 'index'])->name('transactions');
        Route::get('cash-reconciliation', [App\Http\Controllers\CashReconciliationController::class, 'index'])->name('cash-reconciliation.index');
        Route::get('cash-reconciliation/open', [App\Http\Controllers\CashReconciliationController::class, 'openForm'])->name('cash-reconciliation.open-form');
        Route::post('cash-reconciliation/open', [App\Http\Controllers\CashReconciliationController::class, 'openDrawer'])->name('cash-reconciliation.open');
        Route::get('cash-reconciliation/{id}/close', [App\Http\Controllers\CashReconciliationController::class, 'closeForm'])->name('cash-reconciliation.close-form');
        Route::post('cash-reconciliation/{id}/close', [App\Http\Controllers\CashReconciliationController::class, 'closeDrawer'])->name('cash-reconciliation.close');
        Route::get('cash-reconciliation/calculate-expected', [App\Http\Controllers\CashReconciliationController::class, 'calculateExpected'])->name('cash-reconciliation.calculate-expected');
        Route::get('cash-reconciliation/create', [App\Http\Controllers\CashReconciliationController::class, 'create'])->name('cash-reconciliation.create');
        Route::post('cash-reconciliation/store', [App\Http\Controllers\CashReconciliationController::class, 'store'])->name('cash-reconciliation.store');
        Route::get('cash-reconciliation/{id}', [App\Http\Controllers\CashReconciliationController::class, 'show'])->name('cash-reconciliation.show');
        Route::get('cash-reconciliation/{id}/edit', [App\Http\Controllers\CashReconciliationController::class, 'edit'])->whereNumber('id')->name('cash-reconciliation.edit');
        Route::match(['put', 'patch'], 'cash-reconciliation/{id}', [App\Http\Controllers\CashReconciliationController::class, 'update'])->whereNumber('id')->name('cash-reconciliation.update');
        Route::post('cash-reconciliation/bulk-delete', [App\Http\Controllers\CashReconciliationController::class, 'bulkDestroy'])->name('cash-reconciliation.bulk-delete');
        Route::delete('cash-reconciliation/delete/{id}', [App\Http\Controllers\CashReconciliationController::class, 'destroy'])->name('cash-reconciliation.delete');
    });

    // Items
    Route::prefix('items')->name('items.')->group(function () {
        Route::get('add', [App\Http\Controllers\ItemController::class, 'create'])->name('add');
        Route::post('store', [App\Http\Controllers\ItemController::class, 'store'])->name('store');
        
        // Services
        Route::prefix('service')->name('service.')->group(function () {
            Route::get('add', [App\Http\Controllers\ServiceController::class, 'create'])->name('add');
            Route::post('store', [App\Http\Controllers\ServiceController::class, 'store'])->name('store');
            Route::get('list', [App\Http\Controllers\ServiceController::class, 'index'])->name('list');
            Route::get('edit/{id}', [App\Http\Controllers\ServiceController::class, 'edit'])->name('edit');
            Route::post('update/{id}', [App\Http\Controllers\ServiceController::class, 'update'])->name('update');
            Route::delete('delete/{id}', [App\Http\Controllers\ServiceController::class, 'destroy'])->name('delete');
            Route::patch('toggle-status/{id}', [App\Http\Controllers\ServiceController::class, 'toggleStatus'])->name('toggle-status');
        });
        
        Route::get('list', [App\Http\Controllers\ItemController::class, 'index'])->name('list');
        Route::get('show/{id}', [App\Http\Controllers\ItemController::class, 'show'])->name('show');
        Route::get('check-serial', [App\Http\Controllers\ItemController::class, 'checkSerial'])->name('check-serial');
        Route::get('serial-history', [App\Http\Controllers\SerialHistoryController::class, 'index'])->name('serial-history');
        Route::get('edit/{id}', [App\Http\Controllers\ItemController::class, 'edit'])->name('edit');
        Route::post('update/{id}', [App\Http\Controllers\ItemController::class, 'update'])->name('update');
        Route::delete('delete/{id}', [App\Http\Controllers\ItemController::class, 'destroy'])->name('delete');
        
        Route::get('categories', [App\Http\Controllers\CategoryController::class, 'index'])->name('categories');
        Route::get('categories/add', [App\Http\Controllers\CategoryController::class, 'create'])->name('categories.add');
        Route::post('categories', [App\Http\Controllers\CategoryController::class, 'store'])->name('categories.store');
        Route::get('categories/{category}/edit', [App\Http\Controllers\CategoryController::class, 'edit'])->name('categories.edit');
        Route::put('categories/{category}', [App\Http\Controllers\CategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category}', [App\Http\Controllers\CategoryController::class, 'destroy'])->name('categories.destroy');
        Route::patch('categories/toggle-status/{category}', [App\Http\Controllers\CategoryController::class, 'toggleStatus'])->name('categories.toggle-status');
        Route::get('brands', [App\Http\Controllers\BrandController::class, 'index'])->name('brands');
        Route::get('brands/add', [App\Http\Controllers\BrandController::class, 'create'])->name('brands.add');
        Route::post('brands', [App\Http\Controllers\BrandController::class, 'store'])->name('brands.store');
        Route::get('brands/{brand}/edit', [App\Http\Controllers\BrandController::class, 'edit'])->name('brands.edit');
        Route::put('brands/{brand}', [App\Http\Controllers\BrandController::class, 'update'])->name('brands.update');
        Route::delete('brands/{brand}', [App\Http\Controllers\BrandController::class, 'destroy'])->name('brands.destroy');
        Route::patch('brands/toggle-status/{brand}', [App\Http\Controllers\BrandController::class, 'toggleStatus'])->name('brands.toggle-status');
        Route::get('variants', [App\Http\Controllers\VariantController::class, 'index'])->name('variants');
        Route::get('variants/add', [App\Http\Controllers\VariantController::class, 'create'])->name('variants.add');
        Route::post('variants', [App\Http\Controllers\VariantController::class, 'store'])->name('variants.store');
        Route::get('variants/{variant}/edit', [App\Http\Controllers\VariantController::class, 'edit'])->name('variants.edit');
        Route::put('variants/{variant}', [App\Http\Controllers\VariantController::class, 'update'])->name('variants.update');
        Route::delete('variants/{variant}', [App\Http\Controllers\VariantController::class, 'destroy'])->name('variants.destroy');
        Route::patch('variants/toggle-status/{variant}', [App\Http\Controllers\VariantController::class, 'toggleStatus'])->name('variants.toggle-status');
        Route::get('labels', [App\Http\Controllers\ItemController::class, 'printLabels'])->name('labels');
        Route::post('labels', [App\Http\Controllers\ItemController::class, 'printLabels'])->name('labels.post');
        Route::match(['get', 'post'], 'labels/print', [App\Http\Controllers\ItemController::class, 'directPrintLabels'])->name('labels.print');
        Route::match(['get', 'post'], 'labels/pdf', [App\Http\Controllers\ItemController::class, 'generatePdfLabels'])->name('labels.pdf');
        Route::get('search-items', [App\Http\Controllers\ItemController::class, 'searchItems'])->name('search.items');
        Route::get('labels/batch-items', [App\Http\Controllers\ItemController::class, 'getBatchItemsForLabels'])->name('labels.batch');
        Route::get('import', [App\Http\Controllers\ItemController::class, 'import'])->name('import');
        Route::post('import', [App\Http\Controllers\ItemController::class, 'importStore'])->name('import.store');
        Route::get('import/template', [App\Http\Controllers\ItemController::class, 'importTemplate'])->name('import.template');
    });


    // Stock
    Route::prefix('stock')->name('stock.')->group(function () {
        Route::get('adjustment', [App\Http\Controllers\StockAdjustmentController::class, 'index'])->name('adjustment');
        Route::get('adjustment/create', [App\Http\Controllers\StockAdjustmentController::class, 'create'])->name('adjustment.create');
        Route::post('adjustment/store', [App\Http\Controllers\StockAdjustmentController::class, 'store'])->name('adjustment.store');
        Route::get('adjustment/{id}/show', [App\Http\Controllers\StockAdjustmentController::class, 'show'])->name('adjustment.show');
        Route::get('adjustment/{id}/edit', [App\Http\Controllers\StockAdjustmentController::class, 'edit'])->name('adjustment.edit');
        Route::post('adjustment/{id}/update', [App\Http\Controllers\StockAdjustmentController::class, 'update'])->name('adjustment.update');
        Route::delete('adjustment/{id}', [App\Http\Controllers\StockAdjustmentController::class, 'destroy'])->name('adjustment.destroy');
        Route::get('adjustment/search-items', [App\Http\Controllers\StockAdjustmentController::class, 'searchItems'])->name('adjustment.search.items');
        Route::get('transfer', [App\Http\Controllers\StockTransferController::class, 'index'])->name('transfer');
        Route::get('transfer/create', [App\Http\Controllers\StockTransferController::class, 'create'])->name('transfer.create');
        Route::post('transfer/store', [App\Http\Controllers\StockTransferController::class, 'store'])->name('transfer.store');
        Route::get('transfer/{id}/edit', [App\Http\Controllers\StockTransferController::class, 'edit'])->name('transfer.edit');
        Route::post('transfer/{id}/update', [App\Http\Controllers\StockTransferController::class, 'update'])->name('transfer.update');
        Route::delete('transfer/{id}', [App\Http\Controllers\StockTransferController::class, 'destroy'])->name('transfer.destroy');
        Route::get('transfer/search-items', [App\Http\Controllers\StockTransferController::class, 'searchItems'])->name('transfer.search.items');
    });

    // Expenses
    Route::prefix('expenses')->name('expenses.')->group(function () {
        Route::get('list', [App\Http\Controllers\ExpenseController::class, 'index'])->name('list');
        Route::get('add', [App\Http\Controllers\ExpenseController::class, 'create'])->name('add');
        Route::post('store', [App\Http\Controllers\ExpenseController::class, 'store'])->name('store');
        Route::get('edit/{id}', [App\Http\Controllers\ExpenseController::class, 'edit'])->name('edit');
        Route::post('update/{id}', [App\Http\Controllers\ExpenseController::class, 'update'])->name('update');
        Route::delete('delete/{id}', [App\Http\Controllers\ExpenseController::class, 'destroy'])->name('delete');
        Route::get('categories', [App\Http\Controllers\ExpenseCategoryController::class, 'index'])->name('categories');
        Route::get('categories/add', [App\Http\Controllers\ExpenseCategoryController::class, 'create'])->name('categories.add');
        Route::post('categories/store', [App\Http\Controllers\ExpenseCategoryController::class, 'store'])->name('categories.store');
        Route::get('categories/edit/{id}', [App\Http\Controllers\ExpenseCategoryController::class, 'edit'])->name('categories.edit');
        Route::post('categories/update/{id}', [App\Http\Controllers\ExpenseCategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/delete/{id}', [App\Http\Controllers\ExpenseCategoryController::class, 'destroy'])->name('categories.delete');
    });

    // Messaging
    Route::prefix('messaging')->name('messaging.')->group(function () {
        Route::view('send', 'module.messaging.send_message')->name('send');
        Route::get('templates', [App\Http\Controllers\MessageTemplateController::class, 'index'])->name('templates');
        Route::get('templates/create', [App\Http\Controllers\MessageTemplateController::class, 'create'])->name('templates.create');
        Route::post('templates/store', [App\Http\Controllers\MessageTemplateController::class, 'store'])->name('templates.store');
        Route::get('templates/{id}/edit', [App\Http\Controllers\MessageTemplateController::class, 'edit'])->name('templates.edit');
        Route::post('templates/{id}/update', [App\Http\Controllers\MessageTemplateController::class, 'update'])->name('templates.update');
        Route::delete('templates/{id}', [App\Http\Controllers\MessageTemplateController::class, 'destroy'])->name('templates.delete');
        
        // SMS Settings (Automation/Events)
        Route::get('settings', [App\Http\Controllers\SmsSettingsController::class, 'autoSettings'])->name('settings');
        Route::post('settings/status', [App\Http\Controllers\SmsSettingsController::class, 'updateAutoStatus'])->name('settings.status');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('sales-summary', [App\Http\Controllers\ReportController::class, 'salesSummary'])->name('sales_summary');
        Route::get('sales-summary/data', [App\Http\Controllers\ReportController::class, 'getSalesSummaryData'])->name('sales_summary_data');
        Route::get('profit-loss', [App\Http\Controllers\ReportController::class, 'profitLoss'])->name('profit_loss');
        Route::get('profit-loss/data', [App\Http\Controllers\ReportController::class, 'getProfitLossData'])->name('profit_loss_data');
        Route::get('sales-payment', [App\Http\Controllers\ReportController::class, 'salesPayment'])->name('sales_payment');
        Route::get('sales-payment/data', [App\Http\Controllers\ReportController::class, 'getSalesPaymentData'])->name('sales_payment_data');
        Route::get('customer-orders', [App\Http\Controllers\ReportController::class, 'customerOrders'])->name('customer_orders');
        Route::get('customer-orders/data', [App\Http\Controllers\ReportController::class, 'getCustomerOrdersData'])->name('customer_orders_data');
        Route::get('gstr1', [App\Http\Controllers\ReportController::class, 'gstr1'])->name('gstr1');
        Route::get('gstr1/data', [App\Http\Controllers\ReportController::class, 'getGstr1Data'])->name('gstr1_data');
        Route::get('gstr2', [App\Http\Controllers\ReportController::class, 'gstr2'])->name('gstr2');
        Route::get('gstr2/data', [App\Http\Controllers\ReportController::class, 'getGstr2Data'])->name('gstr2_data');
        Route::get('sales-gst', [App\Http\Controllers\ReportController::class, 'salesGst'])->name('sales_gst');
        Route::get('sales-gst/data', [App\Http\Controllers\ReportController::class, 'getSalesGstData'])->name('sales_gst_data');
        Route::get('purchase-gst', [App\Http\Controllers\ReportController::class, 'purchaseGst'])->name('purchase_gst');
        Route::get('purchase-gst/data', [App\Http\Controllers\ReportController::class, 'getPurchaseGstData'])->name('purchase_gst_data');
        Route::get('sales-tax', [App\Http\Controllers\ReportController::class, 'salesTax'])->name('sales_tax');
        Route::get('sales-tax/data', [App\Http\Controllers\ReportController::class, 'getSalesTaxData'])->name('sales_tax_data');
        Route::get('purchase-tax', [App\Http\Controllers\ReportController::class, 'purchaseTax'])->name('purchase_tax');
        Route::get('purchase-tax/data', [App\Http\Controllers\ReportController::class, 'getPurchaseTaxData'])->name('purchase_tax_data');
        Route::get('supplier-items', [App\Http\Controllers\ReportController::class, 'supplierItems'])->name('supplier_items');
        Route::get('supplier-items/data', [App\Http\Controllers\ReportController::class, 'getSupplierItemsData'])->name('supplier_items_data');
        Route::get('sales', [App\Http\Controllers\ReportController::class, 'salesReport'])->name('sales');
        Route::get('sales/data', [App\Http\Controllers\ReportController::class, 'getSalesReportData'])->name('sales_data');
        Route::get('sales-return', [App\Http\Controllers\ReportController::class, 'salesReturnReport'])->name('sales_return');
        Route::get('sales-return/data', [App\Http\Controllers\ReportController::class, 'getSalesReturnReportData'])->name('sales_return_data');
        Route::get('seller-points', [App\Http\Controllers\ReportController::class, 'sellerPointsReport'])->name('seller_points');
        Route::get('seller-points/data', [App\Http\Controllers\ReportController::class, 'getSellerPointsReportData'])->name('seller_points_data');
        Route::get('purchase', [App\Http\Controllers\ReportController::class, 'purchaseReport'])->name('purchase');
        Route::get('purchase/data', [App\Http\Controllers\ReportController::class, 'getPurchaseReportData'])->name('purchase_data');
        Route::get('purchase-return', [App\Http\Controllers\ReportController::class, 'purchaseReturnReport'])->name('purchase_return');
        Route::get('purchase-return/data', [App\Http\Controllers\ReportController::class, 'getPurchaseReturnReportData'])->name('purchase_return_data');
        Route::get('expense', [App\Http\Controllers\ReportController::class, 'expenseReport'])->name('expense');
        Route::get('expense/data', [App\Http\Controllers\ReportController::class, 'getExpenseReportData'])->name('expense_data');
        Route::get('stock', [App\Http\Controllers\ReportController::class, 'stockReport'])->name('stock');
        Route::get('stock/data', [App\Http\Controllers\ReportController::class, 'getStockReportData'])->name('stock_data');
        Route::get('sales-item', [App\Http\Controllers\ReportController::class, 'salesItemReport'])->name('sales_item');
        Route::get('sales-item/data', [App\Http\Controllers\ReportController::class, 'getSalesItemReportData'])->name('sales_item_data');
        Route::get('return-items', [App\Http\Controllers\ReportController::class, 'returnItemsReport'])->name('return_items');
        Route::get('return-items/data', [App\Http\Controllers\ReportController::class, 'getReturnItemsReportData'])->name('return_items_data');
        Route::get('purchase-payments', [App\Http\Controllers\ReportController::class, 'purchasePaymentsReport'])->name('purchase_payments');
        Route::get('purchase-payments/data', [App\Http\Controllers\ReportController::class, 'getPurchasePaymentsReportData'])->name('purchase_payments_data');
        Route::get('sales-payments', [App\Http\Controllers\ReportController::class, 'salesPaymentsReport'])->name('sales_payments');
        Route::get('sales-payments/data', [App\Http\Controllers\ReportController::class, 'getSalesPaymentsReportData'])->name('sales_payments_data');
        Route::get('cash-reconciliation', [App\Http\Controllers\ReportController::class, 'cashReconciliationReport'])->name('cash_reconciliation');
        Route::get('cash-reconciliation/data', [App\Http\Controllers\ReportController::class, 'getCashReconciliationData'])->name('cash_reconciliation_data');
        Route::get('cash-flow', [App\Http\Controllers\ReportController::class, 'cashFlowReport'])->name('cash_flow');
        Route::get('cash-flow/data', [App\Http\Controllers\ReportController::class, 'getCashFlowReportData'])->name('cash_flow_data');
    });

    // Warehouse
    Route::prefix('warehouse')->name('warehouse.')->group(function () {
        Route::get('list', [App\Http\Controllers\WarehouseController::class, 'index'])->name('list');
        Route::get('add', [App\Http\Controllers\WarehouseController::class, 'create'])->name('add');
        Route::post('store', [App\Http\Controllers\WarehouseController::class, 'store'])->name('store');
        Route::get('{warehouse}/edit', [App\Http\Controllers\WarehouseController::class, 'edit'])->name('edit');
        Route::put('{warehouse}', [App\Http\Controllers\WarehouseController::class, 'update'])->name('update');
        Route::delete('{warehouse}', [App\Http\Controllers\WarehouseController::class, 'destroy'])->name('destroy');
    });

    // Settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('store', [App\Http\Controllers\StoreSettingsController::class, 'edit'])->name('store');
        Route::post('store', [App\Http\Controllers\StoreSettingsController::class, 'update'])->name('store.update');
        Route::get('store/states/{country_id}', [App\Http\Controllers\StoreSettingsController::class, 'getStates'])->name('store.states');

        // Languages
        Route::get('languages', [App\Http\Controllers\LanguageController::class, 'index'])->name('languages.index');
        Route::get('languages/create', [App\Http\Controllers\LanguageController::class, 'create'])->name('languages.create');
        Route::post('languages', [App\Http\Controllers\LanguageController::class, 'store'])->name('languages.store');
        Route::get('languages/{id}/edit', [App\Http\Controllers\LanguageController::class, 'edit'])->name('languages.edit');
        Route::post('languages/{id}', [App\Http\Controllers\LanguageController::class, 'update'])->name('languages.update');
        Route::post('languages/{id}/activate', [App\Http\Controllers\LanguageController::class, 'activate'])->name('languages.activate');
        Route::delete('languages/{id}', [App\Http\Controllers\LanguageController::class, 'destroy'])->name('languages.destroy');
        Route::get('countries', [App\Http\Controllers\CountryController::class, 'index'])->name('countries');
        Route::get('countries/add', [App\Http\Controllers\CountryController::class, 'create'])->name('countries.add');
        Route::post('countries', [App\Http\Controllers\CountryController::class, 'store'])->name('countries.store');
        Route::get('countries/{id}/edit', [App\Http\Controllers\CountryController::class, 'edit'])->name('countries.edit');
        Route::post('countries/{id}', [App\Http\Controllers\CountryController::class, 'update'])->name('countries.update');
        Route::delete('countries/{id}', [App\Http\Controllers\CountryController::class, 'destroy'])->name('countries.delete');

        Route::get('states', [App\Http\Controllers\StateController::class, 'index'])->name('states');
        Route::get('states/add', [App\Http\Controllers\StateController::class, 'create'])->name('states.add');
        Route::post('states', [App\Http\Controllers\StateController::class, 'store'])->name('states.store');
        Route::get('states/{id}/edit', [App\Http\Controllers\StateController::class, 'edit'])->name('states.edit');
        Route::post('states/{id}', [App\Http\Controllers\StateController::class, 'update'])->name('states.update');
        Route::delete('states/{id}', [App\Http\Controllers\StateController::class, 'destroy'])->name('states.delete');
        
        Route::get('database-backup', [App\Http\Controllers\BackupController::class, 'index'])->name('backup');
        Route::post('database-backup/create', [App\Http\Controllers\BackupController::class, 'create'])->name('backup.create');
        Route::get('database-backup/download/{filename}', [App\Http\Controllers\BackupController::class, 'download'])->name('backup.download');
        Route::delete('database-backup/delete/{filename}', [App\Http\Controllers\BackupController::class, 'destroy'])->name('backup.delete');

        Route::get('tax', [TaxController::class, 'index'])->name('tax');
        Route::post('tax', [TaxController::class, 'store'])->name('tax.store');
        Route::post('tax/{id}', [TaxController::class, 'update'])->name('tax.update');
        Route::delete('tax/{id}', [TaxController::class, 'destroy'])->name('tax.delete');
        
        Route::get('units', [UnitController::class, 'index'])->name('units');
        Route::post('units', [UnitController::class, 'store'])->name('units.store');
        Route::post('units/{id}', [UnitController::class, 'update'])->name('units.update');
        Route::delete('units/{id}', [UnitController::class, 'destroy'])->name('units.delete');
        Route::get('payment-types', [App\Http\Controllers\PaymentTypeController::class, 'index'])->name('payment_types');
        Route::post('payment-types', [App\Http\Controllers\PaymentTypeController::class, 'store'])->name('payment_types.store');
        Route::post('payment-types/{id}', [App\Http\Controllers\PaymentTypeController::class, 'update'])->name('payment_types.update');
        Route::delete('payment-types/{id}', [App\Http\Controllers\PaymentTypeController::class, 'destroy'])->name('payment_types.delete');
        Route::view('password', 'module.settings.change_password')->name('password');
        Route::view('site-settings', 'module.settings.site_settings')->name('site_settings');
        Route::get('smtp', [App\Http\Controllers\SmtpSettingsController::class, 'index'])->name('smtp');
        Route::post('smtp/update', [App\Http\Controllers\SmtpSettingsController::class, 'update'])->name('smtp.update');
        Route::post('smtp/test', [App\Http\Controllers\SmtpSettingsController::class, 'testSmtp'])->name('smtp.test');
        Route::get('currency', [App\Http\Controllers\CurrencyController::class, 'index'])->name('currency');
        Route::post('currency', [App\Http\Controllers\CurrencyController::class, 'store'])->name('currency.store');
        Route::post('currency/{id}', [App\Http\Controllers\CurrencyController::class, 'update'])->name('currency.update');
        Route::post('currency/{id}/activate', [App\Http\Controllers\CurrencyController::class, 'activate'])->name('currency.activate');
        Route::delete('currency/{id}', [App\Http\Controllers\CurrencyController::class, 'destroy'])->name('currency.delete');
    });

    // Messaging Intelligence Module
    Route::prefix('sms')->name('sms.')->group(function () {
        Route::get('history', [SmsHistoryController::class, 'index'])->name('history');
        Route::get('send', [SmsSendController::class, 'index'])->name('send');
        Route::get('send/count', [SmsSendController::class, 'getCount'])->name('send.count');
        Route::get('send/search-customers', [SmsSendController::class, 'searchCustomers'])->name('send.search.customers');
        Route::post('send/process', [SmsSendController::class, 'process'])->name('send.process');
        
        Route::get('templates', [SmsTemplateController::class, 'index'])->name('templates');
        Route::get('templates/create', [SmsTemplateController::class, 'create'])->name('templates.create');
        Route::post('templates/store', [SmsTemplateController::class, 'store'])->name('templates.store');
        Route::get('templates/{id}/edit', [SmsTemplateController::class, 'edit'])->name('templates.edit');
        Route::post('templates/{id}/update', [SmsTemplateController::class, 'update'])->name('templates.update');
        Route::delete('templates/{id}', [SmsTemplateController::class, 'destroy'])->name('templates.delete');
        Route::get('campaigns', [SmsCampaignController::class, 'index'])->name('campaigns');
        Route::get('campaigns/{id}/edit', [SmsCampaignController::class, 'edit'])->name('campaigns.edit');
        Route::post('campaigns/{id}/update', [SmsCampaignController::class, 'update'])->name('campaigns.update');
        Route::delete('campaigns/{id}', [SmsCampaignController::class, 'destroy'])->name('campaigns.delete');
        Route::get('logs', [SmsLogController::class, 'index'])->name('logs');
        Route::get('auto-rules', [SmsAutoRuleController::class, 'index'])->name('auto-rules');
        Route::post('auto-rules/store', [SmsAutoRuleController::class, 'store'])->name('auto-rules.store');
        Route::post('auto-rules/{id}/update', [SmsAutoRuleController::class, 'update'])->name('auto-rules.update');
        Route::patch('auto-rules/{id}/toggle', [SmsAutoRuleController::class, 'toggleStatus'])->name('auto-rules.toggle');
        Route::delete('auto-rules/{id}', [SmsAutoRuleController::class, 'destroy'])->name('auto-rules.delete');
        
        Route::get('settings', [SmsSettingsController::class, 'index'])->name('settings');
        Route::post('settings/update', [SmsSettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/test', [SmsSettingsController::class, 'testSms'])->name('settings.test');
    });
});

