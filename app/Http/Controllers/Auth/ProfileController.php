<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(): View
    {
        return view('profile.show', ['user' => auth()->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $isPrivileged = $user->can_edit_profile || $user->hasAnyRole(['super-admin', 'admin']);

        $rules = [
            'name'          => ['required', 'string', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:40'],
            'whatsapp'      => ['nullable', 'string', 'max:40'],
            'avatar'        => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'avatar_url'    => ['nullable', 'url', 'starts_with:http://,https://', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ];

        if ($isPrivileged) {
            $rules['email'] = ['required', 'email', 'max:255', 'unique:users,email,' . $user->id];
            if ($request->input('email') !== $user->email) {
                $rules['current_password'] = ['required', 'current_password'];
            }
        }

        $validated = $request->validate($rules, [
            'avatar.image' => 'Please choose a valid image file.',
            'avatar.mimes' => 'Avatar files must be JPEG, PNG, JPG, GIF, or WEBP.',
            'avatar.max' => 'Avatar files must be 2 MB or smaller.',
            'avatar_url.url' => 'Please paste a valid image URL.',
            'avatar_url.starts_with' => 'Image URLs must start with http:// or https://.',
        ]);

        $avatarUrl = trim((string) $request->input('avatar_url'));

        if ($request->boolean('remove_avatar')) {
            $this->deleteLocalAvatar($user->avatar);
            $validated['avatar'] = null;
        } elseif ($request->hasFile('avatar')) {
            $this->deleteLocalAvatar($user->avatar);
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        } elseif ($avatarUrl !== '') {
            $this->deleteLocalAvatar($user->avatar);
            $validated['avatar'] = $avatarUrl;
        }

        unset($validated['avatar_url'], $validated['remove_avatar'], $validated['current_password']);

        $user->update($validated);

        return back()->with('success', 'Profile updated successfully.');
    }

    private function deleteLocalAvatar(?string $avatar): void
    {
        $avatar = trim((string) $avatar);

        if ($avatar === '' || Str::startsWith($avatar, ['http://', 'https://', 'data:image/'])) {
            return;
        }

        $path = ltrim($avatar, '/');
        if (Str::startsWith($path, 'storage/')) {
            $path = Str::after($path, 'storage/');
        }

        Storage::disk('public')->delete($path);
    }

    public function settings(): View
    {
        return view('profile.settings', ['user' => auth()->user()]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $isPrivileged = $user->can_edit_profile || $user->hasAnyRole(['super-admin', 'admin']);

        if (!$isPrivileged) {
            return back()->with('error', 'You do not have permission to change your password. Please contact an administrator.');
        }

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        auth()->user()->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('success', 'Password changed successfully.');
    }
}
