<?php

namespace App\Http\Controllers\Board;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Models\Board;
use App\Models\Card;
use App\Models\User;
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
                $isQc = $user->isQc();
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

        $isQc = $user->isQc();
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
    private function getFilteredCardsQuery(Request $request, ?Board $board = null, ?User $targetUser = null)
    {
        $boardIds = [];
        if ($board) {
            $boardIds = [$board->id];
        }
        
        $targetUser = $targetUser ?? auth()->user();
        if ($targetUser && ($targetUser->isSupervisorRole() || $targetUser->hasAnyRole(['super-admin', 'admin-digital', 'admin'])) && $request->filled('reviewer_id')) {
            $reviewer = \App\Models\User::find($request->input('reviewer_id'));
            if ($reviewer) $targetUser = $reviewer;
        } elseif ($targetUser && ($targetUser->isSupervisorRole() || $targetUser->hasAnyRole(['super-admin', 'admin-digital', 'admin'])) && $request->filled('user_id')) {
            $reviewer = \App\Models\User::find($request->input('user_id'));
            if ($reviewer) $targetUser = $reviewer;
        }

        if ($request->has('board_ids') && is_array($request->board_ids)) {
            $requestedIds = array_map('intval', $request->board_ids);
            $user = $targetUser ?? auth()->user();
            
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
            && $targetUser?->isQc();

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
            ? Card::query()->with(['board', 'boardList', 'assignees', 'labels', 'files', 'allFiles', 'activities', 'comments', 'checklists.items.assignedUser', 'checklists.items.markedBy', 'checklists.items.issueBy'])
            : Card::whereIn('board_id', $boardIds)->with(['board', 'boardList', 'assignees', 'labels', 'files', 'allFiles', 'activities', 'comments', 'checklists.items.assignedUser', 'checklists.items.markedBy', 'checklists.items.issueBy']);

        // When include_comments is requested, load all non-system comments with author information.
        // Otherwise, for QC personal exports, load only QC-approved comments for timestamp calculation.
        $isPersonalExportCheck = $request->boolean('is_personal_report', false)
            || str_contains(request()->path(), 'personal-report')
            || request()->routeIs('*.personal.export')
            || request()->routeIs('reports.personal.export')
            || request()->routeIs('boards.reports.personal.export');
        $currentUser = $targetUser ?? auth()->user();
        if ($request->boolean('include_comments', false)) {
            $query->with(['comments' => function($q) {
                $q->where('is_system', false)->orderBy('created_at', 'asc');
            }, 'comments.user', 'allFiles']);
        } elseif ($isPersonalExportCheck && $currentUser && $currentUser->isQc()) {
            $qcUserId = $currentUser->id;
            $qcUserIds = [$qcUserId];
            if ($currentUser->isDara()) $qcUserIds = array_unique(array_merge($qcUserIds, [12, 24]));
            if ($currentUser->isKim()) $qcUserIds = array_unique(array_merge($qcUserIds, [13]));

            $query->with(['comments' => function($q) use ($qcUserIds) {
                // Only load the QC-approved comments by this user — these are all
                // that assignActivityDates needs for QC timestamp calculation.
                $q->whereIn('user_id', $qcUserIds)
                  ->where('is_system', false)
                  ->where(function($sub) {
                      $sub->whereRaw("LOWER(content) LIKE '%qc%approve%'")
                          ->orWhereRaw("LOWER(content) LIKE '%production%approve%'")
                          ->orWhereRaw("LOWER(content) LIKE '%production approved%'")
                          ->orWhereRaw("LOWER(content) LIKE '%production approved smm%'")
                          ->orWhereRaw("LOWER(content) LIKE '%head%approve%'")
                          ->orWhereRaw("LOWER(content) LIKE '%approved%smm%'");
                  })
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

        // 2. Members Filtering (Standard report filters by card assignees or SMM checklist items)
        if ($request->filled('member_id') && $request->member_id !== 'all') {
            $memberId = (int)$request->member_id;
            $query->where(function($q) use ($memberId) {
                $q->whereHas('assignees', function($aq) use ($memberId) {
                    $aq->where('users.id', $memberId);
                })->orWhereHas('checklists.items', function($ci) use ($memberId) {
                    $ci->where('assigned_user_id', $memberId)
                       ->orWhereJsonContains('assigned_user_ids', $memberId)
                       ->orWhere('completed_by', $memberId)
                       ->orWhere('marked_by', $memberId)
                       ->orWhere('approved_by', $memberId);
                });
            });
        }
        
        // 2b. Assign By Filtering
        if ($request->filled('assign_by_id') && $request->assign_by_id !== 'all') {
            $assignById = (int)$request->assign_by_id;
            $query->where('user_id', $assignById);
        }

        // 2c. Label Filtering (Single or Multi-select)
        $labelIds = [];
        if ($request->has('label_ids')) {
            $raw = $request->input('label_ids');
            $labelIds = is_array($raw) ? $raw : explode(',', $raw);
        } elseif ($request->has('labels')) {
            $raw = $request->input('labels');
            $labelIds = is_array($raw) ? $raw : explode(',', $raw);
        } elseif ($request->filled('label_id') && $request->label_id !== 'all') {
            $labelIds = [$request->label_id];
        }

        $labelIds = array_values(array_filter($labelIds, fn($val) => !empty($val) && $val !== 'all'));

        if (!empty($labelIds)) {
            $numericIds = array_map('intval', array_filter($labelIds, fn($v) => is_numeric($v)));
            $nameIds = array_values(array_filter($labelIds, fn($v) => !is_numeric($v)));

            // If names were passed (e.g. 'Graphic', 'Video'), resolve any matching label IDs as well
            if (!empty($nameIds)) {
                $foundIds = \App\Models\Label::whereIn('name', $nameIds)->pluck('id')->all();
                $numericIds = array_unique(array_merge($numericIds, $foundIds));
            }
            if (!empty($numericIds)) {
                $resolvedNames = \App\Models\Label::whereIn('id', $numericIds)->pluck('name')->all();
                $nameIds = array_unique(array_merge($nameIds, $resolvedNames));
            }

            $query->where(function($masterQ) use ($numericIds, $nameIds) {
                if (!empty($numericIds)) {
                    $masterQ->whereHas('labels', function($q) use ($numericIds) {
                        $q->whereIn('labels.id', $numericIds);
                    });
                }
                if (!empty($nameIds)) {
                    $masterQ->orWhere(function($orQ) use ($nameIds) {
                        foreach ($nameIds as $name) {
                            $orQ->orWhere('cards.label', 'like', "%{$name}%");
                        }
                    });
                }
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

        // 4. Role-based QC / Supervisor Personal Report filtering
        if ($isPersonalExport) {
            $user = $targetUser ?? auth()->user();

            if ($user && $user->isQc()) {
                // QC / Production Lead Personal Report scope:
                //  • ONLY cards where THIS QC user has commented approval within the selected date range
                //  • System-generated comments are excluded to avoid false positives
                $userId = $user->id;
                $uName = strtolower($user->name ?? '');
                $uUsername = strtolower($user->username ?? '');
                $isDara = str_contains($uUsername, 'dara') || str_contains($uName, 'dara') || in_array($user->id, [12, 24]);
                $isKim = str_contains($uUsername, 'kim') || str_contains($uName, 'kim') || $user->id === 13;

                // STRICT TEAM SEPARATION: Ensure no confused counts between Mr. Kim and Mr. Dara
                if ($isDara) {
                    // Mr. Dara leads Team A: exclude boards that are specifically Team B (and NOT also Team A)
                    $query->whereDoesntHave('board', function($b) {
                        $b->where(function($sub) {
                            $sub->whereRaw("(LOWER(name) LIKE '%team b%' OR LOWER(name) LIKE '%teamb%' OR LOWER(name) LIKE '%team-b%')")
                                ->whereRaw("(LOWER(name) NOT LIKE '%team a%' AND LOWER(name) NOT LIKE '%teama%' AND LOWER(name) NOT LIKE '%team-a%' AND LOWER(name) NOT LIKE '%& b%' AND LOWER(name) NOT LIKE '%&b%')");
                        });
                    });
                    $query->whereDoesntHave('boardList', function($bl) {
                        $bl->whereRaw("(LOWER(name) LIKE '%team b%' OR LOWER(name) LIKE '%teamb%') AND LOWER(name) NOT LIKE '%team a%'");
                    });
                    // Exclude cards designated specifically for Team B
                    $query->where(function($q) {
                        $q->whereNull('team')
                          ->orWhere('team', '!=', 'B')
                          ->orWhere('team', 'Both');
                    });
                } elseif ($isKim) {
                    // Mr. Kim leads Team B: exclude boards that are specifically Team A (and NOT also Team B)
                    $query->whereDoesntHave('board', function($b) {
                        $b->where(function($sub) {
                            $sub->whereRaw("(LOWER(name) LIKE '%team a%' OR LOWER(name) LIKE '%teama%' OR LOWER(name) LIKE '%team-a%')")
                                ->whereRaw("(LOWER(name) NOT LIKE '%team b%' AND LOWER(name) NOT LIKE '%teamb%' AND LOWER(name) NOT LIKE '%team-b%' AND LOWER(name) NOT LIKE '%& b%' AND LOWER(name) NOT LIKE '%&b%')");
                        });
                    });
                    $query->whereDoesntHave('boardList', function($bl) {
                        $bl->whereRaw("(LOWER(name) LIKE '%team a%' OR LOWER(name) LIKE '%teama%') AND LOWER(name) NOT LIKE '%team b%'");
                    });
                    // Exclude cards designated specifically for Team A
                    $query->where(function($q) {
                        $q->whereNull('team')
                          ->orWhere('team', '!=', 'A')
                          ->orWhere('team', 'Both');
                    });
                }

                $reviewerIds = [$userId];
                if ($isDara) $reviewerIds = array_unique(array_merge($reviewerIds, [12, 24]));
                if ($isKim) $reviewerIds = array_unique(array_merge($reviewerIds, [13]));

                $query->where(function($q) use ($reviewerIds, $startDate, $endDate) {
                    $q->whereHas('comments', function($qc) use ($reviewerIds, $startDate, $endDate) {
                        $qc->whereIn('user_id', $reviewerIds)
                           ->where('is_system', false)  // Exclude auto-generated comments
                           ->where(function($sub) {
                               $sub->whereRaw("LOWER(content) LIKE '%qc%approve%'")
                                   ->orWhereRaw("LOWER(content) LIKE '%production%approve%'")
                                   ->orWhereRaw("LOWER(content) LIKE '%production approved%'")
                                   ->orWhereRaw("LOWER(content) LIKE '%production approved smm%'")
                                   ->orWhereRaw("LOWER(content) LIKE '%head%approve%'")
                                   ->orWhereRaw("LOWER(content) LIKE '%approved%smm%'");
                           });
                        if (isset($startDate)) $qc->where('created_at', '>=', $startDate);
                        if (isset($endDate)) $qc->where('created_at', '<=', $endDate);
                    })
                    ->orWhereHas('checklists.items', function($qi) use ($reviewerIds, $startDate, $endDate) {
                        $qi->where(function($sub) use ($reviewerIds, $startDate, $endDate) {
                            $sub->where(function($sm) use ($reviewerIds, $startDate, $endDate) {
                                $sm->where('is_marked', true)
                                   ->whereIn('marked_by', $reviewerIds);
                                if (isset($startDate)) $sm->where('marked_at', '>=', $startDate);
                                if (isset($endDate)) $sm->where('marked_at', '<=', $endDate);
                            })
                            ->orWhere(function($si) use ($reviewerIds, $startDate, $endDate) {
                                $si->where('has_issue', true)
                                   ->whereIn('issue_by', $reviewerIds);
                                if (isset($startDate)) $si->where('issue_at', '>=', $startDate);
                                if (isset($endDate)) $si->where('issue_at', '<=', $endDate);
                            })
                            ->orWhere(function($sa) use ($reviewerIds, $startDate, $endDate) {
                                $sa->where('is_approved', true)
                                   ->whereIn('approved_by', $reviewerIds);
                                if (isset($startDate)) $sa->where('approved_at', '>=', $startDate);
                                if (isset($endDate)) $sa->where('approved_at', '<=', $endDate);
                            });
                        });
                    });
                });

                // Eager-load QC / Production approved comments strictly by THIS user to support revision counting
                $query->with(['qcApprovalComments' => function($q) use ($reviewerIds) {
                    $q->whereIn('user_id', $reviewerIds)
                      ->where('is_system', false)
                      ->where(function($sub) {
                          $sub->whereRaw("LOWER(content) LIKE '%qc%approve%'")
                              ->orWhereRaw("LOWER(content) LIKE '%production%approve%'")
                              ->orWhereRaw("LOWER(content) LIKE '%production approved%'")
                              ->orWhereRaw("LOWER(content) LIKE '%production approved smm%'")
                              ->orWhereRaw("LOWER(content) LIKE '%head%approve%'")
                              ->orWhereRaw("LOWER(content) LIKE '%approved%smm%'");
                      })
                      ->orderBy('created_at');
                }]);

            } elseif ($user && $user->isSupervisorRole()) {
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
            } elseif ($user) {
                // Standard member personal export scope:
                //  • Cards assigned to this member
                //  • SMM Cards where this member ticked (completed) checklist items within the date range
                $userId = $user->id;
                $query->where(function($q) use ($userId, $startDate, $endDate) {
                    $q->whereHas('assignees', function($aq) use ($userId) {
                        $aq->where('users.id', $userId);
                    })
                    ->orWhereHas('checklists.items', function($qi) use ($userId, $startDate, $endDate) {
                        $qi->where(function($sub) use ($userId, $startDate, $endDate) {
                            $sub->where(function($cSub) use ($userId, $startDate, $endDate) {
                                $cSub->where('is_completed', true)
                                     ->where(function($ucond) use ($userId) {
                                         $ucond->where('completed_by', $userId)
                                               ->orWhere('assigned_user_id', $userId)
                                               ->orWhereJsonContains('assigned_user_ids', $userId);
                                     });
                                if (isset($startDate)) $cSub->where('completed_at', '>=', $startDate);
                                if (isset($endDate)) $cSub->where('completed_at', '<=', $endDate);
                            })
                            ->orWhere(function($iSub) use ($userId, $startDate, $endDate) {
                                $iSub->where('has_issue', true)
                                     ->where(function($ucond) use ($userId) {
                                         $ucond->where('completed_by', $userId)
                                               ->orWhere('assigned_user_id', $userId)
                                               ->orWhereJsonContains('assigned_user_ids', $userId);
                                     });
                                if (isset($startDate)) $iSub->where('issue_at', '>=', $startDate);
                                if (isset($endDate)) $iSub->where('issue_at', '<=', $endDate);
                            });
                        });
                    });
                });
            }
        }

        return $query;
    }

    private function assignActivityDates($cards, $startDate, $endDate, $isPersonalExport, $isQc, ?User $targetUser = null)
    {
        $targetUser = $targetUser ?? auth()->user();
        foreach ($cards as $card) {
            if ($card->is_checklist_task && $card->computed_activity_date) {
                continue;
            }
            $activityDate = null;
            
            // Collect all timestamps
            $timestamps = collect();

            if ($isPersonalExport && $isQc) {
                // For QC / Production Personal Report, ONLY look at approved comments by this user
                $userId = $targetUser?->id ?? auth()->id();
                foreach ($card->comments as $comment) {
                    $cText = strtolower($comment->content ?? $comment->body ?? '');
                    $isApprovedComment = str_contains($cText, 'qc approved')
                        || str_contains($cText, 'production approved')
                        || str_contains($cText, 'production approved smm')
                        || str_contains($cText, 'head approved')
                        || str_contains($cText, 'approved smm')
                        || (str_contains($cText, 'production') && str_contains($cText, 'approve'))
                        || (str_contains($cText, 'qc') && str_contains($cText, 'approve'));

                    if ($comment->user_id === $userId && $isApprovedComment) {
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
            if ($c->is_checklist_task ?? false) {
                $isError = (bool)($c->is_error ?? false);
            } elseif ($c->status === \App\Enums\CardStatus::Rejected || !empty($c->rejection_reason)) {
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
            
            // Completed date
            if ($c->is_checklist_task ?? false) {
                $c->exact_completed_date = $c->exact_completed_date ?: '-';
            } else {
                $completedDate = null;
                $statusVal = $c->status instanceof \BackedEnum ? $c->status->value : (string)($c->status ?? '');
                $isApproved = $c->status === \App\Enums\CardStatus::Approved || $c->status === \App\Enums\CardStatus::Done || in_array(strtolower($statusVal), ['approved', 'done']) || stripos($listName, 'Approved') !== false;
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
            }

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
                    if (str_contains($l, 'listing') || str_contains($l, 'desc')) return 4;
                    return 5;
                };

                return $priority($labelA) <=> $priority($labelB);
            });
        }

        return [
            'groupedCards' => $weeks,
            'errorTasks' => $errorTasks,
        ];
    }

    /**
     * Check if a card is an SMM card (has SMM label, smm class/team, or belongs to an SMM board/workspace).
     */
    public static function isSmmCard(Card $card): bool
    {
        if (!empty($card->smm_class_label) || !empty($card->smm_team_label) || !empty($card->smm_cluster_label)) {
            return true;
        }
        if ($card->relationLoaded('labels') && $card->labels->contains(fn($l) => strtoupper($l->name) === 'SMM')) {
            return true;
        }
        $bName = strtolower($card->board?->name ?? '');
        $bType = strtolower($card->board?->type ?? '');
        if ($bType === 'smm' || str_contains($bName, 'smm') || str_contains($bName, 'social media')) {
            return true;
        }
        $wsName = strtolower($card->board?->workspace?->name ?? '');
        if (str_contains($wsName, 'smm') || str_contains($wsName, 'social media')) {
            return true;
        }
        return false;
    }

    /**
     * Deduplicate twin cards across synced boards (e.g. Workflow, Planning, and SMM boards)
     * Ensuring no duplicate tasks appear in reports.
     */
    public static function deduplicateSyncedCards($cards, ?User $targetUser = null): \Illuminate\Support\Collection
    {
        $isDara = $targetUser?->isDara() ?? false;
        $isKim = $targetUser?->isKim() ?? false;

        return $cards->groupBy(function($c) {
            if (!empty($c->sync_group_id)) {
                return 'sync_' . $c->sync_group_id;
            }
            $normTitle = mb_strtolower(trim($c->title ?? ''));
            $team = strtoupper($c->team ?? '');
            $wsId = $c->board?->workspace_id ?? 0;
            return 'title_' . $normTitle . '_' . $team . '_' . $wsId;
        })->map(function($group) use ($isDara, $isKim) {
            if ($group->count() === 1) {
                return $group->first();
            }

            $selected = null;
            if ($isDara) {
                $selected = $group->first(fn($c) => 
                    stripos($c->board?->name ?? '', 'team a') !== false 
                    || stripos($c->boardList?->name ?? '', 'team a') !== false
                    || strtoupper($c->team ?? '') === 'A'
                );
                if (!$selected) {
                    $selected = $group->first(fn($c) => 
                        stripos($c->board?->name ?? '', 'team b') === false 
                        && stripos($c->boardList?->name ?? '', 'team b') === false
                        && strtoupper($c->team ?? '') !== 'B'
                    );
                }
            } elseif ($isKim) {
                $selected = $group->first(fn($c) => 
                    stripos($c->board?->name ?? '', 'team b') !== false 
                    || stripos($c->boardList?->name ?? '', 'team b') !== false
                    || strtoupper($c->team ?? '') === 'B'
                );
                if (!$selected) {
                    $selected = $group->first(fn($c) => 
                        stripos($c->board?->name ?? '', 'team a') === false 
                        && stripos($c->boardList?->name ?? '', 'team a') === false
                        && strtoupper($c->team ?? '') !== 'A'
                    );
                }
            }

            if (!$selected) {
                // Prioritize Workflow card, or card with checklists/description
                $selected = $group->first(fn($c) => stripos($c->board?->name ?? '', 'workflow') !== false && !empty($c->description))
                    ?? $group->first(fn($c) => $c->checklists->isNotEmpty() && $c->checklists->some(fn($chk) => $chk->items->isNotEmpty()))
                    ?? $group->first(fn($c) => stripos($c->board?->name ?? '', 'workflow') !== false)
                    ?? $group->first();
            }

            // Borrow description, checklists, comments, assignees from siblings if missing
            if (empty($selected->description)) {
                $siblingWithDesc = $group->first(fn($c) => !empty($c->description));
                if ($siblingWithDesc) {
                    $selected->description = $siblingWithDesc->description;
                }
            }
            if ($selected->checklists->isEmpty() || $selected->checklists->every(fn($chk) => $chk->items->isEmpty())) {
                $siblingWithChecklists = $group->first(fn($c) => $c->checklists->isNotEmpty() && $c->checklists->some(fn($chk) => $chk->items->isNotEmpty()));
                if ($siblingWithChecklists) {
                    $selected->setRelation('checklists', $siblingWithChecklists->checklists);
                }
            }
            if ($selected->comments->isEmpty()) {
                $siblingWithComments = $group->first(fn($c) => $c->comments->isNotEmpty());
                if ($siblingWithComments) {
                    $selected->setRelation('comments', $siblingWithComments->comments);
                }
            }
            if ($selected->assignees->isEmpty()) {
                $siblingWithAssignees = $group->first(fn($c) => $c->assignees->isNotEmpty());
                if ($siblingWithAssignees) {
                    $selected->setRelation('assignees', $siblingWithAssignees->assignees);
                }
            }

            return $selected;
        })->values();
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

        $targetUser = null;
        if ($request->filled('member_id') && $request->member_id !== 'all') {
            $targetUser = User::find($request->member_id);
        } elseif ($request->filled('reviewer_id')) {
            $targetUser = User::find($request->reviewer_id);
        } elseif ($request->filled('user_id') && $request->user_id !== 'all') {
            $targetUser = User::find($request->user_id);
        } elseif (!auth()->user()->hasAnyRole(['super-admin', 'admin', 'admin-digital', 'boss', 'supervisor']) && !auth()->user()->isQc()) {
            $targetUser = auth()->user();
        }

        $cards = $this->getFilteredCardsQuery($request, $board, $targetUser)->get();
        $cards = self::deduplicateSyncedCards($cards, $targetUser);
        $cards = $this->integrateChecklistTasks($cards, $targetUser, $filterStartDate, $filterEndDate);
        $cards = $this->assignActivityDates($cards, $filterStartDate, $filterEndDate, false, $targetUser?->isQc() ?? false, $targetUser);

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
                        $memberStats[$u->name] = ['completed' => 0, 'pending' => 0, 'total' => 0, 'user' => $u];
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

        $targetUser = null;
        if ($request->filled('member_id') && $request->member_id !== 'all') {
            $targetUser = User::find($request->member_id);
        } elseif ($request->filled('reviewer_id')) {
            $targetUser = User::find($request->reviewer_id);
        } elseif ($request->filled('user_id') && $request->user_id !== 'all') {
            $targetUser = User::find($request->user_id);
        } elseif (!auth()->user()->hasAnyRole(['super-admin', 'admin', 'admin-digital', 'boss', 'supervisor']) && !auth()->user()->isQc()) {
            $targetUser = auth()->user();
        }

        $cards = $this->getFilteredCardsQuery($request, $board, $targetUser)->get();
        $cards = self::deduplicateSyncedCards($cards, $targetUser);
        $cards = $this->integrateChecklistTasks($cards, $targetUser, $filterStartDate, $filterEndDate);
        $cards = $this->assignActivityDates($cards, $filterStartDate, $filterEndDate, false, $targetUser?->isQc() ?? false, $targetUser);

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
                        $memberStats[$u->name] = ['completed' => 0, 'pending' => 0, 'total' => 0, 'team_role' => $u->team_role ?? 'ZZ_Other', 'user' => $u];
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
     * Extract leading item number from checklist item text (e.g. '2.Video Short : 13TH-OCT' => 2).
     */
    public static function extractNumberFromChecklistItem(?string $content): ?int
    {
        if (empty($content)) return null;
        $content = trim($content);
        if (preg_match('/^#?\s*(\d+)[\.\:\)\-\s]/u', $content, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/^#?\s*(\d+)/u', $content, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    /**
     * Match a task line from the card description matching the given number
     * (e.g. matches '2.TYPH-0509 Vs. TYPH-0565 : Power and size comparison').
     */
    public static function matchDescriptionByNumber(?string $description, int $number): ?string
    {
        if (empty($description)) return null;

        // Clean HTML markup into line breaks
        $text = preg_replace('/<(?:br|\/p|\/div|\/li|tr)\s*\/?>/i', "\n", $description);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\xc2\xa0", "\u{00a0}", '&nbsp;'], ' ', $text);

        $lines = preg_split('/\r\n|\r|\n/', $text);
        if (!$lines) return null;

        // Pattern: exact number at beginning of line, e.g. "2.TYPH-0509" or "2. TYPH-0509" or "2: TYPH"
        $patternExact = '/^\s*#?\s*' . $number . '[\.\:\)\-\s]\s*(.+)$/ui';
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') continue;
            if (preg_match($patternExact, $trimmed)) {
                return $trimmed;
            }
        }

        // Fallback pattern if number is immediately adjacent to text
        $patternFallback = '/^\s*#?\s*' . $number . '[\.\:\)\-]?\s*(.+)$/ui';
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') continue;
            if (preg_match($patternFallback, $trimmed)) {
                return $trimmed;
            }
        }

        return null;
    }

    /**
     * Match a task line from the card description matching category keywords (e.g. Graphic, Video, Description).
     */
    public static function matchDescriptionByCategory(?string $description, string $category, string $itemContent): ?string
    {
        if (empty($description)) return null;

        $text = preg_replace('/<(?:br|\/p|\/div|\/li|tr)\s*\/?>/i', "\n", $description);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\xc2\xa0", "\u{00a0}", '&nbsp;'], ' ', $text);

        $lines = preg_split('/\r\n|\r|\n/', $text);
        if (!$lines) return null;

        $catKeywords = [];
        $catLower = strtolower($category);
        if ($catLower === 'graphic') {
            $catKeywords = ['graphic', 'poster', 'banner', 'design', 'artwork', 'photo', 'image'];
        } elseif ($catLower === 'video') {
            $catKeywords = ['video', 'reel', 'short', 'tiktok', 'landscape'];
        } elseif ($catLower === 'listing' || $catLower === 'description') {
            $catKeywords = ['description', 'listing', 'desc'];
        } elseif ($catLower === 'content') {
            $catKeywords = ['content'];
        }

        $kwPattern = implode('|', array_map('preg_quote', $catKeywords));
        if (empty($kwPattern)) return null;

        $pattern = '/^\s*#?\s*(?:' . $kwPattern . ')\s*[\:\-\.]\s*(.+)$/ui';
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') continue;
            if (preg_match($pattern, $trimmed)) {
                return $trimmed;
            }
        }

        return null;
    }

    /**
     * Format a descriptive task title for checklist tasks on normal and SMM cards.
     * E.g. "PRJ: KUVUO WITH BUNDLE - Graphic" or "PRJ: KUVUO WITH BUNDLE - Description".
     */
    public static function formatChecklistTaskTitle(?string $cardTitle, ?string $itemContent, ?string $categoryName = null): string
    {
        $cardTitle = trim($cardTitle ?? '');
        $itemContent = trim($itemContent ?? '');
        $categoryName = trim($categoryName ?? '');

        if (empty($cardTitle)) {
            return $itemContent ?: ($categoryName ?: 'Task');
        }

        if (empty($itemContent) && empty($categoryName)) {
            return $cardTitle;
        }

        // Determine what suffix to attach
        $taskSuffix = $itemContent;
        if (empty($taskSuffix) || in_array(strtolower($taskSuffix), ['task', 'status', 'checklist', 'todo'])) {
            $taskSuffix = $categoryName;
        }

        $titleLower = mb_strtolower($cardTitle);
        $suffixLower = mb_strtolower($taskSuffix);
        $catLower = mb_strtolower($categoryName);

        // If card title already explicitly contains this task suffix or category, return card title
        if ($suffixLower !== '' && str_contains($titleLower, $suffixLower)) {
            return $cardTitle;
        }
        if ($catLower !== '' && str_contains($titleLower, $catLower)) {
            return $cardTitle;
        }
        if ($catLower === 'video' && (str_contains($titleLower, 'video') || str_contains($titleLower, 'reel') || str_contains($titleLower, 'short') || str_contains($titleLower, 'tiktok'))) {
            return $cardTitle;
        }
        if ($catLower === 'graphic' && (str_contains($titleLower, 'graphic') || str_contains($titleLower, 'poster') || str_contains($titleLower, 'banner') || str_contains($titleLower, 'design'))) {
            return $cardTitle;
        }
        if (($catLower === 'listing' || $catLower === 'description') && (str_contains($titleLower, 'listing') || str_contains($titleLower, 'description') || str_contains($titleLower, 'desc'))) {
            return $cardTitle;
        }

        $displaySuffix = !empty($taskSuffix) ? $taskSuffix : $categoryName;
        return $cardTitle . ' - ' . $displaySuffix;
    }

    /**
     * Resolve the label object (Video, Graphic, Content, Listing/Description) from the checklist item text.
     */
    public static function resolveChecklistTaskLabel(string $content, Card $card, ?\App\Models\CardChecklist $chk = null, ?User $assignedOrCompletedUser = null): \App\Models\Label
    {
        $category = \App\Models\CardChecklistItem::detectCategory($content);
        if (!$category && $chk) {
            $category = \App\Models\CardChecklistItem::detectCategory($chk->title ?? '');
        }
        if (!$category && $assignedOrCompletedUser) {
            if (\App\Models\CardChecklistItem::isVideoUser($assignedOrCompletedUser)) $category = 'video';
            elseif (\App\Models\CardChecklistItem::isGraphicUser($assignedOrCompletedUser)) $category = 'graphic';
            elseif (\App\Models\CardChecklistItem::isListingUser($assignedOrCompletedUser)) $category = 'listing';
        }
        
        $name = 'Graphic';
        $color = '#ec4899';

        if ($category === 'video') {
            $name = 'Video';
            $color = '#8b5cf6';
        } elseif ($category === 'content') {
            $name = 'Content';
            $color = '#06b6d4';
        } elseif ($category === 'listing') {
            if (stripos($content, 'desc') !== false || ($chk && stripos($chk->title ?? '', 'desc') !== false)) {
                $name = 'Description';
            } else {
                $name = 'Listing';
            }
            $color = '#10b981';
        } elseif ($category === 'graphic') {
            $name = 'Graphic';
            $color = '#ec4899';
        } else {
            // Fallback: check if parent card has Video, Graphic, Content, Listing or Description label
            $existing = $card->labels->first(fn($l) => in_array(strtolower($l->name), ['video', 'graphic', 'content', 'listing', 'description']));
            if ($existing) {
                return $existing;
            }
            if (stripos($content, 'video') !== false || ($chk && stripos($chk->title ?? '', 'video') !== false)) {
                $name = 'Video';
                $color = '#8b5cf6';
            } elseif (stripos($content, 'desc') !== false || ($chk && stripos($chk->title ?? '', 'desc') !== false)) {
                $name = 'Description';
                $color = '#10b981';
            }
        }

        // Try to find matching label in DB
        $dbLabel = \App\Models\Label::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
        if (!$dbLabel && ($name === 'Description' || $name === 'Listing')) {
            $dbLabel = \App\Models\Label::whereRaw('LOWER(name) IN (?, ?)', ['listing', 'description'])->first();
        }
        if ($dbLabel) {
            return $dbLabel;
        }

        $virtual = new \App\Models\Label();
        $virtual->name = $name;
        $virtual->color = $color;
        return $virtual;
    }

    /**
     * Integrate checklist tasks for reports (Personal Report and Planning/Workflow Board exports).
     *
     * - Normal cards (non-SMM) with checklists: expanded into individual tasks from checklist items,
     *   detected as Graphic, Video, or Description of that card title (e.g. "PRJ: KUVUO WITH BUNDLE - Graphic").
     * - Normal cards without checklists: included as regular tasks like before.
     * - SMM cards: expanded into individual tasks from checklist items, with task titles
     *   detected from the card description matching the item number (e.g. 2.TYPH-0509),
     *   category label resolved (Video, Graphic, Content, Listing/Description), and assignees properly mapped.
     * - For Mr. Dara & Mr. Kim (QC): show tasks they clicked mark as approved (green tick) or marked error (red flag).
     * - For other users / standard members: show tasks they completed (ticked finish) or items with reviewer errors.
     * - For full board export (no specific user): show all checklist tasks with proper status.
     * - Tasks are deduplicated to ensure no duplicate entries appear.
     */
    public function integrateChecklistTasks($cards, ?User $targetUser, ?\Illuminate\Support\Carbon $startDate, ?\Illuminate\Support\Carbon $endDate): \Illuminate\Support\Collection
    {
        $isQcUser = $targetUser?->isQc() ?? false;
        $isDara = $targetUser?->isDara() ?? false;
        $isKim = $targetUser?->isKim() ?? false;
        $targetUserId = $targetUser ? (int) $targetUser->id : null;

        $isReviewer = function($userId) use ($targetUserId, $isDara, $isKim) {
            if (!$userId) return false;
            $uid = (int) $userId;
            if ($uid === $targetUserId) return true;
            if ($isDara && in_array($uid, [12, 24])) return true;
            if ($isKim && $uid === 13) return true;
            return false;
        };

        $resultCards = collect();
        $seenChecklistItems = [];

        foreach ($cards as $card) {
            $isSmm = self::isSmmCard($card);
            $cardChecklists = $card->checklists;
            $hasChecklistItems = $cardChecklists && $cardChecklists->isNotEmpty() && $cardChecklists->contains(fn($chk) => $chk->items && $chk->items->isNotEmpty());

            $qualifyingItems = collect();

            if ($hasChecklistItems) {
                foreach ($cardChecklists as $chk) {
                    foreach ($chk->items as $item) {
                        $itemKey = $item->sync_id ?: ('item_' . $item->id);
                        if (isset($seenChecklistItems[$itemKey])) {
                            continue;
                        }

                        $itemTimestamp = null;
                        $isError = false;
                        $status = null;
                        $exactCompletedDate = '-';
                        $rejectionReason = null;

                        if ($targetUser && ($isQcUser || $isDara || $isKim)) {
                            // QC Reviewers (Dara & Kim): show tasks they clicked mark as approved (or issue)
                            $isMarkedBy = $item->is_marked && $isReviewer($item->marked_by);
                            $hasIssueBy = $item->has_issue && $isReviewer($item->issue_by);
                            $isApprovedBy = $item->is_approved && ($isReviewer($item->approved_by) || $isReviewer($item->marked_by));

                            if (!$isMarkedBy && !$hasIssueBy && !$isApprovedBy) {
                                continue;
                            }

                            if ($hasIssueBy) {
                                $itemTimestamp = $item->issue_at ?: $item->updated_at;
                                $status = CardStatus::Rejected;
                                $isError = true;
                                $rejectionReason = 'Error on checklist item: ' . $item->content;
                            } elseif ($isMarkedBy) {
                                $itemTimestamp = $item->marked_at ?: ($item->approved_at ?: $item->updated_at);
                                $status = CardStatus::Approved;
                                $isError = false;
                            } else {
                                $itemTimestamp = $item->approved_at ?: ($item->marked_at ?: $item->updated_at);
                                $status = CardStatus::Approved;
                                $isError = false;
                            }

                            $exactCompletedDate = (!$isError && $itemTimestamp) 
                                ? Carbon::parse($itemTimestamp)->setTimezone('Asia/Phnom_Penh')->format('Y-m-d') 
                                : '-';

                        } elseif ($targetUser) {
                            // Standard members: show tasks they completed (ticked finish), or items where reviewer flagged error/approved
                            $isCompleted = (bool) $item->is_completed;
                            $hasIssue = (bool) $item->has_issue;
                            $isApproved = (bool) $item->is_approved;
                            $isMarked = (bool) $item->is_marked;

                            $isCompletedByMe = ((int) $item->completed_by === $targetUserId);
                            $assigneeIds = $item->effectiveAssigneeIds();
                            $isAssignedToMe = in_array($targetUserId, $assigneeIds) || ((int) $item->assigned_user_id === $targetUserId);
                            $isCardAssignedToMe = $card->assignees->contains('id', $targetUserId);

                            // Category matching for normal card checklists:
                            // e.g. if item is "Graphic" and targetUser is the Graphic member on the card
                            $category = \App\Models\CardChecklistItem::detectCategory($item->content)
                                ?: \App\Models\CardChecklistItem::detectCategory($chk->title ?? '');
                            $isCategoryMatch = false;
                            if ($category && $isCardAssignedToMe) {
                                if ($category === 'video' && \App\Models\CardChecklistItem::isVideoUser($targetUser)) $isCategoryMatch = true;
                                elseif ($category === 'graphic' && \App\Models\CardChecklistItem::isGraphicUser($targetUser)) $isCategoryMatch = true;
                                elseif ($category === 'listing' && \App\Models\CardChecklistItem::isListingUser($targetUser)) $isCategoryMatch = true;
                                elseif ($category === 'content' && str_contains(strtolower($targetUser->name ?? ''), 'sreypich')) $isCategoryMatch = true;
                            }

                            $belongsToMe = $isCompletedByMe || $isAssignedToMe || $isCategoryMatch || ($isCardAssignedToMe && empty($item->completed_by));

                            if (!$belongsToMe) {
                                continue;
                            }

                            // Must be completed OR have an issue flagged by reviewer (error)
                            if (!$isCompleted && !$hasIssue) {
                                continue;
                            }

                            if ($hasIssue) {
                                $itemTimestamp = $item->issue_at ?: ($item->completed_at ?: $item->updated_at);
                                $status = CardStatus::Rejected;
                                $isError = true;
                                $rejectionReason = 'Error on checklist item: ' . $item->content;
                                $exactCompletedDate = '-';
                            } else {
                                $itemTimestamp = $item->completed_at ?: $item->updated_at;
                                $status = ($isApproved || $isMarked) ? CardStatus::Approved : CardStatus::Done;
                                $isError = false;
                                $exactCompletedDate = $itemTimestamp 
                                    ? Carbon::parse($itemTimestamp)->setTimezone('Asia/Phnom_Penh')->format('Y-m-d') 
                                    : '-';
                            }

                        } else {
                            // No specific user (full board export): include all checklist tasks
                            $itemTimestamp = $item->completed_at ?: ($item->marked_at ?: ($item->approved_at ?: $item->updated_at));
                            if ($item->has_issue) {
                                $status = CardStatus::Rejected;
                                $isError = true;
                                $rejectionReason = 'Error on checklist item: ' . $item->content;
                            } elseif ($item->is_approved || $item->is_marked) {
                                $status = CardStatus::Approved;
                                $isError = false;
                                $exactCompletedDate = $itemTimestamp ? Carbon::parse($itemTimestamp)->setTimezone('Asia/Phnom_Penh')->format('Y-m-d') : '-';
                            } elseif ($item->is_completed) {
                                $status = CardStatus::Done;
                                $isError = false;
                                $exactCompletedDate = $itemTimestamp ? Carbon::parse($itemTimestamp)->setTimezone('Asia/Phnom_Penh')->format('Y-m-d') : '-';
                            } else {
                                $status = CardStatus::Todo;
                                $isError = false;
                                $exactCompletedDate = '-';
                            }
                        }

                        // Date filtering
                        if ($startDate && $itemTimestamp && $itemTimestamp < $startDate) {
                            continue;
                        }
                        if ($endDate && $itemTimestamp && $itemTimestamp > $endDate) {
                            continue;
                        }

                        $seenChecklistItems[$itemKey] = true;

                        $qualifyingItems->push([
                            'item' => $item,
                            'checklist' => $chk,
                            'status' => $status,
                            'is_error' => $isError,
                            'timestamp' => $itemTimestamp,
                            'exact_completed_date' => $exactCompletedDate,
                            'rejection_reason' => $rejectionReason,
                        ]);
                    }
                }
            }

            if ($qualifyingItems->isNotEmpty()) {
                foreach ($qualifyingItems as $entry) {
                    $item = $entry['item'];
                    $chk = $entry['checklist'] ?? null;
                    $status = $entry['status'];
                    $isError = $entry['is_error'];
                    $timestamp = $entry['timestamp'];
                    $exactCompletedDate = $entry['exact_completed_date'];
                    $rejectionReason = $entry['rejection_reason'];

                    // 1. Assignees
                    $assigneeIds = $item->effectiveAssigneeIds();
                    $taskAssignees = collect();
                    if (!empty($assigneeIds)) {
                        $taskAssignees = User::whereIn('id', $assigneeIds)->get();
                    }
                    if ($taskAssignees->isEmpty() && $item->completed_by) {
                        $taskAssignees = User::where('id', $item->completed_by)->get();
                    }
                    if ($taskAssignees->isEmpty()) {
                        $detectedUserId = \App\Models\CardChecklistItem::detectUserIdForCard($item->content, $card);
                        if ($detectedUserId) {
                            $detectedUser = User::find($detectedUserId);
                            if ($detectedUser) $taskAssignees = collect([$detectedUser]);
                        }
                    }
                    if ($taskAssignees->isEmpty()) {
                        $taskAssignees = $card->assignees;
                    }

                    // 2. Category / Label
                    $firstAssignee = $taskAssignees->first();
                    $labelObj = self::resolveChecklistTaskLabel($item->content, $card, $chk, $firstAssignee);
                    $categoryName = $labelObj->name;

                    // 3. Task Title: match number in description, match category in description, or format with card title
                    $itemNumber = self::extractNumberFromChecklistItem($item->content);
                    $matchedTitle = null;
                    if ($itemNumber !== null) {
                        $matchedTitle = self::matchDescriptionByNumber($card->description, $itemNumber);
                    }

                    if (!$matchedTitle && !empty($card->description)) {
                        $matchedTitle = self::matchDescriptionByCategory($card->description, $categoryName, $item->content);
                    }

                    if ($matchedTitle) {
                        $taskTitle = $matchedTitle;
                    } else {
                        $taskTitle = self::formatChecklistTaskTitle($card->title, $item->content, $categoryName);
                    }

                    // 4. Virtual Card instantiation
                    $taskCard = clone $card;
                    $taskCard->is_checklist_task = true;
                    $taskCard->checklist_item_id = $item->id;
                    $taskCard->checklist_item_name = $item->content;
                    $taskCard->parent_card_title = $card->title;
                    $taskCard->parent_card_id = $card->id;
                    $taskCard->title = $taskTitle;
                    $taskCard->description = $item->content;

                    // Set label
                    $taskCard->setRelation('labels', collect([$labelObj]));
                    $taskCard->label = $labelObj->name;

                    // Set SMM class if missing
                    if (!$taskCard->smm_class_label) {
                        $taskCard->smm_class_label = $labelObj->name;
                        $taskCard->smm_class_color = $labelObj->color ?? '#6366f1';
                    }

                    // Set assignees
                    $taskCard->setRelation('assignees', $taskAssignees);

                    // Set status & error
                    $taskCard->status = $status;
                    $taskCard->is_error = $isError;
                    $taskCard->exact_completed_date = $exactCompletedDate;
                    $taskCard->rejection_reason = $rejectionReason;

                    // Set computed activity date
                    $taskCard->computed_activity_date = $timestamp ? Carbon::parse($timestamp)->setTimezone('Asia/Phnom_Penh') : null;

                    $resultCards->push($taskCard);
                }
            } else {
                // If card had no checklist items, or no checklist items matched:
                if ($targetUser && $isQcUser) {
                    // For QC users: check if they approved this card via comment or status
                    $hasCardApprovalComment = false;
                    if ($card->comments && $card->comments->isNotEmpty()) {
                        $hasCardApprovalComment = $card->comments->contains(function($cmt) use ($isReviewer, $startDate, $endDate) {
                            if ($cmt->is_system) return false;
                            if (!$isReviewer($cmt->user_id)) return false;
                            $txt = strtolower($cmt->content ?? $cmt->body ?? '');
                            $isApprove = str_contains($txt, 'qc approve')
                                || str_contains($txt, 'production approve')
                                || str_contains($txt, 'production approved')
                                || str_contains($txt, 'production approved smm')
                                || str_contains($txt, 'head approve')
                                || str_contains($txt, 'approved smm');
                            if (!$isApprove) return false;
                            if ($startDate && $cmt->created_at < $startDate) return false;
                            if ($endDate && $cmt->created_at > $endDate) return false;
                            return true;
                        });
                    }

                    if ($hasCardApprovalComment || stripos($card->boardList?->name ?? '', 'Approved') !== false || $card->status === CardStatus::Approved) {
                        $card->is_checklist_task = false;
                        $resultCards->push($card);
                    }
                } elseif ($targetUser && !$isQcUser) {
                    // For standard member: if card has no checklists, keep normal card assigned to them
                    if (!$hasChecklistItems) {
                        $card->is_checklist_task = false;
                        $resultCards->push($card);
                    }
                } else {
                    // Full board export with no checklists on this card
                    $card->is_checklist_task = false;
                    $resultCards->push($card);
                }
            }
        }

        return $resultCards;
    }

    /**
     * Backward-compatible proxy for integrateChecklistTasksForQc.
     */
    private function integrateChecklistTasksForQc($cards, User $targetUser, ?\Illuminate\Support\Carbon $startDate, ?\Illuminate\Support\Carbon $endDate): \Illuminate\Support\Collection
    {
        return $this->integrateChecklistTasks($cards, $targetUser, $startDate, $endDate);
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

        $now = Carbon::now('Asia/Phnom_Penh');
        $currentMonthLong = strtolower($now->format('F')); // 'october'
        $currentMonthShort = strtolower($now->format('M')); // 'oct'
        $allMonths = [
            'january', 'february', 'march', 'april', 'may', 'june',
            'july', 'august', 'september', 'october', 'november', 'december'
        ];
        $pastMonths = array_diff($allMonths, [$currentMonthLong]);

        // Filter to include Workflow boards, Planning boards, and SMM boards
        $hiddenBoardsCount = 0;
        foreach ($workspaces as $workspace) {
            $filteredBoards = $workspace->boards->filter(function ($board) {
                $name = strtolower($board->name ?? '');
                $type = strtolower($board->type ?? '');

                $isWorkflow = str_contains($name, 'workflow')
                    || $type === 'workflow'
                    || str_contains($name, 'team a')
                    || str_contains($name, 'team b');

                $isPlanning = str_contains($name, 'planning')
                    || $type === 'planning'
                    || !empty($board->is_template);

                $isSmm = $type === 'smm'
                    || !empty($board->is_active_smm)
                    || str_contains($name, 'smm')
                    || str_contains($name, 'social media');

                return $isWorkflow || $isPlanning || $isSmm;
            })->map(function ($board) use ($currentMonthLong, $currentMonthShort, $pastMonths) {
                $name = strtolower($board->name ?? '');

                // Check if this board is explicitly named after a past month
                $hasPastMonth = false;
                foreach ($pastMonths as $pm) {
                    if (str_contains($name, $pm)) {
                        $hasPastMonth = true;
                        break;
                    }
                }

                // If board explicitly specifies a past month, mark as hidden so it goes under "Old Hidden Board"
                if ($hasPastMonth && !str_contains($name, $currentMonthLong) && !str_contains($name, $currentMonthShort)) {
                    $board->is_hidden = true;
                } elseif (str_contains($name, $currentMonthLong) || str_contains($name, $currentMonthShort)) {
                    // Board is for this month -> show directly as active (not hidden)
                    $board->is_hidden = false;
                }

                return $board;
            })->values();

            $workspace->setRelation('boards', $filteredBoards);
            $workspace->has_active_workflow_boards = $filteredBoards->contains(fn($b) => !$b->is_hidden);
            $workspace->active_workflow_boards_count = $filteredBoards->filter(fn($b) => !$b->is_hidden)->count();
            $workspace->hidden_workflow_boards_count = $filteredBoards->filter(fn($b) => (bool) $b->is_hidden)->count();

            $hiddenBoardsCount += $workspace->hidden_workflow_boards_count;
        }

        // Filter out workspaces that have no qualifying boards
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

        $targetUser = auth()->user();
        if ($request->filled('reviewer_id')) {
            $reviewer = \App\Models\User::find($request->input('reviewer_id'));
            if ($reviewer) $targetUser = $reviewer;
        } elseif ($request->filled('user_id')) {
            $reviewer = \App\Models\User::find($request->input('user_id'));
            if ($reviewer) $targetUser = $reviewer;
        }

        $cards = $this->getFilteredCardsQuery($request, null, $targetUser)->get();

        // Deduplicate twin cards across synced boards (e.g. Workflow, Planning, and SMM boards)
        $cards = self::deduplicateSyncedCards($cards, $targetUser);

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

        // Integrate checklist item reviews (for QC) and ticked tasks (for members) into individual cards
        $cards = $this->integrateChecklistTasks($cards, $targetUser, $filterStartDate, $filterEndDate);

        $cards = $this->assignActivityDates($cards, $filterStartDate, $filterEndDate, true, $targetUser->isQc(), $targetUser);

        if ($format === 'csv' || $format === 'raw_csv') {
            // A card is "completed" when physically in a list named "Approved" (Supervisor approved it),
            // or when it represents an approved checklist item.
            $isCompleted = function($c) {
                if ($c->is_archived) return false;
                if ($c->is_checklist_task ?? false) {
                    return $c->status === \App\Enums\CardStatus::Approved || $c->status === \App\Enums\CardStatus::Done;
                }
                $statusVal = $c->status instanceof \BackedEnum ? $c->status->value : (string)($c->status ?? '');
                return stripos($c->boardList?->name ?? '', 'Approved') !== false 
                    || $c->status === \App\Enums\CardStatus::Approved 
                    || in_array(strtolower($statusVal), ['approved', 'done']);
            };

            $totalTasks    = $cards->count();
            $completedTasks = $cards->filter($isCompleted)->count();
            $archivedTasks  = $cards->filter(fn($c) => $c->is_archived)->count();
            $pendingTasks   = $totalTasks - $completedTasks - $archivedTasks;

            // For QC report: errors = checklist error tasks OR cards that required revisions
            if ($targetUser->isQc()) {
                $reviewerId = $targetUser->id;
                $errorTasks = $cards->filter(function($c) use ($reviewerId) {
                    if ($c->is_checklist_task ?? false) {
                        return (bool)($c->is_error ?? false);
                    }
                    return ($c->qcApprovalComments?->where('user_id', $reviewerId)->count() ?? 0) > 1;
                })->count();
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
                            $memberStats[$u->name] = ['completed' => 0, 'pending' => 0, 'total' => 0, 'user' => $u];
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
        $isCompleted = function($c) {
            if ($c->is_archived) return false;
            if ($c->is_checklist_task ?? false) {
                return $c->status === \App\Enums\CardStatus::Approved || $c->status === \App\Enums\CardStatus::Done;
            }
            $statusVal = $c->status instanceof \BackedEnum ? $c->status->value : (string)($c->status ?? '');
            return stripos($c->boardList?->name ?? '', 'Approved') !== false 
                || $c->status === \App\Enums\CardStatus::Approved 
                || in_array(strtolower($statusVal), ['approved', 'done']);
        };

        $totalTasks    = $cards->count();
        $completedTasks = $cards->filter($isCompleted)->count();
        $archivedTasks  = $cards->filter(fn($c) => $c->is_archived)->count();
        $pendingTasks   = $totalTasks - $completedTasks - $archivedTasks;

        // For QC report: errors = checklist error tasks OR cards that required revisions
        if ($targetUser->isQc()) {
            $reviewerId = $targetUser->id;
            $errorTasks = $cards->filter(function($c) use ($reviewerId) {
                if ($c->is_checklist_task ?? false) {
                    return (bool)($c->is_error ?? false);
                }
                return ($c->qcApprovalComments?->where('user_id', $reviewerId)->count() ?? 0) > 1;
            })->count();
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
                        $memberStats[$u->name] = ['completed' => 0, 'pending' => 0, 'total' => 0, 'team_role' => $u->team_role ?? 'ZZ_Other', 'user' => $u];
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
        if ($targetUser->isQc()) {
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
                    $cleanTitle = trim(preg_replace('/^\d+[\.\:\)\-]\s*/', '', $title));
                    $copyText .= ($idx + 1) . '.' . ($cleanTitle ?: $title) . "\n";
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

        $reportTitle = 'Personal Consolidated Report';
        $currUser = $targetUser;
        if ($currUser) {
            $uName = strtolower($currUser->name ?? '');
            $uUsername = strtolower($currUser->username ?? '');
            if (str_contains($uUsername, 'dara') || str_contains($uName, 'dara')) {
                $reportTitle = 'Production Team A Checking Report';
            } elseif (str_contains($uUsername, 'kim') || str_contains($uName, 'kim')) {
                $reportTitle = 'Production Team B Checking Report';
            } elseif ($currUser->isQc()) {
                $reportTitle = 'Production Checking Report';
            } elseif ($currUser->isSupervisorRole()) {
                $reportTitle = 'Supervisor Personal Report';
            }
        }

        $smmData = $this->prepareSmmExportData($cards, null);

        return view('boards.export-pdf', [
            'board'         => null, // Consolidated report has no single board context
            'reportTitle'   => $reportTitle,
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
            // QC/Production-specific: show revision count column
            'isQcReport'    => $targetUser->isQc(),
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

                    $csvTitle = $c->title;
                    if (($c->is_checklist_task ?? false) && !empty($c->checklist_item_name)) {
                        $csvTitle .= ' [' . $c->checklist_item_name . ']';
                    }

                    $row = [
                        $c->smm_class_label ?? '-',
                        $csvTitle,
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
