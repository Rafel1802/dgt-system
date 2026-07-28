<?php

namespace App\Http\Controllers\SocialMedia;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaAnalytic;
use App\Models\SocialMediaClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SocialMediaAnalyticsController extends Controller
{
    /** List all analytics uploads — admin-digital & social_qc only */
    public function index(Request $request)
    {
        $classes   = SocialMediaClass::orderBy('name')->get();
        $classId   = $request->input('class_id');
        $dateFrom  = $request->input('date_from');
        $dateTo    = $request->input('date_to');

        $query = SocialMediaAnalytic::with(['classes', 'uploader'])
            ->orderByDesc('date_from');

        if ($classId) {
            $query->whereHas('classes', fn ($q) => $q->where('social_media_classes.id', $classId));
        }
        if ($dateFrom && $dateTo) {
            $query->where(function($q) use ($dateFrom, $dateTo) {
                $q->where('date_from', '>=', $dateFrom)
                  ->where('date_to', '<=', $dateTo);
            });
        }

        $analytics = $query->paginate(30)->withQueryString();

        return view('social-media.analytics.index', compact('analytics', 'classes', 'classId', 'dateFrom', 'dateTo'));
    }

    /** Upload one PDF and associate it with one or more classes. */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'class_ids'             => ['required', 'array', 'min:1'],
            'class_ids.*'           => ['integer', 'distinct', 'exists:social_media_classes,id'],
            'date_from'             => ['required', 'date'],
            'date_to'               => ['required', 'date', 'after_or_equal:date_from'],
            'file'                  => ['required', 'file', 'mimes:pdf', 'max:102400'], // 100 MB max
        ]);

        $classIds = collect($validated['class_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $classes = SocialMediaClass::whereIn('id', $classIds)->orderBy('name')->get();
        $dateFrom = $validated['date_from'];
        $dateTo = $validated['date_to'];
        $filename = 'analytics-' . $dateFrom . '-to-' . $dateTo . '-' . Str::uuid() . '.pdf';
        $path = $request->file('file')->storeAs('social-analytics/shared', $filename);

        if (! $path) {
            return back()->withInput()->with('error', 'The analytics PDF could not be stored.');
        }

        try {
            DB::transaction(function () use ($classIds, $dateFrom, $dateTo, $path, $request) {
                // Each class has at most one PDF for a given date range. Detach only
                // the selected classes, preserving an old shared PDF for other classes.
                $existing = SocialMediaAnalytic::with('classes')
                    ->where('date_from', $dateFrom)
                    ->where('date_to', $dateTo)
                    ->whereHas('classes', fn ($q) => $q->whereIn('social_media_classes.id', $classIds))
                    ->get();

                foreach ($existing as $analytic) {
                    $analytic->classes()->detach($classIds);
                    if (! $analytic->classes()->exists()) {
                        if ($analytic->fileExists()) {
                            Storage::delete($analytic->file_path);
                        }
                        $analytic->delete();
                    }
                }

                $analytic = SocialMediaAnalytic::create([
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'file_path' => $path,
                    'original_name' => $request->file('file')->getClientOriginalName(),
                    'uploaded_by' => auth()->id(),
                ]);
                $analytic->classes()->attach($classIds);
            });
        } catch (\Throwable $e) {
            Storage::delete($path);
            throw $e;
        }

        $message = 'Analytics uploaded for ' . $classes->pluck('name')->join(', ') . '.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ], 201);
        }

        return back()->with('success', $message);
    }

    /** Serve / download a single analytics file */
    public function download(SocialMediaAnalytic $analytic)
    {
        if (!$analytic->fileExists()) {
            abort(404, 'Analytics file not found.');
        }

        return Storage::download($analytic->file_path, $analytic->original_name);
    }

    /** Preview / view a single analytics file inline */
    public function preview(SocialMediaAnalytic $analytic)
    {
        if (!$analytic->fileExists()) {
            abort(404, 'Analytics file not found.');
        }

        return Storage::response($analytic->file_path, $analytic->original_name, [
            'Content-Disposition' => 'inline; filename="' . $analytic->original_name . '"',
        ]);
    }

    /** Delete an analytics record + its file */
    public function destroy(SocialMediaAnalytic $analytic)
    {
        if ($analytic->fileExists()) {
            Storage::delete($analytic->file_path);
        }

        $label = $analytic->dateRangeLabel();
        $classNames = $analytic->classes()->pluck('name')->join(', ');
        $analytic->delete();

        return back()->with('success', "Analytics file for {$classNames} ({$label}) deleted.");
    }
}
