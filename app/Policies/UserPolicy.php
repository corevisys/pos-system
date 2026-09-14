<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     *
     * Phase 2.1: branch admins are no longer global super-admins, so this honours the
     * seeded users_view slug (as update()/delete() already do). The Developer/system
     * account still bypasses via isSuperAdmin(). The controller scopes the listing to
     * the acting store, so this does not widen cross-store visibility.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('users_view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->isSuperAdmin()
            || $user->id === $model->id
            || $user->hasPermission('users_view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('users_add');
    }

    /**
     * Determine whether the user can update the model.
     *
     * Aligned to the app-wide inline hasPermission() convention (was
     * isSuperAdmin()-only, which made the seeded users_edit slug decorative).
     * isSuperAdmin() continues to bypass every check, and a user may still edit
     * their own account.
     */
    public function update(User $user, User $model): bool
    {
        return $user->isSuperAdmin()
            || $user->id === $model->id
            || $user->hasPermission('users_edit');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * Honours the seeded users_delete slug for non-super-admins; a user may never
     * delete their own account, and isSuperAdmin() still bypasses the slug check.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->id !== $model->id
            && ($user->isSuperAdmin() || $user->hasPermission('users_delete'));
    }
}
