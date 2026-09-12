<?php

namespace Tests\Feature;

use App\Models\DbCountry;
use App\Models\DbCustomer;
use App\Models\DbItem;
use App\Models\DbPaymentType;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbState;
use App\Models\DbStore;
use App\Models\DbTax;
use App\Models\DbUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Runtime verification for the Settings Modules rollout (G1–G6).
 *
 * Every test prints the concrete observed HTTP status / DB value to STDOUT so the
 * verification report can cite exact evidence rather than a pass/fail label.
 */
class SettingsRolloutRuntimeVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function say(string $line): void
    {
        fwrite(STDOUT, "\n[VERIFY] {$line}\n");
    }

    private function makeStore(int $id, string $name): void
    {
        DbStore::create(['id' => $id, 'store_name' => $name, 'status' => 1, 'mobile' => '1' . $id]);
    }

    /**
     * Create a NON-super-admin user holding the given slugs.
     *
     * is_super_admin is the authoritative global-privilege flag, so these helper
     * roles are created with it explicitly FALSE. A distinct forced role id (50+)
     * is still used for readability, though id no longer confers super powers.
     */
    private function makeRole(int $storeId, array $permissions, int $forcedId): DbRole
    {
        // forceCreate (not create) bypasses DbRole::$fillable, which does NOT list 'id'.
        $role = DbRole::forceCreate([
            'id' => $forcedId,
            'role_name' => 'Role S' . $storeId . ' ' . uniqid(),
            'status' => 1,
            'store_id' => $storeId,
            'is_super_admin' => false,
        ]);
        DbPermission::create([
            'role_id' => $role->id,
            'store_id' => $storeId,
            'permissions' => $permissions,
        ]);

        return $role;
    }

    private function makeUser(int $storeId, array $permissions, int $forcedRoleId = 50): User
    {
        $role = DbRole::create([
            'role_name' => 'Role S' . $storeId . ' ' . uniqid(),
            'status' => 1,
            'store_id' => $storeId,
        ]);
        DbPermission::create([
            'role_id' => $role->id,
            'store_id' => $storeId,
            'permissions' => $permissions,
        ]);

        return User::factory()->create(['store_id' => $storeId, 'role_id' => $role->id]);
    }

    private function makeNonSuperAdminUser(int $storeId, array $permissions, int $forcedRoleId = 50): User
    {
        $role = $this->makeRole($storeId, $permissions, $forcedRoleId);

        return User::factory()->create(['store_id' => $storeId, 'role_id' => $role->id]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeStore(1, 'Store One');
        $this->makeStore(2, 'Store Two');
    }

    // =====================================================================
    // G1 — Cross-store direct-POST rejection (6 requests)
    // =====================================================================
    public function test_g1_cross_store_direct_post_rejection(): void
    {
        // Store-2 user holds ALL the relevant edit/delete slugs, so a rejection
        // can only come from the store-scoping query — not from a permission gate.
        // Forced role id != 1 keeps isSuperAdmin() false so the permission check
        // genuinely passes and the 404 must be produced by the store-scoped query.
        $store2 = $this->makeNonSuperAdminUser(2, [
            'tax_edit', 'tax_delete',
            'units_edit', 'units_delete',
            'payment_types_edit', 'payment_types_delete',
        ]);

        $tax1 = DbTax::create(['tax_name' => 'S1 Tax', 'tax' => 5, 'group_bit' => 0, 'status' => 1, 'store_id' => 1]);
        $unit1 = DbUnit::create(['unit_name' => 'S1 Unit', 'status' => 1, 'store_id' => 1]);
        $pt1 = DbPaymentType::create(['payment_type' => 'S1 Pay', 'status' => 1, 'store_id' => 1]);

        // ---- Tax update ----
        $r = $this->actingAs($store2)->post(route('settings.tax.update', $tax1->id), [
            'tax_name' => 'HACKED TAX', 'tax' => 99, 'status' => 1,
        ]);
        $this->say("G1 Tax UPDATE -> HTTP {$r->status()}");
        $this->assertSame(404, $r->status());
        $this->assertDatabaseHas('db_tax', ['id' => $tax1->id, 'tax_name' => 'S1 Tax', 'tax' => 5]);

        // ---- Tax delete ----
        $r = $this->actingAs($store2)->delete(route('settings.tax.delete', $tax1->id));
        $this->say("G1 Tax DELETE -> HTTP {$r->status()}");
        $this->assertSame(404, $r->status());
        $this->assertDatabaseHas('db_tax', ['id' => $tax1->id]);

        // ---- Unit update ----
        $r = $this->actingAs($store2)->post(route('settings.units.update', $unit1->id), [
            'unit_name' => 'HACKED UNIT', 'status' => 1,
        ]);
        $this->say("G1 Unit UPDATE -> HTTP {$r->status()}");
        $this->assertSame(404, $r->status());
        $this->assertDatabaseHas('db_units', ['id' => $unit1->id, 'unit_name' => 'S1 Unit']);

        // ---- Unit delete ----
        $r = $this->actingAs($store2)->delete(route('settings.units.delete', $unit1->id));
        $this->say("G1 Unit DELETE -> HTTP {$r->status()}");
        $this->assertSame(404, $r->status());
        $this->assertDatabaseHas('db_units', ['id' => $unit1->id]);

        // ---- Payment Type update ----
        $r = $this->actingAs($store2)->post(route('settings.payment_types.update', $pt1->id), [
            'payment_type' => 'HACKED PAY', 'status' => 1,
        ]);
        $this->say("G1 PaymentType UPDATE -> HTTP {$r->status()}");
        $this->assertSame(404, $r->status());
        $this->assertDatabaseHas('db_paymenttypes', ['id' => $pt1->id, 'payment_type' => 'S1 Pay']);

        // ---- Payment Type delete ----
        $r = $this->actingAs($store2)->delete(route('settings.payment_types.delete', $pt1->id));
        $this->say("G1 PaymentType DELETE -> HTTP {$r->status()}");
        $this->assertSame(404, $r->status());
        $this->assertDatabaseHas('db_paymenttypes', ['id' => $pt1->id]);
    }

    // =====================================================================
    // G2 — Non-super-admin direct-URL 403 (8 routes)
    // =====================================================================
    public function test_g2_non_super_admin_direct_url_403(): void
    {
        // Role with NO view slugs at all. Forced role id (60) != 1 so
        // isSuperAdmin() (role_id === 1) is false — otherwise the FIRST role
        // created in a fresh test DB gets id 1 and every check short-circuits TRUE.
        $nobody = $this->makeNonSuperAdminUser(2, ['dashboard_view'], 60);

        $routes = [
            'Languages'     => ['settings.languages.index'],
            'Countries'     => ['settings.countries'],
            'States'        => ['settings.states'],
            'Tax'           => ['settings.tax'],
            'Units'         => ['settings.units'],
            'Payment Types' => ['settings.payment_types'],
            'SMTP'          => ['settings.smtp'],
            'Currency'      => ['settings.currency'],
        ];

        foreach ($routes as $label => $route) {
            $r = $this->actingAs($nobody)->get(route($route[0]));
            $this->say("G2 {$label} GET " . route($route[0]) . " -> HTTP {$r->status()}");
            $this->assertSame(403, $r->status(), "{$label} should be 403");
        }
    }

    // =====================================================================
    // G3 — smtp_pass ciphertext at rest + decrypt/display + migration
    // =====================================================================
    public function test_g3_smtp_pass_ciphertext_at_rest(): void
    {
        $store = DbStore::find(1);
        $store->smtp_pass = 'PlainSecret123!';
        $store->save();

        $raw = DB::table('db_store')->where('id', 1)->value('smtp_pass');
        $this->say('G3 raw db_store.smtp_pass = ' . $raw);
        $this->say('G3 raw == plaintext? ' . ($raw === 'PlainSecret123!' ? 'YES (BAD)' : 'NO (ciphertext, good)'));

        $this->assertNotSame('PlainSecret123!', $raw);
        $this->assertSame('PlainSecret123!', Crypt::decryptString($raw));
        // Model read decrypts transparently.
        $this->assertSame('PlainSecret123!', DbStore::find(1)->smtp_pass);

        // Form displays decrypted value to an authorised user of the SAME store
        // that owns the secret (store 1) — the form renders the acting store's row.
        $viewer = $this->makeNonSuperAdminUser(1, ['smtp_settings_view'], 70);
        $r = $this->actingAs($viewer)->get(route('settings.smtp'));
        $this->say('G3 GET settings.smtp -> HTTP ' . $r->status() . ' (form sees decrypted secret)');
        $this->assertSame(200, $r->status());
        $r->assertSee('PlainSecret123!');
    }

    public function test_g3b_migration_encrypts_preexisting_plaintext(): void
    {
        // Simulate a legacy plaintext row written before the encrypted cast existed.
        DB::table('db_store')->where('id', 1)->update(['smtp_pass' => 'LegacyPlain']);

        $migration = require database_path('migrations/2026_09_10_000003_encrypt_existing_smtp_pass_values.php');
        $migration->up();

        $raw = DB::table('db_store')->where('id', 1)->value('smtp_pass');
        $this->say('G3b after migration raw = ' . $raw);
        $this->assertNotSame('LegacyPlain', $raw);
        $this->assertSame('LegacyPlain', Crypt::decryptString($raw));

        // Idempotent: second run leaves it decryptable and unchanged.
        $migration->up();
        $raw2 = DB::table('db_store')->where('id', 1)->value('smtp_pass');
        $this->say('G3b after 2nd migration run raw unchanged? ' . ($raw === $raw2 ? 'YES (idempotent)' : 'NO (BAD)'));
        $this->assertSame($raw, $raw2);
        $this->assertSame('LegacyPlain', Crypt::decryptString($raw2));
    }

    // =====================================================================
    // G4 — Delete-guard counts accuracy
    // =====================================================================
    public function test_g4_delete_guard_counts_are_accurate(): void
    {
        $admin = $this->makeNonSuperAdminUser(1, [
            'country_view', 'state_view', 'tax_view', 'tax_delete', 'units_view', 'units_delete',
        ], 80);

        // --- Country: exactly 3 states ---
        $country = DbCountry::create(['country' => 'GuardLand', 'status' => 1, 'added_on' => now()]);
        for ($i = 1; $i <= 3; $i++) {
            DbState::create(['store_id' => 1, 'state' => "GS{$i}", 'country_id' => $country->id, 'country' => 'GuardLand', 'status' => 1, 'added_on' => now()]);
        }
        $r = $this->actingAs($admin)->delete(route('settings.countries.delete', $country->id));
        $err = session('error');
        $ok = session('success');
        $actualStates = DbState::where('country_id', $country->id)->count();
        $stillExists = DbCountry::where('id', $country->id)->exists();
        $this->say("G4 Country HTTP {$r->status()} | error=\"" . (string) $err . "\" | success=\"" . (string) $ok . "\" | states={$actualStates} | countryExists=" . ($stillExists ? 'Y' : 'N'));
        $this->assertStringContainsString('3 state(s)', (string) $err);
        $this->assertSame(3, $actualStates);
        $this->assertDatabaseHas('db_country', ['id' => $country->id]);

        // --- State: exactly 2 customers ---
        $state = DbState::create(['store_id' => 1, 'state' => 'GuardState', 'country_id' => $country->id, 'country' => 'GuardLand', 'status' => 1, 'added_on' => now()]);
        for ($i = 1; $i <= 2; $i++) {
            DbCustomer::create(['customer_name' => "GC{$i}", 'store_id' => 1, 'state_id' => $state->id, 'status' => 1, 'customer_code' => 'GC' . $i]);
        }
        $this->actingAs($admin)->delete(route('settings.states.delete', $state->id));
        $msg = session('error');
        $actual = DbCustomer::where('state_id', $state->id)->count();
        $this->say("G4 State message: \"" . (string) $msg . "\" | actual customers in DB = {$actual}");
        $this->assertStringContainsString('2 customer(s)', (string) $msg);
        $this->assertSame(2, $actual);
        $this->assertDatabaseHas('db_states', ['id' => $state->id]);

        // --- Tax: exactly 2 items ---
        $tax = DbTax::create(['tax_name' => 'GuardTax', 'tax' => 7, 'group_bit' => 0, 'status' => 1, 'store_id' => 1]);
        for ($i = 1; $i <= 2; $i++) {
            DbItem::create(['item_name' => "GT{$i}", 'store_id' => 1, 'tax_id' => $tax->id, 'status' => 1, 'item_code' => 'GT' . $i]);
        }
        $this->actingAs($admin)->delete(route('settings.tax.delete', $tax->id));
        $msg = session('error');
        $actual = DbItem::where('tax_id', $tax->id)->count();
        $this->say("G4 Tax message: \"" . (string) $msg . "\" | actual items in DB = {$actual}");
        $this->assertStringContainsString('2 items', (string) $msg);
        $this->assertSame(2, $actual);
        $this->assertDatabaseHas('db_tax', ['id' => $tax->id]);

        // --- Unit: exactly 1 item ---
        $unit = DbUnit::create(['unit_name' => 'GuardUnit', 'status' => 1, 'store_id' => 1]);
        DbItem::create(['item_name' => 'GU1', 'store_id' => 1, 'unit_id' => $unit->id, 'status' => 1, 'item_code' => 'GU1']);
        $this->actingAs($admin)->delete(route('settings.units.delete', $unit->id));
        $msg = session('error');
        $actual = DbItem::where('unit_id', $unit->id)->count();
        $this->say("G4 Unit message: \"" . (string) $msg . "\" | actual items in DB = {$actual}");
        $this->assertStringContainsString('1 item(s)', (string) $msg);
        $this->assertSame(1, $actual);
        $this->assertDatabaseHas('db_units', ['id' => $unit->id]);
    }

    // =====================================================================
    // G5 — Per-store tax cache refresh within the changing store's request
    // =====================================================================
    public function test_g5_per_store_tax_cache_refresh(): void
    {
        // NOTE: the POS/Add-Sale blades attach tax per-item via the product's tax
        // relation; the standalone $taxes dropdown collection (built by
        // SaleController::create() through Cache::remember('db_taxes_list_{store}'))
        // is not rendered on those pages. The correct G5 proof is at the CACHE
        // layer: the write must bust ONLY the acting store's key, and the next
        // read for that store must rebuild and include the new tax while the
        // other store's cached list is untouched.
        // Store 1 must be able to BOTH read the tax dropdown (sales view) and
        // create a tax (tax_add); Store 2 only needs to read.
        $store1 = $this->makeNonSuperAdminUser(1, ['sales_add', 'sales_view', 'tax_add'], 90);
        $store2 = $this->makeNonSuperAdminUser(2, ['sales_add', 'sales_view'], 91);

        // Warm BOTH per-store keys via the Add Sale page (SaleController::create()).
        $this->actingAs($store1)->get(route('sales.add'))->assertOk();
        $this->actingAs($store2)->get(route('sales.add'))->assertOk();
        $this->say('G5 keys present after warm: k1=' . (Cache::has('db_taxes_list_1') ? 'Y' : 'N') . ' k2=' . (Cache::has('db_taxes_list_2') ? 'Y' : 'N'));

        // Store-1 adds a brand-new tax.
        $this->actingAs($store1)->post(route('settings.tax.store'), [
            'tax_name' => 'FreshTaxAlpha', 'tax' => 12, 'group_bit' => 0, 'status' => 1,
        ])->assertRedirect();

        // The write must have busted ONLY store 1's key.
        $this->say('G5 store1 key busted after create? ' . (Cache::has('db_taxes_list_1') ? 'NO (BAD)' : 'YES'));
        $this->say('G5 store2 key untouched after create? ' . (Cache::has('db_taxes_list_2') ? 'YES' : 'NO (BAD)'));
        $this->assertFalse(Cache::has('db_taxes_list_1'));
        $this->assertTrue(Cache::has('db_taxes_list_2'));

        // Store 1 reloads Add Sale in the SAME session — cache rebuilds and
        // includes the new tax immediately (no 1-hour wait).
        $this->actingAs($store1)->get(route('sales.add'))->assertOk();
        $cached1 = Cache::get('db_taxes_list_1');
        $this->say('G5 store1 rebuilt cache contains FreshTaxAlpha? ' . ($cached1 && $cached1->contains('tax_name', 'FreshTaxAlpha') ? 'YES' : 'NO'));
        $this->assertNotNull($cached1);
        $this->assertTrue($cached1->contains('tax_name', 'FreshTaxAlpha'));

        // Store 2's cached list is unaffected — must NOT contain the new tax.
        $cached2 = Cache::get('db_taxes_list_2');
        $seenByStore2 = $cached2 && $cached2->contains('tax_name', 'FreshTaxAlpha');
        $this->say('G5 store2 cached list contains FreshTaxAlpha (should be NO)? ' . ($seenByStore2 ? 'YES (LEAK)' : 'NO'));
        $this->assertFalse($seenByStore2);
    }

    // =====================================================================
    // G6 — SMTP live test-send (environment-dependent)
    // =====================================================================
    public function test_g6_smtp_test_send_path(): void
    {
        // The phpunit environment uses MAIL_MAILER=array (see phpunit.xml), so no
        // real SMTP server is reachable here. We still exercise the endpoint to
        // capture its exact response contract when smtp_status is disabled and
        // when the mailer is the array transport.
        $admin = $this->makeNonSuperAdminUser(1, ['smtp_settings_view'], 100);

        $store = DbStore::find(1);
        $store->smtp_status = 0;
        $store->save();

        $r = $this->actingAs($admin)->postJson(route('settings.smtp.test'), ['email' => 'x@example.com']);
        $this->say('G6 testSmtp (smtp_status=0) -> HTTP ' . $r->status() . ' body=' . $r->getContent());

        $store->smtp_status = 1;
        $store->smtp_host = '127.0.0.1';
        $store->smtp_port = '1';
        $store->smtp_user = 'user';
        $store->smtp_pass = 'pass';
        $store->save();

        $r = $this->actingAs($admin)->postJson(route('settings.smtp.test'), ['email' => 'x@example.com']);
        $this->say('G6 testSmtp (smtp_status=1, unreachable host) -> HTTP ' . $r->status() . ' body=' . $r->getContent());

        // No live mail server available in this environment — reported as such.
        $this->assertTrue(true);
    }
}
