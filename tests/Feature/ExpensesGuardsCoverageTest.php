<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\DbExpense;
use App\Models\DbExpenseCategory;
use App\Models\DbPaymentType;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAP 1 coverage — permission gates + store scoping on EVERY Expense module route
 * other than destroy() (which was already covered by ExpensesRolloutTest).
 *
 * Each item asserts the 403/permission gate, the store-scoping (404/untouched row),
 * and a permissioned same-store control case so a guard test cannot pass because
 * "everything is broken".
 */
class ExpensesGuardsCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function store(int $id = 1, string $name = 'Guard Store'): DbStore
    {
        return DbStore::firstOrCreate(['id' => $id], [
            'store_name' => $name,
            'status' => 1,
            'mobile' => '0179' . str_pad((string) $id, 8, '0', STR_PAD_LEFT),
        ]);
    }

    protected function makeUser(int $storeId = 1, array $permissions = []): User
    {
        $this->store($storeId);

        // ALWAYS seed the Super Admin role first (id=1) so a Limited role never
        // receives id=1 and accidentally passes isSuperAdmin(). The flag is now
        // authoritative, so it must be set explicitly here.
        // Seed with the store scope bypassed: DbRole is now StoreScoped, and this
        // helper may be called while acting as a different store's user, which would
        // otherwise hide the existing store-1 role and trip the per-store unique.
        DbRole::allStores()->firstOrCreate(
            ['id' => 1],
            ['store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1, 'is_super_admin' => true]
        );
        DbPermission::allStores()->firstOrCreate(['role_id' => 1], ['store_id' => 1, 'permissions' => []]);

        if (empty($permissions)) {
            return User::factory()->create(['store_id' => $storeId, 'role_id' => 1, 'role_name' => 'Super Admin']);
        }

        $role = DbRole::create(['store_id' => 1, 'role_name' => 'Limited-' . uniqid(), 'status' => 1]);
        DbPermission::create(['role_id' => $role->id, 'store_id' => 1, 'permissions' => $permissions]);
        return User::factory()->create(['store_id' => $storeId, 'role_id' => $role->id, 'role_name' => $role->role_name]);
    }

    protected function makeCategory(int $storeId = 1, ?string $name = null): DbExpenseCategory
    {
        return DbExpenseCategory::create([
            'store_id' => $storeId,
            'category_name' => $name ?? 'GuardCat-' . uniqid(),
            'status' => 1,
        ]);
    }

    protected function makeAccount(int $storeId = 1, float $balance = 1000.00): AcAccount
    {
        return AcAccount::create([
            'store_id' => $storeId,
            'account_name' => 'GuardAcc-' . uniqid(),
            'account_code' => 'GACC-' . uniqid(),
            'balance' => $balance,
            'status' => 1,
            'delete_bit' => 0,
        ]);
    }

    protected function seedPaymentTypes(): void
    {
        foreach (['CASH', 'Bkash', 'BANK TRANSFER'] as $pt) {
            DbPaymentType::firstOrCreate(['payment_type' => $pt], ['store_id' => 1, 'status' => 1]);
        }
    }

    protected function makeExpense(array $overrides = []): DbExpense
    {
        $defaults = [
            'store_id' => 1,
            'expense_code' => 'EXP-' . uniqid(),
            'expense_date' => now()->format('Y-m-d'),
            'category_id' => $this->makeCategory(1)->id,
            'expense_for' => 'GuardExp-' . uniqid(),
            'expense_amt' => 100.00,
            'payment_type' => 'Cash',
            'account_id' => null,
            'created_by' => 1,
            'created_date' => now()->format('Y-m-d'),
            'created_time' => now()->format('H:i:s'),
            'status' => 1,
            'delete_bit' => 0,
            'ledger_version' => 0,
        ];
        return DbExpense::create(array_merge($defaults, $overrides));
    }

    // ── ITEM 1: no expense_add → 403 on POST store, row NOT created ──

    public function test_gap1_item1_no_expense_add_gets_403_on_store_and_no_row(): void
    {
        $this->seedPaymentTypes();
        $noAdd = $this->makeUser(1, ['expense_view']);
        $category = $this->makeCategory(1);

        $response = $this->actingAs($noAdd)->post(route('expenses.store'), [
            'expense_date' => now()->format('Y-m-d'),
            'category_id' => $category->id,
            'expense_for' => 'ShouldNotExist',
            'expense_amt' => 10.00,
            'payment_type' => 'Cash',
            'account_id' => null,
        ]);
        $response->assertForbidden();
        $this->assertSame(0, DbExpense::where('expense_for', 'ShouldNotExist')->count(), 'No row may be created by a user without expense_add.');

        // Control: permissioned same-store user can store.
        $this->actingAs($this->makeUser(1, ['expense_add', 'expense_view']))->post(route('expenses.store'), [
            'expense_date' => now()->format('Y-m-d'),
            'category_id' => $category->id,
            'expense_for' => 'ControlStored',
            'expense_amt' => 15.00,
            'payment_type' => 'Cash',
            'account_id' => null,
        ])->assertRedirect(route('expenses.list'));
        $this->assertSame(1, DbExpense::where('expense_for', 'ControlStored')->count());
    }

    // ── ITEM 2: no expense_edit → 403 on GET edit + POST update, row unchanged ──

    public function test_gap1_item2_no_expense_edit_gets_403_on_edit_and_update_row_unchanged(): void
    {
        $this->seedPaymentTypes();
        $acc = $this->makeAccount(1, 1000.00);
        $category = $this->makeCategory(1);
        $expense = $this->makeExpense(['store_id' => 1, 'account_id' => $acc->id, 'category_id' => $category->id, 'expense_amt' => 30.00, 'expense_for' => 'EditTarget']);

        $noEdit = $this->makeUser(1, ['expense_view', 'expense_add']);

        $this->actingAs($noEdit)->get(route('expenses.edit', $expense->id))->assertForbidden();

        $this->actingAs($noEdit)->post(route('expenses.update', $expense->id), [
            'expense_date' => $expense->expense_date,
            'category_id' => $category->id,
            'expense_for' => 'EditTarget',
            'expense_amt' => 999.00,
            'payment_type' => 'Cash',
            'account_id' => $acc->id,
        ])->assertForbidden();

        $expense->refresh();
        $this->assertSame(30.0, (float) $expense->expense_amt, 'Row must be unchanged after forbidden update.');
        $this->assertSame(0, (int) $expense->delete_bit);

        // Control: permissioned same-store user can edit.
        $this->actingAs($this->makeUser(1, ['expense_view', 'expense_edit']))->post(route('expenses.update', $expense->id), [
            'expense_date' => $expense->expense_date,
            'category_id' => $category->id,
            'expense_for' => 'EditTarget',
            'expense_amt' => 45.00,
            'payment_type' => 'Cash',
            'account_id' => $acc->id,
        ])->assertRedirect(route('expenses.list'));
        $this->assertSame(45.0, (float) $expense->refresh()->expense_amt);
    }

    // ── ITEM 3: no expense_view → 403 on index + GET create ──

    public function test_gap1_item3_no_expense_view_gets_403_on_index_and_create(): void
    {
        // User with NO expense permissions at all → 403 on index (expense_view) AND create (expense_add).
        $noPerms = $this->makeUser(1, ['sales_view']);
        $this->actingAs($noPerms)->get(route('expenses.list'))->assertForbidden();
        $this->actingAs($noPerms)->get(route('expenses.add'))->assertForbidden();

        // A user WITH expense_view but WITHOUT expense_add: index OK, create still 403.
        $viewOnly = $this->makeUser(1, ['expense_view']);
        $this->actingAs($viewOnly)->get(route('expenses.list'))->assertOk();
        $this->actingAs($viewOnly)->get(route('expenses.add'))->assertForbidden();

        // Control: permissioned same-store user can open both.
        $this->actingAs($this->makeUser(1, ['expense_view', 'expense_add']))->get(route('expenses.list'))->assertOk();
        $this->actingAs($this->makeUser(1, ['expense_view', 'expense_add']))->get(route('expenses.add'))->assertOk();
    }

    // ── ITEM 4: Store-B cannot edit (GET/POST) a Store-A expense; row unchanged ──

    public function test_gap1_item4_store_b_cannot_edit_store_a_expense(): void
    {
        $this->store(1, 'Store A');
        $this->store(2, 'Store B');
        $this->seedPaymentTypes();
        $acc = $this->makeAccount(1, 1000.00);
        $category = $this->makeCategory(1);
        $expense = $this->makeExpense(['store_id' => 1, 'account_id' => $acc->id, 'category_id' => $category->id, 'expense_amt' => 50.00, 'expense_for' => 'CrossStoreEditTarget']);

        $userB = $this->makeUser(2);

        $this->actingAs($userB)->get(route('expenses.edit', $expense->id))->assertNotFound();

        $this->actingAs($userB)->post(route('expenses.update', $expense->id), [
            'expense_date' => $expense->expense_date,
            'category_id' => $category->id,
            'expense_for' => 'CrossStoreEditTarget',
            'expense_amt' => 777.00,
            'payment_type' => 'Cash',
            'account_id' => $acc->id,
        ])->assertNotFound();

        $expense->refresh();
        $this->assertSame(50.0, (float) $expense->expense_amt, 'Store-A row must be unchanged after Store-B attempt.');
        $this->assertSame(0, (int) $expense->delete_bit);

        // Control: Store-A user can edit it.
        $this->actingAs($this->makeUser(1))->post(route('expenses.update', $expense->id), [
            'expense_date' => $expense->expense_date,
            'category_id' => $category->id,
            'expense_for' => 'CrossStoreEditTarget',
            'expense_amt' => 60.00,
            'payment_type' => 'Cash',
            'account_id' => $acc->id,
        ])->assertRedirect(route('expenses.list'));
        $this->assertSame(60.0, (float) $expense->refresh()->expense_amt);
    }

    // ── ITEM 5: ExpenseCategoryController — all six methods ──

    public function test_gap1_item5a_category_index_and_create_require_permission_and_are_store_scoped(): void
    {
        $this->store(1);
        $this->store(2);
        $catA = $this->makeCategory(1, 'CatA-Only');

        $noView = $this->makeUser(1, ['expense_view']);
        $this->actingAs($noView)->get(route('expenses.categories'))->assertForbidden();
        $this->actingAs($noView)->get(route('expenses.categories.add'))->assertForbidden();

        $userB = $this->makeUser(2);
        $resB = $this->actingAs($userB)->get(route('expenses.categories'));
        $resB->assertOk();
        $resB->assertDontSee('CatA-Only');

        $resA = $this->actingAs($this->makeUser(1, ['expense_category_view']))->get(route('expenses.categories'));
        $resA->assertOk();
        $resA->assertSee('CatA-Only');
    }

    public function test_gap1_item5b_category_store_requires_permission_and_is_store_scoped(): void
    {
        $noAdd = $this->makeUser(1, ['expense_category_view']);
        $this->actingAs($noAdd)->post(route('expenses.categories.store'), [
            'category_name' => 'ShouldNotCreate',
            'status' => 1,
        ])->assertForbidden();
        $this->assertSame(0, DbExpenseCategory::where('category_name', 'ShouldNotCreate')->count());

        $this->actingAs($this->makeUser(1, ['expense_category_add']))->post(route('expenses.categories.store'), [
            'category_name' => 'ControlCategory',
            'status' => 1,
        ])->assertRedirect(route('expenses.categories'));
        $this->assertSame(1, DbExpenseCategory::where('category_name', 'ControlCategory')->count());
    }

    public function test_gap1_item5c_category_edit_update_require_permission_and_store_scope(): void
    {
        $this->store(1, 'Store A');
        $this->store(2, 'Store B');
        $catA = $this->makeCategory(1, 'CatA-EditTarget');

        $noEdit = $this->makeUser(1, ['expense_category_view']);
        $this->actingAs($noEdit)->get(route('expenses.categories.edit', $catA->id))->assertForbidden();
        $this->actingAs($noEdit)->post(route('expenses.categories.update', $catA->id), [
            'category_name' => 'Hacked',
            'status' => 1,
        ])->assertForbidden();
        $this->assertSame('CatA-EditTarget', $catA->refresh()->category_name);

        $userB = $this->makeUser(2);
        // GET edit 404s (store-scoped findOrFail outside try/catch).
        $this->actingAs($userB)->get(route('expenses.categories.edit', $catA->id))->assertNotFound();
        // POST update: store-scoped findOrFail inside try/catch → redirect back with error.
        $this->actingAs($userB)->post(route('expenses.categories.update', $catA->id), [
            'category_name' => 'HackedFromB',
            'status' => 1,
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertSame('CatA-EditTarget', $catA->refresh()->category_name);

        $this->actingAs($this->makeUser(1, ['expense_category_edit', 'expense_category_view']))->post(route('expenses.categories.update', $catA->id), [
            'category_name' => 'CatA-Edited',
            'status' => 1,
        ])->assertRedirect(route('expenses.categories'));
        $this->assertSame('CatA-Edited', $catA->refresh()->category_name);
    }

    public function test_gap1_item5d_category_destroy_requires_permission_and_store_scope(): void
    {
        $this->store(1, 'Store A');
        $this->store(2, 'Store B');
        $catA = $this->makeCategory(1, 'CatA-DeleteTarget');

        $noDel = $this->makeUser(1, ['expense_category_view']);
        $this->actingAs($noDel)->delete(route('expenses.categories.delete', $catA->id))->assertForbidden();
        $this->assertTrue(DbExpenseCategory::where('id', $catA->id)->exists());

        $userB = $this->makeUser(2);
        // Store-scoped findOrFail inside try/catch → redirect back with error (category untouched).
        $this->actingAs($userB)->delete(route('expenses.categories.delete', $catA->id))->assertRedirect()->assertSessionHas('error');
        $this->assertTrue(DbExpenseCategory::allStores()->where('id', $catA->id)->exists(), 'Store-A category must be untouched by Store-B delete.');

        $this->actingAs($this->makeUser(1, ['expense_category_delete']))->delete(route('expenses.categories.delete', $catA->id))->assertSessionHas('success');
        $this->assertFalse(DbExpenseCategory::where('id', $catA->id)->exists());
    }
}
