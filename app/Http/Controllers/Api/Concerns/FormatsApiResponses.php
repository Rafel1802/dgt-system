<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

trait FormatsApiResponses
{
    protected function paginated(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
    }

    protected function userSummary(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'whatsapp' => $user->whatsapp,
            'avatar_url' => $user->avatar_url,
            'avatar_initials' => $user->avatar_initials,
            'avatar_color' => $user->avatar_color,
            'team_role' => $user->team_role,
            'crm_role' => $user->crm_role,
            'roles' => $user->roles->pluck('name')->values(),
        ];
    }

    protected function normalizeNotification(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $type = $data['type'] ?? $data['action'] ?? class_basename($notification->type);
        $relatedModule = $data['module']
            ?? (isset($data['board_id']) ? 'boards' : null)
            ?? (isset($data['card_id']) ? 'cards' : null)
            ?? (isset($data['website_id']) ? 'websites' : null)
            ?? (isset($data['lead_id']) ? 'crm' : null);
        $relatedId = $data['related_id']
            ?? $data['card_id']
            ?? $data['board_id']
            ?? $data['website_id']
            ?? $data['lead_id']
            ?? null;

        return [
            'id' => (string) $notification->id,
            'title' => $data['title']
                ?? $data['card_title']
                ?? $data['board_name']
                ?? $this->humanizeType((string) $type),
            'message' => strip_tags((string) ($data['message'] ?? $data['description'] ?? $data['action'] ?? 'New DGT System notification')),
            'type' => (string) $type,
            'related_module' => $relatedModule,
            'related_id' => $relatedId,
            'link' => $data['link'] ?? null,
            'created_at' => optional($notification->created_at)->toIso8601String(),
            'read_at' => optional($notification->read_at)->toIso8601String(),
            'sender' => [
                'id' => $data['actor_id'] ?? $data['sender_id'] ?? null,
                'name' => $data['actor_name'] ?? $data['sender_name'] ?? $data['approved_by'] ?? $data['rejected_by'] ?? null,
                'avatar_url' => $data['actor_avatar'] ?? null,
                'initials' => $data['actor_initials'] ?? null,
                'avatar_color' => $data['actor_avatar_color'] ?? null,
            ],
            'data' => $data,
        ];
    }

    protected function humanizeType(string $type): string
    {
        return str($type)->replace(['_', '-'], ' ')->title()->toString();
    }

    protected function changed(Model $model, array $fields): array
    {
        return collect($fields)
            ->filter(fn ($field) => $model->wasChanged($field))
            ->values()
            ->all();
    }

    protected function collectionSummaries(Collection $users): array
    {
        return $users->map(fn (User $user) => $this->userSummary($user))->values()->all();
    }
}
