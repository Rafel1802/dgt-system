<?php

namespace App\Http\Controllers\SocialMedia;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaAnalytic;
use App\Models\SocialMediaClass;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SocialMediaAnalyticsController extends Controller
{
    /** List all analytics uploads — admin-digital & social_qc only */
    public function index(Request $request)
    {
        $classes   = SocialMediaClass::orderBy('position')->orderBy('name')->get();
        $classId   = $request->input('class_id');
        $dateFrom  = $request->input('date_from');
        $dateTo    = $request->input('date_to');

        $users = User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'team_role']);

        $query = SocialMediaAnalytic::with(['classes', 'uploader'])
            ->orderByDesc('date_from');

        if ($classId) {
            $query->whereHas('classes', fn ($q) => $q->where('social_media_classes.id', $classId));
        }
        if ($dateFrom && $dateTo) {
            $query->where(function($q) use ($dateFrom, $dateTo) {
                $q->where('date_from', '<=', $dateTo)
                  ->where('date_to', '>=', $dateFrom);
            });
        }

        $analytics = $query->paginate(30)->withQueryString();

        return view('social-media.analytics.index', compact('analytics', 'classes', 'classId', 'dateFrom', 'dateTo', 'users'));
    }

    /** Upload one PDF and/or provide a Canva link, associated with one or more classes. */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'class_ids'             => ['required', 'array', 'min:1'],
            'class_ids.*'           => ['integer', 'distinct', 'exists:social_media_classes,id'],
            'date_from'             => ['required', 'date'],
            'date_to'               => ['required', 'date', 'after_or_equal:date_from'],
            'file'                  => ['required', 'file', 'mimetypes:application/pdf', 'max:102400'], // 100 MB max
            'canva_link'            => ['required', 'string', 'max:2048'],
        ], [
            'canva_link.required'   => 'The Canva link is required.',
            'file.required'         => 'Please choose an analytics PDF.',
        ]);

        $classIds = collect($validated['class_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $classes = SocialMediaClass::whereIn('id', $classIds)->orderBy('position')->orderBy('name')->get();
        $dateFrom = $validated['date_from'];
        $dateTo = $validated['date_to'];
        $canvaLink = trim($validated['canva_link']);
        if (!preg_match('~^(?:f|ht)tps?://~i', $canvaLink)) {
            $canvaLink = 'https://' . $canvaLink;
        }

        $filename = 'analytics-' . $dateFrom . '-to-' . $dateTo . '-' . Str::uuid() . '.pdf';
        $path = $request->file('file')->storeAs('social-analytics/shared', $filename);

        if (! $path) {
            return back()->withInput()->with('error', 'The analytics PDF could not be stored.');
        }
        $originalName = $request->file('file')->getClientOriginalName();

        try {
            DB::transaction(function () use ($classIds, $dateFrom, $dateTo, $path, $originalName, $canvaLink) {
                // Each class has at most one analytics record for a given date range. Detach only
                // the selected classes, preserving an old shared record for other classes.
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
                    'date_from'     => $dateFrom,
                    'date_to'       => $dateTo,
                    'file_path'     => $path,
                    'original_name' => $originalName,
                    'canva_link'    => $canvaLink,
                    'uploaded_by'   => auth()->id(),
                ]);
                $analytic->classes()->attach($classIds);
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::delete($path);
            }
            throw $e;
        }

        $message = 'Analytics saved for ' . $classes->pluck('name')->join(', ') . '.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ], 201);
        }

        return back()->with('success', $message);
    }

    /** Update an existing analytics record (date range, classes, user, canva link, optional PDF) */
    public function update(Request $request, SocialMediaAnalytic $analytic)
    {
        $validated = $request->validate([
            'class_ids'     => ['required', 'array', 'min:1'],
            'class_ids.*'   => ['integer', 'distinct', 'exists:social_media_classes,id'],
            'date_from'     => ['required', 'date'],
            'date_to'       => ['required', 'date', 'after_or_equal:date_from'],
            'uploaded_by'   => ['nullable', 'integer', 'exists:users,id'],
            'canva_link'    => ['nullable', 'string', 'max:2048'],
            'file'          => ['nullable', 'file', 'mimetypes:application/pdf', 'max:102400'], // 100 MB max
        ], [
            'class_ids.required' => 'Please select at least one class.',
            'date_to.after_or_equal' => 'Date To must be equal to or after Date From.',
        ]);

        $classIds = collect($validated['class_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $classes = SocialMediaClass::whereIn('id', $classIds)->orderBy('position')->orderBy('name')->get();
        $dateFrom = $validated['date_from'];
        $dateTo = $validated['date_to'];
        $uploadedBy = $validated['uploaded_by'] ?? $analytic->uploaded_by;

        $canvaLink = !empty($validated['canva_link']) ? trim($validated['canva_link']) : null;
        if ($canvaLink && !preg_match('~^(?:f|ht)tps?://~i', $canvaLink)) {
            $canvaLink = 'https://' . $canvaLink;
        }

        $newPath = null;
        $originalName = $analytic->original_name;
        $oldPath = $analytic->file_path;

        if ($request->hasFile('file')) {
            $filename = 'analytics-' . $dateFrom . '-to-' . $dateTo . '-' . Str::uuid() . '.pdf';
            $newPath = $request->file('file')->storeAs('social-analytics/shared', $filename);
            if (! $newPath) {
                return back()->withInput()->with('error', 'The new analytics PDF could not be stored.');
            }
            $originalName = $request->file('file')->getClientOriginalName();
        }

        try {
            DB::transaction(function () use ($analytic, $classIds, $dateFrom, $dateTo, $newPath, $originalName, $canvaLink, $uploadedBy, $oldPath) {
                // Remove selected classes from any other existing analytics record for this exact date range
                $existing = SocialMediaAnalytic::with('classes')
                    ->where('id', '!=', $analytic->id)
                    ->where('date_from', $dateFrom)
                    ->where('date_to', $dateTo)
                    ->whereHas('classes', fn ($q) => $q->whereIn('social_media_classes.id', $classIds))
                    ->get();

                foreach ($existing as $other) {
                    $other->classes()->detach($classIds);
                    if (! $other->classes()->exists()) {
                        if ($other->fileExists()) {
                            Storage::delete($other->file_path);
                        }
                        $other->delete();
                    }
                }

                $updateData = [
                    'date_from'   => $dateFrom,
                    'date_to'     => $dateTo,
                    'canva_link'  => $canvaLink,
                    'uploaded_by' => $uploadedBy,
                ];

                if ($newPath) {
                    $updateData['file_path']     = $newPath;
                    $updateData['original_name'] = $originalName;
                    if ($oldPath && Storage::exists($oldPath)) {
                        Storage::delete($oldPath);
                    }
                }

                $analytic->update($updateData);
                $analytic->classes()->sync($classIds);
            });
        } catch (\Throwable $e) {
            if ($newPath && Storage::exists($newPath)) {
                Storage::delete($newPath);
            }
            throw $e;
        }

        $message = 'Analytics updated for ' . $classes->pluck('name')->join(', ') . '.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    /** Update or add a Canva link to an existing analytics record */
    public function updateCanvaLink(Request $request, SocialMediaAnalytic $analytic)
    {
        $validated = $request->validate([
            'canva_link' => ['nullable', 'string', 'max:2048'],
        ]);

        $canvaLink = !empty($validated['canva_link']) ? trim($validated['canva_link']) : null;
        if ($canvaLink && !preg_match('~^(?:f|ht)tps?://~i', $canvaLink)) {
            $canvaLink = 'https://' . $canvaLink;
        }

        $analytic->update([
            'canva_link' => $canvaLink,
        ]);

        $message = $canvaLink ? 'Canva link updated successfully.' : 'Canva link removed.';

        if ($request->expectsJson()) {
            return response()->json([
                'success'    => true,
                'message'    => $message,
                'canva_link' => $analytic->formattedCanvaLink(),
            ]);
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

    public function preview(SocialMediaAnalytic $analytic)
    {
        if (!$analytic->fileExists()) {
            abort(404, 'Analytics file not found.');
        }

        return response()->file($analytic->absolutePath(), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . addslashes($analytic->original_name) . '"'
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
