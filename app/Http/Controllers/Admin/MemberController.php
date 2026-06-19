<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class MemberController extends Controller
{
    /**
     * All logged-in users can browse the company directory.
     * Data is sourced directly from the existing users table.
     */
    public function index(): View
    {
        $members = User::active()
            ->with('roles')
            ->orderBy('name')
            ->get();

        $stats = [
            'total'        => $members->count(),
            'online_today' => User::active()
                ->where('last_login_at', '>=', now()->startOfDay())
                ->count(),
            'online_now'   => User::active()
                ->where('last_login_at', '>=', now()->subMinutes(30))
                ->count(),
            'new_month'    => User::active()
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count(),
        ];

        return view('members.index', compact('members', 'stats'));
    }

    /**
     * AJAX: search/filter members — returns JSON for live search.
     */
    public function search(\Illuminate\Http\Request $request): JsonResponse
    {
        $query = User::active()->with('roles')->orderBy('name');

        if ($q = $request->input('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('team_role', 'like', "%{$q}%");
            });
        }

        $members = $query->get()->map(fn(User $u) => $this->mapUser($u));

        return response()->json(['members' => $members]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function mapUser(User $u): array
    {
        $phone     = $u->phone;
        $whatsapp  = $u->whatsapp ?: $phone; // dedicated field, fallback to phone
        $waNumber  = $whatsapp ? preg_replace('/[^0-9]/', '', $whatsapp) : null;

        return [
            'id'               => $u->id,
            'name'             => $u->name,
            'email'            => $u->email,
            'phone'            => $phone,
            'whatsapp'         => $u->whatsapp ?: $phone,
            'phone_url'        => $phone ? "tel:{$phone}" : null,
            'whatsapp_url'     => $waNumber ? "https://wa.me/{$waNumber}" : null,
            'role_display'     => $u->role_display,
            'team_role'        => $u->team_role,
            'avatar_url'       => $u->avatar_url,
            'is_online'        => $u->last_login_at && $u->last_login_at->gte(now()->subMinutes(30)),
            'last_seen'        => $u->last_login_at?->diffForHumans(),
            'joined'           => $u->created_at?->format('F j, Y'),
        ];
    }
}
