<?php

namespace App\Models;

use App\Models\Traits\StoreScoped;
use Illuminate\Database\Eloquent\Model;

class DbRole extends Model
{
    use StoreScoped;
    use \App\Models\Concerns\PinsExplicitIdInTests;

    protected $table = 'db_roles';

    protected $fillable = [
        'store_id',
        'role_name',
        'description',
        'status',
        // Authoritative global-privilege flag. Exposed as fillable so seeders and
        // the admin Roles UI can set it, but RoleController strips it from any
        // request made by a non-super-admin (privilege-escalation guard).
        'is_super_admin',
        // Phase 2.2 — cross-store visibility flag (Owner). Independent of
        // is_super_admin: the Owner SEES across stores but does NOT bypass
        // authorization the way the Developer/system account does.
        'is_owner',
    ];

    protected $casts = [
        'is_super_admin' => 'boolean',
        'is_owner' => 'boolean',
        'status' => 'integer',
    ];

    /**
     * Name-based super-admin seeding.
     *
     * DEFAULTS TO FALSE and is deliberately an explicit opt-in, because this
     * behaviour is a privilege-escalation vector if it can run in a normal
     * application request: any code path that creates a role named "Super Admin"
     * would silently receive global privileges. It is enabled ONLY by the test
     * bootstrap (tests/TestCase.php) so the large body of existing fixtures that
     * use the name as shorthand keeps working. Production seeders set
     * `is_super_admin` explicitly instead and never need this.
     *
     * @var bool
     */
    protected static bool $seedSuperAdminByName = false;

    /**
     * Enable/disable name-based super-admin seeding. Intended for the test
     * bootstrap and seeders only — never call this from request handling.
     */
    public static function seedSuperAdminByName(bool $allow = true): void
    {
        static::$seedSuperAdminByName = $allow;
    }

    /**
     * Seed the global-privilege flag from the conventional role name at CREATION
     * time — but ONLY when seeding has been explicitly opted into (see above) and
     * the caller did not supply the flag itself.
     *
     * `User::isSuperAdmin()` reads ONLY the flag at check time, so the role name
     * never confers privilege in application code. Renaming a role to
     * "Super Admin" later does not grant anything (creating-only hook), and
     * RoleController always passes the flag explicitly, so its escalation guard
     * is authoritative on the request path.
     */
    protected static function booted(): void
    {
        static::creating(function (DbRole $role) {
            if (static::$seedSuperAdminByName
                && !array_key_exists('is_super_admin', $role->getAttributes())
                && $role->role_name === 'Super Admin') {
                $role->is_super_admin = true;
            }
        });
    }

    public function permissions()
    {
        // Exempt from the StoreScoped global scope on purpose — same reason as
        // User::role(): this is read on every hasPermission() call and must not be
        // filtered by the acting store, or privilege resolution becomes circular.
        return $this->hasOne(DbPermission::class, 'role_id')->withoutGlobalScope('store_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'role_id');
    }
}
