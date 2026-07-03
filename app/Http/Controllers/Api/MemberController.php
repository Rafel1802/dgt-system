<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsApiResponses;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    use FormatsApiResponses;

    public function index(Request $request): JsonResponse
    {
        $query = User::with('roles')
            ->active()
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $term = $request->string('q')->toString();
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('username', 'like', "%{$term}%")
                    ->orWhere('team_role', 'like', "%{$term}%"));
            })
            ->when($request->filled('role'), fn (Builder $query) => $query->whereHas('roles', fn ($roles) => $roles->where('name', $request->string('role')->toString())))
            ->orderBy('name');

        return response()->json($this->paginated($query->paginate($request->integer('per_page', 40))));
    }

    public function search(Request $request): JsonResponse
    {
        $term = $request->string('q')->toString();
        $users = User::with('roles')
            ->active()
            ->where(fn ($query) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('username', 'like', "%{$term}%"))
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'label' => $user->name . ' <' . $user->email . '>',
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'roles' => $user->roles->pluck('name')->values(),
            ])->values(),
        ]);
    }
}
