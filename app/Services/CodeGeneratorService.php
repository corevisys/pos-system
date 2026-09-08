<?php

namespace App\Services;

use App\Models\AcAccount;
use App\Models\AcMoneyTransfer;
use App\Models\DbCustAdvance;
use App\Models\DbCustomer;
use App\Models\DbExpense;
use App\Models\DbItem;
use App\Models\DbPurchase;
use App\Models\DbPurchaseReturn;
use App\Models\DbQuotation;
use App\Models\DbSale;
use App\Models\DbSalesReturn;
use App\Models\DbSupplier;

class CodeGeneratorService
{
    /**
     * Generate code based on entity type and store settings configuration.
     *
     * @param string $type
     * @param int|null $customOffset Optional offset (e.g. for bulk generation)
     * @return string
     */
    public static function generate(string $type, ?int $customOffset = null): string
    {
        $store = function_exists('store_settings') ? store_settings() : null;

        switch ($type) {
            case 'sale':
            case 'sales':
                $prefix = !empty($store->sales_init) ? $store->sales_init : 'SA';
                $nextId = (DbSale::max('id') ?? 0) + ($customOffset ?? 1);
                return rtrim($prefix, '-') . '-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);

            case 'purchase':
                $prefix = !empty($store->purchase_init) ? $store->purchase_init : 'PU';
                $nextId = (DbPurchase::max('id') ?? 0) + ($customOffset ?? 1);
                return rtrim($prefix, '-') . '-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);

            case 'quotation':
                $prefix = !empty($store->quotation_init) ? $store->quotation_init : 'QU';
                $nextId = (DbQuotation::max('id') ?? 0) + ($customOffset ?? 1);
                return rtrim($prefix, '-') . '-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);

            case 'item':
                $prefix = !empty($store->item_init) ? $store->item_init : 'IT';
                $nextId = (DbItem::max('id') ?? 0) + ($customOffset ?? 1);
                return rtrim($prefix, '-') . '-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);

            case 'customer':
                $prefix = !empty($store->customer_init) ? $store->customer_init : 'CU';
                $nextId = (DbCustomer::max('id') ?? 0) + ($customOffset ?? 1);
                return rtrim($prefix, '-') . '-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);

            case 'supplier':
                $prefix = !empty($store->supplier_init) ? $store->supplier_init : 'SUP';
                $nextId = (DbSupplier::max('id') ?? 0) + ($customOffset ?? 1);
                return rtrim($prefix, '-') . '-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);

            case 'expense':
                $prefix = !empty($store->expense_init) ? $store->expense_init : 'EXP';
                $nextId = (DbExpense::max('id') ?? 0) + ($customOffset ?? 1);
                // Preserve existing EXP0001 (unseparated unless prefix has hyphen)
                $sep = str_ends_with($prefix, '-') ? '' : '';
                return $prefix . $sep . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            case 'account':
            case 'accounts':
                $prefix = !empty($store->accounts_init) ? $store->accounts_init : 'AC';
                $nextId = (AcAccount::max('id') ?? 0) + ($customOffset ?? 1);
                return $prefix . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            case 'money_transfer':
            case 'transfer':
                $prefix = !empty($store->money_transfer_init) ? $store->money_transfer_init : 'TR';
                $nextId = (AcMoneyTransfer::max('id') ?? 0) + ($customOffset ?? 1);
                return rtrim($prefix, '-') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            case 'customer_advance':
            case 'cust_advance':
            case 'advance':
                $prefix = !empty($store->cust_advance_init) ? $store->cust_advance_init : 'AD';
                $nextId = (DbCustAdvance::max('id') ?? 0) + ($customOffset ?? 1);
                return $prefix . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            case 'sales_return':
                $prefix = !empty($store->sales_return_init) ? $store->sales_return_init : 'RTN';
                return rtrim($prefix, '-') . '-' . date('YmdHis');

            case 'purchase_return':
                $prefix = !empty($store->purchase_return_init) ? $store->purchase_return_init : 'PR';
                return rtrim($prefix, '-') . '-' . strtoupper(uniqid());

            default:
                throw new \InvalidArgumentException("Unknown code generation type: {$type}");
        }
    }
}
