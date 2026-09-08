<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        $query = User::with('role');

        // Search
        $query->when($request->search, fn($q) => $q->search($request->search));

        // Filters
        $query->when($request->role, fn($q) => $q->filterRole($request->role));
        $query->when($request->filled('status'), fn($q) => $q->filterStatus($request->status));

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');
        
        $allowedSorts = ['id', 'username', 'email', 'created_at', 'role_name'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortOrder);
        }

        // Pagination
        $perPage = $request->get('per_page', 10);
        $users = $query->paginate($perPage)->withQueryString();

        // Stats for cards
        $stats = [
            'total' => User::count(),
            'active' => User::where('status', 1)->count(),
            'inactive' => User::where('status', 0)->count(),
        ];

        $roles = DbRole::all();

        return view('module.users.list', compact('users', 'stats', 'roles'));
    }

    public function create()
    {
        Gate::authorize('create', User::class);
        $roles = DbRole::all();
        $stores = DbStore::all();
        return view('module.users.add', compact('roles', 'stores'));
    }

    public function show($id)
    {
        $user = User::with(['role', 'store'])->findOrFail($id);
        Gate::authorize('view', $user);

        return view('module.users.show', compact('user'));
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        Gate::authorize('update', $user);

        $roles = DbRole::all();
        $stores = DbStore::all();

        return view('module.users.edit', compact('user', 'roles', 'stores'));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', User::class);

        $validated = $request->validate([
            'store_id' => 'nullable|exists:db_store,id',
            'username' => 'required|string|max:255|unique:users',
            'name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',

            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role_id' => 'required|exists:db_roles,id',
            'status' => 'required|in:0,1',
            'member_of' => 'nullable|string|max:255',
            'mobile' => 'nullable|string|max:20',
            'gender' => 'nullable|string|max:20',
            'dob' => 'nullable|date',
            'country' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'postcode' => 'nullable|string|max:20',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        try {
            DB::beginTransaction();

            $role = DbRole::findOrFail($validated['role_id']);
            $validated['role_name'] = $role->role_name;
            $validated['password'] = Hash::make($validated['password']);
            
            // Audit fields
            $validated['created_date'] = date('Y-m-d');
            $validated['created_time'] = date('H:i:s');
            $validated['created_by'] = auth()->id();
            $validated['creater_id'] = auth()->id();
            $validated['system_ip'] = $request->ip();
            $validated['system_name'] = gethostname();

            if ($request->hasFile('profile_picture')) {
                $path = $request->file('profile_picture')->store('users/profiles', 'public');
                $validated['profile_picture'] = $path;
            }

            if ($request->hasFile('photo')) {
                $path = $request->file('photo')->store('users/photos', 'public');
                $validated['photo'] = $path;
            }

            User::create($validated);

            DB::commit();
            return redirect()->route('users.list')->with('success', 'User created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create user: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        Gate::authorize('update', $user);

        $validated = $request->validate([
            'store_id' => 'nullable|exists:db_store,id',
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',

            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'role_id' => 'required|exists:db_roles,id',
            'status' => 'required|in:0,1',
            'member_of' => 'nullable|string|max:255',
            'mobile' => 'nullable|string|max:20',
            'gender' => 'nullable|string|max:20',
            'dob' => 'nullable|date',
            'country' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'postcode' => 'nullable|string|max:20',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        try {
            DB::beginTransaction();

            $role = DbRole::findOrFail($validated['role_id']);
            $validated['role_name'] = $role->role_name;
            
            if (!empty($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
            } else {
                unset($validated['password']);
            }

            // Audit
            $validated['updater_id'] = auth()->id();
            $validated['system_ip'] = $request->ip();
            $validated['system_name'] = gethostname();

            if ($request->hasFile('profile_picture')) {
                if ($user->profile_picture) { Storage::disk('public')->delete($user->profile_picture); }
                $validated['profile_picture'] = $request->file('profile_picture')->store('users/profiles', 'public');
            }

            if ($request->hasFile('photo')) {
                if ($user->photo) { Storage::disk('public')->delete($user->photo); }
                $validated['photo'] = $request->file('photo')->store('users/photos', 'public');
            }

            $user->update($validated);

            DB::commit();
            return back()->with('success', 'User updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to update user: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        Gate::authorize('delete', $user);

        try {
            if ($user->profile_picture) {
                Storage::disk('public')->delete($user->profile_picture);
            }
            $user->delete();
            return back()->with('success', 'User deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete user: ' . $e->getMessage());
        }
    }

    /**
     * Toggle the specified user's status.
     */
    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);
        Gate::authorize('update', $user);

        try {
            $user->status = $user->status == 1 ? 0 : 1;
            $user->save();
            return back()->with('success', 'User status updated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update status: ' . $e->getMessage());
        }
    }
}
