<?php

namespace Database\Seeders;

use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Phase 2.2 / 2.3 — the two cross-store identities.
 *
 * Owner     — sees across stores (all-store visibility + consolidated reporting),
 *             but is NOT globally privileged: it is still subject to the normal
 *             permission checks. Its "store" is chosen at runtime via StoreContext,
 *             so it has no fixed users.store_id.
 * Developer — the system/maintenance super-admin. The ONLY account holding the
 *             global-privilege flag (is_super_admin). Never the Owner's login, and
 *             intended to be restricted or disabled after launch.
 *
 * The two are deliberately separate accounts (and separate roles) so activity_logs
 * causer attribution is never conflated between "sees across stores" and "bypasses
 * everything".
 */
class OwnerDeveloperSeeder extends Seeder
{
    public function run(): void
    {
        $ownerRole = DbRole::where('role_name', 'Owner')->first();
        $developerRole = DbRole::where('role_name', 'Developer')->first();

        // users.store_id is NOT NULL, so these cross-store accounts still need a
        // nominal store row. It is NOT their acting store — that is chosen at runtime
        // via StoreContext (the Owner's selector / the all-stores aggregate). The
        // nominal value simply satisfies the column constraint.
        $nominalStoreId = (int) (DbStore::orderBy('id')->value('id') ?: 1);

        if ($ownerRole && !User::where('email', 'owner@corevisys.com')->exists()) {
            User::create([
                'name' => 'Business Owner',
                'first_name' => 'Business',
                'last_name' => 'Owner',
                'username' => 'owner',
                'email' => 'owner@corevisys.com',
                'password' => Hash::make('password'),
                'role_id' => $ownerRole->id,
                'role_name' => $ownerRole->role_name,
                'status' => 1,
                // Nominal only — the acting store is chosen at runtime.
                'store_id' => $nominalStoreId,
                'created_date' => now()->format('Y-m-d'),
                'created_time' => now()->format('H:i:s'),
            ]);
        }

        if ($developerRole && !User::where('email', 'developer@corevisys.com')->exists()) {
            User::create([
                'name' => 'System Developer',
                'first_name' => 'System',
                'last_name' => 'Developer',
                'username' => 'developer',
                'email' => 'developer@corevisys.com',
                'password' => Hash::make('password'),
                'role_id' => $developerRole->id,
                'role_name' => $developerRole->role_name,
                'status' => 1,
                'store_id' => $nominalStoreId,
                'created_date' => now()->format('Y-m-d'),
                'created_time' => now()->format('H:i:s'),
            ]);
        }
    }
}
