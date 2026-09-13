<?php

namespace App\Policies;

use App\Models\User;
use App\Models\DbRole;

class RolePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DbRole $role): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can update the model.
     *
     * Aligned to the app-wide inline hasPermission() convention (was
     * isSuperAdmin()-only, which made the seeded roles_edit slug decorative).
     * isSuperAdmin() continues to bypass the slug check.
     */
    public function update(User $user, DbRole $role): bool
    {
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
