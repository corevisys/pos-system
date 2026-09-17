<?php

namespace App\Policies;

use App\Models\User;
use App\Models\DbRole;

class RolePolicy
{
    /**
     * Determine whether the user can view any models.
     *
     * Phase 2.1: branch admins are no longer global super-admins, so this honours the
     * seeded roles_view slug (as update()/delete() already do). The Developer/system
     * account still bypasses via isSuperAdmin(). Role listing is store-scoped by the
     * controller, so this does not widen cross-store visibility.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('roles_view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DbRole $role): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('roles_view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('roles_add');
    }

    /**
     * Determine whether the user can update the model.
     *
     * Aligned to the app-wide inline hasPermission() convention (was
     * isSuperAdmin()-only, which made the seeded roles_edit slug decorative).
     * isSuperAdmin() continues to bypass the slug check.
     *
     * PROTECTED-RECORD GUARD (mirrors delete()): a super-admin ROLE record — the
     * Developer/system tier that carries the authoritative is_super_admin flag —
     * is immutable to everyone below the super-admin tier. Without this a Branch
     * Admin holding roles_edit could rename the system role or rewrite its
     * permissions (escalation / de-privileging the Developer account). The
     * Developer/super-admin himself continues to manage it.
     */
    public function update(User $user, DbRole $role): bool
    {
        if ($role->is_super_admin && !$user->isSuperAdmin()) {
            return false;
        }

        return $user->isSuperAdmin() || $user->hasPermission('roles_edit');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * Honours the seeded roles_delete slug for non-super-admins. UNCHANGED GUARD:
     * a role is undeletable because it IS a super-admin role — not because of its
     * auto-increment id (roles created via the Roles UI get arbitrary ids).
     */
    public function delete(User $user, DbRole $role): bool
    {
        return ($user->isSuperAdmin() || $user->hasPermission('roles_delete'))
            && !$role->is_super_admin;
    }
}
