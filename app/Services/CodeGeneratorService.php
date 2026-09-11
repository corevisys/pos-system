<?php

namespace App\Services;

use App\Models\AcAccount;
use App\Models\AcMoneyTransfer;
use App\Models\CashDrawerReconciliation;
use App\Models\DbCustAdvance;
use App\Models\DbCustomer;
use App\Models\DbExpense;
use App\Models\DbItem;
use App\Models\DbPurchase;
use App\Models\DbPurchaseReturn;
use App\Models\DbQuotation;
use App\Models\DbSale;
use App\Models\DbSalesReturn;
use App\Models\DbStore;
use App\Models\DbSupplier;
use Illuminate\Support\Facades\DB;

class CodeGeneratorService
{
    /**
     * Generate code based on entity type and store settings configuration.
     * Scoped to the target store, serialized with lockForUpdate within a transaction,
     * and protected by a bounded retry loop against collision.
     *
     * @param string $type
     * @param int|null $customOffset Optional offset (e.g. for bulk generation)
     * @param int|null $storeId Optional store_id (defaults to current_store_id() or 1)
     * @return string
     */
    public static function generate(string $type, ?int $customOffset = null, ?int $storeId = null): string
    {
        $storeId = $storeId ?? (function_exists('current_store_id') ? current_store_id() : null);
        if (!$storeId) {
            $storeId = auth()->check() && !empty(auth()->user()->store_id) ? (int) auth()->user()->store_id : 1;
        }

        $store = function_exists('store_settings') ? store_settings(false, $storeId) : null;

        return DB::transaction(function () use ($type, $customOffset, $storeId, $store) {
            // Touch store lock to guarantee store-level concurrency serialization
            // even when the entity table is currently empty for this store.
            if ($storeId > 0) {
                DbStore::where('id', $storeId)->lockForUpdate()->first();
            }

            switch ($type) {
                case 'sale':
                case 'sales':
                    $prefix = !empty($store->sales_init) ? $store->sales_init : 'SA';
                    return self::generateSequential(
                        DbSale::class,
                        'sales_code',
                        $storeId,
                        $customOffset,
                        fn($nextId) => rtrim($prefix, '-') . '-' . str_pad($nextId, 5, '0', STR_PAD_LEFT)
                    );

                case 'purchase':
                    $prefix = !empty($store->purchase_init) ? $store->purchase_init : 'PU';
                    return self::generateSequential(
                        DbPurchase::class,
                        'purchase_code',
                        $storeId,
                        $customOffset,
                        fn($nextId) => rtrim($prefix, '-') . '-' . str_pad($nextId, 5, '0', STR_PAD_LEFT)
                    );

                case 'quotation':
                    $prefix = !empty($store->quotation_init) ? $store->quotation_init : 'QU';
                    return self::generateSequential(
                        DbQuotation::class,
                        'quotation_code',
                        $storeId,
                        $customOffset,
                        fn($nextId) => rtrim($prefix, '-') . '-' . str_pad($nextId, 5, '0', STR_PAD_LEFT)
                    );

                case 'item':
                    $prefix = !empty($store->item_init) ? $store->item_init : 'IT';
                    return self::generateSequential(
                        DbItem::class,
                        'item_code',
                        $storeId,
                        $customOffset,
                        fn($nextId) => rtrim($prefix, '-') . '-' . str_pad($nextId, 5, '0', STR_PAD_LEFT)
                    );

                case 'customer':
                    $prefix = !empty($store->customer_init) ? $store->customer_init : 'CU';
                    return self::generateSequential(
                        DbCustomer::class,
                        'customer_code',
                        $storeId,
                        $customOffset,
                        fn($nextId) => rtrim($prefix, '-') . '-' . str_pad($nextId, 6, '0', STR_PAD_LEFT)
                    );

                case 'supplier':
                    $prefix = !empty($store->supplier_init) ? $store->supplier_init : 'SUP';
                    return self::generateSequential(
                        DbSupplier::class,
                        'supplier_code',
                        $storeId,
                        $customOffset,
                        fn($nextId) => rtrim($prefix, '-') . '-' . str_pad($nextId, 6, '0', STR_PAD_LEFT)
                    );

                case 'expense':
                    $prefix = !empty($store->expense_init) ? $store->expense_init : 'EXP';
                    $sep = str_ends_with($prefix, '-') ? '' : '';
                    return self::generateSequential(
                        DbExpense::class,
                        'expense_code',
                        $storeId,
                        $customOffset,
                        fn($nextId) => $prefix . $sep . str_pad($nextId, 4, '0', STR_PAD_LEFT)
                    );

                case 'account':
                case 'accounts':
                    $prefix = !empty($store->accounts_init) ? $store->accounts_init : 'AC';
                    return self::generateSequential(
                        AcAccount::class,
                        'account_code',
                        $storeId,
                        $customOffset,
                        fn($nextId) => $prefix . str_pad($nextId, 4, '0', STR_PAD_LEFT)
                    );

                case 'money_transfer':
                case 'transfer':
                    $prefix = !empty($store->money_transfer_init) ? $store->money_transfer_init : 'TR';
                    return self::generateSequential(
                        AcMoneyTransfer::class,
                        'transfer_code',
                        $storeId,
                        $customOffset,
                        fn($nextId) => rtrim($prefix, '-') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT)
                    );

                case 'customer_advance':
                case 'cust_advance':
                case 'advance':
                    $prefix = !empty($store->cust_advance_init) ? $store->cust_advance_init : 'AD';
                    return self::generateSequential(
                        DbCustAdvance::class,
                        'payment_code',
                        $storeId,
                        $customOffset,
                        fn($nextId) => $prefix . str_pad($nextId, 4, '0', STR_PAD_LEFT)
                    );

                case 'sales_return':
                    // Phase 2: Fixed from raw timestamp to store-scoped lock+sequence pattern
                    $prefix = !empty($store->sales_return_init) ? $store->sales_return_init : 'RTN';
                    return self::generateSequential(
                        DbSalesReturn::class,
                        'return_code',
                        $storeId,
                        $customOffset,
                        fn($nextId) => rtrim($prefix, '-') . '-' . str_pad($nextId, 5, '0', STR_PAD_LEFT)
                    );

                case 'purchase_return':
                    $prefix = !empty($store->purchase_return_init) ? $store->purchase_return_init : 'PR';
                    for ($attempt = 0; $attempt < 10; $attempt++) {
                        $code = rtrim($prefix, '-') . '-' . strtoupper(uniqid());
                        $exists = DbPurchaseReturn::withoutGlobalScopes()
                            ->where('store_id', $storeId)
                            ->where('return_code', $code)
                            ->exists();
                        if (!$exists) {
                            return $code;
                        }
                    }
                    return $code;

                case 'reconciliation':
                case 'cash_reconciliation':
                    return self::generateSequential(
                        CashDrawerReconciliation::class,
                        'reconciliation_code',
                        $storeId,
                        $customOffset,
                        fn($nextId) => 'REC-' . str_pad($nextId, 5, '0', STR_PAD_LEFT)
                    );

                default:
                    throw new \InvalidArgumentException("Unknown code generation type: {$type}");
            }
        });
    }

    /**
     * Compute sequential code with store-scoping, pessimistic locking, and retry against collision.
     *
     * @param class-string $modelClass
     * @param string $codeColumn
     * @param int $storeId
     * @param int|null $customOffset
     * @param callable $formatter fn(int $nextId): string
     * @return string
     */
    private static function generateSequential(
        string $modelClass,
        string $codeColumn,
        int $storeId,
        ?int $customOffset,
        callable $formatter
    ): string {
        // 1. Lock the latest row for this store and read max id
        $maxId = $modelClass::withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('id') ?? 0;

        $offset = $customOffset ?? 1;
        $maxAttempts = 10;

        // 2. Bounded retry loop against collisions
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $nextId = $maxId + $offset;
            $code = $formatter($nextId);

            $exists = $modelClass::withoutGlobalScopes()
                ->where('store_id', $storeId)
                ->where($codeColumn, $code)
                ->lockForUpdate()
                ->exists();

            if (!$exists) {
                return $code;
            }

            $offset++;
        }

        return $formatter($maxId + $offset);
    }

    /**
     * Execute an operation (e.g. model save) with automatic retry on unique constraint violations.
     * Catches SQLSTATE 23000 (MySQL 1062, SQLite 19 / UNIQUE constraint failed).
     *
     * @param callable $operation function(int $attempt): mixed
     * @param int $maxRetries
     * @return mixed
     */
    public static function executeWithRetry(callable $operation, int $maxRetries = 5)
    {
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            try {
                return $operation($attempt);
            } catch (\Illuminate\Database\QueryException $e) {
                $driverCode = $e->errorInfo[1] ?? null;
                $sqlState = $e->errorInfo[0] ?? null;
                $isRetryable =
                    $driverCode === 1062 ||
                    $driverCode === 19 ||
                    $driverCode === 1213 ||
                    $driverCode === 1205 ||
                    $driverCode === 5 ||
                    $sqlState === '23000' ||
                    $sqlState === '40001' ||
                    str_contains(strtolower($e->getMessage()), 'unique') ||
                    str_contains(strtolower($e->getMessage()), 'deadlock') ||
                    str_contains(strtolower($e->getMessage()), 'database is locked');

                if ($isRetryable && $attempt < $maxRetries - 1) {
                    usleep(10000 * ($attempt + 1));
                    continue;
                }

                throw $e;
            }
        }
    }
}
