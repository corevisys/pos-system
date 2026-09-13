<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make sms_auto_rules.event_type unique PER STORE instead of globally.
 *
 * Prior state: enforcement lived ONLY in the controller validation rule
 * (`unique:sms_auto_rules,event_type`), which was GLOBAL — so Store B could not
 * create an automation rule for an event_type Store A already used, even though
 * these are store-owned rows (sms_auto_rules.store_id, duplicated per active
 * store by 2026_09_12_000004). There was no DB-level unique (the create
 * migration only added a plain index('event_type')).
 *
 * This mirrors the exact precedent set by
 * 2026_09_08_000001_make_category_brand_variant_name_code_unique_per_store and
 * 2026_09_07_000002_make_supplier_mobile_email_unique_per_store:
 *   1. Surface any pre-existing per-store duplicate (do NOT silently dedupe).
 *   2. Add the composite unique (store_id, event_type).
 *   3. Drop the superseded plain single-column index (and any legacy global
 *      unique, defensively).
 *
 * NOTE ON SOFT DELETES: SmsAutoRule uses SoftDeletes and the composite unique is
 * intentionally a PLAIN (store_id, event_type) index — the same choice the
 * supplier precedent made. Soft-deleted rows therefore continue to occupy the
 * pair. The controller validation is aligned to the same semantics (it does NOT
 * exclude soft-deleted rows) so a re-create after a soft delete fails with a
 * clean 422 validation error rather than an uncaught unique-constraint 500.
 */
return new class extends Migration
{
    private string $table = 'sms_auto_rules';

    public function up(): void
    {
        // 1. Pre-existing per-store duplicate detection — abort loudly rather than
        //    silently corrupting (mirrors the accounts/category migrations).
        $duplicates = DB::table($this->table)
            ->select('store_id', 'event_type', DB::raw('COUNT(*) as cnt'))
            ->groupBy('store_id', 'event_type')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $details = $duplicates
                ->map(fn ($r) => "store_id={$r->store_id} event_type='{$r->event_type}' count={$r->cnt}")
                ->implode('; ');

            throw new \RuntimeException(
                'make_event_type_unique_per_store_on_sms_auto_rules aborted: duplicate ' .
                "(store_id, event_type) rows found in sms_auto_rules. Resolve them before " .
                "re-running: {$details}"
            );
        }

        // 2. Add the composite unique BEFORE dropping the superseded plain index,
        //    so the table is never left without a uniqueness guarantee.
        Schema::table($this->table, function (Blueprint $table) {
            $table->unique(['store_id', 'event_type'], 'sms_auto_rules_store_event_type_unique');
        });

        // 3. Drop the superseded plain index the original create migration added.
        //    NOTE: the try/catch must wrap the Schema::table() call itself — the
        //    blueprint command is executed AFTER the closure returns, so a
        //    try/catch inside the closure would never catch a missing-index error.
        try {
            Schema::table($this->table, function (Blueprint $table) {
                $table->dropIndex('sms_auto_rules_event_type_index');
            });
        } catch (\Throwable $e) {
            // Index absent (fresh install / driver naming) — nothing to drop.
        }
    }

    public function down(): void
    {
        try {
            Schema::table($this->table, function (Blueprint $table) {
                $table->dropUnique('sms_auto_rules_store_event_type_unique');
            });
        } catch (\Throwable $e) {
            // Already dropped — nothing to do.
        }

        try {
            Schema::table($this->table, function (Blueprint $table) {
                $table->index('event_type');
            });
        } catch (\Throwable $e) {
            // Index already present.
        }
    }
};
