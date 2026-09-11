<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Map of table name => [code_column, unique_constraint_name, legacy_index_name]
     */
    private array $targets = [
        'db_sales' => [
            'column' => 'sales_code',
            'unique' => 'db_sales_store_sales_code_unique',
            'legacy_index' => 'db_sales_sales_code_index',
        ],
        'db_purchase' => [
            'column' => 'purchase_code',
            'unique' => 'db_purchase_store_purchase_code_unique',
            'legacy_index' => 'db_purchase_purchase_code_index',
        ],
        'db_quotation' => [
            'column' => 'quotation_code',
            'unique' => 'db_quotation_store_quotation_code_unique',
            'legacy_index' => 'db_quotation_quotation_code_index',
        ],
        'db_customers' => [
            'column' => 'customer_code',
            'unique' => 'db_customers_store_customer_code_unique',
            'legacy_index' => 'db_customers_customer_code_index',
        ],
        'db_suppliers' => [
            'column' => 'supplier_code',
            'unique' => 'db_suppliers_store_supplier_code_unique',
            'legacy_index' => 'db_suppliers_supplier_code_index',
        ],
        'db_expense' => [
            'column' => 'expense_code',
            'unique' => 'db_expense_store_expense_code_unique',
            'legacy_index' => 'db_expense_expense_code_index',
        ],
        'db_custadvance' => [
            'column' => 'payment_code',
            'unique' => 'db_custadvance_store_payment_code_unique',
            'legacy_index' => 'db_custadvance_payment_code_index',
        ],
        'db_salesreturn' => [
            'column' => 'return_code',
            'unique' => 'db_salesreturn_store_return_code_unique',
            'legacy_index' => 'db_salesreturn_return_code_index',
        ],
        'db_purchasereturn' => [
            'column' => 'return_code',
            'unique' => 'db_purchasereturn_store_return_code_unique',
            'legacy_index' => 'db_purchasereturn_return_code_index',
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Pre-existing per-store duplicate detection — abort loudly rather than
        //    silently corrupting or deduplicating.
        foreach ($this->targets as $table => $cfg) {
            $this->assertNoDuplicates($table, $cfg['column'], $cfg['unique']);
        }

        // 2 + 3. Drop superseded plain single-column index and add composite per-store unique.
        foreach ($this->targets as $table => $cfg) {
            Schema::table($table, function (Blueprint $t) use ($cfg) {
                $this->dropIndexSafe($t, $cfg['legacy_index']);
                $t->unique(['store_id', $cfg['column']], $cfg['unique']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->targets as $table => $cfg) {
            Schema::table($table, function (Blueprint $t) use ($cfg) {
                $this->dropIndexSafe($t, $cfg['unique']);
                $t->index($cfg['column']);
            });
        }
    }

    private function assertNoDuplicates(string $table, string $column, string $constraintName): void
    {
        $duplicates = DB::select(
            "SELECT store_id, {$column}, COUNT(*) as cnt
             FROM {$table}
             WHERE {$column} IS NOT NULL AND {$column} <> ''
             GROUP BY store_id, {$column}
             HAVING COUNT(*) > 1"
        );

        if (!empty($duplicates)) {
            throw new \RuntimeException(
                "Pre-existing duplicate {$table}.{$column} detected per store. Migration stopped before adding unique constraint {$constraintName}: " . json_encode($duplicates)
            );
        }
    }

    private function dropIndexSafe(Blueprint $table, string $indexName): void
    {
        try {
            $table->dropIndex($indexName);
        } catch (\Exception $e) {
            // Ignore if index name differs or not present on SQLite/MySQL
        }
    }
};
