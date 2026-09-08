<?php

namespace App\Http\Controllers;

use App\Models\DbRole;
use App\Models\DbPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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

    public function show(DbRole $role)
    {
        Gate::authorize('view', $role);
        $role->load('permissions', 'users');
        return view('module.users.roles_show', compact('role'));
    }

    public function edit(DbRole $role)
    {
        Gate::authorize('update', $role);
        $role->load('permissions');
        return view('module.users.roles_edit', compact('role'));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', DbRole::class);

        $request->validate([
            'role_name' => 'required|string|max:255|unique:db_roles,role_name',
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
        ]);

        $role = DbRole::create([
            'role_name' => $request->role_name,
            'description' => $request->description,
            'status' => 1,
            'store_id' => auth()->user()->store_id,
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

    public function update(Request $request, DbRole $role)
    {
        Gate::authorize('update', $role);

        $request->validate([
            'role_name' => 'required|string|max:255|unique:db_roles,role_name,' . $role->id,
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
        ]);

        $role->update([
            'role_name' => $request->role_name,
            'description' => $request->description,
        ]);

        if ($request->has('permissions')) {
            DbPermission::updateOrCreate(
                ['role_id' => $role->id],
                ['permissions' => $request->permissions, 'store_id' => auth()->user()->store_id]
            );
        }

        return redirect()->back()->with('success', 'Role updated successfully.');
    }

    public function destroy(DbRole $role)
    {
        Gate::authorize('delete', $role);

        $role->permissions()->delete();
        $role->delete();

        return redirect()->back()->with('success', 'Role deleted successfully.');
    }
}
