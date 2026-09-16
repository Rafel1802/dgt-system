<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'module',
        'description',
        'subject_type',
        'subject_id',
        'properties',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Accessor: Always return clean, human-readable English without route dots.
     */
    public function getDescriptionAttribute(?string $value): string
    {
        return self::humanizeDescription($value);
    }

    /**
     * Convert technical or dot-notated route descriptions into clean, professional English.
     */
    public static function humanizeDescription(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        // Direct dictionary for known route identifiers and technical action names
        $routeMap = [
            'boards.cards.bulkAction'               => 'Updated board cards in bulk',
            'boards.cards.destroy'                  => 'Deleted a board card',
            'boards.cards.labels'                   => 'Updated board card labels',
            'boards.cards.store'                    => 'Created a new board card',
            'boards.cards.update'                   => 'Updated board card details',
            'boards.cards.move'                     => 'Moved board card',
            'boards.cards.copy'                     => 'Copied board card',
            'boards.cards.members'                  => 'Updated board card members',
            'boards.cards.block-complete'           => 'Marked board card block as completed',
            'boards.cards.toggle-approve'           => 'Toggled approval on board card',
            'boards.cards.checklists.store'         => 'Added checklist to board card',
            'boards.cards.checklists.update'        => 'Updated checklist on board card',
            'boards.cards.checklists.destroy'       => 'Deleted checklist from board card',
            'boards.cards.checklists.items.store'   => 'Added item to board card checklist',
            'boards.cards.checklists.items.toggle'  => 'Checked/unchecked checklist item on card',
            'boards.cards.checklists.items.destroy' => 'Deleted checklist item from card',
            'boards.cards.comments.store'           => 'Posted a comment on board card',
            'boards.cards.comments.update'          => 'Updated a comment on board card',
            'boards.cards.comments.destroy'         => 'Deleted a comment from board card',
            'boards.cards.files.store'              => 'Uploaded attachment to board card',
            'boards.cards.files.update'             => 'Updated attachment on board card',
            'boards.cards.files.destroy'            => 'Removed attachment from board card',
            'boards.cards.files.download'           => 'Downloaded attachment from board card',
            'boards.cards.files.preview'            => 'Previewed attachment on board card',
            'boards.cards.reorder'                  => 'Reordered board cards',

            'boards.lists.store'                    => 'Created a new list on board',
            'boards.lists.update'                   => 'Renamed a list on board',
            'boards.lists.destroy'                  => 'Deleted a list from board',
            'boards.lists.clear'                    => 'Cleared all cards from board list',
            'boards.lists.reorder'                  => 'Reordered lists on board',
            'boards.store'                          => 'Created a new board',
            'boards.update'                         => 'Updated board settings',
            'boards.destroy'                        => 'Deleted board',
            'boards.basic-update'                   => 'Updated basic board details',
            'boards.toggle-hidden'                  => 'Toggled board visibility',
            'boards.copy'                           => 'Copied board',
            'boards.watch'                          => 'Toggled watching board',
            'boards.labels.create'                  => 'Created a new board label',
            'boards.background.upload'              => 'Uploaded board background',
            'boards.members.add'                    => 'Added member to board',
            'boards.members.remove'                 => 'Removed member from board',
            'boards.workspaces.store'               => 'Created a new workspace',
            'boards.workspaces.update'              => 'Updated workspace settings',
            'boards.workspaces.destroy'             => 'Deleted workspace',
            'boards.workspaces.reorder'             => 'Reordered workspaces',

            'websites.followups.qc'                 => 'Updated website follow-up QC',
            'websites.followups.store'              => 'Added a new website follow-up',
            'websites.followups.update'             => 'Updated website follow-up',
            'websites.followups.destroy'            => 'Deleted website follow-up',
            'websites.followups.bulk'               => 'Performed bulk update on follow-ups',
            'websites.store'                        => 'Added a new website',
            'websites.update'                       => 'Updated website information',
            'websites.destroy'                      => 'Deleted website',
            'websites.members.add'                  => 'Assigned user to website',
            'websites.members.remove'               => 'Removed user from website',
            'websites.qc.approve'                   => 'Approved website QC review',
            'websites.supervisor.approve'           => 'Approved website Supervisor review',

            'notes.api.store'                       => 'Created a new note',
            'notes.api.update'                      => 'Updated note content',
            'notes.api.destroy'                     => 'Moved note to bin',
            'notes.api.folder.store'                => 'Created a new note folder',
            'notes.api.folder.update'               => 'Renamed note folder',
            'notes.api.folder.destroy'              => 'Deleted note folder',
            'notes.api.bulk-delete'                 => 'Deleted multiple notes',
            'notes.api.bulk-move'                   => 'Moved multiple notes to folder',
            'notes.api.bulk-duplicate'              => 'Duplicated notes',
            'notes.api.restore'                     => 'Restored note from bin',
            'notes.api.force-destroy'               => 'Permanently deleted note',

            'admin.security.ban-ip'                 => 'Banned an IP address',
            'admin.security.unban-ip'               => 'Unbanned an IP address',
            'admin.security.unblock-user'           => 'Unblocked user account',
            'admin.security.settings'               => 'Updated security settings',
            'admin.security.activity.clear'         => 'Cleared security activity logs',
            'admin.security.attempts.clear'         => 'Cleared failed login attempts',

            'admin.users.store'                     => 'Created a new user account',
            'admin.users.update'                    => 'Updated user account',
            'admin.users.destroy'                   => 'Deleted user account',
            'admin.users.toggle-status'             => 'Changed user active status',
            'admin.users.reset-password'            => 'Reset user password',

            'profile.update'                        => 'Updated profile details',
            'profile.password'                      => 'Changed account password',
            'profile.avatar'                        => 'Updated profile avatar',
        ];

        // 1. Check if the text ends with or matches any known route
        foreach ($routeMap as $routeKey => $humanLabel) {
            if ($text === $routeKey || str_ends_with($text, $routeKey)) {
                return $humanLabel;
            }
        }

        // 2. If it contains route-style dots (e.g. "Created or submitted some.module.action")
        if (preg_match('/[a-zA-Z0-9]\.[a-zA-Z0-9]/', $text)) {
            $prefixes = [
                'Created or submitted ' => 'Created ',
                'Deleted '              => 'Deleted ',
                'Updated '              => 'Updated ',
                'Visited '              => 'Visited ',
                'Changed '              => 'Changed ',
            ];

            $verb = '';
            $remainder = $text;

            foreach ($prefixes as $prefix => $cleanVerb) {
                if (str_starts_with($text, $prefix)) {
                    $verb = $cleanVerb;
                    $remainder = substr($text, strlen($prefix));
                    break;
                }
            }

            if (isset($routeMap[$remainder])) {
                return $routeMap[$remainder];
            }

            $segments = explode('.', $remainder);
            $cleanSegments = [];

            foreach ($segments as $segment) {
                $segment = preg_replace('/([a-z])([A-Z])/', '$1 $2', $segment);
                $segment = str_replace(['_', '-'], ' ', $segment);
                $segment = trim($segment);
                if ($segment !== '') {
                    $cleanSegments[] = $segment;
                }
            }

            $last = strtolower(end($cleanSegments) ?: '');
            if (in_array($last, ['store', 'create', 'update', 'edit', 'destroy', 'delete'])) {
                if ($verb === '') {
                    $verb = match ($last) {
                        'store', 'create' => 'Created ',
                        'destroy', 'delete' => 'Deleted ',
                        default => 'Updated ',
                    };
                }
                array_pop($cleanSegments);
            }

            $readable = ucwords(implode(' ', array_filter($cleanSegments)));
            return trim($verb . $readable);
        }

        return $text;
    }
}
