<?php

namespace App\Models\Concerns;

/**
 * Test-only support for fixtures that pin well-known primary keys.
 *
 * Fixtures across this suite do e.g. DbRole::firstOrCreate(['id' => 1], [...]).
 * 'id' is not declared fillable, so mass-assignment normally drops it and the row
 * receives an auto-increment id instead.
 *
 * SQLite (:memory:) hides the problem: every test starts from a freshly built
 * database whose auto-increment resets to 1, so the first insert is always id 1.
 * Under MySQL the shared test database is not recreated per test. RefreshDatabase
 * wraps each test in a transaction, but MySQL auto-increment counters are
 * NON-transactional and keep climbing across rollbacks. After the first test, id 1
 * no longer exists for the pinned table, so later fixtures such as
 * User::factory()->create(['role_id' => 1]) fail with a foreign-key violation
 * (users.role_id -> db_roles.id).
 *
 * This trait merges 'id' into $fillable ONLY while the test runner is active, and
 * ONLY when the model already declares a non-empty $fillable. That last condition
 * matters: for a model that uses $guarded = [] (e.g. DbStore), 'id' is ALREADY
 * mass-assignable, and adding a non-empty $fillable = ['id'] would instead NARROW
 * the model — Eloquent's isFillable() rejects any key absent from a non-empty
 * $fillable — silently dropping every other attribute (timezone, store_name, ...).
 *
 * It is deliberately NOT a global Model::unguard(): that flag is shared by every
 * model subclass, so it would re-enable mass assignment everywhere and surface
 * phantom attributes (e.g. account_number passed to AcAccount although ac_accounts
 * has no such column).
 */
trait PinsExplicitIdInTests
{
    public function __construct(array $attributes = [])
    {
        if (app()->runningUnitTests() && !empty($this->getFillable())) {
            $this->mergeFillable(['id']);
        }

        parent::__construct($attributes);
    }
}
