<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;
    use \App\Models\Concerns\PinsExplicitIdInTests;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_id',
        'username',
        'name',
        'first_name',
        'last_name',
        'email',
        'email_verified_at',
        'password',
        'member_of',

        'mobile',
        'photo',
        'gender',
        'dob',
        'country',
        'state',
        'city',
        'address',
        'postcode',
        'role_name',
        'role_id',
        'profile_picture',
        'created_date',
        'created_time',
        'created_by',
        'system_ip',
        'system_name',
        'status',
        'creater_id',
        'updater_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'integer',
        ];
    }

    public function role()
    {
        // Exempt from the StoreScoped global scope on purpose.
        //
        // This relation backs isSuperAdmin() and hasPermission(), which control
        // AUTHENTICATION and must never be filtered by current_store_id() — that
        // value is itself derived from this user (circular), and a super admin is
        // by definition allowed to operate across stores. Without this exemption a
        // user whose role row's store_id differs from their own would silently lose
        // all privileges (and EnsureUserHasStore's super-admin bypass).
        return $this->belongsTo(DbRole::class, 'role_id')->withoutGlobalScope('store_id');
    }

    public function scopeSearch($query, $term)
    {
        return $query->where(function($q) use ($term) {
            $q->where('username', 'like', "%{$term}%")
              ->orWhere('first_name', 'like', "%{$term}%")
              ->orWhere('last_name', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%");
        });
    }

    public function scopeFilterRole($query, $roleId)
    {
        return $query->when($roleId, fn($q) => $q->where('role_id', $roleId));
    }

    public function scopeFilterStatus($query, $status)
    {
        return $query->when($status !== null && $status !== '', fn($q) => $q->where('status', $status));
    }

    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Whether this user's role is a genuine (global) super-admin role.
     *
     * Authoritative source is db_roles.is_super_admin — NOT the role NAME (three
     * per-store roles were all called "Super Admin") and NOT role_id === 1 (roles
     * created through the Roles UI auto-increment, so a new store's Super Admin
     * can receive any id).
     *
     * The role relation is memoised by Eloquent on first access, so the repeated
     * hasPermission() calls during a single request (sidebar rendering) resolve
     * the role row at most once.
     */
    public function isSuperAdmin()
    {
        return (bool) optional($this->role)->is_super_admin;
    }

    /**
     * Phase 2.2 — the Owner: sees across stores (all-store visibility + consolidated
     * reporting) but is NOT a bypass-everything super admin. Unlike isSuperAdmin(),
     * this flag does NOT short-circuit permission checks — the Owner still goes
     * through the normal permission:/hasPermission() gates. It only widens READ
     * scope (gated explicitly where consolidated data is exposed).
     */
    public function isOwner()
    {
        return (bool) optional($this->role)->is_owner;
    }

    /**
     * Whether this user may view across stores (Owner or Developer/system account).
     * Used to gate consolidated reporting; the Developer additionally bypasses
     * permission checks via isSuperAdmin().
     */
    public function canViewAllStores()
    {
        return $this->isSuperAdmin() || $this->isOwner();
    }

    /**
     * The acting user's own effective permission-slug set — the single source of
     * truth for "which slugs may this user hold, grant, or see".
     *
     * Returns NULL for a genuine super admin (is_super_admin = true), meaning
     * UNRESTRICTED. Every other user returns the flat slug array stored on their
     * own role. The Role Management permission-grant guard (RoleController) and
     * the permissions-matrix display filter both read this one method, so the
     * server-side guard and the UI filter can never drift apart.
     */
    public function effectivePermissions(): ?array
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        return array_values((array) data_get($this->role, 'permissions.permissions', []));
    }

    public function hasPermission($permission)
    {
        $effective = $this->effectivePermissions();

        // null = super admin (unrestricted).
        if ($effective === null) {
            return true;
        }

        return in_array($permission, $effective, true);
    }

    public function store()
    {
        return $this->belongsTo(\App\Models\DbStore::class, 'store_id');
    }
}
