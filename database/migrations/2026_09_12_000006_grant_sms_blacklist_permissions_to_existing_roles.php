<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Grant the three new SMS-blacklist permission slugs to already-existing roles.
 *
 * WHY: seeders are not re-run in production, so the slugs added to
 * PermissionSeeder/RolePermissionSeeder would never reach live roles. Without this
 * the new Blacklist page would be invisible to every non-super-admin.
 *
 * PRESERVES CURRENT EFFECTIVE ACCESS — it grants nothing new in spirit:
 * any role that can already reach the Messaging module (it holds any sms_* or
 * messaging-related slug) receives the blacklist slugs, mirroring the
 * reports_view precedent. Super-admin roles are served by is_super_admin anyway.
 *
 * Idempotent: `array_unique` prevents duplicates on re-run.
 */
return new class extends Migration
{
    private array $newSlugs = ['sms_blacklist_view', 'sms_blacklist_add', 'sms_blacklist_delete'];

    /**
     * Signals that a role can already reach Messaging.
     */
    private array $messagingSlugs = [
        'send_sms', 'sms_settings', 'sms_api_view', 'sms_api_edit',
        'sms_template_view', 'sms_template_edit',
        'sms_whatsapp_send_message', 'sms_whatsapp_message_template_view',
        'sms_whatsapp_message_api_view', 'sms_whatsapp_message_settings',
    ];

    public function up(): void
    {
        foreach (DB::table('db_permissions')->get(['id', 'permissions']) as $row) {
            $list = json_decode((string) $row->permissions, true);
            if (!is_array($list)) {
                continue;
            }

            // Already has them? Skip.
            if (count(array_intersect($this->newSlugs, $list)) === count($this->newSlugs)) {
                continue;
            }

            if (!array_intersect($this->messagingSlugs, $list)) {
                continue;
            }

            $updated = array_values(array_unique(array_merge($list, $this->newSlugs)));

            DB::table('db_permissions')
                ->where('id', $row->id)
                ->update(['permissions' => json_encode($updated)]);
        }
    }

    public function down(): void
    {
        // Remove only the slugs this migration introduced.
        foreach (DB::table('db_permissions')->get(['id', 'permissions']) as $row) {
            $list = json_decode((string) $row->permissions, true);
            if (!is_array($list)) {
                continue;
            }

            if (!array_intersect($this->messagingSlugs, $list)) {
                continue;
            }

            $updated = array_values(array_diff($list, $this->newSlugs));

            DB::table('db_permissions')
                ->where('id', $row->id)
                ->update(['permissions' => json_encode($updated)]);
        }
    }
};
