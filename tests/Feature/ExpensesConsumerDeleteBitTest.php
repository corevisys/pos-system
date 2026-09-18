<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\DbExpense;
use App\Models\DbExpenseCategory;
use App\Models\DbPaymentType;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbSale;
use App\Models\DbSaleItem;
use App\Models\DbStore;
use App\Models\DbCustomer;
use App\Models\DbWarehouse;
use App\Models\DbItem;
use App\Models\DbCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAP 2 coverage — proves the delete_bit=0 filter actually works on the 5
 * non-reconciliation db_expense consumers by creating an expense, asserting it IS
 * counted, then soft-deleting it via the normal destroy() flow and asserting it is
 * now EXCLUDED with an exact expected value (not just "still loads").
 */
class ExpensesConsumerDeleteBitTest extends TestCase
{
    use RefreshDatabase;

    protected function store(int $id = 1, string $name = 'Consumer Store'): DbStore
    {
        return DbStore::firstOrCreate(['id' => $id], [
            'store_name' => $name,
            'status' => 1,
            'mobile' => '0178' . str_pad((string) $id, 8, '0', STR_PAD_LEFT),
        ]);
    }

    protected function makeSuperAdmin(int $storeId = 1): User
    {
        $this->store($storeId);
        $role = DbRole::firstOrCreate(['id' => 1], ['store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1]);
        DbPermission::firstOrCreate(['role_id' => 1], ['store_id' => 1, 'permissions' => []]);
        return User::factory()->create(['store_id' => $storeId, 'role_id' => 1, 'role_name' => 'Super Admin']);
    }

    protected function seedPaymentTypes(): void
    {
        foreach (['CASH', 'Bkash'] as $pt) {
            DbPaymentType::firstOrCreate(['payment_type' => $pt], ['store_id' => 1, 'status' => 1]);
        }
    }

    protected function makeCategory(): DbExpenseCategory
    {
        return DbExpenseCategory::create(['store_id' => 1, 'category_name' => 'ConsumerCat-' . uniqid(), 'status' => 1]);
    }

    protected function makeAccount(float $balance = 1000.00): AcAccount
    {
        return AcAccount::create([
            'store_id' => 1,
            'account_name' => 'ConsumerAcc-' . uniqid(),
            'account_code' => 'CACC-' . uniqid(),
            'balance' => $balance,
            'status' => 1,
            'delete_bit' => 0,
        ]);
    }

    protected function createExpenseViaRoute(User $user, float $amount, string $for, string $date): DbExpense
    {
        $acc = $this->makeAccount(1000.00);
        $cat = $this->makeCategory();

        $this->actingAs($user)->post(route('expenses.store'), [
            'expense_date' => $date,
            'category_id' => $cat->id,
            'expense_for' => $for,
            'expense_amt' => $amount,
            'payment_type' => 'Cash',
            'account_id' => $acc->id,
        ])->assertRedirect(route('expenses.list'));

        return DbExpense::where('expense_for', $for)->first();
    }

    protected function softDeleteViaRoute(User $user, DbExpense $expense): void
    {
        $this->actingAs($user)->delete(route('expenses.delete', $expense->id))->assertRedirect(route('expenses.list'));
        $expense->refresh();
        $this->assertSame(1, (int) $expense->delete_bit, 'Precondition: expense must be soft-deleted (delete_bit=1).');
    }

    // ── CONSUMER 1: P&L profit-loss report ──

    public function test_gap2_consumer1_profit_loss_excludes_soft_deleted_expense(): void
    {
        $user = $this->makeSuperAdmin(1);
        $this->seedPaymentTypes();
        $date = now()->format('Y-m-d');

        $expense = $this->createExpenseViaRoute($user, 100.00, 'PL-Expense', $date);

        $res = $this->actingAs($user)->get(route('reports.profit_loss_data', ['start_date' => $date, 'end_date' => $date]));
        $res->assertOk();
        $this->assertSame(100.0, (float) $res->json('data.expenses.total'), 'P&L must count the live expense ($100).');

        $this->softDeleteViaRoute($user, $expense);

        $res2 = $this->actingAs($user)->get(route('reports.profit_loss_data', ['start_date' => $date, 'end_date' => $date]));
        $res2->assertOk();
        $this->assertSame(0.0, (float) $res2->json('data.expenses.total'), 'P&L must drop by exactly $100 after soft-delete.');
    }

    // ── CONSUMER 2: Expense report ──

    public function test_gap2_consumer2_expense_report_excludes_soft_deleted_expense(): void
    {
        $user = $this->makeSuperAdmin(1);
        $this->seedPaymentTypes();
        $date = now()->format('Y-m-d');

        $expense = $this->createExpenseViaRoute($user, 75.00, 'ER-Expense', $date);

        $res = $this->actingAs($user)->get(route('reports.expense_data', ['start_date' => $date, 'end_date' => $date, 'category_id' => 'all']));
        $res->assertOk();
        $this->assertCount(1, $res->json('records'), 'Expense report must include the live expense.');
        $this->assertSame('75.00', $res->json('records.0.amount'), 'Live expense amount in report = 75.00.');

        $this->softDeleteViaRoute($user, $expense);

        $res2 = $this->actingAs($user)->get(route('reports.expense_data', ['start_date' => $date, 'end_date' => $date, 'category_id' => 'all']));
        $res2->assertOk();
        $this->assertCount(0, $res2->json('records'), 'Expense report must exclude the soft-deleted expense (0 records).');
    }

    // ── CONSUMER 3: Cash-flow report ──

    public function test_gap2_consumer3_cash_flow_excludes_soft_deleted_expense(): void
    {
        $user = $this->makeSuperAdmin(1);
        $this->seedPaymentTypes();
        $date = now()->format('Y-m-d');

        $expense = $this->createExpenseViaRoute($user, 200.00, 'CF-Expense', $date);

        $res = $this->actingAs($user)->get(route('reports.cash_flow_data', ['start_date' => $date, 'end_date' => $date]));
        $res->assertOk();
        // The db_expense-based consumer for cash-flow is by_expense_category
        // (ReportController.php:2393) — NOT operating_activities.expenses (which
        // derives from ac_transactions and intentionally retains the original
        // EXPENSE row under non-destructive soft-delete).
        $byCat = $res->json('operating_activities.by_expense_category') ?? [];
        $this->assertNotEmpty($byCat, 'Cash-flow by_expense_category must include the live expense.');
        $this->assertSame(200.0, (float) ($byCat[0]['amount'] ?? 0), 'Cash-flow category breakdown must total $200 for the live expense.');

        $this->softDeleteViaRoute($user, $expense);

        $res2 = $this->actingAs($user)->get(route('reports.cash_flow_data', ['start_date' => $date, 'end_date' => $date]));
        $res2->assertOk();
        $byCat2 = $res2->json('operating_activities.by_expense_category') ?? [];
        $this->assertSame([], $byCat2, 'Cash-flow category breakdown must be empty after soft-delete (delete_bit=0 filter).');
    }

    // ── CONSUMER 4: Dashboard today net profit (expense-driven) ──

    public function test_gap2_consumer4_dashboard_net_profit_excludes_soft_deleted_expense(): void
    {
        $user = $this->makeSuperAdmin(1);
        $this->seedPaymentTypes();
        $today = now()->format('Y-m-d');

        $customer = DbCustomer::create(['store_id' => 1, 'customer_name' => 'C1', 'customer_code' => 'CUST-C1', 'status' => 1]);
        $warehouse = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'W1', 'status' => 1]);
        $cat = DbCategory::create(['store_id' => 1, 'category_name' => 'ItemCat', 'status' => 1]);
        $item = DbItem::create([
            'store_id' => 1, 'item_name' => 'ZeroCOGS', 'item_code' => 'ZC-1',
            'category_id' => $cat->id, 'purchase_price' => 0, 'sales_price' => 500,
            'stock' => 100, 'status' => 1,
        ]);
        $sale = DbSale::create([
            'store_id' => 1, 'warehouse_id' => $warehouse->id, 'customer_id' => $customer->id,
            'sales_code' => 'SA-C1', 'sales_date' => $today, 'grand_total' => 500,
            'paid_amount' => 500, 'payment_status' => 'Paid', 'status' => 1,
            'sales_status' => 'Final',
        ]);
        // NOTE: Dashboard todayRevenue = SUM(db_salesitems.total_cost)
        // (DashboardController.php:59) — so total_cost must be 500 for revenue 500.
        DbSaleItem::create([
            'store_id' => 1, 'sales_id' => $sale->id, 'item_id' => $item->id,
            'sales_qty' => 1, 'sales_price' => 500, 'purchase_price' => 0,
            'total_cost' => 500, 'tax_amt' => 0,
        ]);

        $expense = $this->createExpenseViaRoute($user, 100.00, 'Dash-Expense', $today);

        // Dashboard today net profit = revenue(500) - expenses(100) = 400.
        $res = $this->actingAs($user)->get(route('dashboard'));
        $res->assertOk();
        assert_compact_amount($res, 400.00);

        $this->softDeleteViaRoute($user, $expense);

        // After soft-delete: net profit = 500 - 0 = 500.
        $res2 = $this->actingAs($user)->get(route('dashboard'));
        $res2->assertOk();
        assert_compact_amount($res2, 500.00);
        assert_compact_amount_absent($res2, 400.00);
    }

    // ── CONSUMER 5: Global search ──

    public function test_gap2_consumer5_global_search_excludes_soft_deleted_expense(): void
    {
        $user = $this->makeSuperAdmin(1);
        $this->seedPaymentTypes();
        $date = now()->format('Y-m-d');

        $expense = $this->createExpenseViaRoute($user, 55.00, 'GS-Expense-Unique', $date);

        $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'GS-Expense-Unique']));
        $res->assertOk();
        $this->assertSame('Expenses', $res->json('categories.expenses.label'), 'Global search must surface the live expense category.');
        $this->assertNotEmpty($res->json('categories.expenses.items'), 'Global search must include the live expense.');

        $this->softDeleteViaRoute($user, $expense);

        $res2 = $this->actingAs($user)->getJson(route('global.search', ['q' => 'GS-Expense-Unique']));
        $res2->assertOk();
        $this->assertArrayNotHasKey('expenses', $res2->json('categories'), 'Global search must not return soft-deleted expenses (expenses category absent).');
    }
}
