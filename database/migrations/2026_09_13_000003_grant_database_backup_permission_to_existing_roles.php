<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Grant the new `database_backup` permission slug to already-existing roles.
 *
 * WHY: BackupController was previously UNGATED, and the sidebar checked a
 * `database_backup` slug that NO seeder ever granted — so the Database Backup
 * page was unreachable for every non-super-admin while simultaneously being
 * reachable by URL for every authenticated user. The slug has now been added to
 * PermissionSeeder/RolePermissionSeeder and the controller gated with it.
 *
 * Because seeders are not re-run in production, this migration back-fills live
 * roles. Roles that could ALREADY administer the system (they hold any of the
 * settings/admin slugs below) receive `database_backup`, preserving their
 * existing effective access — mirroring the reports_view and sms_blacklist
 * precedents. Super-admin roles are served by is_super_admin anyway.
 *
 * Idempotent: `array_unique` prevents duplicates on re-run.
 */
return new class extends Migration
{
    private string $newSlug = 'database_backup';

    /**
     * Slugs that signal a role already administers system settings.
     */
    private array $adminSlugs = [
        'store_edit', 'store_settings_view', 'store_settings_edit',
        'smtp_settings_view', 'sms_settings', 'sms_api_edit',
    ];

    public function up(): void
    {
        foreach (DB::table('db_permissions')->get(['id', 'permissions']) as $row) {
            $list = json_decode((string) $row->permissions, true);
            if (!is_array($list)) {
                continue;
            }

            // Already granted? Skip.
            if (in_array($this->newSlug, $list, true)) {
                continue;
            }

            if (!array_intersect($this->adminSlugs, $list)) {
                continue;
            }

            $updated = array_values(array_unique(array_merge($list, [$this->newSlug])));

            DB::table('db_permissions')
                ->where('id', $row->id)
                ->update(['permissions' => json_encode($updated)]);
        }
    }

    public function down(): void
    {
        // Remove only the slug this migration introduced.
        foreach (DB::table('db_permissions')->get(['id', 'permissions']) as $row) {
            $list = json_decode((string) $row->permissions, true);
            if (!is_array($list) || !in_array($this->newSlug, $list, true)) {
                continue;
            }

            if (!array_intersect($this->adminSlugs, $list)) {
                continue;
            }

            $updated = array_values(array_diff($list, [$this->newSlug]));

            DB::table('db_permissions')
                ->where('id', $row->id)
                ->update(['permissions' => json_encode($updated)]);
        }
    }
};
