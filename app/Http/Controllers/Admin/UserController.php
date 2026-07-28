<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{

    /** User management index */
    public function index(Request $request): View
    {
        session(['users_index_url' => $request->fullUrl()]);

        $query = User::with('roles')->withCount('roles');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->get('role')) {
            $query->role($role);
        }

        if ($request->filled('active')) {
            $query->where('is_active', (bool) $request->get('active'));
        }

        $users = $query->orderBy('name')->get();
        $roles = Role::where('name', 'not like', 'social_%')->orderBy('name')->get();

        $stats = [
            'total'    => User::count(),
            'active'   => User::where('is_active', true)->count(),
            'inactive' => User::where('is_active', false)->count(),
        ];

        return view('admin.users.index', compact('users', 'roles', 'stats'));
    }

    /** Create user form */
    public function create(): View
    {
        return view('admin.users.create', [
            'roles' => Role::where('name', 'not like', 'social_%')->orderBy('name')->get(),
        ]);
    }

    /** Store new user */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email'    => ['required', 'email', 'unique:users,email', 'max:255'],
            'password' => ['required', Password::min(8)->mixedCase()->numbers(), 'confirmed'],
            'role'     => ['required', 'string', 'exists:roles,name'],
            'team_role'=> ['nullable', 'string', 'max:50'],
            'crm_role' => ['nullable', 'string', 'in:supervisor'],
            'is_active' => ['nullable', 'boolean'],
            'avatar'   => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ]);

        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        $user = User::create([
            'name'      => $validated['name'],
            'username'  => $validated['username'],
            'email'     => $validated['email'],
            'password'  => Hash::make($validated['password']),
            'is_active' => $request->boolean('is_active', true),
            'avatar'    => $avatarPath,
            'team_role' => $validated['team_role'] ?? null,
            'crm_role'  => $validated['crm_role'] ?? null,
        ]);

        $user->assignRole($validated['role']);

        return redirect()->route('admin.users.index')
            ->with('success', "User \"{$user->name}\" created successfully.");
    }

    /** Edit user form */
    public function edit(User $user): View
    {
        // Prevent admins from editing super-admins
        if ($user->hasRole('super-admin') && ! auth()->user()->hasRole('super-admin')) {
            abort(403, 'Only Super Admins can edit Super Admin accounts.');
        }

        return view('admin.users.edit', [
            'user'  => $user->load('roles'),
            'roles' => Role::where('name', 'not like', 'social_%')->orderBy('name')->get(),
        ]);
    }

    /** Update user */
    public function update(Request $request, User $user): RedirectResponse
    {
        if ($user->hasRole('super-admin') && ! auth()->user()->hasRole('super-admin')) {
            abort(403);
        }

        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'username'  => ['required', 'string', 'max:255', "unique:users,username,{$user->id}"],
            'email'     => ['required', 'email', 'max:255', "unique:users,email,{$user->id}"],
            'role'      => ['required', 'string', 'exists:roles,name'],
            'team_role' => ['nullable', 'string', 'max:50'],
            'crm_role'  => ['nullable', 'string', 'in:supervisor'],
            'is_active' => ['nullable', 'boolean'],
            'password'  => ['nullable', Password::min(8)->mixedCase()->numbers(), 'confirmed'],
            'avatar'    => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'avatar_url_input' => ['nullable', 'url', 'max:1024'],
        ]);

        $isSelf = $user->id === auth()->id();

        $updateData = [
            'name'      => $validated['name'],
            'username'  => $validated['username'],
            'email'     => $validated['email'],
            'team_role' => $validated['team_role'] ?? null,
            'crm_role'  => $validated['crm_role'] ?? null,
            'is_active' => $isSelf ? true : $request->boolean('is_active'),
        ];

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $updateData['avatar'] = $request->file('avatar')->store('avatars', 'public');
        } elseif (!empty($validated['avatar_url_input'])) {
            try {
                $response = \Illuminate\Support\Facades\Http::get($validated['avatar_url_input']);
                if ($response->successful()) {
                    $name = 'avatars/' . uniqid() . '.jpg';
                    Storage::disk('public')->put($name, $response->body());
                    if ($user->avatar) {
                        Storage::disk('public')->delete($user->avatar);
                    }
                    $updateData['avatar'] = $name;
                }
            } catch (\Exception $e) {}
        }

        $user->update($updateData);

        if (! empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        // Preserve any existing social_ roles
        $socialRoles = $user->roles()->where('name', 'like', 'social_%')->pluck('name')->toArray();
        $user->syncRoles(array_merge([$validated['role']], $socialRoles));

        return redirect()->route('admin.users.edit', $user)
            ->with('success', "User \"{$user->name}\" updated.");
    }

    /** Toggle active/inactive (AJAX) */
    public function toggleActive(User $user): JsonResponse
    {
        // Prevent locking out yourself or super-admins
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 403);
        }
        if ($user->hasRole('super-admin') && ! auth()->user()->hasRole('super-admin')) {
            return response()->json(['message' => 'Cannot deactivate Super Admin accounts.'], 403);
        }

        $user->update(['is_active' => ! $user->is_active]);

        return response()->json([
            'is_active' => $user->is_active,
            'message'   => $user->is_active ? "{$user->name} activated." : "{$user->name} deactivated.",
        ]);
    }

    /** Handle bulk actions on users */
    public function bulkAction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:allow_security,disallow_security,freeze,unfreeze'],
            'users'  => ['required', 'array', 'min:1'],
            'users.*' => ['integer', 'exists:users,id'],
        ]);

        $users = User::whereIn('id', $validated['users'])->get();
        $count = 0;

        foreach ($users as $user) {
            // Prevent modifying self or super-admins where restricted
            if ($user->id === auth()->id() && in_array($validated['action'], ['freeze', 'unfreeze', 'disallow_security'])) {
                continue;
            }
            if ($user->hasRole('super-admin') && !auth()->user()->hasRole('super-admin')) {
                continue;
            }

            switch ($validated['action']) {
                case 'allow_security':
                    $user->update(['can_edit_profile' => true]);
                    break;
                case 'disallow_security':
                    $user->update(['can_edit_profile' => false]);
                    break;
                case 'freeze':
                    $user->update(['is_active' => false]);
                    break;
                case 'unfreeze':
                    $user->update(['is_active' => true]);
                    break;
            }
            $count++;
        }

        return response()->json([
            'message' => "Successfully updated {$count} user(s)."
        ]);
    }

    /** Reset user password directly (admin action) */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', Password::min(8)->mixedCase()->numbers(), 'confirmed'],
        ]);

        $user->update(['password' => Hash::make($validated['password'])]);

        return response()->json(['message' => "Password for {$user->name} has been reset."]);
    }

    /** Soft-delete a user */
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            if (request()->expectsJson()) {
                return response()->json(['message' => 'You cannot delete your own account.'], 403);
            }
            return back()->with('error', 'You cannot delete your own account.');
        }
        if ($user->hasRole('super-admin')) {
            if (request()->expectsJson()) {
                return response()->json(['message' => 'Super Admin accounts cannot be deleted.'], 403);
            }
            return back()->with('error', 'Super Admin accounts cannot be deleted.');
        }

        $user->delete();

        if (request()->expectsJson()) {
            return response()->json(['message' => "\"{$user->name}\" has been removed."]);
        }

        return redirect()->to(session('users_index_url', route('admin.users.index')))
            ->with('success', "\"{$user->name}\" has been removed.");
    }
}
