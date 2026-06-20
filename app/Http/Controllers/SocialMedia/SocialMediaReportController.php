<?php

namespace App\Http\Controllers\SocialMedia;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaClass;
use App\Models\SocialMediaItem;
use App\Models\SocialMediaPost;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class SocialMediaReportController extends Controller
{
    public function index(Request $request)
    {
        $user    = auth()->user();
        $isAdmin = $user->hasAnyRole(['super-admin', 'admin-digital', 'social_admin']);
        $isQc    = $user->hasAnyRole(['super-admin', 'admin-digital', 'social_admin', 'social_qc']);

        $dateFrom   = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo     = $request->input('date_to', now()->toDateString());
        $classId    = $request->input('class_id');
        $itemId     = $request->input('item_id');
        $userId     = $request->input('user_id');
        $qcStatus   = $request->input('qc_status');
        $postStatus = $request->input('post_status');

        $posts = $this->buildQuery($user, $isQc, $dateFrom, $dateTo, $classId, $itemId, $userId, $qcStatus, $postStatus)->get();

        // Classes visible to user
        $classQuery = SocialMediaClass::orderBy('name');
        if (!$isQc) {
            $classQuery->whereHas('assignedUsers', fn ($q) => $q->where('user_id', $user->id));
        }
        $classes = $classQuery->get();

        $items   = SocialMediaItem::orderBy('name')->get();
        $users   = $isQc ? User::where('is_active', true)->orderBy('name')->get() : collect([$user]);

        $summary = [
            'total'     => $posts->count(),
            'completed' => $posts->where('is_completed', true)->count(),
            'pending'   => $posts->where('is_completed', false)->count(),
            'checked'   => $posts->where('is_checked', true)->count(),
            'qcPending' => $posts->where('is_completed', true)->where('is_checked', false)->count(),
        ];

        return view('social-media.reports.index', compact(
            'posts', 'classes', 'items', 'users', 'summary',
            'dateFrom', 'dateTo', 'classId', 'itemId', 'userId',
            'qcStatus', 'postStatus', 'isAdmin', 'isQc'
        ));
    }

    public function exportCsv(Request $request)
    {
        $user    = auth()->user();
        $isQc    = $user->hasAnyRole(['super-admin', 'admin-digital', 'social_qc']);

        $posts = $this->buildQuery(
            $user, $isQc,
            $request->input('date_from', now()->startOfMonth()->toDateString()),
            $request->input('date_to', now()->toDateString()),
            $request->input('class_id'),
            $request->input('item_id'),
            $request->input('user_id'),
            $request->input('qc_status'),
            $request->input('post_status')
        )->get();

        $filename = 'social-media-report-' . now()->format('Y-m-d') . '.csv';
        $headers  = ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="' . $filename . '"'];

        $callback = function () use ($posts) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM

            // Output summary totals vertically
            fputcsv($handle, ['--- SUMMARY ---']);
            fputcsv($handle, ['Total Tasks', $posts->count()]);
            fputcsv($handle, ['Posted', $posts->where('is_completed', true)->count()]);
            fputcsv($handle, ['Pending', $posts->where('is_completed', false)->count()]);
            fputcsv($handle, ['QC Checked', $posts->where('is_checked', true)->count()]);
            fputcsv($handle, ['QC Pending', $posts->where('is_completed', true)->where('is_checked', false)->count()]);
            fputcsv($handle, []);
            fputcsv($handle, []);
            
            // Output detailed data
            fputcsv($handle, ['Date', 'Class', 'Social Media', 'User', 'Post Link', 'Completed', 'Completed At', 'QC Status', 'Checked By', 'Checked At']);
            foreach ($posts as $post) {
                fputcsv($handle, [
                    $post->post_date->format('Y-m-d'),
                    $post->socialMediaClass->name ?? '',
                    $post->socialMediaItem->name ?? '',
                    $post->user->name ?? '',
                    $post->post_url ?? '',
                    $post->is_completed ? 'Yes' : 'No',
                    $post->completed_at?->format('Y-m-d H:i') ?? '',
                    $post->qc_status_label,
                    $post->checker->name ?? '',
                    $post->checked_at?->format('Y-m-d H:i') ?? '',
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        $user  = auth()->user();
        $isQc  = $user->hasAnyRole(['super-admin', 'admin-digital', 'social_qc']);
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo   = $request->input('date_to', now()->toDateString());

        $posts = $this->buildQuery(
            $user, $isQc, $dateFrom, $dateTo,
            $request->input('class_id'), $request->input('item_id'),
            $request->input('user_id'), $request->input('qc_status'), $request->input('post_status')
        )->get();

        $summary = [
            'total'     => $posts->count(),
            'completed' => $posts->where('is_completed', true)->count(),
            'pending'   => $posts->where('is_completed', false)->count(),
            'checked'   => $posts->where('is_checked', true)->count(),
            'qcPending' => $posts->where('is_completed', true)->where('is_checked', false)->count(),
        ];

        $pdf = Pdf::loadView('social-media.reports.pdf', [
            'posts'         => $posts,
            'summary'       => $summary,
            'dateFrom'      => $dateFrom,
            'dateTo'        => $dateTo,
            'generatedBy'   => $user->name,
            'generatedDate' => now()->format('d M Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('social-media-report-' . now()->format('Y-m-d') . '.pdf');
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function buildQuery(User $user, bool $isQc, string $dateFrom, string $dateTo, ?string $classId, ?string $itemId, ?string $userId, ?string $qcStatus, ?string $postStatus)
    {
        $query = SocialMediaPost::with(['socialMediaClass', 'socialMediaItem', 'user', 'checker'])
            ->whereBetween('post_date', [$dateFrom, $dateTo]);

        // Role-based scope
        if (!$isQc) {
            $query->where('user_id', $user->id);
            // Also restrict to assigned classes
            $assignedClassIds = SocialMediaClass::whereHas('assignedUsers', fn ($q) => $q->where('user_id', $user->id))->pluck('id');
            $query->whereIn('social_media_class_id', $assignedClassIds);
        }

        return $query
            ->when($classId,                fn ($q) => $q->where('social_media_class_id', $classId))
            ->when($itemId,                 fn ($q) => $q->where('social_media_item_id', $itemId))
            ->when($userId && $isQc,        fn ($q) => $q->where('user_id', $userId))
            ->when($qcStatus === 'checked', fn ($q) => $q->where('is_checked', true))
            ->when($qcStatus === 'pending', fn ($q) => $q->where('is_checked', false))
            ->when($postStatus === 'completed', fn ($q) => $q->where('is_completed', true))
            ->when($postStatus === 'pending',   fn ($q) => $q->where('is_completed', false))
            ->orderBy('post_date')
            ->orderBy('social_media_class_id');
    }
}
