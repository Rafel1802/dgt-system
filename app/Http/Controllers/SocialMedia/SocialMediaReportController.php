<?php

namespace App\Http\Controllers\SocialMedia;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaAnalytic;
use App\Models\SocialMediaClass;
use App\Models\SocialMediaItem;
use App\Models\SocialMediaPost;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class SocialMediaReportController extends Controller
{
    public function index(Request $request)
    {
        $user    = auth()->user();
        $isAdmin = $user->hasAnyRole(['super-admin', 'admin-digital', 'social_admin']);
        $isQc    = $user->hasAnyRole(['super-admin', 'admin-digital', 'social_admin', 'social_qc', 'boss']);

        $dateFrom   = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo     = $request->input('date_to', now()->toDateString());
        $classId    = $request->input('class_id');
        $userId     = $request->input('user_id');
        $qcStatus   = $request->input('qc_status');
        $postStatus = $request->input('post_status');

        $posts = $this->buildQuery($user, $isQc, $dateFrom, $dateTo, $classId, $userId, $qcStatus, $postStatus)->get();

        // Classes visible to user
        $classQuery = SocialMediaClass::orderBy('name');
        if (!$isQc) {
            $classQuery->whereHas('assignedUsers', fn ($q) => $q->where('user_id', $user->id));
        }
        $classes = $classQuery->get();

        $users = $isQc 
            ? User::role(['super-admin', 'admin-digital', 'social_admin', 'social_qc', 'boss'])->where('is_active', true)->orderBy('name')->get() 
            : collect([$user]);

        $summary = [
            'total'     => $posts->count(),
            'completed' => $posts->where('is_completed', true)->count(),
            'pending'   => $posts->where('is_completed', false)->count(),
            'checked'   => $posts->where('is_checked', true)->count(),
            'qcPending' => $posts->where('is_completed', true)->where('is_checked', false)->count(),
        ];

        // Check whether any analytics files exist for the current filter
        $analyticsQuery = SocialMediaAnalytic::query();
        if ($classId) {
            $analyticsQuery->whereHas('classes', fn ($q) => $q->where('social_media_classes.id', $classId));
        }
        $analyticsQuery->where(function($q) use ($dateFrom, $dateTo) {
            $q->whereDate('date_from', '<=', $dateTo)
              ->whereDate('date_to', '>=', $dateFrom);
        });
        $hasAnalytics = $analyticsQuery->exists();

        // Available analytics attached to the classes in scope
        $classIds = $classId
            ? [$classId]
            : $classes->pluck('id')->toArray();

        $availableAnalytics = SocialMediaAnalytic::whereHas('classes', fn ($q) => $q->whereIn('social_media_classes.id', $classIds))
            ->with('classes')
            ->where(function($q) use ($dateFrom, $dateTo) {
                $q->whereDate('date_from', '<=', $dateTo)
                  ->whereDate('date_to', '>=', $dateFrom);
            })
            ->orderByDesc('date_from')
            ->get();

        return view('social-media.reports.index', compact(
            'posts', 'classes', 'users', 'summary',
            'dateFrom', 'dateTo', 'classId', 'userId',
            'qcStatus', 'postStatus', 'isAdmin', 'isQc',
            'hasAnalytics', 'availableAnalytics'
        ));
    }

    // ─── Unified Export (ZIP or single file) ──────────────────────────────────

    public function exportZip(Request $request)
    {
        $request->validate([
            'include_csv'       => ['nullable', 'boolean'],
            'include_pdf'       => ['nullable', 'boolean'],
            'include_analytics' => ['nullable', 'boolean'],
        ]);

        $user  = auth()->user();
        $isQc  = $user->hasAnyRole(['super-admin', 'admin-digital', 'social_admin', 'social_qc', 'boss']);
        
        abort_unless($isQc, 403, 'View-only users do not have permission to export reports.');

        $dateFrom   = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo     = $request->input('date_to', now()->toDateString());
        $classId    = $request->input('class_id');

        $posts = $this->buildQuery(
            $user, $isQc, $dateFrom, $dateTo,
            $classId,
            $request->input('user_id'),
            $request->input('qc_status'),
            $request->input('post_status')
        )->get();

        $summary = [
            'total'     => $posts->count(),
            'completed' => $posts->where('is_completed', true)->count(),
            'pending'   => $posts->where('is_completed', false)->count(),
            'checked'   => $posts->where('is_checked', true)->count(),
            'qcPending' => $posts->where('is_completed', true)->where('is_checked', false)->count(),
        ];

        $wantCsv       = (bool) $request->input('include_csv', false);
        $wantPdf       = (bool) $request->input('include_pdf', false);
        $wantAnalytics = (bool) $request->input('include_analytics', false);

        $chosenCount = (int) $wantCsv + (int) $wantPdf + (int) $wantAnalytics;

        // Single-file shortcuts
        if ($chosenCount === 1 && $wantCsv)  return $this->streamCsv($posts);
        if ($chosenCount === 1 && $wantPdf)  return $this->streamPdf($posts, $summary, $dateFrom, $dateTo, $user->name);

        // Analytics-only shortcut
        if ($chosenCount === 1 && $wantAnalytics) {
            $analytic = $this->pickAnalytic($classId, $dateFrom, $dateTo);
            if ($analytic && $analytic->fileExists()) {
                return Storage::download($analytic->file_path, $analytic->original_name);
            }
            return back()->with('error', 'No analytics file found for the current filter.');
        }

        // ── Build ZIP ─────────────────────────────────────────────────────────
        $stamp   = now()->format('Y-m-d');
        $zipName = 'social-media-report-' . $stamp . '.zip';
        $tmpPath = sys_get_temp_dir() . '/' . $zipName;

        $zip = new ZipArchive();
        if ($zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Could not create ZIP archive.');
        }

        if ($wantCsv) {
            $zip->addFromString(
                'social-media-report-' . $stamp . '.csv',
                $this->buildCsvString($posts)
            );
        }

        if ($wantPdf) {
            $pdfContent = Pdf::loadView('social-media.reports.pdf', [
                'posts'         => $posts,
                'summary'       => $summary,
                'dateFrom'      => $dateFrom,
                'dateTo'        => $dateTo,
                'generatedBy'   => $user->name,
                'generatedDate' => now()->format('d M Y H:i'),
            ])->setPaper('a4', 'landscape')->output();

            $zip->addFromString('social-media-report-' . $stamp . '.pdf', $pdfContent);
        }

        if ($wantAnalytics) {
            // Gather analytics for all relevant classes within the date range
            $classIds = $classId ? [$classId] : SocialMediaClass::pluck('id')->toArray();
            $analytics = SocialMediaAnalytic::whereHas('classes', fn ($q) => $q->whereIn('social_media_classes.id', $classIds))
                ->with('classes')
                ->where(function($q) use ($dateFrom, $dateTo) {
                    $q->whereDate('date_from', '<=', $dateTo)
                      ->whereDate('date_to', '>=', $dateFrom);
                })
                ->orderByDesc('date_from')
                ->get();

            // Keep the latest PDF covering each class, while adding a shared PDF
            // only once even when it belongs to several selected classes.
            $coveredClassIds = collect();
            $analytics = $analytics->filter(function ($analytic) use (&$coveredClassIds, $classIds) {
                $relevantIds = $analytic->classes->pluck('id')->intersect($classIds);
                if ($relevantIds->diff($coveredClassIds)->isEmpty()) {
                    return false;
                }
                $coveredClassIds = $coveredClassIds->merge($relevantIds)->unique();
                return true;
            });

            foreach ($analytics as $analytic) {
                if ($analytic->fileExists()) {
                    $className = $analytic->classes
                        ->pluck('name')
                        ->map(fn ($name) => preg_replace('/[^A-Za-z0-9_\-]/', '-', $name))
                        ->join('_');
                    $entryName  = 'analytics/' . ($className ?: 'classes') . '-' . $analytic->date_from->format('Y-m-d') . '-to-' . $analytic->date_to->format('Y-m-d') . '.pdf';
                    $zip->addFile($analytic->absolutePath(), $entryName);
                }
            }
        }

        $zip->close();

        return response()->download($tmpPath, $zipName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    // ─── Legacy single-format routes (kept for backward compat) ───────────────

    public function exportCsv(Request $request)
    {
        $user = auth()->user();
        $isQc = $user->hasAnyRole(['super-admin', 'admin-digital', 'social_admin', 'social_qc', 'boss']);
        
        abort_unless($isQc, 403, 'View-only users do not have permission to export reports.');
        
        $posts = $this->buildQuery(
            $user, $isQc,
            $request->input('date_from', now()->startOfMonth()->toDateString()),
            $request->input('date_to', now()->toDateString()),
            $request->input('class_id'),
            $request->input('user_id'),
            $request->input('qc_status'),
            $request->input('post_status')
        )->get();
        return $this->streamCsv($posts);
    }

    public function exportPdf(Request $request)
    {
        $user     = auth()->user();
        $isQc     = $user->hasAnyRole(['super-admin', 'admin-digital', 'social_admin', 'social_qc', 'boss']);
        
        abort_unless($isQc, 403, 'View-only users do not have permission to export reports.');

        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo   = $request->input('date_to', now()->toDateString());
        $posts    = $this->buildQuery(
            $user, $isQc, $dateFrom, $dateTo,
            $request->input('class_id'),
            $request->input('user_id'), $request->input('qc_status'), $request->input('post_status')
        )->get();
        $summary = [
            'total'     => $posts->count(),
            'completed' => $posts->where('is_completed', true)->count(),
            'pending'   => $posts->where('is_completed', false)->count(),
            'checked'   => $posts->where('is_checked', true)->count(),
            'qcPending' => $posts->where('is_completed', true)->where('is_checked', false)->count(),
        ];
        return $this->streamPdf($posts, $summary, $dateFrom, $dateTo, $user->name);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function streamCsv($posts)
    {
        $filename = 'social-media-report-' . now()->format('Y-m-d') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        $callback = fn () => fwrite(fopen('php://output', 'w'), "\xEF\xBB\xBF" . $this->buildCsvString($posts));
        return response()->stream(function () use ($posts) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['--- SUMMARY ---']);
            fputcsv($handle, ['Total Tasks', $posts->count()]);
            fputcsv($handle, ['Posted',      $posts->where('is_completed', true)->count()]);
            fputcsv($handle, ['Pending',     $posts->where('is_completed', false)->count()]);
            fputcsv($handle, ['QC Checked',  $posts->where('is_checked', true)->count()]);
            fputcsv($handle, ['QC Pending',  $posts->where('is_completed', true)->where('is_checked', false)->count()]);
            fputcsv($handle, []);
            fputcsv($handle, ['Date', 'Class', 'Social Media', 'User', 'Link Entered', 'Text/Content Entered', 'Completed', 'Completed At', 'QC Status', 'Checked By', 'Checked At']);
            foreach ($posts as $post) {
                fputcsv($handle, [
                    $post->post_date->format('Y-m-d'),
                    $post->socialMediaClass->name ?? '',
                    $post->socialMediaItem->name ?? '',
                    $post->user->name ?? '',
                    $post->post_url ?? '',
                    $post->optional_text ?? '',
                    $post->is_completed ? 'Yes' : 'No',
                    $post->completed_at?->format('Y-m-d H:i') ?? '',
                    $post->qc_status_label,
                    $post->checker->name ?? '',
                    $post->checked_at?->format('Y-m-d H:i') ?? '',
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    private function buildCsvString($posts): string
    {
        ob_start();
        $handle = fopen('php://output', 'w');
        fputcsv($handle, ['--- SUMMARY ---']);
        fputcsv($handle, ['Total Tasks', $posts->count()]);
        fputcsv($handle, ['Posted',      $posts->where('is_completed', true)->count()]);
        fputcsv($handle, ['Pending',     $posts->where('is_completed', false)->count()]);
        fputcsv($handle, ['QC Checked',  $posts->where('is_checked', true)->count()]);
        fputcsv($handle, ['QC Pending',  $posts->where('is_completed', true)->where('is_checked', false)->count()]);
        fputcsv($handle, []);
        fputcsv($handle, ['Date', 'Class', 'Social Media', 'User', 'Link Entered', 'Text/Content Entered', 'Completed', 'Completed At', 'QC Status', 'Checked By', 'Checked At']);
        foreach ($posts as $post) {
            fputcsv($handle, [
                $post->post_date->format('Y-m-d'),
                $post->socialMediaClass->name ?? '',
                $post->socialMediaItem->name ?? '',
                $post->user->name ?? '',
                $post->post_url ?? '',
                $post->optional_text ?? '',
                $post->is_completed ? 'Yes' : 'No',
                $post->completed_at?->format('Y-m-d H:i') ?? '',
                $post->qc_status_label,
                $post->checker->name ?? '',
                $post->checked_at?->format('Y-m-d H:i') ?? '',
            ]);
        }
        fclose($handle);
        return ob_get_clean();
    }

    private function streamPdf($posts, $summary, $dateFrom, $dateTo, $userName)
    {
        $pdf = Pdf::loadView('social-media.reports.pdf', [
            'posts'         => $posts,
            'summary'       => $summary,
            'dateFrom'      => $dateFrom,
            'dateTo'        => $dateTo,
            'generatedBy'   => $userName,
            'generatedDate' => now()->format('d M Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('social-media-report-' . now()->format('Y-m-d') . '.pdf');
    }

    private function pickAnalytic(?string $classId, string $dateFrom, string $dateTo): ?SocialMediaAnalytic
    {
        $query = SocialMediaAnalytic::query();
        if ($classId) {
            $query->whereHas('classes', fn ($q) => $q->where('social_media_classes.id', $classId));
        }
        $query->where(function($q) use ($dateFrom, $dateTo) {
            $q->whereDate('date_from', '<=', $dateTo)
              ->whereDate('date_to', '>=', $dateFrom);
        });
        return $query->orderByDesc('date_from')->first();
    }

    private function buildQuery(User $user, bool $isQc, string $dateFrom, string $dateTo, ?string $classId, ?string $userId, ?string $qcStatus, ?string $postStatus)
    {
        $query = SocialMediaPost::with(['socialMediaClass', 'socialMediaItem', 'user', 'checker'])
            ->whereBetween('post_date', [$dateFrom, $dateTo]);

        if (!$isQc) {
            $query->where('user_id', $user->id);
            $assignedClassIds = SocialMediaClass::whereHas('assignedUsers', fn ($q) => $q->where('user_id', $user->id))->pluck('id');
            $query->whereIn('social_media_class_id', $assignedClassIds);
        }

        return $query
            ->when($classId,                fn ($q) => $q->where('social_media_class_id', $classId))
            ->when($userId, function ($q) use ($userId, $isQc) {
                 if ($isQc) {
                     // For QC, if filtering by specific user, we want posts they created OR posts they checked (reviewed/approved)
                     $q->where(function($q2) use ($userId) {
                         $q2->where('user_id', $userId)
                            ->orWhere('checked_by', $userId);
                     });
                 } else {
                     $q->where('user_id', $userId);
                 }
            })
            ->when($qcStatus === 'checked', fn ($q) => $q->where('is_checked', true))
            ->when($qcStatus === 'pending', fn ($q) => $q->where('is_checked', false))
            ->when($postStatus === 'completed', fn ($q) => $q->where('is_completed', true))
            ->when($postStatus === 'pending',   fn ($q) => $q->where('is_completed', false))
            ->orderBy('post_date')
            ->orderBy('social_media_class_id');
    }

    public function exportPersonalReport(Request $request)
    {
        abort_unless(auth()->user()?->isQcOrSupervisor(), 403, 'Unauthorized access to personal reports.');

        $format = $request->get('format', 'csv');
        $userId = auth()->id(); // They are QC or Supervisor

        $dateFrom = now()->startOfMonth()->toDateString();
        $dateTo   = now()->toDateString();

        if ($request->filled('date_range') && $request->date_range !== 'all_time') {
            switch ($request->date_range) {
                case 'this_week':
                    $dateFrom = now()->startOfWeek()->toDateString();
                    $dateTo   = now()->endOfWeek()->toDateString();
                    break;
                case 'this_month':
                    $dateFrom = now()->startOfMonth()->toDateString();
                    $dateTo   = now()->endOfMonth()->toDateString();
                    break;
                case 'last_month':
                    $dateFrom = now()->subMonth()->startOfMonth()->toDateString();
                    $dateTo   = now()->subMonth()->endOfMonth()->toDateString();
                    break;
                case 'custom':
                case 'custom_period':
                    if ($request->filled('start_date')) $dateFrom = \Carbon\Carbon::parse($request->start_date)->toDateString();
                    if ($request->filled('end_date'))   $dateTo   = \Carbon\Carbon::parse($request->end_date)->toDateString();
                    break;
            }
        } else {
            // all_time
            $dateFrom = '2000-01-01';
            $dateTo   = '2100-01-01';
        }

        $userId = $request->input('user_id'); // Optional specific member to filter

        $posts = $this->buildQuery(
            $user, $isQc, $dateFrom, $dateTo,
            null, // no specific class
            $userId, 
            null, 
            null
        )->get();

        $format = $request->input('format', 'csv');

        if ($format === 'pdf') {
            $summary = [
                'total'     => $posts->count(),
                'completed' => $posts->where('is_completed', true)->count(),
                'pending'   => $posts->where('is_completed', false)->count(),
                'checked'   => $posts->where('is_checked', true)->count(),
                'qcPending' => $posts->where('is_completed', true)->where('is_checked', false)->count(),
            ];
            return $this->streamPdf($posts, $summary, $dateFrom, $dateTo, 'Personal Report (' . $user->name . ')');
        }

        return $this->streamCsv($posts);
    }
}
