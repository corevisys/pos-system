<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time permission-slug reconciliation data fix (production-safe).
 *
 * WHY: the seeded slug `print_labels` never matched the slug the application
 * actually checks. ItemController (print labels methods), the items list blade,
 * the app layout sidebar and NavigationShortcutService all gate on
 * `items_print_labels` — while PermissionSeeder/RolePermissionSeeder granted
 * `print_labels`. Net effect: the Print Labels feature was unreachable for every
 * non-super-admin, even when an admin explicitly granted "print_labels" believing
 * it enabled the feature.
 *
 * The seeders have been corrected to grant `items_print_labels` (aligning it with
 * the items_view/items_add/items_edit/items_delete naming convention). This
 * migration fixes ALREADY-DEPLOYED data: it rewrites the slug IN PLACE inside
 * each db_permissions.permissions JSON array, so existing role→permission
 * assignments carry over automatically (no delete+recreate, no role reassignment).
 *
 * It is idempotent and touches only the permissions column (no schema changes),
 * mirroring 2026_09_12_000001_reconcile_permission_slugs_reports_view_and_sales.
 */
return new class extends Migration
{
    private string $old = 'print_labels';
    private string $new = 'items_print_labels';

    public function up(): void
    {
        $this->remap($this->old, $this->new);
    }

    public function down(): void
    {
        $this->remap($this->new, $this->old);
    }

    /**
     * Replace $from with $to inside every db_permissions.permissions JSON array,
     * preserving all other slugs and de-duplicating.
     */
    private function remap(string $from, string $to): void
    {
        $rows = DB::table('db_permissions')->get(['id', 'permissions']);

        foreach ($rows as $row) {
            $list = json_decode((string) $row->permissions, true);
            if (!is_array($list)) {
                continue;
            }

            if (!in_array($from, $list, true)) {
                continue;
            }

            $list = array_map(fn ($slug) => $slug === $from ? $to : $slug, $list);
            $list = array_values(array_unique($list));

            DB::table('db_permissions')
                ->where('id', $row->id)
                ->update(['permissions' => json_encode($list)]);
        }
    }
};
