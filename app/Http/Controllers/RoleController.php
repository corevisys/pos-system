<?php

namespace App\Http\Controllers;

use App\Models\DbRole;
use App\Models\DbPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', DbRole::class);

        $query = DbRole::withCount('users')->with('permissions');

        // Search
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function($q) use ($term) {
                $q->where('role_name', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Sorting
        $sort = $request->get('sort', 'created_at');
        $order = $request->get('order', 'desc');
        $query->orderBy($sort, $order);

        $roles = $query->paginate($request->get('per_page', 10))->withQueryString();

        // Stats
        $stats = [
            'total' => DbRole::count(),
            'active' => DbRole::where('status', 1)->count(),
            'inactive' => DbRole::where('status', 0)->count(),
        ];

        return view('module.users.roles_list', compact('roles', 'stats'));
    }

    public function create()
    {
        Gate::authorize('create', DbRole::class);
        return view('module.users.roles_add');
    }

    /**
     * Resolve a role id strictly within the acting store (IDOR guard).
     *
     * DbRole uses the StoreScoped trait, so this is also enforced by the global
     * scope; the explicit where() keeps the protection obvious at the call site
     * (and independent of whether a global scope is ever bypassed), matching the
     * pattern used by VariantController / CategoryController / BrandController.
     */
    private function findActingStoreRole($id): DbRole
    {
        return DbRole::where('store_id', auth()->user()->store_id)->findOrFail($id);
    }

    public function show($id)
    {
        $role = $this->findActingStoreRole($id);
        Gate::authorize('view', $role);
        $role->load('permissions', 'users');
        return view('module.users.roles_show', compact('role'));
    }

    public function edit($id)
    {
        $role = $this->findActingStoreRole($id);
        Gate::authorize('update', $role);
        $role->load('permissions');
        return view('module.users.roles_edit', compact('role'));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', DbRole::class);

        $request->validate([
            // role_name is unique PER STORE, not globally: two branches may each have
            // a "Manager" role. Mirrors the (store_id, role_name) composite unique.
            'role_name' => [
                'required', 'string', 'max:255',
                Rule::unique('db_roles', 'role_name')->where('store_id', auth()->user()->store_id),
            ],
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
        ]);

        $role = DbRole::create([
            'role_name' => $request->role_name,
            'description' => $request->description,
            'status' => 1,
            'store_id' => auth()->user()->store_id,
            // Privilege-escalation guard: only an existing super admin may mint
            // another super-admin role. For everyone else the flag stays false.
            'is_super_admin' => $request->boolean('is_super_admin') && auth()->user()->isSuperAdmin(),
        ]);

        if ($request->has('permissions')) {
            DbPermission::create([
                'role_id' => $role->id,
                'permissions' => $request->permissions,
                'store_id' => auth()->user()->store_id,
            ]);
        }

        return redirect()->route('users.roles')->with('success', 'Role created successfully.');
    }

    public function update(Request $request, $id)
    {
        $role = $this->findActingStoreRole($id);

        Gate::authorize('update', $role);

        $request->validate([
            // Per-store uniqueness with ignore-self, so a role keeps its own name on edit.
            'role_name' => [
                'required', 'string', 'max:255',
                Rule::unique('db_roles', 'role_name')
                    ->where('store_id', auth()->user()->store_id)
                    ->ignore($role->id),
            ],
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
        ]);

        $updateData = [
            'role_name' => $request->role_name,
            'description' => $request->description,
        ];

        // Privilege-escalation guard: a non-super-admin can neither grant nor
        // revoke the super-admin flag — their submitted value is ignored entirely.
        // A super admin may change it (subject to the delete-guard in RolePolicy).
        if (auth()->user()->isSuperAdmin()) {
            $updateData['is_super_admin'] = $request->boolean('is_super_admin');
        }

        $role->update($updateData);

        if ($request->has('permissions')) {
            DbPermission::updateOrCreate(
                ['role_id' => $role->id],
                ['permissions' => $request->permissions, 'store_id' => auth()->user()->store_id]
            );
        }

        return redirect()->back()->with('success', 'Role updated successfully.');
    }

    public function destroy($id)
    {
        $role = $this->findActingStoreRole($id);

        Gate::authorize('delete', $role);

        // Delete-guard: never let a role with assigned users be removed. Without
        // this, the users.role_id foreign key's cascade would silently delete those
        // user accounts along with the role.
        $usersCount = $role->users()->count();
        if ($usersCount > 0) {
            return redirect()->back()->with(
                'error',
                "This role cannot be deleted because {$usersCount} user(s) are still assigned to it. " .
                'Reassign those users to another role first.'
            );
        }

        $role->permissions()->delete();
        $role->delete();

        return redirect()->back()->with('success', 'Role deleted successfully.');
    }
}
