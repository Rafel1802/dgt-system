<?php

namespace App\Http\Controllers\Board;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Models\Board;
use App\Models\Card;
use App\Enums\CardStatus;
use App\Enums\CardPriority;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BoardExportController extends Controller
{
    /**
     * Extracts markdown image tags (both base64 and URLs) and HTML img tags from text.
     * Returns an array with 'text' (cleaned), 'formatted_html' (clean formatted HTML),
     * and 'screenshots' (array of resolved screenshots with 'src', 'url', and 'name').
     */
    public static function extractScreenshotsAndClean(?string $text, ?\App\Models\Card $card = null): array
    {
        if (empty($text)) {
            return [
                'text' => '',
                'formatted_html' => '',
                'screenshots' => []
            ];
        }

        $screenshots = [];
        $cleanedText = $text;

        // 1. Match markdown image syntax: ![alt](url)
        $mdPattern = '/!\[([^\]]*?)\]\(([^)]+?)\)/i';
        if (preg_match_all($mdPattern, $cleanedText, $mdMatches, PREG_SET_ORDER)) {
            foreach ($mdMatches as $m) {
                $alt = trim($m[1]);
                $url = trim($m[2]);
                $screenshots[] = self::resolveScreenshotData($url, $alt, $card);
            }
            $cleanedText = preg_replace($mdPattern, '', $cleanedText);
        }

        // 2. Match HTML <img> tags: <img ... src="..." ...>
        $htmlImgPattern = '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i';
        if (preg_match_all($htmlImgPattern, $cleanedText, $htmlMatches, PREG_SET_ORDER)) {
            foreach ($htmlMatches as $m) {
                $url = trim($m[1]);
                $screenshots[] = self::resolveScreenshotData($url, 'Screenshot', $card);
            }
            $cleanedText = preg_replace($htmlImgPattern, '', $cleanedText);
        }

        // 3. Match markdown links pointing directly to card files or images: [alt](.../files/\d+/(download|preview))
        $fileLinkPattern = '/\[([^\]]*?)\]\((https?:\/\/[^\s\)]*\/files\/\d+\/(?:download|preview)|[^\s\)]*\/files\/\d+\/(?:download|preview))\)/i';
        if (preg_match_all($fileLinkPattern, $cleanedText, $linkMatches, PREG_SET_ORDER)) {
            foreach ($linkMatches as $m) {
                $alt = trim($m[1]);
                $url = trim($m[2]);
                $screenshots[] = self::resolveScreenshotData($url, $alt ?: 'Screenshot', $card);
            }
            $cleanedText = preg_replace($fileLinkPattern, '', $cleanedText);
        }

        $cleanedText = trim(str_replace(['**', '__'], '', $cleanedText));
        $formattedHtml = self::formatCommentText($cleanedText);

        return [
            'text' => $cleanedText,
            'formatted_html' => $formattedHtml,
            'screenshots' => $screenshots
        ];
    }

    /**
     * Resolves screenshot image data, reading local files into Base64 data URIs
     * so that printing/PDF exports never fail or trigger attachment downloads.
     */
    public static function resolveScreenshotData(string $rawUrl, string $alt = '', ?\App\Models\Card $card = null): array
    {
        $rawUrl = trim($rawUrl);
        $displaySrc = $rawUrl;
        $openUrl = $rawUrl;
        $name = !empty($alt) ? $alt : 'Screenshot';

        // Base64 data URI already
        if (str_starts_with($rawUrl, 'data:image/')) {
            return [
                'src' => $rawUrl,
                'url' => '#',
                'name' => $name,
            ];
        }

        // Check if URL references a card file by ID: /files/(\d+)
        $cardFile = null;
        if (preg_match('/files\/(\d+)/', $rawUrl, $fileIdMatch)) {
            $fileId = (int) $fileIdMatch[1];
            if ($card && $card->relationLoaded('allFiles')) {
                $cardFile = $card->allFiles->firstWhere('id', $fileId);
            }
            if (!$cardFile) {
                $cardFile = \App\Models\CardFile::find($fileId);
            }
        } elseif ($card && $card->relationLoaded('allFiles')) {
            $cardFile = $card->allFiles->first(fn($f) => !empty($f->stored_name) && str_contains($rawUrl, $f->stored_name));
        }

        if ($cardFile) {
            $name = !empty($cardFile->original_name) ? $cardFile->original_name : $name;
            $openUrl = $cardFile->preview_url ?: $cardFile->url;

            // Attempt to read physical file and convert to base64
            $disk = $cardFile->disk && $cardFile->disk !== 'url' ? $cardFile->disk : config('filesystems.default', 'local');
            $storage = \Illuminate\Support\Facades\Storage::disk($disk);

            $candidatePaths = [
                $cardFile->path,
                storage_path('app/' . $cardFile->path),
                storage_path('app/private/' . $cardFile->path),
                storage_path('app/public/' . $cardFile->path),
                public_path('storage/' . $cardFile->path),
                public_path($cardFile->path),
            ];

            foreach ($candidatePaths as $p) {
                if (file_exists($p) && is_file($p)) {
                    $size = filesize($p);
                    if ($size > 0 && $size < 15 * 1024 * 1024) {
                        $mime = $cardFile->mime_type ?: (mime_content_type($p) ?: 'image/jpeg');
                        $fileData = file_get_contents($p);
                        if ($fileData !== false) {
                            $displaySrc = 'data:' . $mime . ';base64,' . base64_encode($fileData);
                            break;
                        }
                    }
                } elseif ($storage->exists($cardFile->path)) {
                    $mime = $cardFile->mime_type ?: 'image/jpeg';
                    $fileData = $storage->get($cardFile->path);
                    if ($fileData !== null && strlen($fileData) > 0 && strlen($fileData) < 15 * 1024 * 1024) {
                        $displaySrc = 'data:' . $mime . ';base64,' . base64_encode($fileData);
                        break;
                    }
                }
            }

            // Fallback: use preview URL rather than download URL (avoids Content-Disposition: attachment)
            if ($displaySrc === $rawUrl) {
                $displaySrc = $cardFile->preview_url;
            }
        } else {
            // Check if URL points to storage /storage/...
            if (str_contains($rawUrl, '/storage/')) {
                $subPath = preg_replace('/^.*\/storage\//', '', $rawUrl);
                $fullPath = public_path('storage/' . $subPath);
                if (!file_exists($fullPath)) {
                    $fullPath = storage_path('app/public/' . $subPath);
                }
                if (file_exists($fullPath) && is_file($fullPath)) {
                    $size = filesize($fullPath);
                    if ($size > 0 && $size < 15 * 1024 * 1024) {
                        $mime = mime_content_type($fullPath) ?: 'image/jpeg';
                        $data = file_get_contents($fullPath);
                        if ($data !== false) {
                            $displaySrc = 'data:' . $mime . ';base64,' . base64_encode($data);
                        }
                    }
                }
            }

            // Make relative URLs absolute
            if (str_starts_with($rawUrl, '/')) {
                $openUrl = url($rawUrl);
                if ($displaySrc === $rawUrl) {
                    $displaySrc = url($rawUrl);
                }
            }

            // Avoid /download endpoint in displaySrc
            if (str_contains($displaySrc, '/download')) {
                $displaySrc = str_replace('/download', '/preview', $displaySrc);
            }
            if (str_contains($openUrl, '/download')) {
                $openUrl = str_replace('/download', '/preview', $openUrl);
            }
        }

        return [
            'src'  => $displaySrc,
            'url'  => $openUrl,
            'name' => $name,
        ];
    }

    /**
     * Formats comment text with clean HTML escaping, styled @mentions, bold markdown, and clickable links.
     */
    public static function formatCommentText(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // HTML escape
        $html = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // Mentions @username -> clean pill badge
        $html = preg_replace(
            '/(?:^|(?<=\s))@([\w.\-]+)/',
            '<span style="display: inline-block; font-weight: 600; color: #4f46e5; background-color: #eef2ff; padding: 0.5px 6px; border-radius: 9999px; font-size: 10px;">@$1</span>',
            $html
        );

        // Markdown bold **text**
        $html = preg_replace('/\*\*(.*?)\*\*/s', '<strong style="font-weight: 700; color: #0f172a;">$1</strong>', $html);

        // Markdown links [label](url)
        $html = preg_replace(
            '/\[([^\]]+)\]\((https?:\/\/[^\s\)]+)\)/',
            '<a href="$2" target="_blank" style="color: #4f46e5; text-decoration: underline;">$1</a>',
            $html
        );

        // Raw URLs
        $html = preg_replace(
            '/(?<!href="|">)(https?:\/\/[^\s<]+[^<.,:;"\')\]\s])/i',
            '<a href="$1" target="_blank" style="color: #4f46e5; text-decoration: underline; word-break: break-all;">$1</a>',
            $html
        );

        return nl2br($html);
    }

    /**
     * Helper to get all workspaces and boards a user can access.
     *
     * @param bool $includeHidden  When true, hidden boards are included.
     *                             Used for QC/Supervisor personal report so they
     *                             can select past months' boards (which are hidden
     *                             once the month ends) when exporting historical data.
     */
    private function getAuthorizedWorkspaces(\App\Models\User $user, bool $includeHidden = false)
    {
        if ($user->hasAnyRole(['super-admin', 'admin-digital'])) {
            $workspaces = Workspace::with([
                'boards' => function($q) use ($includeHidden) {
                    $q->where('is_archived', false)
                      ->when(!$includeHidden, fn($q) => $q->where('is_hidden', false))
                      ->orderBy('position');
                },
            ])
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        } else {
            $allActiveWorkspaces = Workspace::with([
                'boards' => function($q) use ($includeHidden) {
                    $q->where('is_archived', false)
                      ->when(!$includeHidden, fn($q) => $q->where('is_hidden', false))
                      ->orderBy('position');
                },
            ])
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
            $workspaces = $allActiveWorkspaces->filter(function ($ws) use ($user) {
                if ($ws->hasMember($user->id)) return true;
                foreach ($ws->boards as $board) {
                    if ($board->hasMember($user->id)) return true;
                }
                return false;
            });
        }

        foreach ($workspaces as $workspace) {
            $workspace->setRelation('boards', $workspace->boards->filter(function ($board) use ($user) {
                $isQc = str_contains(strtolower($user->team_role ?? ''), 'qc');
                $isBypassed = $user->hasAnyRole(['super-admin', 'admin-digital', 'admin', 'supervisor', 'boss']) || $isQc;

                if ($isBypassed) {
                    return true;
                }

                if ($user->hasAnyRole(['digital-team', 'sales-crm'])) {
                    return $board->hasMember($user->id);
                }
                return true;
            }));
        }

        $isQc = str_contains(strtolower($user->team_role ?? ''), 'qc');
        $isBypassed = $user->hasAnyRole(['super-admin', 'admin-digital', 'admin', 'supervisor', 'boss']) || $isQc;
        if (!$isBypassed && $user->hasRole('digital-team')) {
            $workspaces = $workspaces->filter(function ($ws) {
                return $ws->boards->isNotEmpty();
            });
        }

        return $workspaces;
    }

    /**
     * Apply request filters and return a card query.
     */
    private function getFilteredCardsQuery(Request $request, ?Board $board = null)
    {
        $boardIds = [];
        if ($board) {
            $boardIds = [$board->id];
        }
        
        if ($request->has('board_ids') && is_array($request->board_ids)) {
            $requestedIds = array_map('intval', $request->board_ids);
            $user = auth()->user();
            
            $boardsToCheck = Board::whereIn('id', $requestedIds)->with(['workspace', 'members'])->get();
            
            $allowedBoards = $boardsToCheck->filter(function($b) use ($user) {
                if ($user->hasRole('super-admin')) {
                    return true;
                }

                // QC users can see any board they selected — they are reviewers, not board members
                if ($user->isQc()) {
                    return true;
                }
                
                if ($b->hasMember($user->id)) {
                    return true;
                }
                
                if (!$b->workspace || !$b->workspace->hasMember($user->id)) {
                    return false;
                }
                
                if ($b->visibility === 'workspace' || $b->visibility === 'public') {
                    return true;
                }
                
                return $b->workspace->owner_id === $user->id;
            });
            
            $boardIds = $allowedBoards->pluck('id')->toArray();
        }

        // For QC personal report with no boards selected: search ALL boards.
        // QC reviewers approve cards across every workflow board in the system,
        // they are not board members, so we must follow their comment trail globally.
        $isQcPersonalExport = ($request->boolean('is_personal_report', false)
            || str_contains(request()->path(), 'personal-report')
            || request()->routeIs('*.personal.export', 'reports.personal.export', 'boards.reports.personal.export'))
            && auth()->user()?->isQc();

        if (empty($boardIds)) {
            if ($isQcPersonalExport) {
                // No restriction — let the QC date+comment filter below narrow results
                $boardIds = null; // null = all boards
            } else {
                return Card::whereRaw('1 = 0');
            }
        }

        // $boardIds === null means QC personal export with no specific board selection (search all boards)
        $query = ($boardIds === null)
            ? Card::query()->with(['board', 'boardList', 'assignees', 'labels', 'files', 'allFiles', 'activities', 'comments'])
            : Card::whereIn('board_id', $boardIds)->with(['board', 'boardList', 'assignees', 'labels', 'files', 'allFiles', 'activities', 'comments']);

        // When include_comments is requested, load all non-system comments with author information.
        // Otherwise, for QC personal exports, load only QC-approved comments for timestamp calculation.
        $isPersonalExportCheck = $request->boolean('is_personal_report', false)
            || str_contains(request()->path(), 'personal-report')
            || request()->routeIs('*.personal.export')
            || request()->routeIs('reports.personal.export')
            || request()->routeIs('boards.reports.personal.export');
        $currentUser = auth()->user();
        if ($request->boolean('include_comments', false)) {
            $query->with(['comments' => function($q) {
                $q->where('is_system', false)->orderBy('created_at', 'asc');
            }, 'comments.user', 'allFiles']);
        } elseif ($isPersonalExportCheck && $currentUser && $currentUser->isQc()) {
            $qcUserId = $currentUser->id;
            $query->with(['comments' => function($q) use ($qcUserId) {
                // Only load the QC-approved comments by this user — these are all
                // that assignActivityDates needs for QC timestamp calculation.
                $q->where('user_id', $qcUserId)
                  ->where('is_system', false)
                  ->whereRaw("LOWER(content) LIKE '%qc%approve%'")
                  ->orderBy('created_at', 'asc');
            }]);
        }

        $isPersonalExport = $request->boolean('is_personal_report', false)
            || str_contains(request()->path(), 'personal-report')
            || request()->routeIs('*.personal.export')
            || request()->routeIs('reports.personal.export')
            || request()->routeIs('boards.reports.personal.export');

        $startDate = null;
        $endDate = null;

        // 1. Date Range Filtering
        if ($request->filled('date_range') && $request->date_range !== 'all_time') {
            $now = Carbon::now('Asia/Phnom_Penh');

            switch ($request->date_range) {
                case 'today':
                     $startDate = $now->copy()->startOfDay();
                     $endDate = $now->copy()->endOfDay();
                     break;
                case 'this_week':
                     $startDate = $now->copy()->startOfWeek();
                     $endDate = $now->copy()->endOfWeek();
                     break;
                case 'this_month':
                     $startDate = $now->copy()->startOfMonth();
                     $endDate = $now->copy()->endOfMonth();
                     break;
                case 'last_month':
                     $startDate = $now->copy()->subMonth()->startOfMonth();
                     $endDate = $now->copy()->subMonth()->endOfMonth();
                     break;
                case 'custom':
                case 'custom_period':
                     if ($request->filled('start_date')) {
                         $startDate = Carbon::parse($request->start_date, 'Asia/Phnom_Penh')->startOfDay();
                     }
                     if ($request->filled('end_date')) {
                         $endDate = Carbon::parse($request->end_date, 'Asia/Phnom_Penh')->endOfDay();
                     }
                     break;
            }

            if ($isPersonalExport) {
                // Personal exports handle their own date filtering in the role-based section
            } else if ($startDate || $endDate) {
                $query->where(function($q) use ($startDate, $endDate) {
                    $q->whereHas('activities', function($qa) use ($startDate, $endDate) {
                        $qa->where('action', '!=', 'created');
                        if ($startDate) $qa->where('created_at', '>=', $startDate);
                        if ($endDate) $qa->where('created_at', '<=', $endDate);
                    })->orWhereHas('comments', function($qc) use ($startDate, $endDate) {
                        $qc->where('is_system', false);
                        if ($startDate) $qc->where('created_at', '>=', $startDate);
                        if ($endDate) $qc->where('created_at', '<=', $endDate);
                    });
                });
            }
        }

        // 2. Members Filtering (Standard report filters by card assignees)
        if ($request->filled('member_id') && $request->member_id !== 'all') {
            $memberId = (int)$request->member_id;
            $query->whereHas('assignees', function($q) use ($memberId) {
                $q->where('users.id', $memberId);
            });
        }
        
        // 2b. Assign By Filtering
        if ($request->filled('assign_by_id') && $request->assign_by_id !== 'all') {
            $assignById = (int)$request->assign_by_id;
            $query->where('user_id', $assignById);
        }

        // 2c. Label Filtering
        if ($request->filled('label_id') && $request->label_id !== 'all') {
            $labelId = (int)$request->label_id;
            $query->whereHas('labels', function($q) use ($labelId) {
                $q->where('labels.id', $labelId);
            });
        }

        // 3. Status Filtering
        if ($request->has('statuses') && is_array($request->statuses)) {
            $statuses = $request->statuses;
            $query->where(function($q) use ($statuses) {
                $hasCond = false;

                // Archived tasks status condition
                if (in_array('archived', $statuses)) {
                    $q->orWhere('is_archived', true);
                    $hasCond = true;
                }

                // Check other non-archived statuses
                $dbStatuses = [];
                if (in_array('draft', $statuses)) {
                    $dbStatuses[] = CardStatus::Todo->value;
                    $dbStatuses[] = CardStatus::Rejected->value;
                }
                if (in_array('in_progress', $statuses)) {
                    $dbStatuses[] = CardStatus::InProgress->value;
                }
                if (in_array('review', $statuses)) {
                    $dbStatuses[] = CardStatus::Review->value;
                    $dbStatuses[] = CardStatus::Approved->value;
                }
                if (in_array('completed', $statuses)) {
                    $dbStatuses[] = CardStatus::Done->value;
                }

                if (!empty($dbStatuses)) {
                    $q->orWhere(function($sq) use ($dbStatuses) {
                        $sq->whereIn('status', $dbStatuses)->where('is_archived', false);
                    });
                    $hasCond = true;
                }

                if (!$hasCond) {
                    $q->whereRaw('1 = 0');
                }
            });
        }

        // 4. Role-based QC / Supervisor Personal Report Filtering
        // 4. Role-based QC / Supervisor Personal Report filtering
        if ($isPersonalExport) {
            $user = auth()->user();

            if ($user->isQc()) {
                // QC Personal Report scope:
                //  • ONLY cards where THIS QC user has commented "QC approved" within the selected date range
                //  • System-generated comments are excluded to avoid false positives
                $userId = $user->id;
                $query->where(function($q) use ($userId, $startDate, $endDate) {
                    $q->whereHas('comments', function($qc) use ($userId, $startDate, $endDate) {
                        $qc->where('user_id', $userId)
                           ->where('is_system', false)  // Exclude auto-generated comments that may contain "qc approved"
                           ->whereRaw("LOWER(content) LIKE '%qc%approve%'");
                        if (isset($startDate)) $qc->where('created_at', '>=', $startDate);
                        if (isset($endDate)) $qc->where('created_at', '<=', $endDate);
                    });
                });

                // Eager-load QC approved comments by this user to support revision counting
                $query->with(['qcApprovalComments' => function($q) use ($userId) {
                    $q->where('user_id', $userId)
                      ->where('is_system', false)
                      ->whereRaw("LOWER(content) LIKE '%qc%approve%'")
                      ->orderBy('created_at');
                }]);

            } elseif ($user->isSupervisorRole()) {
                // Supervisor Personal Report scope:
                //  • Cards moved from Supervisor to Approved list
                //  • Cards moved from Supervisor to Blocked list
                //  • Cards approved by Supervisor
                //  • Cards marked as errors by Supervisor
                $userId = $user->id;
                $query->where(function($q) use ($userId, $startDate, $endDate) {
                    $q->whereHas('activities', function($qal) use ($userId, $startDate, $endDate) {
                        $qal->where('user_id', $userId);
                        if (isset($startDate)) $qal->where('created_at', '>=', $startDate);
                        if (isset($endDate)) $qal->where('created_at', '<=', $endDate);
                    })->orWhereHas('comments', function($qc) use ($userId, $startDate, $endDate) {
                        $qc->where('user_id', $userId);
                        if (isset($startDate)) $qc->where('created_at', '>=', $startDate);
                        if (isset($endDate)) $qc->where('created_at', '<=', $endDate);
                    });
                });
            }
        }

        return $query;
    }

    private function assignActivityDates($cards, $startDate, $endDate, $isPersonalExport, $isQc)
    {
        foreach ($cards as $card) {
            $activityDate = null;
            
            // Collect all timestamps
            $timestamps = collect();

            if ($isPersonalExport && $isQc) {
                // For QC Personal Report, ONLY look at "QC approved" comments by this user
                $userId = auth()->id();
                foreach ($card->comments as $comment) {
                    $cText = strtolower($comment->content ?? $comment->body ?? '');
                    if ($comment->user_id === $userId && (str_contains($cText, 'qc approved') || str_contains($cText, 'qc  approved') || (str_contains($cText, 'qc') && str_contains($cText, 'approve')))) {
                        $timestamps->push($comment->created_at);
                    }
                }
            } else {
                // Regular report or other personal reports
                foreach ($card->activities as $activity) {
                    if ($activity->action !== 'created') {
                        $timestamps->push($activity->created_at);
                    }
                }
                foreach ($card->comments as $comment) {
                    if (!$comment->is_system) {
                        $timestamps->push($comment->created_at);
                    }
                }
                // If there are no activities or comments, fallback to updated_at
                if ($timestamps->isEmpty()) {
                    $timestamps->push($card->updated_at);
                }
            }

            // Filter timestamps within the requested date range
            if ($startDate || $endDate) {
                $filtered = $timestamps->filter(function($ts) use ($startDate, $endDate) {
                    $valid = true;
                    if ($startDate && $ts < $startDate) $valid = false;
                    if ($endDate && $ts > $endDate) $valid = false;
                    return $valid;
                });
                
                // If we found activities in the range, use the most recent one in that range
                if ($filtered->isNotEmpty()) {
                    $activityDate = $filtered->max();
                } else {
                    // Fallback to the absolute latest activity (though this card shouldn't be here if strictly filtered)
                    $activityDate = $timestamps->max();
                }
            } else {
                // No date filter, just use the latest activity overall
                $activityDate = $timestamps->max();
            }

            // Set the computed date as a virtual attribute on the card
            // Use Cambodia timezone for display since that's what the user expects
            if ($activityDate) {
                $card->computed_activity_date = \Carbon\Carbon::parse($activityDate)->setTimezone('Asia/Phnom_Penh');
            } else {
                $card->computed_activity_date = $card->created_at ? \Carbon\Carbon::parse($card->created_at)->setTimezone('Asia/Phnom_Penh') : null;
            }
        }
        return $cards;
    }

    private function prepareSmmExportData($cards, $board)
    {
        $errorTasks = 0;
        $weeks = [];

        // Pre-fetch all synced cards and activity logs in bulk to eliminate N+1 queries (fixes 504 Gateway Timeout)
        $syncGroupIds = $cards->pluck('sync_group_id')->filter()->unique();
        $syncedCardsByGroup = $syncGroupIds->isNotEmpty()
            ? \App\Models\Card::with('boardList')->whereIn('sync_group_id', $syncGroupIds)->get()->groupBy('sync_group_id')
            : collect();

        $cardIds = $cards->pluck('id')->filter()->unique();
        $logsByCard = $cardIds->isNotEmpty()
            ? \App\Models\ActivityLog::where('subject_type', \App\Models\Card::class)
                ->whereIn('subject_id', $cardIds)
                ->whereIn('action', ['moved', 'updated'])
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('subject_id')
            : collect();

        foreach ($cards as $c) {
            // Determine the week
            $weekName = 'Other';
            $listName = $c->boardList?->name ?? '';
            
            // Check if it's currently in a Week list
            if (stripos($listName, 'Week') !== false || stripos($listName, 'Final') !== false) {
                $weekName = $listName;
            } else if ($c->sync_group_id && $board) {
                // Find the synced card in the SMM board from pre-fetched collection
                $syncedInGroup = $syncedCardsByGroup->get($c->sync_group_id, collect());
                $smmCard = $syncedInGroup->firstWhere('board_id', $board->id);
                if ($smmCard && $smmCard->boardList) {
                    $smmListName = $smmCard->boardList->name;
                    if (stripos($smmListName, 'Week') !== false || stripos($smmListName, 'Final') !== false) {
                        $weekName = $smmListName;
                    }
                }
            }
            
            // Calculate Error
            $isError = false;
            if ($c->status === \App\Enums\CardStatus::Rejected || !empty($c->rejection_reason)) {
                $isError = true;
            } else if ($c->sync_group_id) {
                // Check if any synced card is in a Blocked list from pre-fetched collection
                $syncedCards = $syncedCardsByGroup->get($c->sync_group_id, collect());
                foreach ($syncedCards as $sc) {
                    if (stripos($sc->boardList?->name ?? '', 'Block') !== false) {
                        $isError = true;
                        break;
                    }
                }
            }
            $c->is_error = $isError;
            if ($isError) {
                $errorTasks++;
            }
            
            // Completed date from pre-fetched ActivityLog collection
            $completedDate = null;
            $isApproved = $c->status === \App\Enums\CardStatus::Approved || $c->status === \App\Enums\CardStatus::Done || stripos($listName, 'Approved') !== false;
            if ($isApproved) {
                $cardLogs = $logsByCard->get($c->id, collect());
                $log = $cardLogs->first(function($l) {
                    return $l->action === 'moved' && (stripos($l->description ?? '', 'Approved') !== false || stripos($l->description ?? '', 'Done') !== false);
                });
                if ($log) {
                    $completedDate = $log->created_at;
                } else {
                     $logStatus = $cardLogs->first(function($l) {
                         return $l->action === 'updated' && stripos($l->description ?? '', 'status to Approved') !== false;
                     });
                     if ($logStatus) {
                         $completedDate = $logStatus->created_at;
                     } else {
                         $completedDate = $c->approved_at ?? $c->updated_at;
                     }
                }
            }
            $c->exact_completed_date = $completedDate ? \Carbon\Carbon::parse($completedDate)->setTimezone('Asia/Phnom_Penh')->format('Y-m-d') : '-';

            $weeks[$weekName][] = $c;
        }

        // Sort weeks logically (Week 1, Week 2, ..., Final Captions, Other)
        uksort($weeks, function($a, $b) {
            if ($a === 'Other') return 1;
            if ($b === 'Other') return -1;
            return strcmp($a, $b);
        });

        // Sort inside each week by Label Priority: Video, Graphic, Content, Listing
        foreach ($weeks as $weekName => &$weekCards) {
            usort($weekCards, function($a, $b) {
                $labelA = $a->labels->first()?->name ?? '';
                $labelB = $b->labels->first()?->name ?? '';

                $priority = function($l) {
                    $l = strtolower($l);
                    if (str_contains($l, 'video')) return 1;
                    if (str_contains($l, 'graphic')) return 2;
                    if (str_contains($l, 'content')) return 3;
                    if (str_contains($l, 'listing')) return 4;
                    return 5;
                };

                return $priority($labelA) <=> $priority($labelB);
            });
        }

        return [
            'groupedCards' => $weeks,
            'errorTasks' => $errorTasks
        ];
    }

    /**
     * Export board tasks to CSV.
     */
    public function exportCsv(Request $request, Board $board)
    {
        @set_time_limit(180);
        @ini_set('memory_limit', '512M');

        $includeDesc = $request->boolean('include_desc', false);
        $includeComments = $request->boolean('include_comments', false);
        $cards = $this->getFilteredCardsQuery($request, $board)->get();

        // Calculate statistics for the summary sections
        $totalTasks = $cards->count();
        $completedTasks = $cards->filter(fn($c) => ($c->status === CardStatus::Done || $c->status === CardStatus::Approved) && !$c->is_archived)->count();
        $archivedTasks = $cards->filter(fn($c) => $c->is_archived)->count();
        $pendingTasks = $totalTasks - $completedTasks - $archivedTasks;
        
        $overdueTasks = $cards->filter(function($c) {
            return $c->due_at 
                && $c->due_at->isPast() 
                && $c->status !== CardStatus::Done 
                && $c->status !== CardStatus::Approved 
                && !$c->is_archived;
        })->count();

        $memberStats = [];
        foreach ($cards as $c) {
            if ($c->is_archived) {
                continue;
            }
            $assignees = $c->assignees;
            if ($assignees->isEmpty()) {
                if (!isset($memberStats['Unassigned'])) {
                    $memberStats['Unassigned'] = ['completed' => 0, 'pending' => 0, 'total' => 0];
                }
                if (($c->status === CardStatus::Done || $c->status === CardStatus::Approved) && !$c->is_archived) {
                    $memberStats['Unassigned']['completed']++;
                } else {
                    $memberStats['Unassigned']['pending']++;
                }
                $memberStats['Unassigned']['total']++;
            } else {
                foreach ($assignees as $u) {
                    if (!isset($memberStats[$u->name])) {
                        $memberStats[$u->name] = ['completed' => 0, 'pending' => 0, 'total' => 0];
                    }
                    if (($c->status === CardStatus::Done || $c->status === CardStatus::Approved) && !$c->is_archived) {
                        $memberStats[$u->name]['completed']++;
                    } else {
                        $memberStats[$u->name]['pending']++;
                    }
                    $memberStats[$u->name]['total']++;
                }
            }
        }

        $period = 'All Time';
        $filterStartDate = null;
        $filterEndDate = null;
        $now = Carbon::now('Asia/Phnom_Penh');

        if ($request->filled('date_range')) {
            switch ($request->date_range) {
                case 'today': 
                    $period = 'Today'; 
                    $filterStartDate = $now->copy()->startOfDay();
                    $filterEndDate = $now->copy()->endOfDay();
                    break;
                case 'this_week': 
                    $period = 'This Week';
                    $filterStartDate = $now->copy()->startOfWeek();
                    $filterEndDate = $now->copy()->endOfWeek();
                    break;
                case 'this_month': 
                    $period = 'This Month';
                    $filterStartDate = $now->copy()->startOfMonth();
                    $filterEndDate = $now->copy()->endOfMonth();
                    break;
                case 'last_month': 
                    $period = 'Last Month';
                    $filterStartDate = $now->copy()->subMonth()->startOfMonth();
                    $filterEndDate = $now->copy()->subMonth()->endOfMonth();
                    break;
                case 'custom':
                case 'custom_period':
                    $start = $request->start_date ? Carbon::parse($request->start_date, 'Asia/Phnom_Penh')->format('M d, Y') : 'Beginning';
                    $end = $request->end_date ? Carbon::parse($request->end_date, 'Asia/Phnom_Penh')->format('M d, Y') : 'End';
                    $period = "$start - $end";
                    if ($request->filled('start_date')) $filterStartDate = Carbon::parse($request->start_date, 'Asia/Phnom_Penh')->startOfDay();
                    if ($request->filled('end_date')) $filterEndDate = Carbon::parse($request->end_date, 'Asia/Phnom_Penh')->endOfDay();
                    break;
            }
        }

        $cards = $this->assignActivityDates($cards, $filterStartDate, $filterEndDate, false, false);
        $smmData = $this->prepareSmmExportData($cards, $board);

        if ($request->boolean('raw') || $request->input('format') === 'raw_csv') {
            return $this->streamRawCsv($smmData['groupedCards'], "board-report-{$board->slug}-" . now()->format('Y-m-d') . '.csv', $includeComments);
        }

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="board-report-' . now()->format('Y-m-d') . '.xls"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $response = response()->view('boards.export-xls', [
            'board' => $board,
            'groupedCards' => $smmData['groupedCards'],
            'period' => $period,
            'totalTasks' => $totalTasks,
            'completedTasks' => $completedTasks,
            'pendingTasks' => $pendingTasks,
            'overdueTasks' => $overdueTasks,
            'errorTasks' => $smmData['errorTasks'],
            'archivedTasks' => $archivedTasks,
            'memberStats' => $memberStats,
            'includeDesc' => $includeDesc,
            'includeComments' => $includeComments,
            'exportDate' => now()->format('M d, Y g:i A')
        ]);

        foreach ($headers as $key => $value) {
            $response->header($key, $value);
        }

        return $response;
    }

    /**
     * Render the print-optimized PDF view.
     */
    public function exportPdf(Request $request, Board $board)
    {
        @set_time_limit(180);
        @ini_set('memory_limit', '512M');

        $cards = $this->getFilteredCardsQuery($request, $board)->get();

        // Calculate statistics - Completed tasks includes Done and Approved
        $totalTasks = $cards->count();
        $completedTasks = $cards->filter(fn($c) => ($c->status === CardStatus::Done || $c->status === CardStatus::Approved) && !$c->is_archived)->count();
        $archivedTasks = $cards->filter(fn($c) => $c->is_archived)->count();
        $pendingTasks = $totalTasks - $completedTasks - $archivedTasks;
        
        $overdueTasks = $cards->filter(function($c) {
            return $c->due_at 
                && $c->due_at->isPast() 
                && $c->status !== CardStatus::Done 
                && $c->status !== CardStatus::Approved 
                && !$c->is_archived;
        })->count();

        // Team productivity summary: tasks completed & pending per member
        $memberStats = [];
        foreach ($cards as $c) {
            if ($c->is_archived) {
                continue; // Do not list members for archived cards in productivity summary
            }

            $assignees = $c->assignees;
            if ($assignees->isEmpty()) {
                if (!isset($memberStats['Unassigned'])) {
                    $memberStats['Unassigned'] = ['completed' => 0, 'pending' => 0, 'total' => 0, 'team_role' => 'ZZ_Unassigned'];
                }
                if (($c->status === CardStatus::Done || $c->status === CardStatus::Approved) && !$c->is_archived) {
                    $memberStats['Unassigned']['completed']++;
                } else {
                    $memberStats['Unassigned']['pending']++;
                }
                $memberStats['Unassigned']['total']++;
            } else {
                foreach ($assignees as $u) {
                    if (!isset($memberStats[$u->name])) {
                        $memberStats[$u->name] = ['completed' => 0, 'pending' => 0, 'total' => 0, 'team_role' => $u->team_role ?? 'ZZ_Other'];
                    }
                    if (($c->status === CardStatus::Done || $c->status === CardStatus::Approved) && !$c->is_archived) {
                        $memberStats[$u->name]['completed']++;
                    } else {
                        $memberStats[$u->name]['pending']++;
                    }
                    $memberStats[$u->name]['total']++;
                }
            }
        }
        
        uksort($memberStats, function($a, $b) use ($memberStats) {
            $teamA = $memberStats[$a]['team_role'] ?? '';
            $teamB = $memberStats[$b]['team_role'] ?? '';
            if ($teamA === $teamB) {
                return strcmp($a, $b);
            }
            return strcmp($teamA, $teamB);
        });

        // Get report period string
        $period = 'All Time';
        $filterStartDate = null;
        $filterEndDate = null;
        $now = Carbon::now('Asia/Phnom_Penh');
        
        if ($request->filled('date_range')) {
            switch ($request->date_range) {
                case 'today': 
                    $period = 'Today'; 
                    $filterStartDate = $now->copy()->startOfDay();
                    $filterEndDate = $now->copy()->endOfDay();
                    break;
                case 'this_week': 
                    $period = 'This Week'; 
                    $filterStartDate = $now->copy()->startOfWeek();
                    $filterEndDate = $now->copy()->endOfWeek();
                    break;
                case 'this_month': 
                    $period = 'This Month'; 
                    $filterStartDate = $now->copy()->startOfMonth();
                    $filterEndDate = $now->copy()->endOfMonth();
                    break;
                case 'last_month': 
                    $period = 'Last Month'; 
                    $filterStartDate = $now->copy()->subMonth()->startOfMonth();
                    $filterEndDate = $now->copy()->subMonth()->endOfMonth();
                    break;
                case 'custom':
                case 'custom_period':
                    $start = $request->start_date ? Carbon::parse($request->start_date, 'Asia/Phnom_Penh')->format('M d, Y') : 'Beginning';
                    $end = $request->end_date ? Carbon::parse($request->end_date, 'Asia/Phnom_Penh')->format('M d, Y') : 'End';
                    $period = "$start - $end";
                    if ($request->filled('start_date')) $filterStartDate = Carbon::parse($request->start_date, 'Asia/Phnom_Penh')->startOfDay();
                    if ($request->filled('end_date')) $filterEndDate = Carbon::parse($request->end_date, 'Asia/Phnom_Penh')->endOfDay();
                    break;
            }
        }

        $cards = $this->assignActivityDates($cards, $filterStartDate, $filterEndDate, false, false);

        $labelStats = [];
        foreach ($cards as $c) {
            if ($c->is_archived) continue;
            
            $validLabels = $c->labels->filter(fn($l) => strtoupper($l->name) !== 'SMM');
            
            if ($validLabels->isEmpty()) {
                $labelStats['No Label'] = ($labelStats['No Label'] ?? 0) + 1;
            } else {
                foreach ($validLabels as $label) {
                    $name = $label->name;
                    $labelStats[$name] = ($labelStats[$name] ?? 0) + 1;
                }
            }
        }

        $copyText = '';
        if (auth()->user()->isQc()) {
            $groupedByLabel = [];
            foreach ($cards as $c) {
                if ($c->is_archived) continue;
                
                $validLabels = $c->labels->filter(fn($l) => strtoupper($l->name) !== 'SMM');
                
                if ($validLabels->isEmpty()) {
                    $groupedByLabel['No Label'][] = $c->title;
                } else {
                    foreach ($validLabels as $label) {
                        $groupedByLabel[$label->name][] = $c->title;
                    }
                }
            }
            foreach ($groupedByLabel as $labelName => $titles) {
                $copyText .= $labelName . ' (total ' . count($titles) . ")\n";
                foreach ($titles as $idx => $title) {
                    $copyText .= ($idx + 1) . '.' . $title . "\n";
                }
                $copyText .= "\n";
            }
        } else {
            $count = 0;
            foreach ($cards as $c) {
                if ($c->is_archived) continue;
                $count++;
                $copyText .= $count . '.' . $c->title . "\n";
            }
            $copyText .= "Total: " . $count . "\n";
        }

        // PDF display option
        $includeDesc = $request->boolean('include_desc', false);
        $includeComments = $request->boolean('include_comments', false);

        $smmData = $this->prepareSmmExportData($cards, $board);

        return view('boards.export-pdf', [
            'board' => $board,
            'cards' => $cards,
            'groupedCards' => $smmData['groupedCards'],
            'period' => $period,
            'totalTasks' => $totalTasks,
            'completedTasks' => $completedTasks,
            'pendingTasks' => $pendingTasks,
            'overdueTasks' => $overdueTasks,
            'errorTasks' => $smmData['errorTasks'],
            'archivedTasks' => $archivedTasks,
            'memberStats' => $memberStats,
            'labelStats' => $labelStats,
            'copyText' => $copyText,
            'includeDesc' => $includeDesc,
            'includeComments' => $includeComments,
            'exportDate' => now()->format('M d, Y g:i A'),
            'reportUrl' => request()->fullUrl(),
            'isQcReport' => true,
            'startDate' => $filterStartDate,
            'endDate' => $filterEndDate,
        ]);
    }

    /**
     * Render the setup page for compiling a consolidated Personal Report.
     */
    public function personalReport(Request $request)
    {
        abort_unless(auth()->user()->isQcOrSupervisor(), 403, 'Unauthorized access to personal reports.');

        // Include hidden boards so QC/Supervisor can select past-month boards
        // (boards are hidden at month-end but must still be available for historical exports)
        $workspaces = $this->getAuthorizedWorkspaces(auth()->user(), includeHidden: true);

        // Filter to include ONLY Workflow boards (strictly exclude Planning boards)
        $hiddenBoardsCount = 0;
        foreach ($workspaces as $workspace) {
            $filteredBoards = $workspace->boards->filter(function ($board) {
                $name = strtolower($board->name ?? '');
                return str_contains($name, 'workflow') && !str_contains($name, 'planning');
            })->values();

            $workspace->setRelation('boards', $filteredBoards);
            $workspace->has_active_workflow_boards = $filteredBoards->contains(fn($b) => !$b->is_hidden);
            $workspace->active_workflow_boards_count = $filteredBoards->filter(fn($b) => !$b->is_hidden)->count();
            $workspace->hidden_workflow_boards_count = $filteredBoards->filter(fn($b) => (bool) $b->is_hidden)->count();

            $hiddenBoardsCount += $workspace->hidden_workflow_boards_count;
        }

        // Filter out workspaces that have no workflow boards
        $workspaces = $workspaces->filter(fn($ws) => $ws->boards->isNotEmpty())->values();

        $users = \App\Models\User::where('is_active', true)->orderBy('name')->get();

        return view('reports.personal', compact('workspaces', 'users', 'hiddenBoardsCount'));
    }

    /**
     * Export consolidated Personal Report to CSV or PDF.
     */
    public function exportPersonalReport(Request $request)
    {
        @set_time_limit(180);
        @ini_set('memory_limit', '512M');

        abort_unless(auth()->user()->isQcOrSupervisor(), 403, 'Unauthorized access to personal reports.');

        $cards = $this->getFilteredCardsQuery($request, null)->get();

        if (auth()->user()?->isQc()) {
            // Deduplicate twin cards across synced boards (e.g. Workflow, Planning, and SMM boards)
            // Always prefer the Workflow board card so it has the correct list ('Approved') and assignee
            $cards = $cards->groupBy(fn($c) => $c->sync_group_id ?: ('card_' . $c->id))->map(function($group) {
                return $group->first(fn($c) => stripos($c->board?->name ?? '', 'workflow') !== false) ?? $group->first();
            })->values();
        }

        $format = $request->input('format', 'pdf');
        $includeDesc = $request->boolean('include_desc', false);
        $includeComments = $request->boolean('include_comments', false);

        $period = 'All Time';
        $filterStartDate = null;
        $filterEndDate = null;
        $now = Carbon::now('Asia/Phnom_Penh');
        
        if ($request->filled('date_range')) {
            switch ($request->date_range) {
                case 'today': 
                    $period = 'Today'; 
                    $filterStartDate = $now->copy()->startOfDay();
                    $filterEndDate = $now->copy()->endOfDay();
                    break;
                case 'this_week': 
                    $period = 'This Week'; 
                    $filterStartDate = $now->copy()->startOfWeek();
                    $filterEndDate = $now->copy()->endOfWeek();
                    break;
                case 'this_month': 
                    $period = 'This Month'; 
                    $filterStartDate = $now->copy()->startOfMonth();
                    $filterEndDate = $now->copy()->endOfMonth();
                    break;
                case 'last_month': 
                    $period = 'Last Month'; 
                    $filterStartDate = $now->copy()->subMonth()->startOfMonth();
                    $filterEndDate = $now->copy()->subMonth()->endOfMonth();
                    break;
                case 'custom':
                case 'custom_period':
                    $start = $request->start_date ? Carbon::parse($request->start_date, 'Asia/Phnom_Penh')->format('M d, Y') : 'Beginning';
                    $end = $request->end_date ? Carbon::parse($request->end_date, 'Asia/Phnom_Penh')->format('M d, Y') : 'End';
                    $period = "$start - $end";
                    if ($request->filled('start_date')) $filterStartDate = Carbon::parse($request->start_date, 'Asia/Phnom_Penh')->startOfDay();
                    if ($request->filled('end_date')) $filterEndDate = Carbon::parse($request->end_date, 'Asia/Phnom_Penh')->endOfDay();
                    break;
            }
        }

        $cards = $this->assignActivityDates($cards, $filterStartDate, $filterEndDate, true, auth()->user()->isQc());

        if ($format === 'csv') {
            // A card is "completed" when physically in a list named "Approved" (Supervisor approved it).
            // The `status` field is NOT reliable — all cards keep status='todo' even after being moved.
            $isCompleted = fn($c) => !$c->is_archived
                && stripos($c->boardList?->name ?? '', 'Approved') !== false;

            $totalTasks    = $cards->count();
            $completedTasks = $cards->filter($isCompleted)->count();
            $archivedTasks  = $cards->filter(fn($c) => $c->is_archived)->count();
            $pendingTasks   = $totalTasks - $completedTasks - $archivedTasks;

            // For QC report: errors = cards that required revisions (had to be QC approved more than once)
            // For Supervisor report: errors = cards with rejection_reason or Rejected status
            if (auth()->user()->isQc()) {
                $errorTasks = $cards->filter(fn($c) => ($c->qcApprovalComments?->count() ?? 0) > 1)->count();
            } else {
                $errorTasks = $cards->filter(fn($c) => $c->status === \App\Enums\CardStatus::Rejected || !empty($c->rejection_reason))->count();
            }
            
            // Overdue = has a past deadline AND is NOT completed (not in Approved list) AND not archived
            $overdueTasks = $cards->filter(function($c) use ($isCompleted) {
                $deadline = $c->deadline ?? $c->due_at;
                return $deadline
                    && \Carbon\Carbon::parse($deadline)->isPast()
                    && !$isCompleted($c)
                    && !$c->is_archived;
            })->count();

            $memberStats = [];
            foreach ($cards as $c) {
                if ($c->is_archived) continue;
                $done = $isCompleted($c);
                $assignees = $c->assignees;
                if ($assignees->isEmpty()) {
                    if (!isset($memberStats['Unassigned'])) {
                        $memberStats['Unassigned'] = ['completed' => 0, 'pending' => 0, 'total' => 0];
                    }
                    $done ? $memberStats['Unassigned']['completed']++ : $memberStats['Unassigned']['pending']++;
                    $memberStats['Unassigned']['total']++;
                } else {
                    foreach ($assignees as $u) {
                        if (!isset($memberStats[$u->name])) {
                            $memberStats[$u->name] = ['completed' => 0, 'pending' => 0, 'total' => 0];
                        }
                        $done ? $memberStats[$u->name]['completed']++ : $memberStats[$u->name]['pending']++;
                        $memberStats[$u->name]['total']++;
                    }
                }
            }

            $smmData = $this->prepareSmmExportData($cards, null);

            if ($request->boolean('raw') || $format === 'raw_csv') {
                return $this->streamRawCsv($smmData['groupedCards'], 'personal-report-' . now()->format('Y-m-d') . '.csv', $includeComments);
            }

            $headers = [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="personal-report-' . now()->format('Y-m-d') . '.xls"',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0'
            ];

            $response = response()->view('boards.export-xls', [
                'board' => null,
                'cards' => $cards,
                'groupedCards' => $smmData['groupedCards'],
                'period' => $period,
                'totalTasks' => $totalTasks,
                'completedTasks' => $completedTasks,
                'pendingTasks' => $pendingTasks,
                'overdueTasks' => $overdueTasks,
                'archivedTasks' => $archivedTasks,
                'errorTasks' => $smmData['errorTasks'] ?? $errorTasks,
                'memberStats' => $memberStats,
                'includeDesc' => $includeDesc,
                'includeComments' => $includeComments,
                'exportDate' => now()->format('M d, Y g:i A')
            ]);

            foreach ($headers as $key => $value) {
                $response->header($key, $value);
            }

            return $response;
        }

        // PDF Consolidated Report
        // A card is "completed" when physically in a list named "Approved" (Supervisor approved it).
        // The `status` field is NOT reliable — all cards keep status='todo' even after being moved.
        $isCompleted = fn($c) => !$c->is_archived
            && stripos($c->boardList?->name ?? '', 'Approved') !== false;

        $totalTasks    = $cards->count();
        $completedTasks = $cards->filter($isCompleted)->count();
        $archivedTasks  = $cards->filter(fn($c) => $c->is_archived)->count();
        $pendingTasks   = $totalTasks - $completedTasks - $archivedTasks;

        // For QC report: errors = cards that required revisions (had to be QC approved more than once)
        // For Supervisor report: errors = cards with rejection_reason or Rejected status
        if (auth()->user()->isQc()) {
            $errorTasks = $cards->filter(fn($c) => ($c->qcApprovalComments?->count() ?? 0) > 1)->count();
        } else {
            $errorTasks = $cards->filter(fn($c) => $c->status === \App\Enums\CardStatus::Rejected || !empty($c->rejection_reason))->count();
        }
        
        // Overdue = has a past deadline AND is NOT completed AND not archived
        $overdueTasks = $cards->filter(function($c) use ($isCompleted) {
            $deadline = $c->deadline ?? $c->due_at;
            return $deadline
                && \Carbon\Carbon::parse($deadline)->isPast()
                && !$isCompleted($c)
                && !$c->is_archived;
        })->count();

        $memberStats = [];
        foreach ($cards as $c) {
            if ($c->is_archived) continue; // Do not list archived cards in productivity summary

            $done      = $isCompleted($c);
            $assignees = $c->assignees;
            if ($assignees->isEmpty()) {
                if (!isset($memberStats['Unassigned'])) {
                    $memberStats['Unassigned'] = ['completed' => 0, 'pending' => 0, 'total' => 0, 'team_role' => 'ZZ_Unassigned'];
                }
                $done ? $memberStats['Unassigned']['completed']++ : $memberStats['Unassigned']['pending']++;
                $memberStats['Unassigned']['total']++;
            } else {
                foreach ($assignees as $u) {
                    if (!isset($memberStats[$u->name])) {
                        $memberStats[$u->name] = ['completed' => 0, 'pending' => 0, 'total' => 0, 'team_role' => $u->team_role ?? 'ZZ_Other'];
                    }
                    $done ? $memberStats[$u->name]['completed']++ : $memberStats[$u->name]['pending']++;
                    $memberStats[$u->name]['total']++;
                }
            }
        }
        
        uksort($memberStats, function($a, $b) use ($memberStats) {
            $teamA = $memberStats[$a]['team_role'] ?? '';
            $teamB = $memberStats[$b]['team_role'] ?? '';
            if ($teamA === $teamB) {
                return strcmp($a, $b);
            }
            return strcmp($teamA, $teamB);
        });

        $labelStats = [];
        foreach ($cards as $c) {
            if ($c->is_archived) continue;
            
            $validLabels = $c->labels->filter(fn($l) => strtoupper($l->name) !== 'SMM');
            
            if ($validLabels->isEmpty()) {
                $labelStats['No Label'] = ($labelStats['No Label'] ?? 0) + 1;
            } else {
                foreach ($validLabels as $label) {
                    $name = $label->name;
                    $labelStats[$name] = ($labelStats[$name] ?? 0) + 1;
                }
            }
        }

        $copyText = '';
        if (auth()->user()->isQc()) {
            $groupedByLabel = [];
            foreach ($cards as $c) {
                if ($c->is_archived) continue;
                
                $validLabels = $c->labels->filter(fn($l) => strtoupper($l->name) !== 'SMM');
                
                if ($validLabels->isEmpty()) {
                    $groupedByLabel['No Label'][] = $c->title;
                } else {
                    foreach ($validLabels as $label) {
                        $groupedByLabel[$label->name][] = $c->title;
                    }
                }
            }
            foreach ($groupedByLabel as $labelName => $titles) {
                $copyText .= $labelName . ' (total ' . count($titles) . ")\n";
                foreach ($titles as $idx => $title) {
                    $copyText .= ($idx + 1) . '.' . $title . "\n";
                }
                $copyText .= "\n";
            }
        } else {
            $count = 0;
            foreach ($cards as $c) {
                if ($c->is_archived) continue;
                $count++;
                $copyText .= $count . '.' . $c->title . "\n";
            }
            $copyText .= "Total: " . $count . "\n";
        }

        $smmData = $this->prepareSmmExportData($cards, null);

        return view('boards.export-pdf', [
            'board'         => null, // Consolidated report has no single board context
            'cards'         => $cards,
            'groupedCards'  => $smmData['groupedCards'],
            'period'        => $period,
            'totalTasks'    => $totalTasks,
            'completedTasks'=> $completedTasks,
            'pendingTasks'  => $pendingTasks,
            'overdueTasks'  => $overdueTasks,
            'archivedTasks' => $archivedTasks,
            'errorTasks'    => $errorTasks,
            'memberStats'   => $memberStats,
            'labelStats'    => $labelStats,
            'copyText'      => $copyText,
            'includeDesc'   => $includeDesc,
            'includeComments'=> $includeComments,
            'exportDate'    => now()->format('M d, Y g:i A'),
            // QC-specific: show revision count column
            'isQcReport'    => auth()->user()->isQc(),
            'reportUrl'     => request()->fullUrl(),
            'startDate'     => $filterStartDate ?? null,
            'endDate'       => $filterEndDate ?? null,
        ]);
    }

    /**
     * Stream a raw RFC-4180 CSV file with UTF-8 BOM.
     */
    private function streamRawCsv(array $groupedCards, string $filename, bool $includeComments = false): StreamedResponse
    {
        return response()->streamDownload(function () use ($groupedCards, $includeComments) {
            $out = fopen('php://output', 'w');
            // Write UTF-8 BOM for Excel / Numbers compatibility
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            $columns = ['Class', 'Task / Title', 'Status', 'Assigned Members', 'Activity Date', 'Due Date', 'Completed Date', 'Attached', 'Labels'];
            if ($includeComments) {
                $columns[] = 'Comments';
            }
            fputcsv($out, $columns);

            foreach ($groupedCards as $cardsInWeek) {
                foreach ($cardsInWeek as $c) {
                    $status = $c->is_archived ? 'Archived' : ($c->status ? $c->status->label() : 'To Do');
                    $activityDate = $c->computed_activity_date ? $c->computed_activity_date->format('Y-m-d H:i') : ($c->created_at ? $c->created_at->format('Y-m-d H:i') : 'N/A');
                    $dueDate = $c->due_at ? $c->due_at->format('Y-m-d') : 'None';
                    $completedDate = $c->exact_completed_date ?? '-';
                    $labels = $c->labels->pluck('name')->join(', ');

                    $attachedParts = [];
                    $imageFiles = $c->files->filter(fn($f) => $f->is_image)->values();
                    $linksList  = $c->files->filter(fn($f) => $f->disk === 'url' || $f->mime_type === 'link')->values();
                    $otherFiles = $c->files->filter(fn($f) => !$f->is_image && $f->disk !== 'url' && $f->mime_type !== 'link')->values();

                    foreach ($imageFiles as $i => $img) {
                        $label = $imageFiles->count() === 1 ? '1 Images' : 'Image ' . ($i + 1);
                        $attachedParts[] = "{$label}: {$img->preview_url}";
                    }
                    foreach ($otherFiles as $i => $file) {
                        $label = $otherFiles->count() === 1 ? '1 Files' : 'File ' . ($i + 1);
                        $attachedParts[] = "{$label}: {$file->preview_url}";
                    }
                    foreach ($linksList as $i => $link) {
                        $attachedParts[] = "Link " . ($i + 1) . ": {$link->path}";
                    }

                    $attached = !empty($attachedParts) ? implode("; ", $attachedParts) : '-';

                    $row = [
                        $c->smm_class_label ?? '-',
                        $c->title,
                        $status,
                        $c->assignees->pluck('name')->join(', ') ?: 'Unassigned',
                        $activityDate,
                        $dueDate,
                        $completedDate,
                        $attached,
                        $labels,
                    ];

                    if ($includeComments) {
                        $commentEntries = [];
                        if ($c->relationLoaded('comments') && $c->comments->isNotEmpty()) {
                            foreach ($c->comments as $cmt) {
                                $parsed = self::extractScreenshotsAndClean($cmt->body);
                                $author = $cmt->user->name ?? 'System';
                                $date = $cmt->created_at ? $cmt->created_at->format('Y-m-d H:i') : '';
                                $entry = "[{$author} - {$date}]: " . $parsed['text'];
                                if (!empty($parsed['screenshots'])) {
                                    $scrList = [];
                                    foreach ($parsed['screenshots'] as $sIdx => $scr) {
                                        $sUrl = is_array($scr) ? ($scr['url'] ?? $scr['src'] ?? '') : $scr;
                                        $sName = is_array($scr) ? ($scr['name'] ?? ('Screenshot ' . ($sIdx + 1))) : ('Screenshot ' . ($sIdx + 1));
                                        $scrList[] = "{$sName}: {$sUrl}";
                                    }
                                    $entry .= " [Screenshots: " . implode(', ', $scrList) . "]";
                                }
                                $commentEntries[] = $entry;
                            }
                        }
                        $row[] = !empty($commentEntries) ? implode("\n", $commentEntries) : '-';
                    }

                    fputcsv($out, $row);
                }
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
