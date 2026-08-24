<?php

namespace App\Http\Controllers\SocialMedia;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaAnalytic;
use App\Models\SocialMediaClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class SocialMediaReportController extends Controller
{
    public function index(Request $request)
    {
        $classes = SocialMediaClass::orderBy('position')->orderBy('name')->get();
        return view('social-media.reports.index', compact('classes'));
    }

    public function exportZip(Request $request)
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to'   => ['required', 'date', 'after_or_equal:date_from'],
            'class_id'  => ['nullable', 'exists:social_media_classes,id'],
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');
        $classId  = $request->input('class_id');
        $exportType = $request->input('export_type', 'zip');

        $classIds = $classId ? [$classId] : SocialMediaClass::pluck('id')->toArray();

        $analytics = SocialMediaAnalytic::whereHas('classes', fn ($q) => $q->whereIn('social_media_classes.id', $classIds))
            ->with('classes')
            ->where(function($q) use ($dateFrom, $dateTo) {
                $q->whereDate('date_from', '<=', $dateTo)
                  ->whereDate('date_to', '>=', $dateFrom);
            })
            ->orderByDesc('date_from')
            ->get();

        if ($analytics->isEmpty()) {
            return back()->with('error', 'No analytics files found for the selected period.');
        }

        if ($exportType === 'single' || $analytics->count() === 1) {
            // For single PDF export, if there are multiple, just take the most recent one covering the date.
            $analytic = $analytics->first();
            if ($analytic->fileExists()) {
                return Storage::download($analytic->file_path, $analytic->original_name);
            }
            return back()->with('error', 'The requested analytics file is missing from storage.');
        }

        // Export as ZIP
        $stamp   = now()->format('Y-m-d');
        $zipName = 'social-media-analytics-' . $stamp . '.zip';
        $tmpPath = sys_get_temp_dir() . '/' . $zipName;

        $zip = new ZipArchive();
        if ($zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Could not create ZIP archive.');
        }

        $coveredClassIds = collect();
        $analyticsToZip = $analytics->filter(function ($analytic) use (&$coveredClassIds, $classIds) {
            $relevantIds = $analytic->classes->pluck('id')->intersect($classIds);
            if ($relevantIds->diff($coveredClassIds)->isEmpty()) {
                return false;
            }
            $coveredClassIds = $coveredClassIds->merge($relevantIds)->unique();
            return true;
        });

        foreach ($analyticsToZip as $analytic) {
            if ($analytic->fileExists()) {
                $className = $analytic->classes->pluck('name')->map(fn ($name) => preg_replace('/[^A-Za-z0-9_\-]/', '-', $name))->join('_');
                $entryName  = ($className ?: 'classes') . '-' . $analytic->date_from->format('Y-m-d') . '-to-' . $analytic->date_to->format('Y-m-d') . '.pdf';
                $zip->addFile($analytic->absolutePath(), $entryName);
            }
        }

        $zip->close();

        return response()->download($tmpPath, $zipName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Personal report social media export — shows analytics preview for QC/Supervisor.
     */
    public function exportPersonalReport(Request $request)
    {
        abort_unless(auth()->user()?->isQcOrSupervisor(), 403, 'Unauthorized access to personal reports.');

        $classId  = $request->input('class_id');
        $dateFrom = now()->startOfDay()->toDateString();
        $dateTo   = now()->endOfDay()->toDateString();

        if ($request->filled('date_range') && $request->date_range !== 'all_time') {
            switch ($request->date_range) {
                case 'today':
                    $dateFrom = now()->startOfDay()->toDateString();
                    $dateTo   = now()->endOfDay()->toDateString();
                    break;
                case 'custom':
                case 'custom_period':
                    if ($request->filled('start_date')) $dateFrom = \Carbon\Carbon::parse($request->start_date)->toDateString();
                    if ($request->filled('end_date'))   $dateTo   = \Carbon\Carbon::parse($request->end_date)->toDateString();
                    break;
            }
        }

        $classes = SocialMediaClass::orderBy('position')->orderBy('name')->get();
        $classIds = $classId ? [$classId] : $classes->pluck('id')->toArray();

        $analytics = SocialMediaAnalytic::whereHas('classes', fn ($q) => $q->whereIn('social_media_classes.id', $classIds))
            ->with('classes', 'uploader')
            ->where(function($q) use ($dateFrom, $dateTo) {
                $q->whereDate('date_from', '<=', $dateTo)
                  ->whereDate('date_to', '>=', $dateFrom);
            })
            ->orderByDesc('date_from')
            ->get();

        return view('social-media.reports.personal-preview', [
            'analytics'  => $analytics,
            'classes'    => $classes,
            'classId'    => $classId,
            'dateFrom'   => $dateFrom,
            'dateTo'     => $dateTo,
        ]);
    }
}
