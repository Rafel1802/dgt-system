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
        $baseQuery = User::active()->whereDoesntHave('roles', function ($q) {
            $q->where('name', 'super-admin');
        });

        $members = (clone $baseQuery)
            ->with('roles')
            ->orderBy('name')
            ->get();

        $stats = [
            'total'        => (clone $baseQuery)->count(),
            'online_today' => (clone $baseQuery)
                ->where('last_login_at', '>=', now()->startOfDay())
                ->count(),
            'online_now'   => (clone $baseQuery)
                ->where('last_login_at', '>=', now()->subMinutes(30))
                ->count(),
            'new_month'    => (clone $baseQuery)
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
        $query = User::active()
            ->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'super-admin');
            })
            ->with('roles')
            ->orderBy('name');

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
