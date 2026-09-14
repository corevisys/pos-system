<?php

namespace Tests\Feature;

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
use App\Models\DbStore;
use App\Models\DbSupplier;
use App\Services\CodeGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodeGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var DbStore */
    private $store;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a fresh store settings row with default prefix values.
        // currency_id and language_id are nullable FKs, so we set them null to
        // avoid needing to seed parent tables in tests.
        $this->store = DbStore::updateOrCreate(['id' => 1], [
            'store_code'              => 'ST001',
            'store_name'              => 'Test Store',
            'sales_init'              => 'SA',
            'purchase_init'           => 'PU',
            'quotation_init'          => 'QU',
            'item_init'               => 'IT',
            'customer_init'           => 'CU',
            'supplier_init'           => 'SUP',
            'expense_init'            => 'EXP',
            'accounts_init'           => 'AC',
            'money_transfer_init'     => 'MT',
            'cust_advance_init'       => 'CA',
            'sales_return_init'       => 'RTN',
            'purchase_return_init'    => 'PR',
            'currency_id'             => null,
            'language_id'             => null,
            'decimals'                => 2,
            'qty_decimals'            => 2,
            'status'                  => 1,
        ]);

        // Bust the store_settings() memoization cache so tests see fresh data
        store_settings(true);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Generate a minimal valid DbSale row */
    private function makeSale(array $overrides = []): DbSale
    {
        return DbSale::create(array_merge([
            'store_id'       => 1,
            'warehouse_id'   => 1,
            'sales_code'     => 'SEED-00001',
            'grand_total'    => 100,
            'subtotal'       => 100,
            'paid_amount'    => 0,
            'payment_status' => 'Unpaid',
            'status'         => 1,
            'sales_date'     => now()->toDateString(),
        ], $overrides));
    }

    // ─── 1. Sale (5-digit sequential) ────────────────────────────────────────

    public function test_sale_default_prefix_empty_table(): void
    {
        $code = CodeGeneratorService::generate('sales');
        $this->assertMatchesRegularExpression('/^SA-\d{5}$/', $code, "Expected SA-XXXXX, got: {$code}");
    }

    public function test_sale_sequential_increments_from_max_id(): void
    {
        $sale = $this->makeSale(['sales_code' => 'SA-00001']);
        $code = CodeGeneratorService::generate('sales');

        $expected = 'SA-' . str_pad($sale->id + 1, 5, '0', STR_PAD_LEFT);
        $this->assertEquals($expected, $code);
    }

    public function test_sale_custom_prefix_changes_code_prefix_only(): void
    {
        $this->store->update(['sales_init' => 'INV']);
        store_settings(true);

        $code = CodeGeneratorService::generate('sales');
        $this->assertStringStartsWith('INV-', $code);
        $this->assertMatchesRegularExpression('/^INV-\d{5}$/', $code);
    }

    // ─── 2. Purchase (5-digit sequential) ────────────────────────────────────

    public function test_purchase_default_prefix_format(): void
    {
        $code = CodeGeneratorService::generate('purchase');
        $this->assertMatchesRegularExpression('/^PU-\d{5}$/', $code);
    }

    public function test_purchase_custom_prefix(): void
    {
        $this->store->update(['purchase_init' => 'PO']);
        store_settings(true);
        $code = CodeGeneratorService::generate('purchase');
        $this->assertStringStartsWith('PO-', $code);
    }

    // ─── 3. Quotation (5-digit sequential) ───────────────────────────────────

    public function test_quotation_default_prefix_format(): void
    {
        $code = CodeGeneratorService::generate('quotation');
        $this->assertMatchesRegularExpression('/^QU-\d{5}$/', $code);
    }

    public function test_quotation_custom_prefix(): void
    {
        $this->store->update(['quotation_init' => 'EST']);
        store_settings(true);
        $code = CodeGeneratorService::generate('quotation');
        $this->assertStringStartsWith('EST-', $code);
    }

    // ─── 4. Item (5-digit sequential) ────────────────────────────────────────

    public function test_item_default_prefix_format(): void
    {
        $code = CodeGeneratorService::generate('item');
        $this->assertMatchesRegularExpression('/^IT-\d{5}$/', $code);
    }

    public function test_item_custom_prefix(): void
    {
        $this->store->update(['item_init' => 'PRD']);
        store_settings(true);
        $code = CodeGeneratorService::generate('item');
        $this->assertStringStartsWith('PRD-', $code);
    }

    // ─── 5. Customer (6-digit sequential) ────────────────────────────────────

    public function test_customer_default_prefix_format(): void
    {
        $code = CodeGeneratorService::generate('customer');
        $this->assertMatchesRegularExpression('/^CU-\d{6}$/', $code, "Expected CU-XXXXXX, got: {$code}");
    }

    public function test_customer_custom_prefix(): void
    {
        $this->store->update(['customer_init' => 'CLI']);
        store_settings(true);
        $code = CodeGeneratorService::generate('customer');
        $this->assertStringStartsWith('CLI-', $code);
        $this->assertMatchesRegularExpression('/^CLI-\d{6}$/', $code);
    }

    // ─── 6. Supplier (6-digit sequential) ────────────────────────────────────

    public function test_supplier_default_prefix_format(): void
    {
        $code = CodeGeneratorService::generate('supplier');
        $this->assertMatchesRegularExpression('/^SUP-\d{6}$/', $code);
    }

    public function test_supplier_custom_prefix(): void
    {
        $this->store->update(['supplier_init' => 'VND']);
        store_settings(true);
        $code = CodeGeneratorService::generate('supplier');
        $this->assertStringStartsWith('VND-', $code);
    }

    // ─── 7. Expense (4-digit, no separator) ──────────────────────────────────

    public function test_expense_default_prefix_format(): void
    {
        $code = CodeGeneratorService::generate('expense');
        // Default prefix is 'EXP', no hyphen → EXP0001
        $this->assertMatchesRegularExpression('/^EXP\d{4}$/', $code, "Expected EXP####, got: {$code}");
    }

    public function test_expense_custom_prefix(): void
    {
        $this->store->update(['expense_init' => 'COST']);
        store_settings(true);
        $code = CodeGeneratorService::generate('expense');
        $this->assertStringStartsWith('COST', $code);
    }

    // ─── 8. Account (4-digit, no separator) ──────────────────────────────────

    public function test_account_default_prefix_format(): void
    {
        $code = CodeGeneratorService::generate('account');
        $this->assertMatchesRegularExpression('/^AC\d{4}$/', $code, "Expected AC####, got: {$code}");
    }

    public function test_account_custom_prefix(): void
    {
        $this->store->update(['accounts_init' => 'BNK']);
        store_settings(true);
        $code = CodeGeneratorService::generate('account');
        $this->assertStringStartsWith('BNK', $code);
    }

    // ─── 9. Money Transfer (4-digit sequential, with separator) ──────────────

    public function test_money_transfer_default_prefix_format(): void
    {
        $code = CodeGeneratorService::generate('money_transfer');
        $this->assertMatchesRegularExpression('/^MT-\d{4}$/', $code, "Expected MT-####, got: {$code}");
    }

    public function test_money_transfer_custom_prefix(): void
    {
        $this->store->update(['money_transfer_init' => 'TRF']);
        store_settings(true);
        $code = CodeGeneratorService::generate('money_transfer');
        $this->assertStringStartsWith('TRF-', $code);
    }

    // ─── 10. Customer Advance (4-digit, no separator) ────────────────────────

    public function test_customer_advance_default_prefix_format(): void
    {
        $code = CodeGeneratorService::generate('customer_advance');
        $this->assertMatchesRegularExpression('/^CA\d{4}$/', $code, "Expected CA####, got: {$code}");
    }

    public function test_customer_advance_custom_prefix(): void
    {
        $this->store->update(['cust_advance_init' => 'ADV']);
        store_settings(true);
        $code = CodeGeneratorService::generate('customer_advance');
        $this->assertStringStartsWith('ADV', $code);
    }

    // ─── 11. Sales Return (5-digit sequential) ──────────────────────────────

    public function test_sales_return_default_prefix_format(): void
    {
        $code = CodeGeneratorService::generate('sales_return');
        // Format: RTN-XXXXX (5-digit sequential per Phase 2)
        $this->assertMatchesRegularExpression('/^RTN-\d{5}$/', $code, "Expected RTN-XXXXX, got: {$code}");
    }

    public function test_sales_return_custom_prefix(): void
    {
        $this->store->update(['sales_return_init' => 'SR']);
        store_settings(true);
        $code = CodeGeneratorService::generate('sales_return');
        $this->assertStringStartsWith('SR-', $code);
        $this->assertMatchesRegularExpression('/^SR-\d{5}$/', $code);
    }

    public function test_sales_return_sequential_increments_with_row(): void
    {
        // Seed a baseline row, then assert generate() returns the NEXT sequential code.
        // Relative (not a hard-coded RTN-00002) because on a shared MySQL test DB the
        // db_salesreturn auto-increment is non-transactional and drifts upward across
        // RefreshDatabase rollbacks, so this test's baseline row is not necessarily id 1.
        $baseline = DbSalesReturn::create([
            'store_id' => 1,
            'return_code' => 'RTN-00001',
            'grand_total' => 10,
            'subtotal' => 10,
            'status' => 1,
        ]);

        $expectedNext = 'RTN-' . str_pad($baseline->id + 1, 5, '0', STR_PAD_LEFT);
        $code = CodeGeneratorService::generate('sales_return');
        $this->assertSame($expectedNext, $code);
    }

    // ─── 12. Purchase Return (Phase 4.3: sequential) ─────────────────────────

    public function test_purchase_return_default_prefix_and_sequential_format(): void
    {
        $code = CodeGeneratorService::generate('purchase_return');
        // Phase 4.3: Format PR-XXXXX (5-digit sequential), not the old uniqid() hex.
        $this->assertStringStartsWith('PR-', $code);
        $this->assertMatchesRegularExpression('/^PR-\d{5}$/', $code, "Expected PR-XXXXX, got: {$code}");
    }

    public function test_purchase_return_custom_prefix(): void
    {
        $this->store->update(['purchase_return_init' => 'RTV']);
        store_settings(true);
        $code = CodeGeneratorService::generate('purchase_return');
        $this->assertStringStartsWith('RTV-', $code);
        $this->assertMatchesRegularExpression('/^RTV-\d{5}$/', $code);
    }

    public function test_purchase_return_sequential_increments_with_row(): void
    {
        // Mirror the sales_return sequential test: seed a baseline row, then assert
        // generate() returns the NEXT sequential code (relative, not an absolute id).
        $baseline = DbPurchaseReturn::create([
            'store_id' => 1,
            'return_code' => 'PR-00001',
            'return_status' => 1,
        ]);

        $expectedNext = 'PR-' . str_pad($baseline->id + 1, 5, '0', STR_PAD_LEFT);
        $code = CodeGeneratorService::generate('purchase_return');
        $this->assertSame($expectedNext, $code);
    }

    // ─── Unknown type throws ─────────────────────────────────────────────────

    public function test_unknown_type_throws_invalid_argument_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CodeGeneratorService::generate('banana');
    }
}
