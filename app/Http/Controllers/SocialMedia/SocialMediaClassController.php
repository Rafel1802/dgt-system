<?php

namespace App\Http\Controllers\SocialMedia;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaClass;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SocialMediaClassController extends Controller
{
    /** Admin management page */
    public function index()
    {
        $classes   = SocialMediaClass::with(['items', 'creator', 'assignedUsers'])->withCount('items')->orderBy('name')->get();
        // Only show active users in the 'digital-team' role
        $allUsers  = User::with('roles')->role('digital-team')->where('is_active', true)->orderBy('name')->get();

        return view('social-media.manage', compact('classes', 'allUsers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255|unique:social_media_classes,name',
            'description'  => 'nullable|string|max:1000',
            'status'       => ['required', \Illuminate\Validation\Rule::in(['active', 'inactive'])],
            'user_ids'     => 'nullable|array',
            'user_ids.*'   => 'exists:users,id',
            'use_template' => 'nullable|boolean',
            'icon_url'     => 'nullable|string|max:2048',
            'icon_file'    => 'nullable|image|max:2048',
        ]);

        $icon = null;
        if ($request->hasFile('icon_file')) {
            $path = $request->file('icon_file')->store('class_icons', 'public');
            $icon = '/storage/' . $path;
        } elseif (!empty($data['icon_url'])) {
            $icon = $data['icon_url'];
        }

        $class = SocialMediaClass::create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'icon'        => $icon,
            'status'      => $data['status'],
            'created_by'  => auth()->id(),
        ]);

        if (!empty($data['user_ids'])) {
            $attachData = [];
            foreach ($data['user_ids'] as $uid) {
                $attachData[$uid] = ['assigned_by' => auth()->id()];
            }
            $class->assignedUsers()->attach($attachData);
        }

        if (!empty($data['use_template'])) {
            $templates = [
                ['name' => 'Facebook', 'icon' => 'https://upload.wikimedia.org/wikipedia/commons/b/b8/2021_Facebook_icon.svg'],
                ['name' => 'Instagram', 'icon' => 'https://upload.wikimedia.org/wikipedia/commons/e/e7/Instagram_logo_2016.svg'],
                ['name' => 'X(Twitter)', 'icon' => 'https://upload.wikimedia.org/wikipedia/commons/c/ce/X_logo_2023.svg'],
                ['name' => 'Pinterest', 'icon' => 'https://upload.wikimedia.org/wikipedia/commons/0/08/Pinterest-logo.png'],
                ['name' => 'YouTube', 'icon' => 'https://upload.wikimedia.org/wikipedia/commons/0/09/YouTube_full-color_icon_%282017%29.svg'],
                ['name' => 'TikTok', 'icon' => 'https://sf-static.tiktokcdn.com/obj/eden-sg/uhtyvueh7nulogpoguhm/tiktok-icon2.png'],
                ['name' => 'Tumblr', 'icon' => 'https://cdn-icons-png.flaticon.com/512/1409/1409942.png'],
            ];

            $order = 0;
            foreach ($templates as $t) {
                $order += 10;
                $class->items()->create([
                    'name'       => $t['name'],
                    'icon'       => $t['icon'],
                    'status'     => 'active',
                    'sort_order' => $order,
                    'created_by' => auth()->id(),
                ]);
            }
        }

        return back()->with('success', 'Class "' . $data['name'] . '" created successfully.');
    }

    public function update(Request $request, SocialMediaClass $class)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255', Rule::unique('social_media_classes', 'name')->ignore($class->id)],
            'description' => 'nullable|string|max:1000',
            'status'      => ['required', Rule::in(['active', 'inactive'])],
            'icon_url'    => 'nullable|string|max:2048',
            'icon_file'   => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('icon_file')) {
            $path = $request->file('icon_file')->store('class_icons', 'public');
            $data['icon'] = '/storage/' . $path;
        } elseif ($request->has('icon_url')) {
            $data['icon'] = $data['icon_url'];
        }

        $class->update($data);

        return back()->with('success', 'Class updated successfully.');
    }

    public function destroy(SocialMediaClass $class)
    {
        if ($class->posts()->exists()) {
            return back()->with('error', 'Cannot delete: class has existing post records. Deactivate it instead.');
        }
        $class->delete();
        return back()->with('success', 'Class deleted successfully.');
    }

    public function toggleStatus(SocialMediaClass $class)
    {
        $class->update(['status' => $class->status === 'active' ? 'inactive' : 'active']);
        return back()->with('success', 'Class status updated.');
    }

    /** Assign one or more users to a class */
    public function assignUsers(Request $request, SocialMediaClass $class)
    {
        $request->validate([
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
        ]);

        $synced = 0;
        foreach ($request->user_ids as $userId) {
            // Don't re-assign if already exists
            if (!$class->assignedUsers()->where('user_id', $userId)->exists()) {
                $class->assignedUsers()->attach($userId, ['assigned_by' => auth()->id()]);
                $synced++;
            }
        }

        return back()->with('success', $synced . ' user(s) assigned successfully.');
    }

    /** Remove a single user from a class */
    public function removeUser(SocialMediaClass $class, User $user)
    {
        $class->assignedUsers()->detach($user->id);
        return back()->with('success', $user->name . ' removed from class.');
    }

    /** Update social media roles in bulk */
    public function updateBulkUserRoles(Request $request)
    {
        $request->validate([
            'roles'   => ['required', 'array'],
            'roles.*' => ['string', 'in:social_admin,social_qc,none'],
        ]);

        foreach ($request->roles as $userId => $role) {
            $user = User::find($userId);
            if (!$user) continue;

            $primaryRoles = $user->roles()->where('name', 'not like', 'social_%')->pluck('name')->toArray();

            if ($role !== 'none') {
                $primaryRoles[] = $role;
            }

            $user->syncRoles($primaryRoles);
        }

        return back()->with('success', 'Digital Team roles updated successfully.');
    }
}
