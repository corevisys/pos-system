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
     */
    public function update(User $user, DbRole $role): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DbRole $role): bool
    {
        // A role is undeletable because it IS a super-admin role — not because of
        // its auto-increment id (roles created via the Roles UI get arbitrary ids).
        return $user->isSuperAdmin() && !$role->is_super_admin;
    }
}
