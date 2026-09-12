<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Store-scope the three SMS tables that were authored single-tenant:
 *   - sms_auto_rules  (trigger rules; 20 seeded rows, store-less)
 *   - sms_campaigns   (0 rows)
 *   - sms_logs        (0 rows)
 *
 * WHY: provider credentials (db_smsapi/db_fivemojo) and templates
 * (db_smstemplates) are ALREADY per-store, and SmsService resolves the gateway
 * from $options['store_id']. But SmsAutoRule had no store column at all, so
 * SmsTriggerService's `$rule->store_id ?? 1` was ALWAYS 1 — meaning every
 * automatic SMS triggered by a Store-2/Store-3 sale was sent through Store 1's
 * gateway credentials and billed to Store 1's balance.
 *
 * ROW COUNTS AT WRITE TIME (verified live): campaigns=0, logs=0, auto_rules=20,
 * templates=20 (all at store 1). So campaigns/logs need no backfill; auto_rules
 * and templates are duplicated per store (the established pattern for seeded
 * master data in this engagement, cf. db_tax / db_units).
 *
 * NOTE ON TEMPLATES: sms_auto_rules.template_id points at db_smstemplates, which
 * uses the StoreScoped trait. Duplicating rules to store 2/3 WITHOUT duplicating
 * their templates would resolve `$rule->template` to null for those stores and
 * fatal in SmsTriggerService::processRule(). Templates are therefore duplicated
 * as part of this migration, and each store's rules are re-pointed at that
 * store's own template row.
 *
 * The columns are added nullable, backfilled, then tightened to NOT NULL so the
 * migration is safe regardless of current row counts.
 */
return new class extends Migration
{
    private array $tables = ['sms_auto_rules', 'sms_campaigns', 'sms_logs'];

    public function up(): void
    {
        $driver = DB::getDriverName();

        // ── 1. Add nullable store_id columns ────────────────────────────────
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'store_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('store_id')->nullable()->after('id');
            });
        }

        // ── 2. Backfill ─────────────────────────────────────────────────────
        // Everything currently existing belongs to the original (first) store.
        $firstStoreId = (int) (DB::table('db_store')->orderBy('id')->value('id') ?? 1);

        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'store_id')) {
                DB::table($table)->whereNull('store_id')->update(['store_id' => $firstStoreId]);
            }
        }

        // ── 3. Duplicate sms_auto_rules + their templates per active store ──
        $storeIds = DB::table('db_store')->where('status', 1)->orderBy('id')->pluck('id')
            ->map(fn ($id) => (int) $id)->all();

        if (count($storeIds) > 1 && Schema::hasTable('db_smstemplates') && Schema::hasColumn('db_smstemplates', 'store_id')) {
            $this->duplicateTemplatesAndRules($firstStoreId, $storeIds);
        }

        // ── 4. Tighten to NOT NULL ──────────────────────────────────────────
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'store_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('store_id')->nullable(false)->change();
            });

            // Index for the store-scoped reads this migration exists to enable.
            Schema::table($table, function (Blueprint $t) use ($table) {
                try {
                    $t->index('store_id', $table . '_store_id_index');
                } catch (\Throwable $e) {
                    // Index already present (re-run after a partial failure) — fine.
                }
            });
        }

        // ── 5. Foreign keys (not supported by SQLite ALTER) ─────────────────
        if ($driver !== 'sqlite') {
            foreach ($this->tables as $table) {
                if (!Schema::hasTable($table)) {
                    continue;
                }
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->foreign('store_id', $table . '_store_id_foreign')
                        ->references('id')->on('db_store')
                        ->onDelete('cascade');
                });
            }
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'store_id')) {
                continue;
            }

            if ($driver !== 'sqlite') {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    try { $t->dropForeign($table . '_store_id_foreign'); } catch (\Throwable $e) {}
                });
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                try { $t->dropIndex($table . '_store_id_index'); } catch (\Throwable $e) {}
                $t->dropColumn('store_id');
            });
        }
    }

    /**
     * Give every active store its own copy of the seeded templates and rules,
     * with each store's rules pointing at that store's own template rows.
     */
    private function duplicateTemplatesAndRules(int $sourceStoreId, array $storeIds): void
    {
        $now = now();

        // Source templates, keyed by name for re-pointing.
        $sourceTemplates = DB::table('db_smstemplates')
            ->where('store_id', $sourceStoreId)
            ->get();

        $templateIdByStoreAndName = [];

        foreach ($storeIds as $storeId) {
            foreach ($sourceTemplates as $tpl) {
                $templateIdByStoreAndName[$storeId][$tpl->template_name] = null;

                if ($storeId === $sourceStoreId) {
                    $templateIdByStoreAndName[$storeId][$tpl->template_name] = $tpl->id;
                    continue;
                }

                $existingId = DB::table('db_smstemplates')
                    ->where('store_id', $storeId)
                    ->where('template_name', $tpl->template_name)
                    ->value('id');

                if ($existingId) {
                    $templateIdByStoreAndName[$storeId][$tpl->template_name] = $existingId;
                    continue;
                }

                $row = (array) $tpl;
                unset($row['id']);
                $row['store_id'] = $storeId;
                $row['created_at'] = $now;
                $row['updated_at'] = $now;

                $templateIdByStoreAndName[$storeId][$tpl->template_name] = DB::table('db_smstemplates')->insertGetId($row);
            }
        }

        // Source rules, with their template NAME resolved for re-pointing.
        $sourceRules = DB::table('sms_auto_rules')->where('store_id', $sourceStoreId)->get();

        foreach ($storeIds as $storeId) {
            foreach ($sourceRules as $rule) {
                $templateName = $rule->template_id
                    ? optional($sourceTemplates->firstWhere('id', $rule->template_id))->template_name
                    : null;

                $targetTemplateId = $templateName
                    ? ($templateIdByStoreAndName[$storeId][$templateName] ?? null)
                    : null;

                $existingRuleId = DB::table('sms_auto_rules')
                    ->where('store_id', $storeId)
                    ->where('event_type', $rule->event_type)
                    ->value('id');

                $payload = [
                    'rule_name' => $rule->rule_name,
                    'event_source' => $rule->event_source,
                    'template_id' => $targetTemplateId ?? $rule->template_id,
                    'trigger_time' => $rule->trigger_time,
                    'days_offset' => $rule->days_offset,
                    'cooldown_days' => $rule->cooldown_days,
                    'is_active' => $rule->is_active,
                    'updated_at' => $now,
                ];

                if ($existingRuleId) {
                    DB::table('sms_auto_rules')->where('id', $existingRuleId)->update($payload);
                    continue;
                }

                DB::table('sms_auto_rules')->insert(array_merge($payload, [
                    'store_id' => $storeId,
                    'event_type' => $rule->event_type,
                    'last_executed_at' => null,
                    'created_at' => $now,
                    'deleted_at' => null,
                ]));
            }
        }
    }
};
